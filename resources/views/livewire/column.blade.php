@props(['columnId', 'column', 'config'])

@php
    $total = $column['total'] ?? (isset($column['items']) ? count($column['items']) : 0);
    $isCollapsed = ($config['collapseEmptyColumns'] ?? false) && $total === 0;
    $icon = $column['icon'] ?? null;
    $actions = $this->getBoardColumnActions($columnId);

    // A collapsed column keeps the expanded markup and swaps only the classes that
    // differ, so hovering it during a drag restores the full header in place.
    $isOpen = 'dragOverColumn === '.json_encode($columnId);
@endphp

<div
    @class([
        'flowforge-column flex-shrink-0 border border-gray-200 dark:border-gray-700 shadow-sm dark:shadow-md rounded-xl flex flex-col max-h-full overflow-hidden',
        'w-[300px] min-w-[300px]' => ! $isCollapsed,
        'flowforge-column-collapsed w-12 min-w-12' => $isCollapsed,
    ])
    {{-- SortableJS stops propagation inside its own container, so the header and
         footer of a rail need their own hook to register as hovered. --}}
    x-on:dragenter="dragOverColumn = @js($columnId)"
    @if($isCollapsed)
        title="{{ $column['label'] }}"
        x-bind:class="{
            'w-12 min-w-12': ! ({{ $isOpen }}),
            'w-[300px] min-w-[300px]': {{ $isOpen }},
        }"
    @endif
>
    <!-- Column Header -->
    <div
        @class([
            'flowforge-column-header flex items-center gap-2 py-3',
            'justify-between px-4 border-b border-gray-200 dark:border-gray-700' => ! $isCollapsed,
            'flex-col px-1' => $isCollapsed,
        ])
        @if($isCollapsed)
            x-bind:class="{
                'justify-between px-4 border-b border-gray-200 dark:border-gray-700': {{ $isOpen }},
                'flex-col px-1': ! ({{ $isOpen }}),
            }"
        @endif
    >
        <div
            @class([
                'flex min-w-0 items-center gap-2' => ! $isCollapsed,
                'contents' => $isCollapsed,
            ])
            @if($isCollapsed)
                x-bind:class="{
                    'flex min-w-0 items-center gap-2': {{ $isOpen }},
                    'contents': ! ({{ $isOpen }}),
                }"
            @endif
        >
            @if ($icon)
                <x-filament::icon :icon="$icon" class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" />
            @endif

            <h3
                @class([
                    'text-sm font-medium',
                    'truncate text-gray-700 dark:text-gray-200' => ! $isCollapsed,
                    'max-h-64 overflow-hidden text-ellipsis whitespace-nowrap text-gray-500 dark:text-gray-400 [writing-mode:vertical-rl]' => $isCollapsed,
                ])
                @if($isCollapsed)
                    x-bind:class="{
                        'truncate text-gray-700 dark:text-gray-200': {{ $isOpen }},
                        'max-h-64 text-gray-500 dark:text-gray-400 [writing-mode:vertical-rl]': ! ({{ $isOpen }}),
                    }"
                @endif
            >
                {{ $column['label'] }}
            </h3>

            <x-flowforge::column-count :color="$column['color']" :total="$total" />
        </div>

        @if(count($actions) > 0)
            <div class="shrink-0">
                @if(count($actions) === 1)
                    {{ $actions[0] }}
                @else
                    <x-filament-actions::group :actions="$actions"/>
                @endif
            </div>
        @endif
    </div>

    <!-- Column Content -->
    <div
        data-column-id="{{ $columnId }}"
        @if($this->getBoard()->getPositionIdentifierAttribute())
            x-sortable
        x-sortable-group="cards"
        @dragenter="dragOverColumn = @js($columnId)"
        @end.stop="dragOverColumn = null; handleSortableEnd($event)"
        @endif
        @if(isset($column['total']) && $column['total'] > count($column['items']))
            @scroll.throttle.100ms="handleColumnScroll($event, '{{ $columnId }}')"
        @endif
        @class([
            'flowforge-column-content relative flex-1 overflow-y-auto overflow-x-hidden overscroll-y-contain kanban-cards',
            'p-3' => ! $isCollapsed,
            'p-0' => $isCollapsed,
        ])
        @if($isCollapsed) x-bind:class="{ 'p-3': dragOverColumn === @js($columnId), 'p-0': dragOverColumn !== @js($columnId) }" @endif
        style="max-height: calc(100vh - 13rem);"
    >
        @if (isset($column['items']) && count($column['items']) > 0)
            @foreach ($column['items'] as $record)
                <x-flowforge::card
                    :record="$record"
                    :config="$config"
                    :columnId="$columnId"
                    wire:key="card-{{ $record['id'] }}"
                />
            @endforeach

            {{-- Always show status message at bottom --}}
            <div class="py-3 text-center">
                @if(isset($column['total']) && $column['total'] > count($column['items']))
                    {{-- More items available --}}
                    <div
                        x-intersect.margin.300px="handleSmoothScroll('{{ $columnId }}')"
                        class="w-full">

                        <div x-show="isLoadingColumn('{{ $columnId }}')"
                             x-transition
                             class="text-xs text-primary-600 dark:text-primary-400 flex items-center justify-center gap-2">
                            {{ __('flowforge::flowforge.loading_more_cards') }}
                        </div>
                    </div>
                @endif
            </div>
        @elseif($isCollapsed)
            {{-- The rail under the pointer widens to a full drop zone --}}
            <div x-cloak x-show="dragOverColumn === @js($columnId)" class="h-full">
                <x-flowforge::empty-column
                    :columnId="$columnId"
                    :pluralCardLabel="$config['pluralCardLabel']"
                />
            </div>
        @else
            <x-flowforge::empty-column
                :columnId="$columnId"
                :pluralCardLabel="$config['pluralCardLabel']"
            />
        @endif
    </div>

</div>
