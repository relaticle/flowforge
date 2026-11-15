@props(['columnId', 'column', 'isCollapsed' => false])

@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Filament\Support\Facades\FilamentIcon;
    use Relaticle\Flowforge\View\FlowforgeIconAlias;

    $actions = array_values(array_filter($this->getBoardColumnActions($columnId), fn (Action | ActionGroup $action): bool => $action->isVisible()));
    $menuBlock = $isCollapsed ? null : $this->getBoard()->getColumnHeaderMenuBlock($column);
    $menuActions = $this->getBoard()->hasColumnHeaderMenu() && ! $isCollapsed ? $actions : [];
    $hasMenu = $menuBlock || $menuActions;
    $createAction = $this->getBoardCreateCardAction($columnId)
        ?->getClone()
        ->iconButton()
        ->icon(FilamentIcon::resolve(FlowforgeIconAlias::ADD_CARD) ?? 'heroicon-m-plus')
        ->label(__('flowforge::flowforge.add_card', ['card' => $this->getBoard()->getCardLabel()]))
        ->tooltip(($column['isFull'] ?? false) ? __('flowforge::flowforge.limit_reached') : __('flowforge::flowforge.add_card', ['card' => $this->getBoard()->getCardLabel()]))
        ->disabled($column['isFull'] ?? false);

    $createAction = $createAction?->isVisible() ? $createAction : null;

    // x-float reads this before the board's Alpine data loads, so it carries its own logic.
    $menuOffset = '({ elements: { reference } }) => { const column = reference.closest(\'[data-column-root]\').getBoundingClientRect(); const trigger = reference.getBoundingClientRect(); return { mainAxis: column.top - trigger.bottom, crossAxis: column.left - trigger.left }; }';
@endphp

<div
    class="ff-column-header"
    @if (($column['canToggleCollapse'] ?? false) && ! $isCollapsed && ! $hasMenu)
        x-on:dblclick="toggleColumnCollapse($event, @js($columnId))"
        x-on:mousedown="suppressDoubleClickSelection($event)"
    @endif
>
    @if ($hasMenu)
        <x-filament::dropdown class="ff-column-menu" placement="bottom-start" :offset="$menuOffset" :flip="false" :size="true" wire:key="column-menu-{{ $columnId }}">
            <x-slot name="trigger">
                <x-flowforge::modern.column-heading
                    :columnId="$columnId"
                    :column="$column"
                    role="button"
                    tabindex="0"
                    x-init="initColumnMenu($el)"
                    x-on:mousedown="columnMenuPress($event)"
                    x-on:click="columnMenuClick($event)"
                />
            </x-slot>

            @if ($menuBlock)
                <div class="ff-column-menu-block">{{ $menuBlock }}</div>
            @endif

            @if ($menuActions)
                <x-filament::dropdown.list>
                    @foreach ($menuActions as $menuAction)
                        {{ $menuAction->grouped() }}
                    @endforeach
                </x-filament::dropdown.list>
            @endif
        </x-filament::dropdown>
    @else
        <x-flowforge::modern.column-heading :columnId="$columnId" :column="$column" :isCollapsed="$isCollapsed" />
    @endif

    @if ($createAction || (! $hasMenu && count($actions) > 0))
        <div class="ff-column-actions">
            {{ $createAction }}

            @if (! $hasMenu && count($actions) === 1)
                {{ $actions[0] }}
            @elseif (! $hasMenu && count($actions) > 1)
                <x-filament-actions::group :actions="$actions" />
            @endif
        </div>
    @endif
</div>
