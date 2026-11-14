@props(['columns', 'lanes', 'config', 'hasMoreLanes'])

@php
    use Filament\Support\Enums\IconSize;
    use Relaticle\Flowforge\Support\ColorResolver;
    use function Filament\Support\get_color_css_variables;

    $isSortable = $this->getBoard()->getPositionIdentifierAttribute() !== null;
    $tracks = collect($columns)
        ->map(fn (array $column): string => ($column['isCollapsed'] ?? false) ? 'var(--_collapsed-width)' : 'var(--_column-width)')
        ->implode(' ');
    $tracksStyle = '--_tracks: ' . $tracks . '; --_tracks-open: repeat(' . count($columns) . ', var(--_column-width))';
    $canAddColumns = (bool) ($config['canAddColumns'] ?? false);
    $headsStyle = $canAddColumns
        ? '--_tracks: ' . $tracks . ' var(--_collapsed-width); --_tracks-open: repeat(' . count($columns) . ', var(--_column-width)) var(--_collapsed-width)'
        : $tracksStyle;
@endphp

<div class="ff-lanes">
    <div class="ff-lanes-grid" style="{{ $tracksStyle }}">
        <div class="ff-lanes-heads" style="{{ $headsStyle }}" wire:key="column-headers">
            @foreach ($columns as $columnId => $column)
                @php $isCollapsed = (bool) ($column['isCollapsed'] ?? false); @endphp

                <div
                    class="ff-column"
                    data-column-root="{{ $columnId }}"
                    data-column-total="{{ $column['limitTotal'] ?? $column['total'] }}"
                    @if (($column['limit'] ?? null) !== null) data-column-limit="{{ $column['limit'] }}" @endif
                    @if ($column['isFull'] ?? false) data-full @endif
                    @if ($isCollapsed) data-collapsed title="{{ strip_tags((string) $column['label']) }}" @endif
                    @if ($isCollapsed && ($column['canToggleCollapse'] ?? false))
                        @if ($column['isCollapsedByAll'] ?? false)
                            x-on:click="expandFromCollapseAll($event, @js((string) $columnId))"
                        @else
                            x-on:click="toggleColumnCollapse($event, @js((string) $columnId), true)"
                        @endif
                        x-on:pointerdown="railPress($event)"
                        x-on:mousedown="suppressDoubleClickSelection($event)"
                    @endif
                    x-bind:data-drop="columnDrop(@js((string) $columnId))"
                    wire:key="lane-header-{{ $columnId }}"
                >
                    <x-flowforge::modern.column-header :columnId="(string) $columnId" :column="$column" :isCollapsed="$isCollapsed" />
                </div>
            @endforeach

            @if ($canAddColumns)
                <x-flowforge::modern.add-column :label="$config['addColumnLabel']" :placeholder="$config['addColumnPlaceholder']" />
            @endif
        </div>

        @foreach ($lanes as $lane)
            @php $laneColor = ColorResolver::resolve($lane['color']); @endphp

            @php
                $laneKey = (string) $lane['id'];
                $laneTitleId = 'ff-lane-title-' . $this->getId() . '-' . md5($laneKey);
            @endphp

            <div
                class="ff-lane-header"
                wire:key="lane-{{ $lane['id'] }}"
                x-bind:data-collapsed="isLaneCollapsed(@js($laneKey))"
                x-on:click="toggleLane(@js($laneKey))"
            >
                <button
                    type="button"
                    class="ff-lane-toggle"
                    aria-labelledby="{{ $laneTitleId }}"
                    x-bind:aria-expanded="! isLaneCollapsed(@js($laneKey))"
                >
                    <x-filament::icon icon="heroicon-m-chevron-down" class="ff-lane-chevron" />
                </button>

                @if ($lane['avatar'])
                    <img src="{{ $lane['avatar'] }}" alt="" class="ff-lane-avatar" />
                @elseif ($lane['icon'])
                    <x-filament::icon :icon="$lane['icon']" :size="IconSize::Small" class="ff-lane-icon" />
                @elseif ($laneColor)
                    <span class="ff-lane-dot" style="{{ get_color_css_variables($laneColor, shades: [500]) }}"></span>
                @endif

                <h4 id="{{ $laneTitleId }}" class="ff-lane-title">{{ $lane['label'] }}</h4>
                <span class="ff-lane-count">{{ $lane['total'] }}</span>
            </div>

            @foreach ($columns as $columnId => $column)
                @php
                    $cell = $lane['cells'][$columnId] ?? ['items' => [], 'total' => 0];
                    $isCollapsed = (bool) ($column['isCollapsed'] ?? false);
                @endphp

                <div
                    class="ff-cell"
                    @if ($isCollapsed) data-collapsed @endif
                    @if (! $isCollapsed && ($column['canToggleCollapse'] ?? false))
                        x-on:dblclick="collapseFromEmptySpace($event, @js((string) $columnId))"
                        x-on:mousedown="suppressEmptySpaceSelection($event)"
                    @endif
                    x-show="! isLaneCollapsed(@js($laneKey))"
                    x-bind:data-drop="cellDrop(@js((string) $columnId), @js((string) $lane['id']))"
                    wire:key="cell-{{ $lane['id'] }}-{{ $columnId }}"
                >
                    @if ($isCollapsed)
                        <span class="ff-count">{{ $cell['total'] }}</span>
                    @endif

                    <div
                        class="ff-cell-cards"
                        data-column-id="{{ $columnId }}"
                        data-lane-id="{{ $lane['id'] }}"
                        @if ($isSortable)
                            x-sortable
                            x-sortable-group="cards"
                            @end.stop="handleSortableEnd($event)"
                            x-init="$nextTick(() => configureSortable($el))"
                        @endif
                    >
                        @foreach ($cell['items'] as $record)
                            <x-flowforge::modern.card :record="$record" :columnId="$columnId" :laneId="$lane['id']" />
                        @endforeach
                    </div>

                    @if (! $isCollapsed && $cell['total'] > count($cell['items']))
                        <button
                            type="button"
                            class="ff-add-row"
                            wire:click="loadMoreItems(@js((string) $columnId), null, @js($lane['id']))"
                            wire:key="more-cards-{{ $lane['id'] }}-{{ $columnId }}"
                        >
                            {{ __('flowforge::flowforge.show_more_cards', ['count' => $cell['total'] - count($cell['items'])]) }}
                        </button>
                    @endif
                </div>
            @endforeach
        @endforeach

        @if ($hasMoreLanes)
            <button
                type="button"
                class="ff-add-row ff-lanes-more"
                wire:click="loadMoreLanes"
                wire:key="more-lanes"
            >
                {{ __('flowforge::flowforge.show_more_lanes') }}
            </button>
        @endif
    </div>
</div>
