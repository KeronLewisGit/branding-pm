<?php

declare(strict_types=1);

namespace App\Livewire\Kiosk;

use App\Http\Middleware\EnsureKioskDevice;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The "tap your name" grid that precedes the kiosk PIN pad.
 *
 * Lists active users with a PIN who are assigned to the machine in
 * question (user_machine); if none are assigned it falls back to the
 * active PIN users at the device's site (via its location), and as a
 * last resort to every active PIN user, so the kiosk never dead-ends.
 *
 * Tapping a tile links to route('kiosk.pin.show') — the PIN pad rendered
 * by Kiosk\KioskSessionController::create().
 */
#[Layout('layouts.kiosk')]
class OperatorPicker extends Component
{
    #[Locked]
    public ?int $machineId = null;

    #[Locked]
    public ?string $machineName = null;

    #[Locked]
    public ?int $runId = null;

    #[Locked]
    public ?int $deviceSiteId = null;

    public string $search = '';

    public function mount(?Machine $machine = null, ?int $run = null): void
    {
        $this->machineId = $machine?->id;
        $this->machineName = $machine?->name;
        $this->runId = $run;

        /*
         * Livewire's subsequent update requests do not pass back through
         * the `kiosk` middleware, so the device's site is resolved once at
         * mount and locked into component state.
         */
        $this->deviceSiteId = EnsureKioskDevice::device(request())?->location?->site_id;
    }

    public function render(): View
    {
        return view('livewire.kiosk.operator-picker', [
            'operators' => $this->operators(),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function operators(): Collection
    {
        $query = $this->baseScope();

        $search = trim($this->search);

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

            $query->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('employee_number', 'like', $like));
        }

        return $query->orderBy('full_name')->limit(60)->get();
    }

    /**
     * @return Builder<User>
     */
    private function baseScope(): Builder
    {
        // 1. Operators assigned to this machine …
        if ($this->machineId !== null) {
            $assigned = $this->pinUsers()
                ->whereHas('machines', fn (Builder $q) => $q->whereKey($this->machineId));

            if ((clone $assigned)->exists()) {
                return $assigned;
            }
        }

        // 2. … else every PIN operator at the device's site …
        if ($this->deviceSiteId !== null) {
            $atSite = $this->pinUsers()->where('default_site_id', $this->deviceSiteId);

            if ((clone $atSite)->exists()) {
                return $atSite;
            }
        }

        // 3. … else anyone active with a PIN.
        return $this->pinUsers();
    }

    /**
     * @return Builder<User>
     */
    private function pinUsers(): Builder
    {
        return User::query()->active()->whereNotNull('pin');
    }
}
