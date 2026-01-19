<?php

namespace App\Livewire;

use App\Models\Profile;
use Livewire\Component;

class ProfileManager extends Component
{
    public bool $showModal = false;
    public bool $showRulesModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'production';
    public bool $isDefault = false;
    
    // Kuralları gösterme
    public ?array $viewingRules = null;
    public string $viewingProfileName = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'type' => 'required|in:production,operation',
        'isDefault' => 'boolean',
    ];

    public function create()
    {
        $this->reset(['editingId', 'name', 'type', 'isDefault']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $profile = Profile::findOrFail($id);
        $this->editingId = $profile->id;
        $this->name = $profile->name;
        $this->type = $profile->type;
        $this->isDefault = $profile->is_default;
        $this->showModal = true;
    }

    public function viewRules($id)
    {
        $profile = Profile::findOrFail($id);
        $this->viewingRules = $profile->ai_generated_rules ?? [];
        $this->viewingProfileName = $profile->name;
        $this->showRulesModal = true;
    }

    public function closeRulesModal()
    {
        $this->showRulesModal = false;
        $this->viewingRules = null;
        $this->viewingProfileName = '';
    }

    public function save()
    {
        $this->validate();

        $data = [
            'user_id' => auth()->id(),
            'name' => $this->name,
            'type' => $this->type,
            'is_default' => $this->isDefault,
        ];

        if ($this->isDefault) {
            Profile::where('type', $this->type)->update(['is_default' => false]);
        }

        if ($this->editingId) {
            Profile::find($this->editingId)->update($data);
        } else {
            Profile::create($data);
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'type', 'isDefault']);
    }

    public function delete($id)
    {
        Profile::find($id)?->delete();
    }

    public function setDefault($id)
    {
        $profile = Profile::findOrFail($id);
        Profile::where('type', $profile->type)->update(['is_default' => false]);
        $profile->update(['is_default' => true]);
    }

    public function getProfilesProperty()
    {
        return Profile::orderBy('type')->orderBy('is_default', 'desc')->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.profile-manager')
            ->layout('layouts.app');
    }
}
