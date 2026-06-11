<?php

namespace App\Services\Crm;

use App\Models\CargoReportItem;
use App\Models\ChannelClaim;
use App\Models\ChannelOrder;
use App\Models\CrmCase;
use App\Models\CrmContact;
use App\Models\CrmTimelineEvent;
use App\Models\MarketplaceQuestion;
use App\Models\ReturnIntakeItem;
use App\Models\SupplyOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CrmProjectionService
{
    public function __construct(
        protected CrmIdentityResolver $identityResolver,
        protected CrmAlertRuleService $alertRuleService,
        protected CrmCustomerLedgerProjectionService $customerLedgerProjectionService,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function projectUser(User $user, array $options = []): array
    {
        if (!$this->tablesReady()) {
            return [
                'contacts' => 0,
                'events' => 0,
            'cases' => 0,
            'alerts' => 0,
            'resolved_alerts' => 0,
            'ledger_entries' => 0,
            'skipped' => 1,
        ];
        }

        $sources = $this->normalizeSources($options['sources'] ?? []);
        $since = $this->resolveSince($options['since'] ?? null, $options['recent_days'] ?? null);
        $summary = [
            'contacts' => 0,
            'events' => 0,
            'cases' => 0,
            'alerts' => 0,
            'resolved_alerts' => 0,
            'ledger_entries' => 0,
            'skipped' => 0,
        ];

        if ($this->shouldProject('orders', $sources)) {
            $this->projectOrders($user, $summary, $since);
        }

        if ($this->shouldProject('questions', $sources)) {
            $this->projectQuestions($user, $summary, $since);
        }

        if ($this->shouldProject('returns', $sources)) {
            $this->projectReturns($user, $summary, $since);
        }

        if ($this->shouldProject('claims', $sources) || in_array('returns', $sources, true)) {
            $this->projectMarketplaceClaims($user, $summary, $since);
        }

        if ($this->shouldProject('cargo', $sources)) {
            $this->projectCargoReports($user, $summary, $since);
        }

        if ($this->shouldProject('supply', $sources)) {
            $this->projectSupplyOrders($user, $summary, $since);
        }

        $this->recalculateContactMetrics($user);
        $alertSummary = $this->alertRuleService->runForUser($user);
        $summary['alerts'] = $alertSummary['created'];
        $summary['resolved_alerts'] = $alertSummary['resolved'];

        if ($alertSummary['created'] > 0 || $alertSummary['resolved'] > 0) {
            $this->recalculateContactMetrics($user);
        }

        return $summary;
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('crm_contacts')
            && Schema::hasTable('crm_cases')
            && Schema::hasTable('crm_timeline_events');
    }

    protected function projectOrders(User $user, array &$summary, ?Carbon $since = null): void
    {
        if (!Schema::hasTable('channel_orders')) {
            return;
        }

        ChannelOrder::query()
            ->with(['store', 'profitSnapshot', 'items.financialEvents', 'items.listing', 'items.product.activeRecipe'])
            ->whereHas('store', fn ($query) => $query->where('user_id', $user->id))
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'ordered_at']))
            ->orderBy('id')
            ->chunkById(250, function ($orders) use ($user, &$summary) {
                foreach ($orders as $order) {
                    $contact = $this->identityResolver->resolve([
                        'user_id' => $user->id,
                        'store_id' => $order->store_id,
                        'marketplace' => $order->store?->marketplace,
                        'source_type' => 'order_customer',
                        'external_customer_id' => $this->firstFilled([
                            data_get($order->raw_payload, 'customerId'),
                            data_get($order->raw_payload, 'customer.id'),
                            data_get($order->raw_payload, 'customer.customerId'),
                        ]),
                        'name' => $order->customer_name ?: $order->billing_name,
                        'email' => $order->customer_email,
                        'phone' => $order->customer_phone,
                        'tax_number' => $order->billing_tax_number,
                        'city' => $order->shipment_city,
                        'district' => $order->shipment_district,
                        'confidence' => $order->customer_phone || $order->customer_email || $order->billing_tax_number ? 96 : 68,
                        'raw_payload' => [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                        ],
                    ]);

                    $summary['contacts']++;
                    $grossAmount = (float) ($order->profitSnapshot?->gross_revenue ?? $order->items()->sum('gross_amount'));
                    $profit = (float) ($order->profitSnapshot?->confirmed_profit ?: $order->profitSnapshot?->estimated_profit);

                    $event = $this->upsertTimeline($contact, [
                        'store_id' => $order->store_id,
                        'event_key' => 'order:' . $order->id,
                        'event_type' => 'order',
                        'source_type' => 'marketplace_orders',
                        'subject' => $order,
                        'title' => 'Sipariş #' . $order->order_number,
                        'body' => trim(($order->order_status ?: 'Durum yok') . ' · ' . ($order->store?->store_name ?: 'Mağaza yok')),
                        'occurred_at' => $order->ordered_at ?: $order->created_at,
                        'payload_json' => [
                            'order_number' => $order->order_number,
                            'status' => $order->order_status,
                            'gross_amount' => $grossAmount,
                            'profit' => $profit,
                            'marketplace' => $order->store?->marketplace,
                        ],
                    ]);
                    $summary['events']++;

                    if ($this->customerLedgerProjectionService->tablesReady()) {
                        $ledgerSummary = $this->customerLedgerProjectionService->syncOrder($contact, $order);
                        $summary['ledger_entries'] += $ledgerSummary['entries'];
                    }

                    $status = Str::lower((string) $order->order_status);
                    if (Str::contains($status, ['iade', 'return'])) {
                        $summary['cases'] += $this->upsertCase($contact, [
                            'store_id' => $order->store_id,
                            'source_type' => 'marketplace_orders',
                            'category' => 'return',
                            'priority' => 'normal',
                            'case_key' => 'order-return:' . $order->id,
                            'subject' => $order,
                            'title' => 'İade sipariş takibi',
                            'summary' => "#{$order->order_number} siparişi iade durumunda. Müşteri geçmişi ve finans etkisi CRM içinde izlenmeli.",
                            'sla_due_at' => now()->addDay(),
                        ], $event);
                    }

                    if ($profit < 0) {
                        $summary['cases'] += $this->upsertCase($contact, [
                            'store_id' => $order->store_id,
                            'source_type' => 'marketplace_finance',
                            'category' => 'profit',
                            'priority' => 'high',
                            'case_key' => 'negative-profit:' . $order->id,
                            'subject' => $order,
                            'title' => 'Zarar eden sipariş',
                            'summary' => "#{$order->order_number} siparişinde tahmini/teyitli kâr negatif görünüyor.",
                            'sla_due_at' => now()->addDays(2),
                        ], $event);
                    }
                }
            });
    }

    protected function projectQuestions(User $user, array &$summary, ?Carbon $since = null): void
    {
        if (!Schema::hasTable('marketplace_questions')) {
            return;
        }

        MarketplaceQuestion::query()
            ->with('store')
            ->whereHas('store', fn ($query) => $query->where('user_id', $user->id))
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'asked_at', 'answered_at']))
            ->orderBy('id')
            ->chunkById(250, function ($questions) use ($user, &$summary) {
                foreach ($questions as $question) {
                    $contact = $this->identityResolver->resolve([
                        'user_id' => $user->id,
                        'store_id' => $question->store_id,
                        'marketplace' => $question->store?->marketplace,
                        'source_type' => 'question_customer',
                        'external_customer_id' => $question->customer_external_id,
                        'name' => $question->customer_name,
                        'confidence' => $question->customer_external_id ? 92 : 55,
                        'raw_payload' => [
                            'question_id' => $question->id,
                            'external_question_id' => $question->external_question_id,
                        ],
                    ]);

                    $summary['contacts']++;
                    $event = $this->upsertTimeline($contact, [
                        'store_id' => $question->store_id,
                        'event_key' => 'question:' . $question->id,
                        'event_type' => 'question',
                        'source_type' => 'marketplace_questions',
                        'subject' => $question,
                        'title' => 'Müşteri sorusu',
                        'body' => Str::limit($question->question_text, 180),
                        'occurred_at' => $question->asked_at ?: $question->created_at,
                        'payload_json' => [
                            'status' => $question->status,
                            'product_name' => $question->product_name,
                            'product_sku' => $question->product_sku,
                            'expires_at' => optional($question->expires_at)->toIso8601String(),
                        ],
                    ]);
                    $summary['events']++;

                    if (in_array($question->status, ['open', 'pending', 'draft'], true)) {
                        $summary['cases'] += $this->upsertCase($contact, [
                            'store_id' => $question->store_id,
                            'source_type' => 'marketplace_questions',
                            'category' => 'message',
                            'priority' => $question->expires_at && $question->expires_at->isPast() ? 'high' : 'normal',
                            'case_key' => 'question-open:' . $question->id,
                            'subject' => $question,
                            'title' => 'Yanıt bekleyen müşteri sorusu',
                            'summary' => Str::limit($question->question_text, 240),
                            'sla_due_at' => $question->expires_at ?: now()->addHours(12),
                        ], $event);
                    }
                }
            });
    }

    protected function projectReturns(User $user, array &$summary, ?Carbon $since = null): void
    {
        if (!Schema::hasTable('return_intake_items')) {
            return;
        }

        ReturnIntakeItem::query()
            ->with(['store', 'order.store'])
            ->where(function ($query) use ($user) {
                $query->where('submitted_by_user_id', $user->id)
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('user_id', $user->id))
                    ->orWhereHas('order.store', fn ($storeQuery) => $storeQuery->where('user_id', $user->id));
            })
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'arrived_at', 'analysis_completed_at']))
            ->orderBy('id')
            ->chunkById(250, function ($items) use ($user, &$summary) {
                foreach ($items as $item) {
                    $order = $item->order;
                    $store = $item->store ?: $order?->store;
                    $contact = $this->identityResolver->resolve([
                        'user_id' => $user->id,
                        'store_id' => $store?->id,
                        'marketplace' => $store?->marketplace,
                        'source_type' => 'return_customer',
                        'external_customer_id' => null,
                        'name' => $order?->customer_name ?: $item->detected_customer_name,
                        'email' => $order?->customer_email,
                        'phone' => $order?->customer_phone,
                        'city' => $order?->shipment_city,
                        'district' => $order?->shipment_district,
                        'confidence' => $order ? 88 : 52,
                        'raw_payload' => [
                            'return_intake_item_id' => $item->id,
                            'detected_order_number' => $item->detected_order_number,
                        ],
                    ]);

                    $summary['contacts']++;
                    $event = $this->upsertTimeline($contact, [
                        'store_id' => $store?->id,
                        'event_key' => 'return-intake:' . $item->id,
                        'event_type' => 'return',
                        'source_type' => 'returns',
                        'subject' => $item,
                        'title' => 'İade inceleme kaydı',
                        'body' => $item->statusLabel() . ' · ' . $item->conditionLabel(),
                        'occurred_at' => $item->arrived_at ?: $item->created_at,
                        'payload_json' => [
                            'intake_status' => $item->intake_status,
                            'condition_status' => $item->condition_status,
                            'decision_status' => $item->decision_status,
                            'tracking_number' => $item->detected_tracking_number,
                        ],
                    ]);
                    $summary['events']++;

                    if (
                        in_array($item->intake_status, ['needs_review', 'failed', 'ready_for_decision'], true)
                        || in_array($item->decision_status, ['pending', 'needs_review'], true)
                        || $item->condition_status === 'damaged'
                    ) {
                        $summary['cases'] += $this->upsertCase($contact, [
                            'store_id' => $store?->id,
                            'source_type' => 'returns',
                            'category' => 'return',
                            'priority' => $item->condition_status === 'damaged' ? 'high' : 'normal',
                            'case_key' => 'return-review:' . $item->id,
                            'subject' => $item,
                            'title' => 'İade karar takibi',
                            'summary' => 'İade kaydı karar veya manuel inceleme bekliyor.',
                            'sla_due_at' => now()->addDay(),
                        ], $event);
                    }
                }
            });
    }

    protected function projectCargoReports(User $user, array &$summary, ?Carbon $since = null): void
    {
        if (!Schema::hasTable('cargo_report_items')) {
            return;
        }

        CargoReportItem::query()
            ->with('cargoReport')
            ->whereHas('cargoReport', fn ($query) => $query->where('user_id', $user->id))
            ->where('has_error', true)
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'tarih']))
            ->orderBy('id')
            ->chunkById(250, function ($items) use ($user, &$summary) {
                foreach ($items as $item) {
                    $contact = $this->identityResolver->resolve([
                        'user_id' => $user->id,
                        'store_id' => null,
                        'marketplace' => $item->pazaryeri,
                        'source_type' => 'cargo_customer',
                        'external_customer_id' => null,
                        'name' => $item->musteri_adi,
                        'city' => $item->cikis_il,
                        'confidence' => $item->siparis_no ? 74 : 48,
                        'raw_payload' => [
                            'cargo_report_item_id' => $item->id,
                            'siparis_no' => $item->siparis_no,
                            'takip_kodu' => $item->takip_kodu,
                        ],
                    ]);

                    $summary['contacts']++;
                    $event = $this->upsertTimeline($contact, [
                        'store_id' => null,
                        'event_key' => 'cargo-report-item:' . $item->id,
                        'event_type' => 'cargo',
                        'source_type' => 'cargo_reports',
                        'subject' => $item,
                        'title' => 'Kargo raporu uyarısı',
                        'body' => ($item->error_info['label'] ?? $item->error_type) . ' · ' . ($item->takip_kodu ?: 'Takip yok'),
                        'occurred_at' => $item->tarih ?: $item->created_at,
                        'payload_json' => [
                            'error_type' => $item->error_type,
                            'amount_diff' => (float) $item->tutar_fark,
                            'desi_diff' => (float) $item->desi_fark,
                            'tracking_number' => $item->takip_kodu,
                            'order_number' => $item->siparis_no,
                        ],
                    ]);
                    $summary['events']++;

                    $summary['cases'] += $this->upsertCase($contact, [
                        'store_id' => null,
                        'source_type' => 'cargo_reports',
                        'category' => 'cargo',
                        'priority' => (float) $item->tutar_fark > 0 ? 'high' : 'normal',
                        'case_key' => 'cargo-error:' . $item->id,
                        'subject' => $item,
                        'title' => 'Kargo farkı incelemesi',
                        'summary' => 'Kargo raporunda müşteri/sipariş bazlı fark tespit edildi.',
                        'sla_due_at' => now()->addDays(3),
                    ], $event);
                }
            });
    }

    protected function projectMarketplaceClaims(User $user, array &$summary, ?Carbon $since = null): void
    {
        if (!Schema::hasTable('channel_claims')) {
            return;
        }

        ChannelClaim::query()
            ->with(['store', 'items'])
            ->whereHas('store', fn ($query) => $query->where('user_id', $user->id))
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'created_date']))
            ->orderBy('id')
            ->chunkById(250, function ($claims) use ($user, &$summary) {
                foreach ($claims as $claim) {
                    $contact = $this->identityResolver->resolve([
                        'user_id' => $user->id,
                        'store_id' => $claim->store_id,
                        'marketplace' => $claim->store?->marketplace,
                        'source_type' => 'marketplace_claim_customer',
                        'external_customer_id' => null,
                        'name' => $claim->customer_name,
                        'confidence' => $claim->customer_name ? 58 : 42,
                        'raw_payload' => [
                            'claim_id' => $claim->id,
                            'external_claim_id' => $claim->external_claim_id,
                            'order_number' => $claim->order_number,
                        ],
                    ]);

                    $summary['contacts']++;
                    $event = $this->upsertTimeline($contact, [
                        'store_id' => $claim->store_id,
                        'event_key' => 'marketplace-claim:' . $claim->id,
                        'event_type' => 'return',
                        'source_type' => 'marketplace_claims',
                        'subject' => $claim,
                        'title' => 'Pazaryeri iade talebi',
                        'body' => trim($claim->statusLabel() . ' · ' . ($claim->reason ?: 'Neden yok')),
                        'occurred_at' => $claim->created_date ?: $claim->created_at,
                        'payload_json' => [
                            'external_claim_id' => $claim->external_claim_id,
                            'order_number' => $claim->order_number,
                            'status' => $claim->status,
                            'reason' => $claim->reason,
                            'tracking_number' => $claim->cargo_tracking_number,
                            'item_count' => $claim->items->count(),
                        ],
                    ]);
                    $summary['events']++;

                    $caseKey = 'marketplace-claim-open:' . $claim->id;

                    if (in_array($claim->status, ['approved', 'cancelled'], true)) {
                        CrmCase::query()
                            ->where('user_id', $user->id)
                            ->where('case_key', $caseKey)
                            ->whereNotIn('status', ['resolved', 'closed'])
                            ->update([
                                'status' => 'resolved',
                                'resolved_at' => now(),
                            ]);

                        continue;
                    }

                    $summary['cases'] += $this->upsertCase($contact, [
                        'store_id' => $claim->store_id,
                        'source_type' => 'marketplace_claims',
                        'category' => 'return',
                        'priority' => in_array($claim->status, ['delivered', 'rejected', 'unresolved'], true) ? 'high' : 'normal',
                        'case_key' => $caseKey,
                        'subject' => $claim,
                        'title' => 'Pazaryeri iade talebi',
                        'summary' => "#{$claim->external_claim_id} iade talebi CRM takibi bekliyor.",
                        'sla_due_at' => $claim->status === 'delivered' ? now()->addHours(12) : now()->addDay(),
                    ], $event);
                }
            });
    }

    protected function projectSupplyOrders(User $user, array &$summary, ?Carbon $since = null): void
    {
        if (!Schema::hasTable('supply_orders')) {
            return;
        }

        SupplyOrder::query()
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'kayit_tarihi', 'soz_tarihi']))
            ->orderBy('id')
            ->chunkById(250, function ($orders) use ($user, &$summary) {
                foreach ($orders as $order) {
                    $contact = $this->identityResolver->resolve([
                        'user_id' => $user->id,
                        'source_type' => 'supply_customer',
                        'external_customer_id' => $order->siparis_no,
                        'name' => $order->musteri_adi,
                        'phone' => $order->telefon,
                        'city' => $order->il,
                        'district' => $order->ilce,
                        'confidence' => $order->telefon ? 86 : 62,
                        'raw_payload' => [
                            'supply_order_id' => $order->id,
                            'siparis_no' => $order->siparis_no,
                        ],
                    ]);

                    $summary['contacts']++;
                    $event = $this->upsertTimeline($contact, [
                        'event_key' => 'supply-order:' . $order->id,
                        'event_type' => 'supply',
                        'source_type' => 'supply_reports',
                        'subject' => $order,
                        'title' => 'Tedarik/üretim siparişi',
                        'body' => $order->durum_label . ' · ' . Str::limit($order->urun_adi, 80),
                        'occurred_at' => $order->kayit_tarihi ?: $order->created_at,
                        'payload_json' => [
                            'order_number' => $order->siparis_no,
                            'status' => $order->durum,
                            'reason' => $order->sebebiyet,
                            'promise_date' => optional($order->soz_tarihi)->toDateString(),
                        ],
                    ]);
                    $summary['events']++;

                    if ($order->is_gecikmi) {
                        $summary['cases'] += $this->upsertCase($contact, [
                            'source_type' => 'supply_reports',
                            'category' => 'supply',
                            'priority' => 'high',
                            'case_key' => 'supply-delay:' . $order->id,
                            'subject' => $order,
                            'title' => 'Geciken tedarik/üretim',
                            'summary' => "#{$order->siparis_no} için söz tarihi geçmiş görünüyor.",
                            'sla_due_at' => now()->addDay(),
                        ], $event);
                    }
                }
            });
    }

    /**
     * @param array{
     *     store_id?:int|null,
     *     event_key:string,
     *     event_type:string,
     *     source_type:string,
     *     subject?:Model|null,
     *     title:string,
     *     body?:string|null,
     *     occurred_at?:mixed,
     *     payload_json?:array|null
     * } $data
     */
    protected function upsertTimeline(CrmContact $contact, array $data): CrmTimelineEvent
    {
        $subject = $data['subject'] ?? null;

        return CrmTimelineEvent::updateOrCreate(
            [
                'user_id' => $contact->user_id,
                'event_key' => $data['event_key'],
            ],
            [
                'contact_id' => $contact->id,
                'store_id' => $data['store_id'] ?? null,
                'event_type' => $data['event_type'],
                'source_type' => $data['source_type'],
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'occurred_at' => $this->asCarbon($data['occurred_at'] ?? null) ?: now(),
                'payload_json' => $data['payload_json'] ?? null,
            ]
        );
    }

    /**
     * @param array{
     *     store_id?:int|null,
     *     source_type:string,
     *     category:string,
     *     priority:string,
     *     case_key:string,
     *     subject?:Model|null,
     *     title:string,
     *     summary?:string|null,
     *     sla_due_at?:mixed
     * } $data
     */
    protected function upsertCase(CrmContact $contact, array $data, ?CrmTimelineEvent $event = null): int
    {
        $subject = $data['subject'] ?? null;
        $case = CrmCase::firstOrNew([
            'user_id' => $contact->user_id,
            'case_key' => $data['case_key'],
        ]);
        $wasNew = !$case->exists;
        $resolved = in_array($case->status, ['resolved', 'closed'], true);

        $case->fill([
            'contact_id' => $contact->id,
            'store_id' => $data['store_id'] ?? null,
            'source_type' => $data['source_type'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'sla_due_at' => $this->asCarbon($data['sla_due_at'] ?? null),
        ]);

        if ($wasNew || !$resolved) {
            $case->status = $case->status ?: 'open';
        }

        $case->save();

        if ($event && !$event->case_id) {
            $event->case_id = $case->id;
            $event->save();
        }

        return $wasNew ? 1 : 0;
    }

    protected function recalculateContactMetrics(User $user): void
    {
        CrmContact::query()
            ->where('user_id', $user->id)
            ->with(['timelineEvents' => fn ($query) => $query->latest('occurred_at')->latest('id')])
            ->chunkById(150, function ($contacts) {
                foreach ($contacts as $contact) {
                    $events = $contact->timelineEvents;
                    $orderEvents = $events->where('event_type', 'order');
                    $returnCount = $events->where('event_type', 'return')->count();
                    $questionCount = $events->where('event_type', 'question')->count();
                    $openCaseCount = $contact->openCases()->count();
                    $grossRevenue = $orderEvents->sum(fn ($event) => (float) data_get($event->payload_json, 'gross_amount', 0));
                    $negativeProfitCount = $events->filter(fn ($event) => (float) data_get($event->payload_json, 'profit', 0) < 0)->count();
                    $cargoIssueCount = $events->where('event_type', 'cargo')->count();
                    $lastEvent = $events->first();

                    $riskScore = min(100, ($openCaseCount * 18) + ($returnCount * 10) + ($negativeProfitCount * 16) + ($cargoIssueCount * 8));
                    $valueScore = min(100, (int) round(($orderEvents->count() * 4) + min(70, $grossRevenue / 1000)));
                    $firstOrderAt = optional($orderEvents->sortBy('occurred_at')->first())->occurred_at;
                    $lastOrderAt = optional($orderEvents->sortByDesc('occurred_at')->first())->occurred_at;

                    $contact->forceFill([
                        'first_order_at' => $firstOrderAt,
                        'last_order_at' => $lastOrderAt,
                        'last_event_at' => $lastEvent?->occurred_at,
                        'last_event_type' => $lastEvent?->event_type,
                        'last_event_title' => $lastEvent?->title,
                        'order_count' => $orderEvents->count(),
                        'gross_revenue_total' => $grossRevenue,
                        'return_count' => $returnCount,
                        'question_count' => $questionCount,
                        'open_case_count' => $openCaseCount,
                        'risk_score' => $riskScore,
                        'value_score' => max(0, $valueScore - min(25, $returnCount * 4)),
                    ])->save();
                }
            });
    }

    /**
     * @param array<int, string> $sources
     */
    protected function shouldProject(string $source, array $sources): bool
    {
        return $sources === [] || in_array($source, $sources, true);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeSources(mixed $sources): array
    {
        $sources = is_array($sources) ? $sources : [$sources];
        $aliases = [
            'order' => 'orders',
            'orders' => 'orders',
            'marketplace_orders' => 'orders',
            'question' => 'questions',
            'questions' => 'questions',
            'marketplace_questions' => 'questions',
            'return' => 'returns',
            'returns' => 'returns',
            'iade' => 'returns',
            'claim' => 'claims',
            'claims' => 'claims',
            'marketplace_claims' => 'claims',
            'pazaryeri_iade' => 'claims',
            'cargo' => 'cargo',
            'cargo_reports' => 'cargo',
            'kargo' => 'cargo',
            'supply' => 'supply',
            'supply_reports' => 'supply',
            'tedarik' => 'supply',
        ];
        $normalized = [];

        foreach ($sources as $source) {
            $key = (string) Str::of((string) $source)->lower()->trim()->replace('-', '_');

            if ($key === '' || $key === 'all' || $key === 'tum' || $key === 'tümü') {
                continue;
            }

            if (isset($aliases[$key])) {
                $normalized[] = $aliases[$key];
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function resolveSince(mixed $since, mixed $recentDays = null): ?Carbon
    {
        if ($since instanceof Carbon) {
            return $since;
        }

        if ($since instanceof \DateTimeInterface) {
            return Carbon::instance($since);
        }

        if (filled($since)) {
            return Carbon::parse($since);
        }

        if (filled($recentDays) && (int) $recentDays > 0) {
            return now()->subDays((int) $recentDays);
        }

        return null;
    }

    /**
     * @param array<int, string> $columns
     */
    protected function applySince(Builder $query, Carbon $since, array $columns): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($since, $columns) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $dateQuery->{$method}($column, '>=', $since);
            }
        });
    }

    protected function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $clean = trim((string) $value);

            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
    }

    protected function asCarbon(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }
}
