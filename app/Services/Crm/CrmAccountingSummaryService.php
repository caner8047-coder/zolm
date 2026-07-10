<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\Party;
use App\Models\PartyLedgerEntry;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\User;
use App\Services\Accounting\PartyLedgerService;

class CrmAccountingSummaryService
{
    /**
     * CRM Contact için güvenli, tenant izole cari/muhasebe özeti üretir.
     */
    public function summaryForContact(User $user, CrmContact $contact): array
    {
        $crmEnabled = (bool) config('marketplace.features.crm_enabled', false);
        $partyCoreEnabled = (bool) config('marketplace.features.party_core_enabled', false);
        $accountingEnabled = (bool) config('marketplace.features.accounting_enabled', false);

        if (!$crmEnabled || !$partyCoreEnabled || (int) $contact->user_id !== (int) $user->id) {
            return [
                'enabled' => false,
                'has_party' => false,
                'party' => null,
                'balance' => null,
                'open_totals' => null,
                'latest_entries' => [],
                'links' => [
                    'party_ledger_url' => null,
                    'accounting_party_url' => null,
                ],
            ];
        }

        $party = $this->resolvePartyForContact($user, $contact);
        if (!$party) {
            return [
                'enabled' => true,
                'has_party' => false,
                'party' => null,
                'balance' => null,
                'open_totals' => null,
                'latest_entries' => [],
                'links' => [
                    'party_ledger_url' => null,
                    'accounting_party_url' => null,
                ],
            ];
        }

        $party->loadMissing('roles');

        $balance = null;
        $openTotals = null;
        $latestEntries = [];
        $links = [
            'party_ledger_url' => null,
            'accounting_party_url' => null,
        ];

        if ($accountingEnabled) {
            $balance = $this->balanceSummary($user, $party);
            $openTotals = $this->openTotals($user, $party);
            $latestEntries = $this->latestLedgerEntries($user, $party);
            $links = $this->accountingLinks($user, $party, $contact);
        }

        return [
            'enabled' => true,
            'has_party' => true,
            'party' => [
                'id' => $party->id,
                'display_name' => $party->display_name,
                'status' => $party->status,
                'party_type' => $party->party_type,
                'roles' => $party->roles->pluck('role')->toArray(),
            ],
            'balance' => $balance,
            'open_totals' => $openTotals,
            'latest_entries' => $latestEntries,
            'links' => $links,
        ];
    }

    /**
     * CRM contact'ın bağlı olduğu Party modelini çözer ve tenant doğrulaması yapar.
     */
    public function resolvePartyForContact(User $user, CrmContact $contact): ?Party
    {
        if ((int) $contact->user_id !== (int) $user->id) {
            return null;
        }

        if ($contact->party_id) {
            $party = Party::find($contact->party_id);
            if ($party && (int) $party->user_id === (int) $user->id) {
                return $party;
            }
        }

        return null;
    }

    /**
     * Party'ye ait son posted durumundaki ledger hareketlerini getirir.
     */
    public function latestLedgerEntries(User $user, Party $party, int $limit = 8): array
    {
        if ((int) $party->user_id !== (int) $user->id) {
            return [];
        }

        $entries = PartyLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('party_id', $party->id)
            ->posted()
            ->latest('document_date')
            ->latest('id')
            ->limit($limit)
            ->get();

        return $entries->map(fn(PartyLedgerEntry $entry) => [
            'id' => $entry->id,
            'document_date' => $entry->document_date ? $entry->document_date->toDateString() : null,
            'document_type' => $entry->document_type,
            'description' => $entry->description,
            'debit_amount' => (float) $entry->debit_amount,
            'credit_amount' => (float) $entry->credit_amount,
            'status' => $entry->status,
        ])->toArray();
    }

    /**
     * Party cari bakiye durumunu hesaplar.
     */
    public function balanceSummary(User $user, Party $party): array
    {
        if ((int) $party->user_id !== (int) $user->id) {
            return [
                'debit_total' => 0.0,
                'credit_total' => 0.0,
                'net_balance' => 0.0,
                'direction' => 'balanced',
                'label' => 'Dengede',
            ];
        }

        $balanceData = app(PartyLedgerService::class)->balanceForParty($party);
        $debit = (float) $balanceData['debit'];
        $credit = (float) $balanceData['credit'];
        $net = $debit - $credit;

        if ($net > 0.005) {
            $direction = 'receivable';
            $label = 'Biz alacaklıyız';
        } elseif ($net < -0.005) {
            $direction = 'payable';
            $label = 'Biz borçluyuz';
        } else {
            $direction = 'balanced';
            $label = 'Dengede';
        }

        return [
            'debit_total' => $debit,
            'credit_total' => $credit,
            'net_balance' => $net,
            'direction' => $direction,
            'label' => $label,
        ];
    }

    /**
     * Açık alacak / borç bakiyeleri ve adetlerini hesaplar.
     */
    protected function openTotals(User $user, Party $party): array
    {
        if ((int) $party->user_id !== (int) $user->id) {
            return [
                'open_receivable' => 0.0,
                'open_payable' => 0.0,
                'open_entry_count' => 0,
            ];
        }

        $openReceivables = Receivable::query()
            ->where('user_id', $user->id)
            ->where('party_id', $party->id)
            ->whereIn('status', ['open', 'partially_paid'])
            ->get();

        $openPayables = Payable::query()
            ->where('user_id', $user->id)
            ->where('party_id', $party->id)
            ->whereIn('status', ['open', 'partially_paid'])
            ->get();

        $openReceivableTotal = (float) $openReceivables->sum(fn($r) => $r->remainingAmount());
        $openPayableTotal = (float) $openPayables->sum(fn($p) => $p->remainingAmount());
        $openEntryCount = $openReceivables->count() + $openPayables->count();

        return [
            'open_receivable' => $openReceivableTotal,
            'open_payable' => $openPayableTotal,
            'open_entry_count' => $openEntryCount,
        ];
    }

    /**
     * Muhasebe ekranlarına verilecek güvenli linkleri üretir.
     */
    public function accountingLinks(User $user, Party $party, CrmContact $contact): array
    {
        $accountingEnabled = (bool) config('marketplace.features.accounting_enabled', false);

        return [
            'party_ledger_url' => ($accountingEnabled && $party->id) ? route('accounting.party-ledger', ['party' => $party->id]) : null,
            'accounting_party_url' => null,
        ];
    }
}
