<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\ChecklistTemplate;
use App\Models\Machine;
use App\Enums\Frequency;
use App\Enums\WorkCategory;
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
 * Admin list of checklist templates (route `admin.templates`).
 *
 * Searchable, filterable, paginated, grouped by machine. Creation happens in
 * a modal here; item/part editing happens in TemplateEditor. Templates are
 * deactivated rather than deleted — hard deletion is only offered when the
 * template has never generated a run (the `restrict` FK on
 * `checklist_runs.checklist_template_id` protects history otherwise).
 */
#[Layout('layouts.app')]
class TemplateManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    // ── Filters (kept in the URL so the view is shareable) ───────────

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'machine')]
    public string $machineFilter = '';

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    #[Url(as: 'frequency')]
    public string $frequencyFilter = '';

    #[Url(as: 'active')]
    public string $activeFilter = '';

    // ── Create-template modal form ───────────────────────────────────

    public string $machineId = '';

    public string $name = '';

    public string $workCategory = WorkCategory::Daily->value;

    public string $workDescription = '';

    public string $frequency = Frequency::Daily->value;

    public bool $perShift = false;

    public string $weeklyWeekday = '1';

    public string $monthlyDay = '1';

    public bool $requiresSupervisorSignoff = true;

    public string $gracePeriodHours = '24';

    // ── Lifecycle ────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('viewAny', ChecklistTemplate::class);
    }

    /**
     * Any filter change resets pagination to page 1.
     */
    public function updating(string $property, mixed $value): void
    {
        if (in_array($property, ['search', 'machineFilter', 'categoryFilter', 'frequencyFilter', 'activeFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'machineFilter', 'categoryFilter', 'frequencyFilter', 'activeFilter');
        $this->resetPage();
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Machine>
     */
    #[Computed]
    public function machines(): Collection
    {
        return Machine::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * @return array<int, string> ISO weekday number => localised name
     */
    public function weekdayOptions(): array
    {
        $options = [];

        foreach (range(1, 7) as $day) {
            $options[$day] = __('app.weekdays.'.$day);
        }

        return $options;
    }

    // ── Actions ──────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('create', ChecklistTemplate::class);

        $this->resetValidation();
        $this->reset(
            'machineId',
            'name',
            'workCategory',
            'workDescription',
            'frequency',
            'perShift',
            'weeklyWeekday',
            'monthlyDay',
            'requiresSupervisorSignoff',
            'gracePeriodHours',
        );

        $this->dispatch('open-modal', name: 'create-template');
    }

    /**
     * Create the template, then jump straight to the editor so the manager
     * can add its checklist items.
     */
    public function createTemplate(): void
    {
        $this->authorize('create', ChecklistTemplate::class);

        $this->validate($this->templateRules(), $this->templateMessages(), $this->templateAttributes());

        $template = DB::transaction(function (): ChecklistTemplate {
            return ChecklistTemplate::create([
                'machine_id' => (int) $this->machineId,
                'name' => trim($this->name),
                'work_category' => $this->workCategory,
                'work_description' => trim($this->workDescription),
                'frequency' => $this->frequency,
                'per_shift' => $this->perShift,
                'weekly_weekday' => (int) $this->weeklyWeekday,
                'monthly_day' => (int) $this->monthlyDay,
                'requires_supervisor_signoff' => $this->requiresSupervisorSignoff,
                'grace_period_hours' => (int) $this->gracePeriodHours,
                'version' => 1,
                'is_active' => true,
            ]);
        });

        session()->flash('status', __('app.templates.created_message', ['name' => $template->name]));

        $this->redirectRoute('admin.templates.edit', ['template' => $template->id]);
    }

    /**
     * Copy a template with its items and parts. The copy starts inactive at
     * version 1 with " (copy)" appended, so nothing generates from it until
     * it has been reviewed and activated.
     */
    public function duplicateTemplate(int $templateId): void
    {
        $this->authorize('create', ChecklistTemplate::class);

        $source = ChecklistTemplate::query()
            ->with(['items', 'templateParts'])
            ->findOrFail($templateId);

        $copy = DB::transaction(function () use ($source): ChecklistTemplate {
            $copy = $source->replicate(['version', 'is_active']);
            $copy->name = $this->uniqueCopyName($source);
            $copy->version = 1;
            $copy->is_active = false;
            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create([
                    'sort_order' => $item->sort_order,
                    'description' => $item->description,
                    'response_type' => $item->response_type,
                    'is_required' => $item->is_required,
                    'guidance' => $item->guidance,
                    'requires_photo_on_fail' => $item->requires_photo_on_fail,
                    'is_active' => $item->is_active,
                ]);
            }

            foreach ($source->templateParts as $templatePart) {
                $copy->templateParts()->create([
                    'part_id' => $templatePart->part_id,
                    'sort_order' => $templatePart->sort_order,
                ]);
            }

            return $copy;
        });

        session()->flash('flash.success', __('app.templates.duplicated_message', ['name' => $copy->name]));
    }

    /**
     * Deactivate / reactivate. Deactivation is the default alternative to
     * deletion — the template and every run it ever produced are kept.
     */
    public function toggleActive(int $templateId): void
    {
        $template = ChecklistTemplate::query()->findOrFail($templateId);

        $this->authorize('update', $template);

        DB::transaction(function () use ($template): void {
            $template->update(['is_active' => ! $template->is_active]);
        });

        session()->flash('flash.success', $template->is_active
            ? __('app.templates.activated_message', ['name' => $template->name])
            : __('app.templates.deactivated_message', ['name' => $template->name]));
    }

    /**
     * Hard delete — only reachable when the template has never generated a
     * run. If a run slips in between the check and the delete, the
     * `restrict` foreign key throws a QueryException, which we translate
     * into a plain-language message instead of a 500.
     */
    public function deleteTemplate(int $templateId): void
    {
        $template = ChecklistTemplate::query()->findOrFail($templateId);

        $this->authorize('delete', $template);

        if ($template->runs()->exists()) {
            session()->flash('flash.error', __('app.templates.delete_blocked_runs'));

            return;
        }

        try {
            DB::transaction(function () use ($template): void {
                $template->forceDelete();
            });
        } catch (QueryException) {
            session()->flash('flash.error', __('app.templates.delete_blocked_runs'));

            return;
        }

        session()->flash('flash.success', __('app.templates.deleted_message', ['name' => $template->name]));
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $templates = ChecklistTemplate::query()
            ->with('machine')
            ->withCount(['activeItems', 'runs'])
            ->withMax('runs', 'scheduled_for')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.addcslashes($this->search, '\\%_').'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhereHas('machine', function (Builder $query) use ($term): void {
                            $query->where('name', 'like', $term);
                        });
                });
            })
            ->when($this->machineFilter !== '', fn (Builder $query) => $query->where('machine_id', (int) $this->machineFilter))
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query->where('work_category', $this->categoryFilter))
            ->when($this->frequencyFilter !== '', fn (Builder $query) => $query->where('frequency', $this->frequencyFilter))
            ->when($this->activeFilter !== '', fn (Builder $query) => $query->where('is_active', $this->activeFilter === '1'))
            ->orderBy(
                Machine::query()
                    ->select('name')
                    ->whereColumn('machines.id', 'checklist_templates.machine_id')
            )
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.template-manager', [
            'templates' => $templates,
        ])->title(__('app.templates.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    private function templateRules(): array
    {
        return [
            'machineId' => ['required', 'integer', Rule::exists('machines', 'id')],
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('checklist_templates', 'name')
                    ->where(fn ($query) => $query->where('machine_id', (int) $this->machineId))
                    ->withoutTrashed(),
            ],
            'workCategory' => ['required', Rule::enum(WorkCategory::class)],
            'workDescription' => ['required', 'string', 'max:2000'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'perShift' => ['boolean'],
            'weeklyWeekday' => ['required', 'integer', 'between:1,7'],
            'monthlyDay' => ['required', 'integer', 'between:1,28'],
            'requiresSupervisorSignoff' => ['boolean'],
            'gracePeriodHours' => ['required', 'integer', 'between:0,720'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function templateMessages(): array
    {
        return [
            'machineId.required' => __('app.templates.validation.machine_required'),
            'machineId.integer' => __('app.templates.validation.machine_required'),
            'machineId.exists' => __('app.templates.validation.machine_required'),
            'name.required' => __('app.templates.validation.name_required'),
            'name.max' => __('app.templates.validation.name_max'),
            'name.unique' => __('app.templates.validation.name_unique'),
            'workDescription.required' => __('app.templates.validation.description_required'),
            'weeklyWeekday.between' => __('app.templates.validation.weekday_range'),
            'monthlyDay.between' => __('app.validation.monthly_day_range'),
            'gracePeriodHours.between' => __('app.templates.validation.grace_range'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function templateAttributes(): array
    {
        return [
            'machineId' => __('app.machines.machine'),
            'name' => __('app.common.name'),
            'workCategory' => __('app.templates.work_category'),
            'workDescription' => __('app.templates.work_description'),
            'frequency' => __('app.templates.frequency'),
            'weeklyWeekday' => __('app.templates.weekly_weekday'),
            'monthlyDay' => __('app.templates.monthly_day'),
            'gracePeriodHours' => __('app.templates.grace_period_hours'),
        ];
    }

    /**
     * " (copy)" name that does not collide with an existing template on the
     * same machine (duplicating twice would otherwise produce two
     * identically-named copies).
     */
    private function uniqueCopyName(ChecklistTemplate $source): string
    {
        $base = Str::limit($source->name, 160, '').' '.__('app.templates.copy_suffix');
        $name = $base;
        $attempt = 2;

        while (
            ChecklistTemplate::query()
                ->where('machine_id', $source->machine_id)
                ->where('name', $name)
                ->exists()
        ) {
            $name = $base.' '.$attempt;
            $attempt++;
        }

        return $name;
    }
}
