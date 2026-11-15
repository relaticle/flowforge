@props(['columnId', 'cardLabel', 'disabled' => false])

@php
    use Filament\Support\Enums\IconSize;

    $createAction = $this->getBoardCreateCardAction($columnId)?->disabled($disabled);
@endphp

@if ($createAction?->isVisible())
    @php
        $isDisabled = $createAction->isDisabled();
    @endphp
    <button
        type="button"
        class="ff-add-row"
        wire:click="mountAction(@js($createAction->getName()), @js(['column' => (string) $columnId]))"
        @disabled($isDisabled)
        @if ($disabled) x-tooltip="{ content: @js(__('flowforge::flowforge.limit_reached')), theme: $store.theme }" @endif
    >
        <x-filament::icon :alias="\Relaticle\Flowforge\View\FlowforgeIconAlias::ADD_CARD" icon="heroicon-m-plus" :size="IconSize::Small" class="ff-add-row-icon" />
        {{ __('flowforge::flowforge.add_card', ['card' => $cardLabel]) }}
    </button>
@endif
