{{--
    Item add/edit form. Included by template-editor.blade.php in two places
    (inline in the row being edited, and at the foot of the list when adding)
    — only one instance ever renders at a time.
--}}
<form wire:submit="saveItem" class="space-y-4 rounded-xl border border-sky-300 bg-sky-50/50 p-4">
    <h3 class="text-lg font-bold">
        {{ $editingItemId !== null ? __('app.templates.edit_item') : __('app.templates.add_item') }}
    </h3>

    <div>
        <label for="item-description" class="mb-1 block text-base font-medium">{{ __('app.templates.item_description') }}</label>
        <x-input id="item-description" wire:model="itemDescription" maxlength="500" />
        @error('itemDescription') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="item-response-type" class="mb-1 block text-base font-medium">{{ __('app.templates.response_type') }}</label>
        <x-select id="item-response-type" wire:model="itemResponseType">
            @foreach (App\Enums\ResponseType::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-sm text-slate-600">{{ __('app.templates.response_type_help') }}</p>
        @error('itemResponseType') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-checkbox id="item-required" wire:model="itemIsRequired">{{ __('app.templates.item_required') }}</x-checkbox>
        <x-checkbox id="item-photo-on-fail" wire:model="itemRequiresPhotoOnFail">{{ __('app.templates.requires_photo_on_fail') }}</x-checkbox>
    </div>

    <div>
        <label for="item-guidance" class="mb-1 block text-base font-medium">
            {{ __('app.templates.guidance') }}
            <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
        </label>
        <x-textarea id="item-guidance" wire:model="itemGuidance" rows="2" />
        <p class="mt-1 text-sm text-slate-600">{{ __('app.templates.guidance_hint') }}</p>
        @error('itemGuidance') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex justify-end gap-3">
        <x-button variant="ghost" wire:click="cancelItemForm">
            {{ __('app.actions.cancel') }}
        </x-button>
        <x-button type="submit" wire:loading.attr="disabled">
            {{ __('app.actions.save') }}
        </x-button>
    </div>
</form>
