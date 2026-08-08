<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Holiday;
use App\Models\Site;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Holiday calendar (route `admin.holidays`).
 *
 * The list is grouped by year — a year switcher shows one year at a time,
 * defaulting to the current year. Recurring holidays (fixed-date, e.g.
 * Christmas Day) are labelled apart from one-offs, and the movable feasts —
 * Carnival, Good Friday, Easter Monday, Corpus Christi, Eid, Divali — get a
 * standing reminder that they must be re-entered every year.
 *
 * Gated by `holiday.manage` (BUILD-CONTRACT §5 defines no HolidayPolicy).
 */
#[Layout('layouts::app')]
class HolidayManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    /**
     * The year being viewed. Kept in the URL so a year view is linkable.
     * Defaults to the current year in mount().
     */
    #[Url(as: 'year')]
    public ?int $year = null;

    // ── Create / edit modal form ─────────────────────────────────────

    public ?int $editingId = null;

    /** Empty string = all sites (stored as NULL). */
    public string $siteId = '';

    public string $date = '';

    public string $name = '';

    public bool $isRecurring = false;

    // ── Delete confirmation ──────────────────────────────────────────

    public ?int $deletingId = null;

    // ── Lifecycle ────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('holiday.manage');

        $this->year ??= now()->year;
    }

    // ── Year switcher ────────────────────────────────────────────────

    public function previousYear(): void
    {
        $this->year = (int) $this->year - 1;
        $this->resetPage();
    }

    public function nextYear(): void
    {
        $this->year = (int) $this->year + 1;
        $this->resetPage();
    }

    public function updatedYear(): void
    {
        $this->year = (int) $this->year;
        $this->resetPage();
    }

    /**
     * Years offered in the switcher: every year that has holidays, plus the
     * current year, next year and whatever year is being viewed.
     *
     * @return list<int>
     */
    #[Computed]
    public function yearOptions(): array
    {
        $years = Holiday::query()
            ->selectRaw('DISTINCT YEAR(date) AS y')
            ->orderBy('y')
            ->pluck('y')
            ->map(fn ($y): int => (int) $y)
            ->all();

        $years[] = now()->year;
        $years[] = now()->year + 1;
        $years[] = (int) $this->year;

        $years = array_values(array_unique($years));
        sort($years);

        return $years;
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
     * The holiday named in the delete-confirmation modal.
     */
    #[Computed]
    public function deletingHoliday(): ?Holiday
    {
        return $this->deletingId === null
            ? null
            : Holiday::query()->find($this->deletingId);
    }

    // ── Actions ──────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('holiday.manage');

        $this->resetForm();
        // Pre-fill the viewed year so a new holiday lands where the admin
        // is looking.
        $this->date = sprintf('%04d-01-01', (int) $this->year);

        $this->dispatch('open-modal', name: 'holiday-form');
    }

    public function openEditModal(int $holidayId): void
    {
        $this->authorize('holiday.manage');

        $holiday = Holiday::query()->findOrFail($holidayId);

        $this->resetForm();
        $this->editingId = $holiday->id;
        $this->siteId = $holiday->site_id === null ? '' : (string) $holiday->site_id;
        $this->date = $holiday->date->toDateString();
        $this->name = $holiday->name;
        $this->isRecurring = $holiday->is_recurring;

        $this->dispatch('open-modal', name: 'holiday-form');
    }

    public function save(): void
    {
        // Re-authorise here — a Livewire action is a public HTTP endpoint,
        // so the check in mount() alone is not enough.
        $this->authorize('holiday.manage');

        $this->validate();

        $data = [
            'site_id' => $this->siteId === '' ? null : (int) $this->siteId,
            'date' => $this->date,
            'name' => trim($this->name),
            'is_recurring' => $this->isRecurring,
        ];

        if ($this->editingId !== null) {
            $holiday = Holiday::query()->findOrFail($this->editingId);

            DB::transaction(fn () => $holiday->update($data));

            session()->flash('flash.success', __('app.holidays.updated_message', ['name' => $holiday->name]));
        } else {
            $holiday = DB::transaction(fn (): Holiday => Holiday::create($data));

            session()->flash('flash.success', __('app.holidays.created_message', ['name' => $holiday->name]));
        }

        // Jump the list to the year the holiday was saved into.
        $this->year = (int) substr($this->date, 0, 4);
        $this->resetPage();

        $this->dispatch('close-modal', name: 'holiday-form');
        $this->resetForm();
    }

    public function confirmDelete(int $holidayId): void
    {
        $this->authorize('holiday.manage');

        $this->deletingId = Holiday::query()->findOrFail($holidayId)->id;
        unset($this->deletingHoliday);

        $this->dispatch('open-modal', name: 'confirm-delete-holiday');
    }

    public function deleteHoliday(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $this->authorize('holiday.manage');

        $holiday = Holiday::query()->findOrFail($this->deletingId);

        DB::transaction(function () use ($holiday): void {
            $holiday->delete();
        });

        session()->flash('flash.success', __('app.holidays.deleted_message', ['name' => $holiday->name]));

        $this->dispatch('close-modal', name: 'confirm-delete-holiday');
        $this->deletingId = null;
    }

    /**
     * Copy every recurring holiday from the viewed year into the next one.
     * Fixed-date holidays (Christmas, Boxing Day, Independence Day, …) then
     * only ever need to be entered once. Existing rows are skipped, so
     * running it twice is harmless. 29 February can't be copied into a
     * non-leap year and is skipped too.
     */
    public function copyRecurringToNextYear(): void
    {
        $this->authorize('holiday.manage');

        $sourceYear = (int) $this->year;
        $targetYear = $sourceYear + 1;

        $recurring = Holiday::query()
            ->where('is_recurring', true)
            ->whereYear('date', $sourceYear)
            ->orderBy('date')
            ->get();

        if ($recurring->isEmpty()) {
            session()->flash('flash.error', __('app.holidays.nothing_to_copy', ['year' => $sourceYear]));

            return;
        }

        $copied = 0;
        $skipped = 0;

        DB::transaction(function () use ($recurring, $targetYear, &$copied, &$skipped): void {
            foreach ($recurring as $holiday) {
                if (! checkdate($holiday->date->month, $holiday->date->day, $targetYear)) {
                    $skipped++;

                    continue;
                }

                $targetDate = sprintf('%04d-%02d-%02d', $targetYear, $holiday->date->month, $holiday->date->day);

                // Skip anything already present — covers both the DB unique
                // index (site rows) and the NULL-site gap it cannot police.
                $exists = Holiday::query()
                    ->when(
                        $holiday->site_id === null,
                        fn (Builder $query) => $query->whereNull('site_id'),
                        fn (Builder $query) => $query->where('site_id', $holiday->site_id),
                    )
                    ->where('date', $targetDate)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                Holiday::create([
                    'site_id' => $holiday->site_id,
                    'date' => $targetDate,
                    'name' => $holiday->name,
                    'is_recurring' => true,
                ]);

                $copied++;
            }
        });

        session()->flash('flash.success', __('app.holidays.copied_message', [
            'copied' => $copied,
            'year' => $targetYear,
            'skipped' => $skipped,
        ]));

        $this->year = $targetYear;
        $this->resetPage();
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $holidays = Holiday::query()
            ->with('site')
            ->whereYear('date', (int) $this->year)
            ->orderBy('date')
            ->orderByRaw('site_id IS NOT NULL') // all-sites rows first per date
            ->paginate(31);

        return view('livewire.admin.holiday-manager', [
            'holidays' => $holidays,
        ])->title(__('app.holidays.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $dateRules = ['required', 'date'];

        if ($this->siteId === '') {
            // INTEGRITY GUARD — why this closure exists:
            // The `holidays` table has a unique index on (site_id, date),
            // but `site_id` is nullable and MySQL never treats two NULLs in
            // a unique index as colliding. So the database happily accepts
            // any number of site-wide (site_id = NULL) holidays on the same
            // date, and the run generator would then double-count them.
            // This application-level check is the ONLY thing preventing
            // duplicate site-wide holidays. It ignores the row being edited.
            $dateRules[] = function (string $attribute, mixed $value, Closure $fail): void {
                $duplicate = Holiday::query()
                    ->whereNull('site_id')
                    ->where('date', (string) $value)
                    ->when($this->editingId !== null, fn (Builder $query) => $query->whereKeyNot($this->editingId))
                    ->exists();

                if ($duplicate) {
                    $fail(__('app.holidays.validation.duplicate_all_sites'));
                }
            };
        } else {
            // Site-specific duplicates ARE caught by the DB unique index —
            // this rule just turns that into a friendly message instead of
            // a QueryException.
            $dateRules[] = Rule::unique('holidays', 'date')
                ->where(fn ($query) => $query->where('site_id', (int) $this->siteId))
                ->ignore($this->editingId);
        }

        return [
            'siteId' => $this->siteId === ''
                ? ['nullable']
                : ['integer', Rule::exists('sites', 'id')],
            'date' => $dateRules,
            'name' => ['required', 'string', 'max:120'],
            'isRecurring' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'date.required' => __('app.holidays.validation.date_required'),
            'date.date' => __('app.holidays.validation.date_required'),
            'date.unique' => __('app.holidays.validation.duplicate_site'),
            'name.required' => __('app.holidays.validation.name_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'siteId' => __('app.holidays.applies_to'),
            'date' => __('app.holidays.date'),
            'name' => __('app.common.name'),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'siteId', 'date', 'name', 'isRecurring');
    }
}
