@props(['columnId', 'column', 'config'])

@php
    $total = $column['total'] ?? (isset($column['items']) ? count($column['items']) : 0);
    $isCollapsed = (bool) ($column['isCollapsed'] ?? false);
    $plainLabel = strip_tags((string) $column['label']);
    $expandLabel = __('flowforge::flowforge.expand_column', ['column' => $plainLabel]);
    $trayLabel = __('flowforge::flowforge.column_label', [
        'column' => $plainLabel,
        'count' => $total,
        'cards' => trans_choice('flowforge::flowforge.cards_count', $total, [
            'card' => $config['cardLabel'],
            'cards' => $config['pluralCardLabel'],
        ]),
    ]);
@endphp

<div
    {{ $attributes->class('ff-column') }}
    data-column-root="{{ $columnId }}"
    data-column-total="{{ $column['limitTotal'] ?? $total }}"
    @if (($column['limit'] ?? null) !== null) data-column-limit="{{ $column['limit'] }}" @endif
    @if ($column['isFull'] ?? false) data-full @endif
    @if ($isCollapsed) data-collapsed @endif
    @if ($isCollapsed && ! ($column['canToggleCollapse'] ?? false))
        x-tooltip="{ content: @js($plainLabel), theme: $store.theme }"
    @endif
    @if ($isCollapsed && ($column['canToggleCollapse'] ?? false))
        role="button"
        tabindex="0"
        aria-label="{{ $expandLabel }}"
        x-tooltip="{ content: @js($expandLabel), theme: $store.theme }"
        x-on:keydown.enter.self.prevent="$el.click()"
        x-on:keydown.space.self.prevent="$el.click()"
        @if ($column['isCollapsedByAll'] ?? false)
            x-on:click="expandFromCollapseAll($event, @js((string) $columnId))"
        @else
            x-on:click="toggleColumnCollapse($event, @js((string) $columnId), true)"
        @endif
        x-on:pointerdown="railPress($event)"
        x-on:mousedown="suppressDoubleClickSelection($event)"
    @endif
    @if (! $isCollapsed && ($column['canToggleCollapse'] ?? false))
        x-on:dblclick="collapseFromEmptySpace($event, @js((string) $columnId))"
        x-on:mousedown="suppressEmptySpaceSelection($event)"
    @endif
    x-bind:data-drop="columnDrop(@js((string) $columnId))"
>
    @unless ($isCollapsed)
        <x-flowforge::modern.column-header :columnId="(string) $columnId" :column="$column" />
    @endunless

    <div
        class="ff-tray"
        role="region"
        aria-label="{{ $trayLabel }}"
    >
        @if ($isCollapsed)
            <x-flowforge::modern.column-heading :columnId="(string) $columnId" :column="$column" :isCollapsed="true" />
        @endif

        @if (($column['limit'] ?? null) !== null)
            <p
                x-cloak
                x-show="columnDrop(@js((string) $columnId)) === 'blocked'"
                class="ff-limit-hint"
            >{{ __('flowforge::flowforge.limit_reached') }}</p>
        @endif

        <div
            class="ff-cards"
            data-column-id="{{ $columnId }}"
            @if($this->getBoard()->getPositionIdentifierAttribute())
                x-sortable
                x-sortable-group="cards"
                @end.stop="handleSortableEnd($event)"
                x-init="$nextTick(() => configureSortable($el))"
            @endif
            @if(! $isCollapsed && isset($column['total']) && $column['total'] > count($column['items']))
                @scroll.throttle.100ms="handleColumnScroll($event, '{{ $columnId }}')"
            @endif
        >
            @foreach ($column['items'] ?? [] as $record)
                <x-flowforge::modern.card
                    :record="$record"
                    :config="$config"
                    :columnId="$columnId"
                />
            @endforeach

            @if(! $isCollapsed && isset($column['total']) && $column['total'] > count($column['items'] ?? []))
                <div class="ff-cards-more" x-intersect.margin.300px="handleSmoothScroll('{{ $columnId }}')">
                    <div
                        x-show="isLoadingColumn('{{ $columnId }}')"
                        x-transition
                        class="ff-cards-loading"
                    >
                        {{ __('flowforge::flowforge.loading_more_cards') }}
                    </div>
                </div>
            @endif

            @unless($isCollapsed)
                <div class="ff-add-slot">
                    <x-flowforge::modern.add-card-row :columnId="(string) $columnId" :cardLabel="$config['cardLabel']" :disabled="$column['isFull'] ?? false" />
                </div>
            @endunless
        </div>
    </div>
</div>
