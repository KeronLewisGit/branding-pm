<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Frequency;
use App\Enums\ResponseType;
use App\Enums\RunStatus;
use App\Enums\WorkCategory;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\ChecklistTemplatePart;
use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Template editor (route `admin.templates.edit`) — the item builder and the
 * "Used Parts" list for one checklist template.
 *
 * Versioning rules (SPEC §Template management, seed-notes D3):
 * - Any change to the ITEM SET (add / reorder / edit description / change
 *   response_type / change is_required / activate / deactivate) calls
 *   `$template->bumpVersion()`.
 * - Cosmetic template fields (name, work_description, grace period, …) do NOT
 *   bump the version.
 * - Existing runs are never rewritten: `checklist_runs.template_version` and
 *   `checklist_run_items.description` are snapshots taken at generation time.
 *   Edits here only shape runs generated AFTER the edit.
 */
#[Layout('layouts::app')]
class TemplateEditor extends Component
{
    use AuthorizesRequests;

    public ChecklistTemplate $template;

    // ── Template settings form (section a) ───────────────────────────

    public string $name = '';

    public string $workCategory = WorkCategory::Daily->value;

    public string $workDescription = '';

    public string $frequency = Frequency::Daily->value;

    public bool $perShift = false;

    public string $weeklyWeekday = '1';

    public string $monthlyDay = '1';

    public bool $requiresSupervisorSignoff = true;

    public string $gracePeriodHours = '24';

    // ── Item form (section b — shared by "add" and "edit") ───────────

    /** Null while adding; the item id while editing an existing item. */
    public ?int $editingItemId = null;

    public bool $showItemForm = false;

    public string $itemDescription = '';

    public string $itemResponseType = ResponseType::Check->value;

    public bool $itemIsRequired = true;

    public string $itemGuidance = '';

    public bool $itemRequiresPhotoOnFail = false;

    // ── Part picker (section c) ──────────────────────────────────────

    public string $partSearch = '';

    // ── Lifecycle ────────────────────────────────────────────────────

    public function mount(ChecklistTemplate $template): void
    {
        $this->authorize('update', $template);

        $this->template = $template;
        $this->fillSettingsForm();
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * Runs already materialised from the CURRENT version that are still
     * pending or in progress. They will NOT pick up edits made here — the
     * view warns the manager with this exact count before they change the
     * item set.
     */
    #[Computed]
    public function openRunCount(): int
    {
        return $this->template->runs()
            ->where('template_version', $this->template->version)
            ->whereIn('status', [RunStatus::Pending, RunStatus::InProgress])
            ->count();
    }

    /**
     * Part-picker matches. `part_code` is searched as a STRING — one real
     * code is literally `XXX` (seed-notes B1) — never cast to int.
     *
     * @return Collection<int, Part>
     */
    #[Computed]
    public function availableParts(): Collection
    {
        $search = trim($this->partSearch);

        if ($search === '') {
            return new Collection;
        }

        $term = '%'.addcslashes($search, '\\%_').'%';

        return Part::query()
            ->where('is_active', true)
            ->whereNotIn('id', $this->template->templateParts()->pluck('part_id'))
            ->where(function (Builder $query) use ($term): void {
                $query->where('part_code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            })
            ->orderBy('part_code')
            ->limit(20)
            ->get();
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

    // ── Section a: template settings ─────────────────────────────────

    /**
     * Cosmetic template fields only — saving these does NOT bump the
     * version. The machine is deliberately not editable: moving a template
     * to another machine would orphan its run history.
     */
    public function saveSettings(): void
    {
        $this->authorize('update', $this->template);

        $this->validate($this->settingsRules(), $this->settingsMessages(), $this->settingsAttributes());

        DB::transaction(function (): void {
            $this->template->update([
                'name' => trim($this->name),
                'work_category' => $this->workCategory,
                'work_description' => trim($this->workDescription),
                'frequency' => $this->frequency,
                'per_shift' => $this->perShift,
                'weekly_weekday' => (int) $this->weeklyWeekday,
                'monthly_day' => (int) $this->monthlyDay,
                'requires_supervisor_signoff' => $this->requiresSupervisorSignoff,
                'grace_period_hours' => (int) $this->gracePeriodHours,
            ]);
        });

        session()->flash('flash.success', __('app.templates.settings_saved'));
    }

    // ── Section b: item builder ──────────────────────────────────────

    public function startAddItem(): void
    {
        $this->authorize('update', $this->template);

        $this->resetItemForm();
        $this->showItemForm = true;
    }

    public function startEditItem(int $itemId): void
    {
        $this->authorize('update', $this->template);

        $item = $this->template->items()->findOrFail($itemId);

        $this->resetValidation();
        $this->editingItemId = $item->id;
        $this->showItemForm = true;
        $this->itemDescription = $item->description;
        $this->itemResponseType = $item->response_type->value;
        $this->itemIsRequired = $item->is_required;
        $this->itemGuidance = (string) $item->guidance;
        $this->itemRequiresPhotoOnFail = $item->requires_photo_on_fail;
    }

    public function cancelItemForm(): void
    {
        $this->resetItemForm();
    }

    /**
     * Create or update an item. Bumps the version only when a VERSIONED
     * field changed (description, response_type, is_required) — guidance
     * and the photo-on-fail flag shape future runs' behaviour but are not
     * part of the printed item set.
     */
    public function saveItem(): void
    {
        $this->authorize('update', $this->template);

        $this->validate($this->itemRules(), $this->itemMessages(), $this->itemAttributes());

        if ($this->editingItemId !== null) {
            $item = $this->template->items()->findOrFail($this->editingItemId);

            $item->fill([
                'description' => trim($this->itemDescription),
                'response_type' => $this->itemResponseType,
                'is_required' => $this->itemIsRequired,
                'guidance' => trim($this->itemGuidance) !== '' ? trim($this->itemGuidance) : null,
                'requires_photo_on_fail' => $this->itemRequiresPhotoOnFail,
            ]);

            $bumpsVersion = $item->isDirty(['description', 'response_type', 'is_required']);

            DB::transaction(function () use ($item, $bumpsVersion): void {
                $item->save();

                if ($bumpsVersion) {
                    $this->template->bumpVersion();
                }
            });

            session()->flash('flash.success', $bumpsVersion
                ? __('app.templates.item_saved_version', ['version' => $this->template->version])
                : __('app.templates.item_saved_no_version', ['version' => $this->template->version]));
        } else {
            DB::transaction(function (): void {
                // Max via an unordered query — the ordered relation would
                // compile `MAX(...) ... ORDER BY`, which ONLY_FULL_GROUP_BY
                // rejects.
                $this->template->items()->create([
                    'sort_order' => ((int) ChecklistTemplateItem::query()
                        ->where('checklist_template_id', $this->template->id)
                        ->max('sort_order')) + 1,
                    'description' => trim($this->itemDescription),
                    'response_type' => $this->itemResponseType,
                    'is_required' => $this->itemIsRequired,
                    'guidance' => trim($this->itemGuidance) !== '' ? trim($this->itemGuidance) : null,
                    'requires_photo_on_fail' => $this->itemRequiresPhotoOnFail,
                    'is_active' => true,
                ]);

                $this->template->bumpVersion();
            });

            session()->flash('flash.success', __('app.templates.item_saved_version', ['version' => $this->template->version]));
        }

        $this->resetItemForm();
    }

    /**
     * Deactivate / reactivate — NEVER delete. Historical run items reference
     * template items, so the row must survive; `is_active = false` only
     * stops the generator copying it into NEW runs.
     */
    public function toggleItemActive(int $itemId): void
    {
        $this->authorize('update', $this->template);

        $item = $this->template->items()->findOrFail($itemId);

        DB::transaction(function () use ($item): void {
            $item->update(['is_active' => ! $item->is_active]);
            $this->template->bumpVersion();
        });

        session()->flash('flash.success', $item->is_active
            ? __('app.templates.item_reactivated', ['version' => $this->template->version])
            : __('app.templates.item_deactivated', ['version' => $this->template->version]));
    }

    public function moveItemUp(int $itemId): void
    {
        $this->moveItem($itemId, -1);
    }

    public function moveItemDown(int $itemId): void
    {
        $this->moveItem($itemId, 1);
    }

    // ── Section c: parts list ────────────────────────────────────────

    /**
     * Append a part to the end of the printed "Used Parts" order. Parts are
     * not part of the item set, so no version bump.
     */
    public function attachPart(int $partId): void
    {
        $this->authorize('update', $this->template);

        $part = Part::query()->findOrFail($partId);

        if ($this->template->templateParts()->where('part_id', $part->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($part): void {
            // Max via an unordered query — see the note in saveItem().
            $this->template->templateParts()->create([
                'part_id' => $part->id,
                'sort_order' => ((int) ChecklistTemplatePart::query()
                    ->where('checklist_template_id', $this->template->id)
                    ->max('sort_order')) + 1,
            ]);
        });

        $this->partSearch = '';

        session()->flash('flash.success', __('app.templates.part_attached', ['code' => $part->part_code]));
    }

    public function detachPart(int $templatePartId): void
    {
        $this->authorize('update', $this->template);

        $templatePart = $this->template->templateParts()->findOrFail($templatePartId);

        DB::transaction(function () use ($templatePart): void {
            $templatePart->delete();

            // Close the gap so the printed order stays contiguous.
            $remaining = $this->template->templateParts()->pluck('id')->all();

            if ($remaining !== []) {
                $this->writeContiguousOrder('checklist_template_parts', $remaining);
            }
        });

        session()->flash('flash.success', __('app.templates.part_detached'));
    }

    public function movePartUp(int $templatePartId): void
    {
        $this->movePart($templatePartId, -1);
    }

    public function movePartDown(int $templatePartId): void
    {
        $this->movePart($templatePartId, 1);
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        // Model::preventLazyLoading() is on outside production — load
        // everything the view touches explicitly.
        $this->template->loadMissing('machine');

        return view('livewire.admin.template-editor', [
            'items' => $this->template->items()->get(),
            'templateParts' => $this->template->templateParts()->with('part')->get(),
        ])->title($this->template->name.' — '.__('app.templates.edit_template'));
    }

    // ── Internals ────────────────────────────────────────────────────

    private function fillSettingsForm(): void
    {
        $this->name = $this->template->name;
        $this->workCategory = $this->template->work_category->value;
        $this->workDescription = $this->template->work_description;
        $this->frequency = $this->template->frequency->value;
        $this->perShift = $this->template->per_shift;
        $this->weeklyWeekday = (string) $this->template->weekly_weekday;
        $this->monthlyDay = (string) $this->template->monthly_day;
        $this->requiresSupervisorSignoff = $this->template->requires_supervisor_signoff;
        $this->gracePeriodHours = (string) $this->template->grace_period_hours;
    }

    private function resetItemForm(): void
    {
        $this->resetValidation();
        $this->reset(
            'editingItemId',
            'showItemForm',
            'itemDescription',
            'itemResponseType',
            'itemIsRequired',
            'itemGuidance',
            'itemRequiresPhotoOnFail',
        );
    }

    private function moveItem(int $itemId, int $offset): void
    {
        $this->authorize('update', $this->template);

        $orderedIds = $this->template->items()->pluck('id')->all();
        $reordered = $this->swap($orderedIds, $itemId, $offset);

        if ($reordered === null) {
            return;
        }

        DB::transaction(function () use ($reordered): void {
            $this->writeContiguousOrder('checklist_template_items', $reordered);
            $this->template->bumpVersion();
        });

        session()->flash('flash.success', __('app.templates.items_reordered', ['version' => $this->template->version]));
    }

    private function movePart(int $templatePartId, int $offset): void
    {
        $this->authorize('update', $this->template);

        $orderedIds = $this->template->templateParts()->pluck('id')->all();
        $reordered = $this->swap($orderedIds, $templatePartId, $offset);

        if ($reordered === null) {
            return;
        }

        DB::transaction(function () use ($reordered): void {
            $this->writeContiguousOrder('checklist_template_parts', $reordered);
        });

        session()->flash('flash.success', __('app.templates.parts_reordered'));
    }

    /**
     * Swap the given id with its neighbour. Null when the id is missing or
     * already at the edge (the view disables those buttons, but a stale
     * click must be a no-op, not an error).
     *
     * @param  list<int>  $orderedIds
     * @return list<int>|null
     */
    private function swap(array $orderedIds, int $id, int $offset): ?array
    {
        $index = array_search($id, $orderedIds, true);

        if ($index === false) {
            return null;
        }

        $target = $index + $offset;

        if ($target < 0 || $target >= count($orderedIds)) {
            return null;
        }

        [$orderedIds[$index], $orderedIds[$target]] = [$orderedIds[$target], $orderedIds[$index]];

        return array_values($orderedIds);
    }

    /**
     * Rewrite contiguous 1..n sort_order values in ONE query (a CASE
     * expression), not one UPDATE per row. Ids come from our own pluck()
     * and are re-cast to int, so interpolation is safe.
     *
     * @param  list<int>  $orderedIds
     */
    private function writeContiguousOrder(string $table, array $orderedIds): void
    {
        $cases = '';

        foreach (array_values($orderedIds) as $index => $id) {
            $cases .= sprintf(' WHEN %d THEN %d', (int) $id, $index + 1);
        }

        $idList = implode(',', array_map(intval(...), $orderedIds));

        DB::update(
            "UPDATE {$table} SET sort_order = CASE id{$cases} END WHERE checklist_template_id = ? AND id IN ({$idList})",
            [$this->template->id],
        );
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    private function settingsRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('checklist_templates', 'name')
                    ->where(fn ($query) => $query->where('machine_id', $this->template->machine_id))
                    ->ignore($this->template->id)
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
    private function settingsMessages(): array
    {
        return [
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
    private function settingsAttributes(): array
    {
        return [
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
     * @return array<string, array<int, mixed>>
     */
    private function itemRules(): array
    {
        return [
            'itemDescription' => ['required', 'string', 'max:500'],
            'itemResponseType' => ['required', Rule::enum(ResponseType::class)],
            'itemIsRequired' => ['boolean'],
            'itemGuidance' => ['nullable', 'string', 'max:2000'],
            'itemRequiresPhotoOnFail' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function itemMessages(): array
    {
        return [
            'itemDescription.required' => __('app.templates.validation.item_description_required'),
            'itemDescription.max' => __('app.templates.validation.item_description_max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function itemAttributes(): array
    {
        return [
            'itemDescription' => __('app.templates.item_description'),
            'itemResponseType' => __('app.templates.response_type'),
            'itemGuidance' => __('app.templates.guidance'),
        ];
    }
}
