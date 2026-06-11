<?php

namespace App\Services\Crm;

use App\Models\CargoReportItem;
use App\Models\ChannelClaim;
use App\Models\ChannelOrder;
use App\Models\CrmCase;
use App\Models\CrmContact;
use App\Models\CrmCustomerLedgerEntry;
use App\Models\CrmTimelineEvent;
use App\Models\MarketplaceQuestion;
use App\Models\ReturnIntakeItem;
use App\Models\SupplyOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CrmSourceLinkService
{
    /**
     * @var array<string, class-string<Model>>
     */
    protected array $sourceSubjects = [
        'order' => ChannelOrder::class,
        'marketplace_order' => ChannelOrder::class,
        'question' => MarketplaceQuestion::class,
        'marketplace_question' => MarketplaceQuestion::class,
        'return' => ReturnIntakeItem::class,
        'return_intake' => ReturnIntakeItem::class,
        'claim' => ChannelClaim::class,
        'marketplace_claim' => ChannelClaim::class,
        'cargo' => CargoReportItem::class,
        'cargo_report_item' => CargoReportItem::class,
        'supply' => SupplyOrder::class,
        'supply_order' => SupplyOrder::class,
        'customer_ledger' => CrmCustomerLedgerEntry::class,
        'crm_customer_ledger' => CrmCustomerLedgerEntry::class,
    ];

    public function urlFor(string $source, Model|int|string|null $subject): string
    {
        $sourceId = $subject instanceof Model ? $subject->getKey() : $subject;

        return route('crm.workspace', [
            'source' => $source,
            'sourceId' => $sourceId,
        ]);
    }

    public function contactUrl(int $contactId): string
    {
        return route('crm.workspace', [
            'contact' => $contactId,
        ]);
    }

    public function resolveContactId(User $user, string $source, int $sourceId): ?int
    {
        if ($source === 'contact') {
            return CrmContact::query()
                ->where('user_id', $user->id)
                ->whereKey($sourceId)
                ->value('id');
        }

        $subjectType = $this->subjectTypeFor($source);

        if (!$subjectType) {
            return null;
        }

        return CrmTimelineEvent::query()
            ->where('user_id', $user->id)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $sourceId)
            ->latest('occurred_at')
            ->latest('id')
            ->value('contact_id');
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    public function actionForTimelineEvent(CrmTimelineEvent $event): ?array
    {
        if (!$event->subject_type || !$event->subject_id) {
            return null;
        }

        return $this->actionForSubject(
            $event->subject_type,
            (int) $event->subject_id,
            $event->source_type,
            $event->title,
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    public function actionForCase(CrmCase $case): ?array
    {
        if (!$case->subject_type || !$case->subject_id) {
            return null;
        }

        return $this->actionForSubject(
            $case->subject_type,
            (int) $case->subject_id,
            $case->source_type,
            $case->title,
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    public function actionForSubject(string $subjectType, int $subjectId, ?string $sourceType = null, ?string $fallbackTitle = null): ?array
    {
        return match ($subjectType) {
            ChannelOrder::class => $this->orderAction($subjectId, $fallbackTitle),
            MarketplaceQuestion::class => $this->questionAction($subjectId, $fallbackTitle),
            ReturnIntakeItem::class => $this->returnItemAction($subjectId, $fallbackTitle),
            ChannelClaim::class => $this->claimAction($subjectId, $fallbackTitle),
            CargoReportItem::class => $this->cargoAction($subjectId, $fallbackTitle),
            SupplyOrder::class => $this->supplyAction($subjectId, $fallbackTitle),
            CrmCustomerLedgerEntry::class => $this->customerLedgerAction($subjectId, $fallbackTitle),
            default => $sourceType === 'crm' ? null : null,
        };
    }

    /**
     * @return class-string<Model>|null
     */
    public function subjectTypeFor(string $source): ?string
    {
        return $this->sourceSubjects[$this->normalizeSource($source)] ?? null;
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function orderAction(int $orderId, ?string $fallbackTitle = null): ?array
    {
        $order = ChannelOrder::query()->find($orderId, ['id', 'order_number', 'external_order_id', 'customer_name']);

        if (!$order) {
            return null;
        }

        $reference = $order->order_number ?: $order->external_order_id;

        return $this->buildAction(
            'Sipariş ekranında aç',
            $fallbackTitle ?: ('Sipariş #' . ($reference ?: $order->id)),
            route('mp.orders', array_filter(['search' => $reference ?: null])),
            $reference ? 'Sipariş filtresiyle açılır' : 'Sipariş listesi açılır',
            'marketplace_orders',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function questionAction(int $questionId, ?string $fallbackTitle = null): ?array
    {
        $question = MarketplaceQuestion::query()->find($questionId, ['id', 'external_question_id', 'customer_name', 'question_text']);

        if (!$question) {
            return null;
        }

        return $this->buildAction(
            'Soruda yanıtla',
            $fallbackTitle ?: ($question->question_text ? mb_strimwidth($question->question_text, 0, 70, '...') : 'Müşteri sorusu'),
            route('marketplace-messages', ['question' => $question->id]),
            'Soru merkezinde seçili kayıt açılır',
            'marketplace_questions',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function returnItemAction(int $itemId, ?string $fallbackTitle = null): ?array
    {
        $item = ReturnIntakeItem::query()->find($itemId, ['id', 'detected_order_number', 'detected_customer_name', 'intake_status']);

        if (!$item) {
            return null;
        }

        return $this->buildAction(
            'İade merkezinde aç',
            $fallbackTitle ?: ('İade #' . $item->id),
            route('returns.workspace', ['tab' => 'havuz', 'item' => $item->id]),
            'Karar havuzunda seçili iade açılır',
            'returns',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function claimAction(int $claimId, ?string $fallbackTitle = null): ?array
    {
        $claim = ChannelClaim::query()->find($claimId, ['id', 'external_claim_id', 'order_number', 'customer_name', 'status']);

        if (!$claim) {
            return null;
        }

        $reference = $claim->external_claim_id ?: $claim->order_number;

        return $this->buildAction(
            'Pazaryeri iadesinde aç',
            $fallbackTitle ?: ('Pazaryeri iadesi #' . ($reference ?: $claim->id)),
            route('returns.workspace', ['tab' => 'pazaryeri', 'claim' => $claim->id]),
            'Pazaryeri iade merkezinde seçili claim açılır',
            'marketplace_claims',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function cargoAction(int $itemId, ?string $fallbackTitle = null): ?array
    {
        $item = CargoReportItem::query()->find($itemId, ['id', 'takip_kodu', 'siparis_no', 'musteri_adi', 'tutar_fark']);

        if (!$item) {
            return null;
        }

        return $this->buildAction(
            'Kargo tazminde aç',
            $fallbackTitle ?: ('Kargo farkı #' . ($item->takip_kodu ?: $item->siparis_no ?: $item->id)),
            route('cargo-reports', ['activeTab' => 'compensation', 'cargoItem' => $item->id]),
            'Tazmin sekmesinde bu farktan talep başlatılır',
            'cargo_reports',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function supplyAction(int $orderId, ?string $fallbackTitle = null): ?array
    {
        $order = SupplyOrder::query()->find($orderId, ['id', 'siparis_no', 'musteri_adi', 'durum']);

        if (!$order) {
            return null;
        }

        return $this->buildAction(
            'Tedarik raporunda aç',
            $fallbackTitle ?: ('Tedarik #' . ($order->siparis_no ?: $order->id)),
            route('supply-reports', array_filter(['search' => $order->siparis_no ?: null])),
            'Tedarik raporu sipariş filtresiyle açılır',
            'supply_reports',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    protected function customerLedgerAction(int $entryId, ?string $fallbackTitle = null): ?array
    {
        $entry = CrmCustomerLedgerEntry::query()->find($entryId, ['id', 'contact_id', 'product_name', 'marketplace_order_number']);

        if (!$entry) {
            return null;
        }

        return $this->buildAction(
            'Müşteri caride aç',
            $fallbackTitle ?: ($entry->product_name ?: 'Müşteri cari hareketi'),
            route('crm.customer-ledger', array_filter([
                'contact' => $entry->contact_id,
                'search' => $entry->marketplace_order_number ?: null,
            ])),
            'Cari defter seçili müşteriyle açılır',
            'crm_customer_ledger',
        );
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}
     */
    protected function buildAction(string $label, string $title, string $url, string $hint, string $source): array
    {
        return compact('label', 'title', 'url', 'hint', 'source');
    }

    protected function normalizeSource(string $source): string
    {
        return str_replace('-', '_', strtolower(trim($source)));
    }
}
