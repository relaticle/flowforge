@php
    use Filament\Pages\Page;
    use Filament\Support\Facades\FilamentAsset;
    use Relaticle\Flowforge\Modern\Concerns\InteractsWithBoard;
@endphp
@props(['columns', 'config', 'lanes' => [], 'hasMoreLanes' => false, 'groupings' => []])

<div style="display: contents">
@unless ($this instanceof Page && in_array(InteractsWithBoard::class, class_uses_recursive($this), true))
    @include('flowforge::modern.stylesheet')
@endunless

<div
    class="ff-board"
    data-lanes-writable="{{ $config['lanesWritable'] ? 'true' : 'false' }}"
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('flowforge-modern', package: 'relaticle/flowforge') }}"
    x-data="flowforgeModern()"
>

    @unless($config['headerToolbar'] ?? false)
        @include('flowforge::modern.components.filters')
    @endunless

    @unless ($config['headerToolbar'] ?? false)
        <x-flowforge::modern.grouping-picker :groupings="$groupings" />
    @endunless

    <div class="ff-board-body">
        @if ($config['grouping'] !== null)
            <x-flowforge::modern.lanes :columns="$columns" :lanes="$lanes" :config="$config" :hasMoreLanes="$hasMoreLanes" />
        @else
            <div class="ff-columns">
                @foreach($columns as $columnId => $column)
                    <x-flowforge::modern.column
                        :columnId="$columnId"
                        :column="$column"
                        :config="$config"
                        wire:key="column-{{ $columnId }}"
                    />
                @endforeach

                @if ($config['canAddColumns'] ?? false)
                    <x-flowforge::modern.add-column :label="$config['addColumnLabel']" :placeholder="$config['addColumnPlaceholder']" :colors="$config['addColumnColors'] ?? []" />
                @endif
            </div>
        @endif
    </div>

    <x-filament-actions::modals/>
</div>
</div>
