<?php

namespace App\Livewire;

use App\Services\MpSettingsService;
use Livewire\Component;

class MarketplaceSettings extends Component
{
    public bool $helpTipsEnabled = true;

    public array $labelPrintSettings = [];

    public array $dispatchPrintSettings = [];

    public array $companyForm = [
        'name' => '',
        'phone' => '',
        'tax_number' => '',
        'address' => '',
    ];

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $settings = app(MpSettingsService::class);

        $this->helpTipsEnabled = $settings->helpTipsEnabled();
        $this->labelPrintSettings = $settings->getArray('print.label', $this->defaultLabelPrintSettings());
        $this->dispatchPrintSettings = $settings->getArray('print.dispatch', $this->defaultDispatchPrintSettings());
        $this->companyForm = [
            'name' => (string) $settings->get('company.name', ''),
            'phone' => (string) $settings->get('company.phone', ''),
            'tax_number' => (string) $settings->get('company.tax_number', ''),
            'address' => (string) $settings->get('company.address', ''),
        ];
    }

    public function saveSettings(): void
    {
        app(MpSettingsService::class)->setMany([
            'ui.help_tips_enabled' => (bool) $this->helpTipsEnabled,
        ]);

        session()->flash('settings_success', 'Genel ayarlar kaydedildi.');
    }

    public function saveDocumentSettings(): void
    {
        $validated = $this->validate([
            'labelPrintSettings.template' => ['required', 'in:courier,compact,minimal'],
            'labelPrintSettings.paper' => ['required', 'in:thermal_100x150,a6,a6_landscape,a5'],
            'labelPrintSettings.barcode_type' => ['required', 'in:code128'],
            'labelPrintSettings.barcode_height' => ['required', 'integer', 'min:32', 'max:96'],
            'labelPrintSettings.show_sender' => ['boolean'],
            'labelPrintSettings.show_customer_phone' => ['boolean'],
            'labelPrintSettings.show_items' => ['boolean'],
            'labelPrintSettings.show_marketplace' => ['boolean'],
            'labelPrintSettings.show_tracking_number' => ['boolean'],
            'labelPrintSettings.show_barcode_text' => ['boolean'],
            'labelPrintSettings.show_item_summary' => ['boolean'],
            'labelPrintSettings.footer_note' => ['nullable', 'string', 'max:240'],
            'dispatchPrintSettings.template' => ['required', 'in:classic,compact,warehouse'],
            'dispatchPrintSettings.paper' => ['required', 'in:a4,a4_landscape,a5,a5_landscape'],
            'dispatchPrintSettings.barcode_type' => ['required', 'in:code128'],
            'dispatchPrintSettings.barcode_height' => ['required', 'integer', 'min:32', 'max:96'],
            'dispatchPrintSettings.show_sender' => ['boolean'],
            'dispatchPrintSettings.show_customer_phone' => ['boolean'],
            'dispatchPrintSettings.show_billing_info' => ['boolean'],
            'dispatchPrintSettings.show_items' => ['boolean'],
            'dispatchPrintSettings.show_barcode' => ['boolean'],
            'dispatchPrintSettings.show_barcode_text' => ['boolean'],
            'dispatchPrintSettings.show_marketplace' => ['boolean'],
            'dispatchPrintSettings.show_signature_area' => ['boolean'],
            'dispatchPrintSettings.footer_note' => ['nullable', 'string', 'max:240'],
            'companyForm.name' => ['nullable', 'string', 'max:150'],
            'companyForm.phone' => ['nullable', 'string', 'max:32'],
            'companyForm.tax_number' => ['nullable', 'string', 'max:32'],
            'companyForm.address' => ['nullable', 'string', 'max:500'],
        ]);

        app(MpSettingsService::class)->setMany([
            'print.label' => $this->normalizeDocumentSettings($validated['labelPrintSettings']),
            'print.dispatch' => $this->normalizeDocumentSettings($validated['dispatchPrintSettings']),
            'company.name' => trim((string) ($validated['companyForm']['name'] ?? '')),
            'company.phone' => trim((string) ($validated['companyForm']['phone'] ?? '')),
            'company.tax_number' => trim((string) ($validated['companyForm']['tax_number'] ?? '')),
            'company.address' => trim((string) ($validated['companyForm']['address'] ?? '')),
        ]);

        $this->loadSettings();

        session()->flash('document_settings_success', 'Kargo barkod ve çıktı ayarları kaydedildi.');
    }

    public function resetUiSettings(): void
    {
        app(MpSettingsService::class)->set('ui.help_tips_enabled', true);

        $this->helpTipsEnabled = true;

        session()->flash('settings_success', 'Arayüz ayarları varsayılan değerlere döndürüldü.');
    }

    public function resetDocumentSettings(): void
    {
        $settings = app(MpSettingsService::class);
        $defaults = $settings->getDefaults();

        $settings->setMany([
            'print.label' => $defaults['print']['label'] ?? $this->defaultLabelPrintSettings(),
            'print.dispatch' => $defaults['print']['dispatch'] ?? $this->defaultDispatchPrintSettings(),
            'company.name' => '',
            'company.phone' => '',
            'company.tax_number' => '',
            'company.address' => '',
        ]);

        $this->loadSettings();

        session()->flash('document_settings_success', 'Çıktı ayarları varsayılan değerlere döndürüldü.');
    }

    public function render()
    {
        return view('livewire.marketplace-settings', [
            'helpTipCoverage' => [
                'Özet',
                'Entegrasyonlar',
                'Siparişler',
                'Ürünler',
                'Eşleştirme',
                'Finans',
                'Muhasebe',
            ],
            'labelTemplateOptions' => [
                'courier' => 'Kurye standart',
                'compact' => 'Kompakt operasyon',
                'minimal' => 'Minimal termal',
            ],
            'labelPaperOptions' => [
                'thermal_100x150' => 'Termal 100x150',
                'a6' => 'A6 dikey',
                'a6_landscape' => 'A6 yatay',
                'a5' => 'A5 dikey',
            ],
            'dispatchTemplateOptions' => [
                'classic' => 'Klasik irsaliye',
                'compact' => 'Kompakt sevk',
                'warehouse' => 'Depo operasyon',
            ],
            'dispatchPaperOptions' => [
                'a4' => 'A4 dikey',
                'a4_landscape' => 'A4 yatay',
                'a5' => 'A5 dikey',
                'a5_landscape' => 'A5 yatay',
            ],
        ])->layout('layouts.app', ['title' => 'Pazaryeri Ayarları']);
    }

    protected function normalizeDocumentSettings(array $settings): array
    {
        $normalized = $settings;

        foreach ($normalized as $key => $value) {
            if (is_bool($value)) {
                $normalized[$key] = (bool) $value;
                continue;
            }

            if ($key === 'barcode_height') {
                $normalized[$key] = (int) $value;
                continue;
            }

            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    protected function defaultLabelPrintSettings(): array
    {
        return [
            'template' => 'courier',
            'paper' => 'thermal_100x150',
            'barcode_type' => 'code128',
            'barcode_height' => 56,
            'show_sender' => true,
            'show_customer_phone' => true,
            'show_items' => true,
            'show_marketplace' => true,
            'show_tracking_number' => true,
            'show_barcode_text' => true,
            'show_item_summary' => true,
            'footer_note' => '',
        ];
    }

    protected function defaultDispatchPrintSettings(): array
    {
        return [
            'template' => 'classic',
            'paper' => 'a4',
            'barcode_type' => 'code128',
            'barcode_height' => 44,
            'show_sender' => true,
            'show_customer_phone' => true,
            'show_billing_info' => true,
            'show_items' => true,
            'show_barcode' => true,
            'show_barcode_text' => true,
            'show_marketplace' => true,
            'show_signature_area' => true,
            'footer_note' => '',
        ];
    }
}
