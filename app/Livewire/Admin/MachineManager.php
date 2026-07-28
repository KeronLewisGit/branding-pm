<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Location;
use App\Models\Machine;
use App\Models\Part;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Machine master data (route `admin.machines`).
 *
 * Searchable, filterable, paginated. Create/edit happens in a modal; the
 * machine's parts list (the `machine_part` pivot) is managed in a second
 * modal with up/down reordering — no drag-and-drop, tablet first.
 *
 * `code` is the QR sticker slug (`/m/{code}`). It is suggested from the name
 * on create only; on edit it is never rewritten automatically, and changing
 * it shows a loud warning because every printed sticker encodes the old code.
 */
#[Layout('layouts.app')]
class MachineManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // ── Filters (kept in the URL so the view is shareable) ───────────

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'location')]
    public string $locationFilter = '';

    #[Url(as: 'active')]
    public string $activeFilter = '';

    // ── Create / edit modal form ─────────────────────────────────────

    public ?int $editingId = null;

    public string $locationId = '';

    public string $code = '';

    public string $name = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $assetTag = '';

    public bool $isActive = true;

    public string $notes = '';

    /**
     * The code as saved in the database — used to detect (and warn about)
     * a code change on edit. Empty when creating.
     */
    public string $originalCode = '';

    /**
     * Once the user has typed in the code field on create, stop suggesting
     * a slug from the name — never overwrite what they chose.
     */
    public bool $codeManuallyEdited = false;

    // ── Delete confirmation ──────────────────────────────────────────

    public ?int $deletingId = null;

    // ── Parts modal ──────────────────────────────────────────────────

    public ?int $partsMachineId = null;

    public string $attachPartId = '';

    // ── Lifecycle ────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('viewAny', Machine::class);
    }

    /**
     * Any filter change resets pagination to page 1.
     */
    public function updating(string $property, mixed $value): void
    {
        if (in_array($property, ['search', 'locationFilter', 'activeFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Suggest the QR slug from the name — on create only, and only while
     * the user has not touched the code field themselves.
     */
    public function updatedName(): void
    {
        if ($this->editingId === null && ! $this->codeManuallyEdited) {
            $this->code = trim(Str::limit(Str::slug($this->name), 64, ''), '-');
        }
    }

    public function updatedCode(): void
    {
        if ($this->editingId === null) {
            // An emptied field re-enables the suggestion.
            $this->codeManuallyEdited = $this->code !== '';
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'locationFilter', 'activeFilter');
        $this->resetPage();
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()
            ->with('site')
            ->orderBy(
                Site::query()
                    ->select('name')
                    ->whereColumn('sites.id', 'locations.site_id')
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * The machine whose parts are being managed, with its parts in pivot
     * order. Eager-loaded because `preventLazyLoading()` is on.
     */
    #[Computed]
    public function partsMachine(): ?Machine
    {
        if ($this->partsMachineId === null) {
            return null;
        }

        return Machine::query()->with('parts')->find($this->partsMachineId);
    }

    /**
     * Active parts not yet attached to the parts-modal machine.
     *
     * @return Collection<int, Part>
     */
    #[Computed]
    public function availableParts(): Collection
    {
        $attachedIds = $this->partsMachine?->parts->pluck('id') ?? collect();

        return Part::query()
            ->active()
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get(['id', 'part_code', 'name', 'unit']);
    }

    /**
     * The machine named in the delete-confirmation modal.
     */
    #[Computed]
    public function deletingMachine(): ?Machine
    {
        return $this->deletingId === null
            ? null
            : Machine::query()->find($this->deletingId);
    }

    // ── Create / edit ────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('create', Machine::class);

        $this->resetForm();
        $this->dispatch('open-modal', name: 'machine-form');
    }

    public function openEditModal(int $machineId): void
    {
        $machine = Machine::query()->findOrFail($machineId);

        $this->authorize('update', $machine);

        $this->resetForm();
        $this->editingId = $machine->id;
        $this->locationId = (string) $machine->location_id;
        $this->code = $machine->code;
        $this->originalCode = $machine->code;
        $this->name = $machine->name;
        $this->manufacturer = $machine->manufacturer ?? '';
        $this->model = $machine->model ?? '';
        $this->assetTag = $machine->asset_tag ?? '';
        $this->isActive = $machine->is_active;
        $this->notes = $machine->notes ?? '';
        // Never slug-suggest over an existing code.
        $this->codeManuallyEdited = true;

        $this->dispatch('open-modal', name: 'machine-form');
    }

    public function save(): void
    {
        // Re-authorise here — a Livewire action is a public HTTP endpoint,
        // so the check in mount() alone is not enough.
        $machine = null;

        if ($this->editingId !== null) {
            $machine = Machine::query()->findOrFail($this->editingId);
            $this->authorize('update', $machine);
        } else {
            $this->authorize('create', Machine::class);
        }

        $this->validate();

        $data = [
            'location_id' => (int) $this->locationId,
            'code' => trim($this->code),
            'name' => trim($this->name),
            'manufacturer' => trim($this->manufacturer) !== '' ? trim($this->manufacturer) : null,
            'model' => trim($this->model) !== '' ? trim($this->model) : null,
            'asset_tag' => trim($this->assetTag) !== '' ? trim($this->assetTag) : null,
            'is_active' => $this->isActive,
            'notes' => trim($this->notes) !== '' ? trim($this->notes) : null,
        ];

        if ($machine !== null) {
            DB::transaction(fn () => $machine->update($data));

            session()->flash('flash.success', __('app.machines.updated_message', ['name' => $machine->name]));
        } else {
            $machine = DB::transaction(fn (): Machine => Machine::create($data));

            session()->flash('flash.success', __('app.machines.created_message', ['name' => $machine->name]));
        }

        $this->dispatch('close-modal', name: 'machine-form');
        $this->resetForm();
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function confirmDelete(int $machineId): void
    {
        $machine = Machine::query()->findOrFail($machineId);

        $this->authorize('delete', $machine);

        $this->deletingId = $machine->id;
        unset($this->deletingMachine);

        $this->dispatch('open-modal', name: 'confirm-delete-machine');
    }

    /**
     * Soft-delete. A machine with templates or run history must NOT be
     * deleted — the `restrict` foreign keys on `checklist_templates` and
     * `checklist_runs` protect the history at the database level, but a
     * soft delete never reaches the database constraint, so the same rule
     * is checked here first. The QueryException catch is the belt to that
     * braces: if a template/run slips in between the check and the delete
     * (or the delete is ever hardened to a force delete), the failure is
     * still a plain-language message, never a 500.
     */
    public function deleteMachine(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $machine = Machine::query()->findOrFail($this->deletingId);

        $this->authorize('delete', $machine);

        if ($machine->templates()->withTrashed()->exists() || $machine->runs()->exists()) {
            session()->flash('flash.error', __('app.machines.delete_blocked'));

            $this->dispatch('close-modal', name: 'confirm-delete-machine');
            $this->deletingId = null;

            return;
        }

        try {
            DB::transaction(function () use ($machine): void {
                $machine->delete();
            });
        } catch (QueryException) {
            session()->flash('flash.error', __('app.machines.delete_blocked'));

            $this->dispatch('close-modal', name: 'confirm-delete-machine');
            $this->deletingId = null;

            return;
        }

        session()->flash('flash.success', __('app.machines.deleted_message', ['name' => $machine->name]));

        $this->dispatch('close-modal', name: 'confirm-delete-machine');
        $this->deletingId = null;
    }

    // ── Parts (machine_part pivot) ───────────────────────────────────

    public function openPartsModal(int $machineId): void
    {
        $machine = Machine::query()->findOrFail($machineId);

        $this->authorize('update', $machine);

        $this->resetValidation();
        $this->partsMachineId = $machine->id;
        $this->attachPartId = '';
        unset($this->partsMachine, $this->availableParts);

        $this->dispatch('open-modal', name: 'machine-parts');
    }

    public function attachPart(): void
    {
        $machine = Machine::query()->with('parts')->findOrFail((int) $this->partsMachineId);

        $this->authorize('update', $machine);

        $this->validate(
            [
                'attachPartId' => [
                    'required',
                    'integer',
                    Rule::exists('parts', 'id'),
                    Rule::notIn($machine->parts->pluck('id')->all()),
                ],
            ],
            [
                'attachPartId.required' => __('app.machines.validation.part_required'),
                'attachPartId.integer' => __('app.machines.validation.part_required'),
                'attachPartId.exists' => __('app.machines.validation.part_required'),
                'attachPartId.not_in' => __('app.machines.validation.part_already_attached'),
            ],
        );

        DB::transaction(function () use ($machine): void {
            $machine->parts()->attach((int) $this->attachPartId, [
                'sort_order' => $machine->parts->count(),
            ]);
        });

        $this->attachPartId = '';
        unset($this->partsMachine, $this->availableParts);

        session()->flash('flash.success', __('app.machines.part_attached'));
    }

    public function detachPart(int $partId): void
    {
        $machine = Machine::query()->with('parts')->findOrFail((int) $this->partsMachineId);

        $this->authorize('update', $machine);

        // Detach and close the gap in one transaction so sort_order stays
        // contiguous (0, 1, 2, …) no matter where the removed part sat.
        DB::transaction(function () use ($machine, $partId): void {
            $machine->parts()->detach($partId);

            $remaining = $machine->parts()->pluck('parts.id')->all();

            foreach (array_values($remaining) as $index => $id) {
                $machine->parts()->updateExistingPivot($id, ['sort_order' => $index]);
            }
        });

        unset($this->partsMachine, $this->availableParts);

        session()->flash('flash.success', __('app.machines.part_detached'));
    }

    public function movePartUp(int $partId): void
    {
        $this->movePart($partId, -1);
    }

    public function movePartDown(int $partId): void
    {
        $this->movePart($partId, 1);
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $machines = Machine::query()
            ->with('location.site')
            ->withCount(['parts', 'templates'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.addcslashes($this->search, '\\%_').'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('asset_tag', 'like', $term);
                });
            })
            ->when($this->locationFilter !== '', fn (Builder $query) => $query->where('location_id', (int) $this->locationFilter))
            ->when($this->activeFilter !== '', fn (Builder $query) => $query->where('is_active', $this->activeFilter === '1'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.machine-manager', [
            'machines' => $machines,
        ])->title(__('app.machines.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'locationId' => ['required', 'integer', Rule::exists('locations', 'id')],
            'name' => ['required', 'string', 'max:160'],
            // The DB unique index on `code` still sees soft-deleted rows, so
            // the rule deliberately does NOT exclude trashed machines.
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('machines', 'code')->ignore($this->editingId),
            ],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'assetTag' => ['nullable', 'string', 'max:64'],
            'isActive' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'locationId.required' => __('app.machines.validation.location_required'),
            'locationId.integer' => __('app.machines.validation.location_required'),
            'locationId.exists' => __('app.machines.validation.location_required'),
            'name.required' => __('app.machines.validation.name_required'),
            'code.required' => __('app.machines.validation.code_required'),
            'code.regex' => __('app.machines.validation.code_format'),
            'code.unique' => __('app.validation.machine_code_taken'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'locationId' => __('app.machines.location'),
            'code' => __('app.common.code'),
            'name' => __('app.common.name'),
            'manufacturer' => __('app.machines.manufacturer'),
            'model' => __('app.machines.model'),
            'assetTag' => __('app.machines.asset_tag'),
            'notes' => __('app.machines.notes'),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────

    /**
     * Swap a part with its neighbour and persist the whole contiguous
     * sequence (0, 1, 2, …) in a single transaction.
     */
    private function movePart(int $partId, int $offset): void
    {
        $machine = Machine::query()->with('parts')->findOrFail((int) $this->partsMachineId);

        $this->authorize('update', $machine);

        $ids = $machine->parts->pluck('id')->all();
        $index = array_search($partId, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $index + $offset;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        DB::transaction(function () use ($machine, $ids): void {
            foreach ($ids as $sortOrder => $id) {
                $machine->parts()->updateExistingPivot($id, ['sort_order' => $sortOrder]);
            }
        });

        unset($this->partsMachine);

        session()->flash('flash.success', __('app.machines.parts_reordered'));
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(
            'editingId',
            'locationId',
            'code',
            'name',
            'manufacturer',
            'model',
            'assetTag',
            'isActive',
            'notes',
            'originalCode',
            'codeManuallyEdited',
        );
    }
}
