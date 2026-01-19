<?php

namespace App\Livewire;

use App\Models\Profile;
use Livewire\Component;

class ProfileManager extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'production';
    public bool $isDefault = false;

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

    public function getProfilesProperty()
    {
        return Profile::orderBy('type')->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.profile-manager')
            ->layout('layouts.app');
    }
}
