@php use Filament\Support\Facades\FilamentAsset; @endphp
@props(['columns', 'swimlanes', 'config'])

<div
    class="w-full h-full flex flex-col relative"
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('flowforge', package: 'relaticle/flowforge') }}"
    x-data="flowforge({
        state: {
            columns: @js($columns),
            swimlanes: @js($swimlanes),
            titleField: '{{ $config['recordTitleAttribute'] }}',
            columnField: '{{ $config['columnIdentifierAttribute'] }}',
            swimlaneField: '{{ $config['swimlaneIdentifierAttribute'] ?? '' }}',
            cardLabel: '{{ $config['cardLabel'] }}',
            pluralCardLabel: '{{ $config['pluralCardLabel'] }}',
        }
    })"
>

    @include('flowforge::components.filters')

    <!-- Board Content -->
    @if($swimlanes)
        {{-- 2D Swimlane Grid Layout --}}
        <x-flowforge::swimlane-board
            :columns="$columns"
            :swimlanes="$swimlanes"
            :config="$config"
        />
    @else
        {{-- Flat Column Layout (original) --}}
        <div class="flex-1 overflow-hidden h-full">
            <div class="flex flex-row h-full overflow-x-auto overflow-y-hidden gap-5">
                @foreach($columns as $columnId => $column)
                    <x-flowforge::column
                        :columnId="$columnId"
                        :column="$column"
                        :config="$config"
                        wire:key="column-{{ $columnId }}"
                    />
                @endforeach
            </div>
        </div>
    @endif

    <x-filament-actions::modals/>
</div>
