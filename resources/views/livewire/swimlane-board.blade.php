@php use Relaticle\Flowforge\Support\ColorResolver; @endphp
@props(['columns', 'swimlanes', 'config'])

@php
    $columnIds = array_keys($columns);
    $columnCount = count($columnIds);
    $hasPositionIdentifier = $this->getBoard()->getPositionIdentifierAttribute() !== null;
@endphp

<div
    class="flowforge-swimlane-board overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm"
    x-data="{
        collapsed: {},
        init() {
            try {
                const stored = localStorage.getItem('flowforge:swimlanes:' + window.location.pathname);
                if (stored) this.collapsed = JSON.parse(stored);
            } catch(e) { this.collapsed = {}; }
        },
        toggle(id) {
            this.collapsed[id] = !(this.collapsed[id] || false);
            try { localStorage.setItem('flowforge:swimlanes:' + window.location.pathname, JSON.stringify(this.collapsed)); } catch(e) {}
        },
        isCollapsed(id) {
            return this.collapsed[id] || false;
        }
    }"
>
    <table class="border-collapse w-full" style="min-width: {{ 180 + ($columnCount * 300) }}px;">
        {{-- Sticky Column Header Row --}}
        <thead>
            <tr>
                {{-- Empty corner cell --}}
                <th class="sticky top-0 left-0 z-30 bg-white dark:bg-gray-900 border-b border-r border-gray-200 dark:border-gray-700 p-2" style="width: 180px; min-width: 180px;"></th>

                @foreach($columns as $columnId => $column)
                    @php
                        $resolvedColor = ColorResolver::resolve($column['color']);
                        $isSemantic = ColorResolver::isSemantic($resolvedColor);
                    @endphp
                    <th
                        class="sticky top-0 z-20 bg-white dark:bg-gray-900 border-b border-r border-gray-200 dark:border-gray-700 p-3 text-left font-normal"
                        style="min-width: 300px;"
                        wire:key="header-{{ $columnId }}"
                    >
                        <div class="flex items-center">
                            @if ($column['icon'] ?? null)
                                <x-filament::icon :icon="$column['icon']" class="h-4 w-4 text-gray-500 dark:text-gray-400 me-2" />
                            @endif
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">
                                {{ $column['label'] }}
                            </h3>
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach($swimlanes as $swimlaneId => $swimlane)
                @php
                    $resolvedLaneColor = ColorResolver::resolve($swimlane['color']);
                    $isLaneSemantic = ColorResolver::isSemantic($resolvedLaneColor);
                    $laneColorShades = $isLaneSemantic ? null : $resolvedLaneColor;
                @endphp

                {{-- Swimlane Row --}}
                <tr wire:key="lane-row-{{ $swimlaneId }}">
                    {{-- Swimlane Label Cell (row header) --}}
                    <td
                        class="sticky left-0 z-10 bg-white dark:bg-gray-900 border-b border-r border-gray-200 dark:border-gray-700 p-3"
                        style="width: 180px; min-width: 180px; vertical-align: top;"
                        wire:key="lane-label-{{ $swimlaneId }}"
                    >
                        <button
                            type="button"
                            class="flex items-center gap-2 w-full text-left group"
                            x-on:click="toggle('{{ $swimlaneId }}')"
                        >
                            {{-- Collapse/expand chevron --}}
                            <svg
                                class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform duration-200 flex-shrink-0"
                                :class="{ '-rotate-90': isCollapsed('{{ $swimlaneId }}') }"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>

                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 truncate">
                                {{ $swimlane['label'] }}
                            </span>

                            {{-- Count badge --}}
                            @if($isLaneSemantic)
                                <x-filament::badge tag="div" :color="$resolvedLaneColor" size="sm">
                                    {{ $swimlane['total'] }}
                                </x-filament::badge>
                            @elseif($laneColorShades)
                                <div
                                    @style([
                                        Filament\Support\get_color_css_variables($resolvedLaneColor, shades: [50, 300, 600, 700])
                                    ])
                                    @class([
                                        'items-center border px-1.5 py-0.5 rounded-md text-xs font-semibold',
                                        'bg-custom-50 dark:bg-custom-600/20',
                                        'text-custom-700 dark:text-custom-300',
                                        'border-custom-700/30 dark:border-custom-300/30',
                                    ])
                                >
                                    {{ $swimlane['total'] }}
                                </div>
                            @else
                                <div class="items-center border px-1.5 py-0.5 rounded-md text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600">
                                    {{ $swimlane['total'] }}
                                </div>
                            @endif
                        </button>
                    </td>

                    {{-- Swimlane Cells --}}
                    @foreach($columnIds as $columnId)
                        @php
                            $cell = $swimlane['cells'][$columnId] ?? ['items' => [], 'total' => 0];
                            $cellKey = $columnId . '|' . $swimlaneId;
                        @endphp
                        <td
                            class="border-b border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-0"
                            style="min-width: 300px; vertical-align: top;"
                            wire:key="cell-{{ $cellKey }}"
                        >
                            <div x-show="!isCollapsed('{{ $swimlaneId }}')">
                                <x-flowforge::swimlane-cell
                                    :columnId="$columnId"
                                    :swimlaneId="$swimlaneId"
                                    :cell="$cell"
                                    :config="$config"
                                    :hasPositionIdentifier="$hasPositionIdentifier"
                                />
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
