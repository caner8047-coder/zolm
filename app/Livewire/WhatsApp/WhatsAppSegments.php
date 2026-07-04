<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaSegment;
use App\Services\WhatsApp\AuditLogService;
use App\Services\WhatsApp\SegmentEngine;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppSegments extends Component
{
    public string $searchQuery = '';
    public ?int $selectedSegmentId = null;

    // Yeni segment formu
    public bool $showCreateForm = false;
    public string $newName = '';
    public string $newDescription = '';
    public int $newStoreId = 0;
    public array $newFilters = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function getSegmentsProperty()
    {
        $query = \App\Models\WaSegment::with('store', 'creator')
            ->orderByDesc('updated_at');

        if ($this->searchQuery) {
            $query->where('name', 'like', "%{$this->searchQuery}%");
        }

        return $query->get();
    }

    public function getAvailableStoresProperty()
    {
        return \App\Models\MarketplaceStore::where('marketplace', 'woocommerce')
            ->where('is_active', true)
            ->get();
    }

    public function selectSegment(int $segmentId): void
    {
        $this->selectedSegmentId = $segmentId;
    }

    public function calculateSegment(int $segmentId): void
    {
        $segment = WaSegment::findOrFail($segmentId);
        $engine = app(SegmentEngine::class);
        $count = $engine->estimateCount($segment);

        session()->flash('wa_success', "Segment hesaplandı: {$count} müşteri.");
    }

    public function createSegment(): void
    {
        $this->validate([
            'newName' => 'required|string|max:120',
            'newStoreId' => 'required|exists:marketplace_stores,id',
        ]);

        $segment = WaSegment::create([
            'store_id' => $this->newStoreId,
            'name' => $this->newName,
            'description' => $this->newDescription,
            'status' => 'active',
            'rules_json' => ['filters' => $this->newFilters],
            'created_by' => auth()->id(),
        ]);

        // İlk hesaplama
        $engine = app(SegmentEngine::class);
        $engine->estimateCount($segment);

        app(AuditLogService::class)->log('segment_created', 'wa_segment', $segment->id, ['name' => $this->newName]);

        $this->showCreateForm = false;
        $this->newName = '';
        $this->newDescription = '';
        $this->newFilters = [];

        session()->flash('wa_success', 'Segment oluşturuldu.');
    }

    public function archiveSegment(int $segmentId): void
    {
        $segment = WaSegment::findOrFail($segmentId);
        $segment->update(['status' => 'archived']);

        app(AuditLogService::class)->log('segment_archived', 'wa_segment', $segment->id);
        session()->flash('wa_success', 'Segment arşivlendi.');
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-segments');
    }
}
