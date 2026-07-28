<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Location;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * Location master data (route `admin.locations`).
 *
 * Plain CRUD: a location is a named area at a site, optionally on a floor.
 * `(site_id, name)` is unique. Machines reference locations with a
 * `restrict` foreign key, so a location with machines cannot be deleted —
 * that failure is explained in plain language, never surfaced as a 500.
 *
 * There is no LocationPolicy (BUILD-CONTRACT §5 lists none) — locations are
 * gated by the `machine.manage` permission, same as the sidebar nav entry.
 */
#[Layout('layouts.app')]
class LocationManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // ── Filters (kept in the URL so the view is shareable) ───────────

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'site')]
    public string $siteFilter = '';

    // ── Create / edit modal form ─────────────────────────────────────

    public ?int $editingId = null;

    public string $siteId = '';

    public string $name = '';

    public string $floor = '';

    // ── Delete confirmation ──────────────────────────────────────────

    public ?int $deletingId = null;

    // ── Lifecycle ────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('machine.manage');
    }

    /**
     * Any filter change resets pagination to page 1.
     */
    public function updating(string $property, mixed $value): void
    {
        if (in_array($property, ['search', 'siteFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'siteFilter');
        $this->resetPage();
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Site>
     */
    #[Computed]
    public function sites(): Collection
    {
        return Site::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The location named in the delete-confirmation modal.
     */
    #[Computed]
    public function deletingLocation(): ?Location
    {
        return $this->deletingId === null
            ? null
            : Location::query()->find($this->deletingId);
    }

    // ── Actions ──────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('machine.manage');

        $this->resetForm();
        $this->dispatch('open-modal', name: 'location-form');
    }

    public function openEditModal(int $locationId): void
    {
        $this->authorize('machine.manage');

        $location = Location::query()->findOrFail($locationId);

        $this->resetForm();
        $this->editingId = $location->id;
        $this->siteId = (string) $location->site_id;
        $this->name = $location->name;
        $this->floor = $location->floor ?? '';

        $this->dispatch('open-modal', name: 'location-form');
    }

    public function save(): void
    {
        // Re-authorise here — a Livewire action is a public HTTP endpoint,
        // so the check in mount() alone is not enough.
        $this->authorize('machine.manage');

        $this->validate();

        $data = [
            'site_id' => (int) $this->siteId,
            'name' => trim($this->name),
            'floor' => trim($this->floor) !== '' ? trim($this->floor) : null,
        ];

        if ($this->editingId !== null) {
            $location = Location::query()->findOrFail($this->editingId);

            DB::transaction(fn () => $location->update($data));

            session()->flash('flash.success', __('app.locations.updated_message', ['name' => $location->name]));
        } else {
            $location = DB::transaction(fn (): Location => Location::create($data));

            session()->flash('flash.success', __('app.locations.created_message', ['name' => $location->name]));
        }

        $this->dispatch('close-modal', name: 'location-form');
        $this->resetForm();
    }

    public function confirmDelete(int $locationId): void
    {
        $this->authorize('machine.manage');

        $this->deletingId = Location::query()->findOrFail($locationId)->id;
        unset($this->deletingLocation);

        $this->dispatch('open-modal', name: 'confirm-delete-location');
    }

    /**
     * Soft-delete. `machines.location_id` is a `restrict` foreign key, but a
     * soft delete never reaches that constraint — so the same rule is
     * enforced here first (including soft-deleted machines, which still
     * reference the row). The QueryException catch keeps any race or future
     * hard delete from surfacing as a 500.
     */
    public function deleteLocation(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $this->authorize('machine.manage');

        $location = Location::query()->findOrFail($this->deletingId);

        if ($location->machines()->withTrashed()->exists()) {
            session()->flash('flash.error', __('app.locations.delete_blocked'));

            $this->dispatch('close-modal', name: 'confirm-delete-location');
            $this->deletingId = null;

            return;
        }

        try {
            DB::transaction(function () use ($location): void {
                $location->delete();
            });
        } catch (QueryException) {
            session()->flash('flash.error', __('app.locations.delete_blocked'));

            $this->dispatch('close-modal', name: 'confirm-delete-location');
            $this->deletingId = null;

            return;
        }

        session()->flash('flash.success', __('app.locations.deleted_message', ['name' => $location->name]));

        $this->dispatch('close-modal', name: 'confirm-delete-location');
        $this->deletingId = null;
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $locations = Location::query()
            ->with('site')
            ->withCount('machines')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.addcslashes($this->search, '\\%_').'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('floor', 'like', $term);
                });
            })
            ->when($this->siteFilter !== '', fn (Builder $query) => $query->where('site_id', (int) $this->siteFilter))
            ->orderBy(
                Site::query()
                    ->select('name')
                    ->whereColumn('sites.id', 'locations.site_id')
            )
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.location-manager', [
            'locations' => $locations,
        ])->title(__('app.locations.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'siteId' => ['required', 'integer', Rule::exists('sites', 'id')],
            // The DB unique index on (site_id, name) still sees soft-deleted
            // rows, so the rule deliberately does NOT exclude trashed ones.
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('locations', 'name')
                    ->where(fn ($query) => $query->where('site_id', (int) $this->siteId))
                    ->ignore($this->editingId),
            ],
            'floor' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'siteId.required' => __('app.locations.validation.site_required'),
            'siteId.integer' => __('app.locations.validation.site_required'),
            'siteId.exists' => __('app.locations.validation.site_required'),
            'name.required' => __('app.locations.validation.name_required'),
            'name.unique' => __('app.locations.validation.name_unique'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'siteId' => __('app.locations.site'),
            'name' => __('app.common.name'),
            'floor' => __('app.locations.floor'),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'siteId', 'name', 'floor');
    }
}
