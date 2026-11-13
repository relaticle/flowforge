@props(['columnId', 'column', 'isCollapsed' => false])

@php
    use Filament\Support\Enums\IconSize;
    use Relaticle\Flowforge\Support\ColorResolver;
    use function Filament\Support\get_color_css_variables;

    $dotColor = ColorResolver::resolve($column['color'] ?? null);
@endphp

<div {{ $attributes->class(['ff-column-heading']) }}>
    @if ($dotColor)
        <span
            class="ff-column-dot"
            style="{{ get_color_css_variables($dotColor, shades: [500]) }}"
        ></span>
    @endif

    @if ($column['icon'] ?? null)
        <x-filament::icon :icon="$column['icon']" :size="IconSize::Small" class="ff-column-icon" />
    @endif

    <h3 class="ff-column-title">{{ $column['label'] }}</h3>

    <x-flowforge::modern.column-count :column="$column" :columnId="$columnId" />

    @if (! $isCollapsed && filled($column['summary'] ?? null))
        <span class="ff-summary">{{ $column['summary'] }}</span>
    @endif
</div>
