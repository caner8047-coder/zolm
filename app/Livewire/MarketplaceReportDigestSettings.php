<?php

namespace App\Livewire;

use App\Models\MarketplaceReportDigestRun;
use App\Models\MarketplaceReportSubscription;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceReportDigestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MarketplaceReportDigestSettings extends Component
{
    public bool $enabled = true;
    public string $name = '';
    public string $frequency = MarketplaceReportSubscription::FREQUENCY_DAILY;
    public string $storeId = '';
    public string $sendTime = '08:30';
    public string $recipientsText = '';
    public bool $webhookEnabled = false;
    public string $webhookUrl = '';
    public bool $telegramEnabled = false;
    public string $telegramBotToken = '';
    public string $telegramChatId = '';
    public array $selectedSections = [];
    public ?string $notice = null;
    public string $noticeTone = 'success';

    public function mount(): void
    {
        $subscription = $this->digestService()->defaultSubscriptionForUser($this->userId());
        $this->fillFromSubscription($subscription);
    }

    #[Computed]
    public function subscription(): MarketplaceReportSubscription
    {
        return $this->digestService()
            ->defaultSubscriptionForUser($this->userId())
            ->load(['store']);
    }

    #[Computed]
    public function storeOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'marketplace']);
    }

    #[Computed]
    public function preview(): array
    {
        try {
            return array_merge(
                ['available' => true],
                $this->digestService()->buildPayload($this->subscription, now())
            );
        } catch (\Throwable) {
            return [
                'available' => false,
                'message' => 'Ön izleme şu anda hazırlanamadı. Rapor kaynaklarını ve entegrasyon verilerini kontrol edin.',
            ];
        }
    }

    #[Computed]
    public function recentRuns()
    {
        return MarketplaceReportDigestRun::query()
            ->where('user_id', $this->userId())
            ->with(['subscription:id,name', 'report:id,status'])
            ->latest()
            ->limit(8)
            ->get();
    }

    public function save(): void
    {
        $this->resetErrorBag();

        $data = $this->validate([
            'enabled' => ['boolean'],
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', Rule::in(array_keys($this->frequencyDefinitions()))],
            'storeId' => ['nullable', 'integer'],
            'sendTime' => ['required', 'date_format:H:i'],
            'recipientsText' => ['required', 'string', 'max:2000'],
            'webhookEnabled' => ['boolean'],
            'webhookUrl' => ['nullable', 'url', 'max:2000'],
            'telegramEnabled' => ['boolean'],
            'telegramBotToken' => ['nullable', 'string', 'max:255'],
            'telegramChatId' => ['nullable', 'string', 'max:255'],
            'selectedSections' => ['array', 'min:1'],
            'selectedSections.*' => ['string', Rule::in(array_keys($this->sectionDefinitions()))],
        ], [], [
            'name' => 'rapor adı',
            'frequency' => 'sıklık',
            'storeId' => 'mağaza',
            'sendTime' => 'gönderim saati',
            'recipientsText' => 'alıcılar',
            'webhookUrl' => 'webhook URL',
            'telegramBotToken' => 'Telegram Bot Token',
            'telegramChatId' => 'Telegram Chat ID',
            'selectedSections' => 'rapor bölümleri',
        ]);

        $recipients = $this->parseRecipients($this->recipientsText);

        if ($recipients === []) {
            $this->addError('recipientsText', 'En az bir geçerli e-posta adresi girin.');

            return;
        }

        $storeId = (int) ($data['storeId'] ?: 0);
        if ($storeId > 0 && ! $this->storeBelongsToUser($storeId)) {
            $this->addError('storeId', 'Bu mağaza için rapor aboneliği oluşturamazsınız.');

            return;
        }

        $channels = ['email'];
        if ((bool) $data['webhookEnabled']) {
            $channels[] = 'webhook';
        }
        if ((bool) $data['telegramEnabled']) {
            $channels[] = 'telegram';
        }

        $subscription = $this->subscription;
        $subscription->forceFill([
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'store_id' => $storeId > 0 ? $storeId : null,
            'channels_json' => $channels,
            'webhook_url' => $data['webhookUrl'],
            'telegram_bot_token' => $data['telegramBotToken'],
            'telegram_chat_id' => $data['telegramChatId'],
            'recipients_json' => $recipients,
            'sections_json' => array_values($data['selectedSections']),
            'filters_json' => [],
            'enabled' => (bool) $data['enabled'],
            'send_time' => $data['sendTime'],
            'timezone' => (string) config('app.timezone', 'Europe/Istanbul'),
            'next_run_at' => (bool) $data['enabled']
                ? $this->digestService()->nextRunAt($data['frequency'], $data['sendTime'], (string) config('app.timezone', 'Europe/Istanbul'), now())
                : null,
            'last_error' => null,
        ])->save();

        unset($this->subscription, $this->preview, $this->recentRuns);

        $this->noticeTone = 'success';
        $this->notice = 'Otomatik rapor ayarları kaydedildi.';
    }

    public function sendNow(): void
    {
        $this->save();

        if ($this->getErrorBag()->any()) {
            return;
        }

        $result = $this->digestService()->sendSubscription(
            $this->subscription->fresh(['user', 'store']),
            now(),
            true,
        );

        unset($this->subscription, $this->preview, $this->recentRuns);

        $this->noticeTone = $result['failed'] > 0 ? 'warning' : 'success';
        $this->notice = sprintf(
            'Manuel gönderim tamamlandı: %d gönderildi, %d başarısız.',
            $result['sent'],
            $result['failed'],
        );
    }

    public function toggleSection(string $section): void
    {
        if (! array_key_exists($section, $this->sectionDefinitions())) {
            return;
        }

        if (in_array($section, $this->selectedSections, true)) {
            if (count($this->selectedSections) > 1) {
                $this->selectedSections = array_values(array_diff($this->selectedSections, [$section]));
            }

            return;
        }

        $this->selectedSections[] = $section;
        $this->selectedSections = array_values(array_unique($this->selectedSections));
    }

    public function frequencyDefinitions(): array
    {
        return $this->digestService()->frequencyDefinitions();
    }

    public function sectionDefinitions(): array
    {
        return $this->digestService()->sectionDefinitions();
    }

    public function formatMoney(float|int|null $value): string
    {
        return '₺' . number_format((float) $value, 2, ',', '.');
    }

    public function formatPercent(float|int|null $value): string
    {
        return '%' . number_format((float) $value, 1, ',', '.');
    }

    public function formatNumber(float|int|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'sent', 'success' => 'Gönderildi',
            'failed' => 'Hatalı',
            'pending' => 'Bekliyor',
            'processing' => 'İşleniyor',
            default => 'Hazır',
        };
    }

    public function statusTone(?string $status): string
    {
        return match ($status) {
            'sent', 'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
            'pending', 'processing' => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function render()
    {
        return view('livewire.marketplace-report-digest-settings')
            ->layout('layouts.app', ['title' => 'Otomatik Raporlar']);
    }

    protected function fillFromSubscription(MarketplaceReportSubscription $subscription): void
    {
        $this->enabled = (bool) $subscription->enabled;
        $this->name = (string) $subscription->name;
        $this->frequency = (string) $subscription->frequency;
        $this->storeId = $subscription->store_id ? (string) $subscription->store_id : '';
        $this->sendTime = (string) ($subscription->send_time ?: '08:30');
        $this->recipientsText = implode("\n", $this->parseRecipients(implode("\n", (array) $subscription->recipients_json)));
        $this->webhookEnabled = in_array('webhook', (array) $subscription->channels_json, true);
        $this->webhookUrl = (string) $subscription->webhook_url;
        $this->telegramEnabled = in_array('telegram', (array) $subscription->channels_json, true);
        $this->telegramBotToken = (string) $subscription->telegram_bot_token;
        $this->telegramChatId = (string) $subscription->telegram_chat_id;
        $this->selectedSections = array_values((array) ($subscription->sections_json ?: array_keys($this->sectionDefinitions())));
    }

    protected function parseRecipients(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', $value) ?: [])
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    protected function storeBelongsToUser(int $storeId): bool
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->whereKey($storeId)
            ->exists();
    }

    protected function digestService(): MarketplaceReportDigestService
    {
        return app(MarketplaceReportDigestService::class);
    }

    protected function userId(): int
    {
        return (int) Auth::id();
    }
}
