<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaContact;
use App\Models\WaCustomerProfile;
use App\Services\WhatsApp\CustomerProfileService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WhatsAppCustomerProfile extends Component
{
    use WithPagination;

    public int $contactId = 0;
    public array $profileData = [];
    public string $searchPhone = '';

    public function mount(int $id = 0): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->contactId = $id;
        if ($id > 0) {
            $this->loadProfile($id);
        }
    }

    public function loadProfile(int $id): void
    {
        $contact = WaContact::find($id);
        if (!$contact) {
            return;
        }
        $service = app(CustomerProfileService::class);
        $this->profileData = $service->getProfilePageData($id, $contact->store_id);
    }

    public function searchByPhone(): void
    {
        if (empty($this->searchPhone)) return;
        $phoneHash = WaContact::hashPhone($this->searchPhone);
        $contact = WaContact::where('phone_hash', $phoneHash)->first();
        if ($contact) {
            $this->contactId = $contact->id;
            $this->loadProfile($contact->id);
        }
    }

    public function getContactsProperty()
    {
        return WaContact::with('store')->orderByDesc('last_seen_at')->paginate(20);
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-customer-profile');
    }
}
