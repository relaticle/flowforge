@props(['colors', 'open', 'selected' => null, 'selectedExpression' => null, 'label' => null, 'setColorLabel' => null])

@php
    $values = array_column($colors, 'value');
    $setColorLabel ??= __('flowforge::flowforge.set_column_color');
    $tabStop = in_array($selected, $values, true) ? $selected : ($values[0] ?? null);
@endphp

<div
    {{ $attributes->class(['ff-swatches']) }}
    role="radiogroup"
    aria-label="{{ $label ?? __('flowforge::flowforge.column_color') }}"
    x-cloak
    x-show="{{ $open }}"
    x-effect="if ({{ $open }}) { $nextTick(() => focusSwatch($el)) }"
    x-on:keydown="swatchKey($event)"
    x-on:focusin="swatchFocused($event)"
    x-on:keydown.escape.stop.prevent="$dispatch('swatch-close')"
    x-on:dropdown-escape.prevent="$dispatch('swatch-close')"
>
    @foreach ($colors as $color)
        <button
            type="button"
            role="radio"
            class="ff-swatch"
            style="{{ $color['style'] }}"
            data-swatch="{{ $color['value'] }}"
            aria-label="{{ str_replace(':color', $color['label'], $setColorLabel) }}"
            aria-checked="{{ $color['value'] === $selected ? 'true' : 'false' }}"
            tabindex="{{ $color['value'] === $tabStop ? '0' : '-1' }}"
            @if ($selectedExpression)
                x-bind:aria-checked="(({{ $selectedExpression }}) === @js($color['value'])).toString()"
            @endif
            x-on:click="$dispatch('swatch-pick', { value: @js($color['value']), style: @js($color['style']) })"
        ></button>
    @endforeach
</div>
