@props(['label', 'placeholder', 'colors' => [], 'defaultColor' => null, 'colorLabel' => null])

@php
    use Filament\Support\Enums\IconSize;

    $defaultStyle = collect($colors)->firstWhere('value', $defaultColor)['style'] ?? '';
@endphp

<div class="ff-add-column" x-bind:data-adding="addingColumn" wire:key="add-column">
    <div class="ff-add-column-ghost" x-cloak x-show="addingColumn">
        <form
            class="ff-add-column-form"
            x-on:submit.prevent="submitNewColumn()"
            x-on:click.outside="cancelNewColumn()"
        >
            <div class="ff-add-column-field">
                @if ($colors)
                    <button
                        type="button"
                        class="ff-add-column-dot"
                        x-ref="newColumnDot"
                        x-bind:style="newColumnDotStyle"
                        aria-haspopup="true"
                        x-bind:aria-expanded="choosingNewColumnColor.toString()"
                        x-on:click="choosingNewColumnColor = ! choosingNewColumnColor"
                        aria-label="{{ __('flowforge::flowforge.column_color') }}"
                    ></button>

                    <x-flowforge::modern.color-swatches
                        :colors="$colors"
                        open="choosingNewColumnColor"
                        selected-expression="newColumnColor"
                        :set-color-label="$colorLabel"
                        x-on:swatch-pick="pickNewColumnColor($event.detail.value, $event.detail.style)"
                        x-on:swatch-close="closeNewColumnColors()"
                        x-on:click.outside="choosingNewColumnColor = false"
                    />
                @else
                    <span class="ff-add-column-dot"></span>
                @endif

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
        x-tooltip="{ content: @js($label), theme: $store.theme }"
        aria-label="{{ $label }}"
        x-on:click="openNewColumn(@js($defaultColor), @js($defaultStyle))"
    >
        <x-filament::icon :alias="\Relaticle\Flowforge\View\FlowforgeIconAlias::ADD_COLUMN" icon="heroicon-m-plus" :size="IconSize::Small" />
    </button>
</div>
