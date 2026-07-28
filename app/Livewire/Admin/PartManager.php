<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Part master data (route `admin.parts`).
 *
 * `part_code` is an external identifier and is a STRING, never a number —
 * one seeded value is literally "XXX" (BUILD-CONTRACT §2). It is stored
 * exactly as typed, validated as a string, and sorted as a string.
 *
 * Parts referenced by machines or templates cannot be deleted (`restrict`
 * foreign keys on the pivots) — that is explained in plain language, with
 * deactivation offered as the alternative. Gated by `part.manage`
 * (BUILD-CONTRACT §5 defines no PartPolicy).
 */
#[Layout('layouts.app')]
class PartManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // ── Filters (kept in the URL so the view is shareable) ───────────

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'active')]
    public string $activeFilter = '';

    /**
     * 'name' or 'code'. Code sorting is a plain string sort — "XXX" is a
     * valid part code and must never be compared numerically.
     */
    #[Url(as: 'sort')]
    public string $sortBy = 'name';

    // ── Create / edit modal form ─────────────────────────────────────

    public ?int $editingId = null;

    public string $partCode = '';

    public string $name = '';

    public string $unit = '';

    public bool $isActive = true;

    // ── Delete confirmation ──────────────────────────────────────────

    public ?int $deletingId = null;

    // ── Lifecycle ────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('part.manage');
    }

    /**
     * Any filter change resets pagination to page 1.
     */
    public function updating(string $property, mixed $value): void
    {
        if (in_array($property, ['search', 'activeFilter', 'sortBy'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'activeFilter');
        $this->resetPage();
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * The part named in the delete-confirmation modal.
     */
    #[Computed]
    public function deletingPart(): ?Part
    {
        return $this->deletingId === null
            ? null
            : Part::query()->find($this->deletingId);
    }

    // ── Actions ──────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('part.manage');

        $this->resetForm();
        $this->dispatch('open-modal', name: 'part-form');
    }

    public function openEditModal(int $partId): void
    {
        $this->authorize('part.manage');

        $part = Part::query()->findOrFail($partId);

        $this->resetForm();
        $this->editingId = $part->id;
        $this->partCode = $part->part_code;
        $this->name = $part->name;
        $this->unit = $part->unit ?? '';
        $this->isActive = $part->is_active;

        $this->dispatch('open-modal', name: 'part-form');
    }

    public function save(): void
    {
        // Re-authorise here — a Livewire action is a public HTTP endpoint,
        // so the check in mount() alone is not enough.
        $this->authorize('part.manage');

        $this->validate();

        $data = [
            // Stored exactly as typed — never cast, never normalised.
            'part_code' => trim($this->partCode),
            'name' => trim($this->name),
            'unit' => trim($this->unit) !== '' ? trim($this->unit) : null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId !== null) {
            $part = Part::query()->findOrFail($this->editingId);

            DB::transaction(fn () => $part->update($data));

            session()->flash('flash.success', __('app.parts.updated_message', ['name' => $part->name]));
        } else {
            $part = DB::transaction(fn (): Part => Part::create($data));

            session()->flash('flash.success', __('app.parts.created_message', ['name' => $part->name]));
        }

        $this->dispatch('close-modal', name: 'part-form');
        $this->resetForm();
    }

    public function confirmDelete(int $partId): void
    {
        $this->authorize('part.manage');

        $this->deletingId = Part::query()->findOrFail($partId)->id;
        unset($this->deletingPart);

        $this->dispatch('open-modal', name: 'confirm-delete-part');
    }

    /**
     * Soft-delete. `machine_part.part_id` and
     * `checklist_template_parts.part_id` are `restrict` foreign keys, but a
     * soft delete never reaches those constraints — so the same rule is
     * enforced here first. The QueryException catch keeps any race or
     * future hard delete from surfacing as a 500.
     */
    public function deletePart(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $this->authorize('part.manage');

        $part = Part::query()->findOrFail($this->deletingId);

        if ($part->machines()->exists() || $part->templates()->exists()) {
            session()->flash('flash.error', __('app.parts.delete_blocked'));

            $this->dispatch('close-modal', name: 'confirm-delete-part');
            $this->deletingId = null;

            return;
        }

        try {
            DB::transaction(function () use ($part): void {
                $part->delete();
            });
        } catch (QueryException) {
            session()->flash('flash.error', __('app.parts.delete_blocked'));

            $this->dispatch('close-modal', name: 'confirm-delete-part');
            $this->deletingId = null;

            return;
        }

        session()->flash('flash.success', __('app.parts.deleted_message', ['name' => $part->name]));

        $this->dispatch('close-modal', name: 'confirm-delete-part');
        $this->deletingId = null;
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $parts = Part::query()
            // Machines are shown per part — eager-loaded because
            // preventLazyLoading() is enabled outside production.
            ->with(['machines' => fn ($query) => $query->orderBy('machines.name')])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.addcslashes($this->search, '\\%_').'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('part_code', 'like', $term);
                });
            })
            ->when($this->activeFilter !== '', fn (Builder $query) => $query->where('is_active', $this->activeFilter === '1'))
            // String column, string sort — either branch.
            ->when(
                $this->sortBy === 'code',
                fn (Builder $query) => $query->orderBy('part_code')->orderBy('name'),
                fn (Builder $query) => $query->orderBy('name')->orderBy('part_code'),
            )
            ->paginate(15);

        return view('livewire.admin.part-manager', [
            'parts' => $parts,
        ])->title(__('app.parts.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            // part_code is a free-form STRING — "XXX" is a real seeded value,
            // so there is no numeric or format rule here on purpose. The DB
            // unique index still sees soft-deleted rows, so the rule does
            // NOT exclude trashed parts.
            'partCode' => [
                'required',
                'string',
                'max:32',
                Rule::unique('parts', 'part_code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:190'],
            'unit' => ['nullable', 'string', 'max:32'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'partCode.required' => __('app.parts.validation.part_code_required'),
            'partCode.unique' => __('app.validation.part_code_taken'),
            'name.required' => __('app.parts.validation.name_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'partCode' => __('app.parts.part_code'),
            'name' => __('app.common.name'),
            'unit' => __('app.parts.unit'),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'partCode', 'name', 'unit', 'isActive');
    }
}
