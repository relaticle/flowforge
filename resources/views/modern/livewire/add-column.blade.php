@props(['label', 'placeholder'])

@php
    use Filament\Support\Enums\IconSize;
@endphp

<div class="ff-add-column" x-bind:data-adding="addingColumn" wire:key="add-column">
    <div class="ff-add-column-ghost" x-cloak x-show="addingColumn">
        <form
            class="ff-add-column-form"
            x-on:submit.prevent="submitNewColumn()"
            x-on:click.outside="cancelNewColumn()"
        >
            <div class="ff-add-column-field">
                <span class="ff-add-column-dot"></span>

                <input
                    type="text"
                    class="ff-add-column-input"
                    x-ref="newColumnInput"
                    x-model="newColumnName"
                    x-on:input="newColumnTouched = true"
                    x-on:keydown.escape.prevent="cancelNewColumn()"
                    placeholder="{{ $placeholder }}"
                    aria-label="{{ $label }}"
                />
            </div>

            @error('boardNewColumn')
                <p class="ff-add-column-error" x-show="! newColumnTouched">{{ $message }}</p>
            @enderror
        </form>
    </div>

    <button
        type="button"
        class="ff-add-column-button"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        x-on:click="openNewColumn()"
    >
        <x-filament::icon icon="heroicon-m-plus" :size="IconSize::Small" />
    </button>
</div>
