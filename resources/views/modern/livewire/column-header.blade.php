@props(['columnId', 'column', 'isCollapsed' => false])

@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Filament\Support\Enums\IconSize;
    use Relaticle\Flowforge\Support\ColorResolver;
    use function Filament\Support\get_color_css_variables;

    $actions = array_values(array_filter($this->getBoardColumnActions($columnId), fn (Action | ActionGroup $action): bool => $action->isVisible()));
    $dotColor = ColorResolver::resolve($column['color'] ?? null);
    $createAction = $this->getBoardCreateCardAction($columnId)
        ?->getClone()
        ->iconButton()
        ->icon('heroicon-m-plus')
        ->label(__('flowforge::flowforge.add_card', ['card' => $this->getBoard()->getCardLabel()]))
        ->disabled($column['isFull'] ?? false);

    if ($column['isFull'] ?? false) {
        $createAction?->tooltip(__('flowforge::flowforge.limit_reached'));
    }

    $createAction = $createAction?->isVisible() ? $createAction : null;
@endphp

<div class="ff-column-header">
    <div class="ff-column-heading">
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

    @if($createAction || count($actions) > 0)
        <div class="ff-column-actions">
            {{ $createAction }}

            @if(count($actions) === 1)
                {{ $actions[0] }}
            @elseif(count($actions) > 1)
                <x-filament-actions::group :actions="$actions" />
            @endif
        </div>
    @endif
</div>
