<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Location;
use App\Models\Machine;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Printable QR sticker sheet (milestone 8) — route `admin.machines.qr`.
 *
 * Each sticker encodes the machine's deep link, `/m/{code}`, which is what
 * `Machine::getRouteKeyName()` binds on. The code is printed under the QR in
 * plain text as well: a sticker that has been scratched, painted over or
 * covered in ink still has to be usable by someone typing it in, and a
 * machine in a print shop will get all three.
 *
 * Codes are permanent once printed — changing a machine code invalidates the
 * sticker on the machine (contract §2, seed-notes C8), which is why the
 * machine admin screen warns before a code edit.
 */
#[Layout('layouts::app')]
class QrStickerSheet extends Component
{
    /** Stickers per row on the printed page. */
    public const COLUMNS = 3;

    #[Url]
    public string $location = '';

    #[Url(as: 'inactive')]
    public bool $includeInactive = false;

    /** @var array<int, bool> machine id => selected */
    public array $selected = [];

    public function mount(): void
    {
        abort_unless((bool) Auth::user()?->can('machine.manage'), 403);

        // Everything is selected until the user says otherwise — the common
        // case is printing a whole location's worth of stickers at once.
        $this->selected = $this->machines()
            ->mapWithKeys(fn (Machine $machine): array => [$machine->id => true])
            ->all();
    }

    public function updatedLocation(): void
    {
        $this->syncSelection();
    }

    public function updatedIncludeInactive(): void
    {
        $this->syncSelection();
    }

    public function selectAll(): void
    {
        $this->selected = $this->machines()
            ->mapWithKeys(fn (Machine $machine): array => [$machine->id => true])
            ->all();
    }

    public function selectNone(): void
    {
        $this->selected = [];
    }

    public function render(): View
    {
        $machines = $this->machines();

        $chosen = $machines->filter(fn (Machine $machine): bool => (bool) ($this->selected[$machine->id] ?? false));

        return view('livewire.admin.qr-sticker-sheet', [
            'machines' => $machines,
            'chosen' => $chosen,
            'rows' => $chosen->chunk(self::COLUMNS),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
        ])->title(__('app.qr.title'));
    }

    /**
     * The QR itself, as inline SVG so the sheet prints without fetching
     * anything — a print dialog on a plant PC may have no network at all.
     */
    public function svg(Machine $machine): string
    {
        return (string) QrCode::format('svg')
            ->size(180)
            ->margin(0)
            // Medium correction survives a smudge or a scuff on a workshop
            // floor without making the pattern too dense to scan.
            ->errorCorrection('M')
            ->generate($this->url($machine));
    }

    public function url(Machine $machine): string
    {
        return route('kiosk.machine', ['machine' => $machine->code]);
    }

    /**
     * @return Collection<int, Machine>
     */
    private function machines(): Collection
    {
        return Machine::query()
            ->when(! $this->includeInactive, fn ($query) => $query->where('is_active', true))
            ->when($this->location !== '', fn ($query) => $query->where('location_id', (int) $this->location))
            ->with('location:id,name')
            ->orderBy('name')
            ->get();
    }

    /** Keep the selection to what is actually on screen after a filter change. */
    private function syncSelection(): void
    {
        $visible = $this->machines()->pluck('id')->all();

        $this->selected = collect($visible)
            ->mapWithKeys(fn (int $id): array => [$id => $this->selected[$id] ?? true])
            ->all();
    }
}
