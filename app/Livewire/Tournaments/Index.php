<?php

namespace App\Livewire\Tournaments;

use App\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Every tournament, newest first. Public, like the tournament pages; admins
 * also get the form to start a new one.
 */
#[Layout('components.layouts.app')]
#[Title('Tournaments — BMI Challenge')]
class Index extends Component
{
    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|date')]
    public string $startsAt = '';

    #[Validate('nullable|string|max:255')]
    public string $location = '';

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->can('manage-tournaments');
    }

    public function newTournament(): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->reset('name', 'startsAt', 'location');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    /** Straight on to setup — a tournament with no teams isn't worth looking at. */
    public function create(): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->name = trim($this->name);
        $this->validate();

        $tournament = Tournament::create([
            'name' => $this->name,
            'slug' => Tournament::slugFor($this->name),
            'starts_at' => $this->startsAt ?: null,
            'location' => trim($this->location) ?: null,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->redirectRoute('tournaments.setup', $tournament);
    }

    public function render()
    {
        return view('livewire.tournaments.index', [
            'tournaments' => Tournament::withCount(['teams', 'fixtures'])
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get(),
            'isAdmin' => $this->isAdmin(),
        ]);
    }
}
