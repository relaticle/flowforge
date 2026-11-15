@props(['groupings' => null])

@php
    use Illuminate\Support\Js;

    $groupings ??= array_map(
        fn ($grouping): string => (string) $grouping->getLabel(),
        $this->getBoard()->getGroupings(),
    );
    $triggerId = 'flowforge-group-by-' . $this->getId();
    $activeLabel = filled($this->groupBy) ? ($groupings[$this->groupBy] ?? null) : null;
    $options = ['' => __('flowforge::flowforge.no_grouping')] + $groupings;
@endphp

@if (count($groupings) > 0)
    <x-filament::dropdown placement="bottom-end" {{ $attributes->class(['ff-grouping']) }}>
        <x-slot name="trigger">
            <x-filament::button
                :id="$triggerId"
                color="gray"
                size="sm"
                icon="heroicon-o-queue-list"
                :icon-alias="\Relaticle\Flowforge\View\FlowforgeIconAlias::GROUPING_TRIGGER"
                :aria-label="$activeLabel ? __('flowforge::flowforge.group_by') . ': ' . $activeLabel : __('flowforge::flowforge.group_by')"
                :data-grouped="$activeLabel !== null"
                class="ff-grouping-trigger"
            >
                <span class="ff-grouping-label">{{ $activeLabel ?? __('flowforge::flowforge.group_by') }}</span>

                <x-filament::icon :alias="\Relaticle\Flowforge\View\FlowforgeIconAlias::GROUPING_CHEVRON" icon="heroicon-m-chevron-down" class="ff-grouping-chevron" />
            </x-filament::button>
        </x-slot>

        <x-filament::dropdown.header class="ff-grouping-heading">
            {{ __('flowforge::flowforge.group_by') }}
        </x-filament::dropdown.header>

        <x-filament::dropdown.list>
            @foreach ($options as $name => $label)
                @php
                    $isActive = (string) $name === (string) $this->groupBy;
                    $value = (string) $name === '' ? null : (string) $name;
                @endphp

                <x-filament::dropdown.list.item
                    role="menuitemradio"
                    :aria-checked="$isActive ? 'true' : 'false'"
                    :color="$isActive ? 'primary' : 'gray'"
                    x-on:click="close(); $wire.$set('groupBy', {{ Js::from($value) }})"
                    class="ff-grouping-option"
                >
                    {{ $label }}

                    @if ($isActive)
                        <x-slot name="badge">
                            <x-filament::icon :alias="\Relaticle\Flowforge\View\FlowforgeIconAlias::GROUPING_CHECK" icon="heroicon-m-check" class="ff-grouping-check" />
                        </x-slot>
                    @endif
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
