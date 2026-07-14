<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportDispatch;
use App\Services\Support\CustomerCareOrganizationContext;

class Home extends Component
{
    public int $storeCount = 0;
    public int $channelCount = 0;
    public int $openConversationCount = 0;
    public int $pendingDispatchCount = 0;
    public string $automationMode = 'manual';
    public bool $isAutoReplyEnabled = false;
    public array $quickLinks = [];

    public function mount()
    {
        $user = auth()->user();
        if ($user) {
            $storeIds = CustomerCareOrganizationContext::getAccessibleStores($user)->pluck('id')->toArray();

            $this->storeCount = count($storeIds);
            $this->channelCount = SupportChannel::whereIn('store_id', $storeIds)->where('is_enabled', true)->count();
            $this->openConversationCount = SupportConversation::whereIn('store_id', $storeIds)
                ->whereIn('status', ['open', 'pending'])
                ->count();
            $this->pendingDispatchCount = SupportDispatch::whereIn('support_channel_id', function ($query) use ($storeIds) {
                $query->select('id')->from('support_channels')->whereIn('store_id', $storeIds);
            })->where('status', 'pending')->count();
        } else {
            $this->storeCount = 0;
            $this->channelCount = 0;
            $this->openConversationCount = 0;
            $this->pendingDispatchCount = 0;
        }

        $this->automationMode = config('customer-care.default_automation_mode', 'manual');
        $this->isAutoReplyEnabled = config('customer-care.auto_reply_enabled', false);
        $this->quickLinks = $this->buildQuickLinks();
    }

    public function render()
    {
        return view('livewire.customer-care.home')
            ->layout('layouts.app');
    }

    protected function buildQuickLinks(): array
    {
        $links = [
            [
                'title' => '1. Kanalları Oluştur',
                'description' => 'Mevcut pazaryeri, WhatsApp ve entegrasyon kayıtlarından güvenli Support kanalları oluştur.',
                'route' => 'customer-care.settings',
                'flag' => 'settings_enabled',
                'badge' => 'İlk adım',
            ],
            [
                'title' => '2. Gelen Kutusunu Aç',
                'description' => 'AI kapalıyken bile manuel destek operasyonunu tek ekrandan yürüt.',
                'route' => 'customer-care.inbox',
                'flag' => 'inbox_enabled',
                'badge' => 'Operasyon',
            ],
            [
                'title' => '3. Pilot Hazırlığını Kontrol Et',
                'description' => 'Golden eval, circuit breaker, kanal sağlığı ve otomasyon kapılarını doğrula.',
                'route' => 'customer-care.pilot',
                'flag' => 'pilot_dashboard_enabled',
                'badge' => 'Pilot',
            ],
            [
                'title' => '4. Canlıya Geçiş Kanıtı Üret',
                'description' => 'Sertifikasyon, güvenlik ve production readiness kontrollerini tek merkezde tamamla.',
                'route' => 'customer-care.production',
                'flag' => 'production_center_enabled',
                'badge' => 'Canlı',
            ],
        ];

        return array_values(array_filter($links, fn ($link) => config('customer-care.' . $link['flag'], false)));
    }
}
