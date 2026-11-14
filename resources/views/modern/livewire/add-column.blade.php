@props(['label'])

@php
    use Filament\Support\Enums\IconSize;
@endphp

<div class="ff-add-column" x-bind:data-adding="addingColumn" wire:key="add-column">
    <button
        type="button"
        class="ff-add-column-button"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        x-on:click="openNewColumn()"
    >
        <x-filament::icon icon="heroicon-m-plus" :size="IconSize::Small" />
    </button>

    <form
        class="ff-add-column-form"
        x-cloak
        x-show="addingColumn"
        x-on:submit.prevent="submitNewColumn()"
    >
        <input
            type="text"
            class="ff-add-column-input"
            x-ref="newColumnInput"
            x-model="newColumnName"
            x-on:input="newColumnTouched = true"
            x-on:keydown.escape.prevent="cancelNewColumn()"
            placeholder="{{ __('flowforge::flowforge.column_name') }}"
            aria-label="{{ $label }}"
        />

        @error('boardNewColumn')
            <p class="ff-add-column-error" x-show="! newColumnTouched">{{ $message }}</p>
        @enderror
    </form>
</div>
