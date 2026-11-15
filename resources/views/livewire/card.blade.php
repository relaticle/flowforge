@props(['columnId', 'record', 'config' => []])

@php
    $processedRecordActions = $this->getBoard()->getBoardRecordActions($record);
    $hasActions = !empty($processedRecordActions);
    $cardAction = $this->getBoard()->getCardAction();
    $hasCardAction = $cardAction !== null;
    $hasPositionIdentifier = $this->getBoard()->getPositionIdentifierAttribute() !== null;

    // A card action configured with a url must navigate like a native link instead of
    // mounting a modal, so middle-click, cmd-click and "copy link" keep working.
    //
    // The rule is Filament's own, taken from ListRecords::table(): a url on the action
    // decides. There, recordAction() skips any action whose getUrl() is filled and
    // recordUrl() takes it instead, with no check of the action's modal state. An
    // action carrying both a url and a confirmation therefore renders as a plain link
    // in core too. Mirroring that keeps a board card and a table row behaving the same
    // way for the same action, which matters more than second-guessing the config.
    //
    // Only POST urls are excluded: those need a form rather than an anchor.
    $cardActionInstance = $this->getBoard()->resolveCardAction($processedRecordActions);
    $cardActionUrl = $cardActionInstance?->shouldPostToUrl()
        ? null
        : $cardActionInstance?->getUrl();
    $hasCardActionUrl = filled($cardActionUrl);
    $cardActionHref = $hasCardActionUrl
        ? \Filament\Support\generate_href_html(
            $cardActionUrl,
            $cardActionInstance->shouldOpenUrlInNewTab(),
            hasNestedClickEventHandler: true,
        )
        : null;
@endphp

<div
    {{ $attributes->class([
        'flowforge-card mb-3 bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden transition-all duration-200 hover:shadow-md',
        'cursor-pointer' => $hasActions || $hasCardAction,
        'cursor-pointer transition-all duration-100 ease-in-out hover:shadow-lg hover:border-gray-400 active:shadow-md' => $hasCardAction,
        'cursor-grab hover:cursor-grabbing' => $hasPositionIdentifier,
        'cursor-default' => !$hasActions && !$hasCardAction && !$hasPositionIdentifier,
    ]) }}
    x-sortable-item="{{ $record['id'] }}"
    data-card-id="{{ $record['id'] }}"
    @if($hasPositionIdentifier)
        x-sortable-handle
    @endif
    data-position="{{ $record['position'] ?? '' }}"
>
    <div class="flowforge-card-content">
        <div class="flex items-start justify-between mb-2">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white p-3"
                @if($hasCardAction && $cardAction && !$hasCardActionUrl)
                    wire:click="mountAction('{{ $cardAction }}', [], @js(['recordKey' => $record['id']]))"
                style="cursor: pointer;"
                @endif
            >
                @if($hasCardActionUrl)
                    <a {{ $cardActionHref }} class="block">
                        {{ $record['title'] }}
                    </a>
                @else
                    {{ $record['title'] }}
                @endif
            </h4>

            @if($hasActions)
                <div class="m-3">
                    <x-filament-actions::group :actions="$processedRecordActions"/>
                </div>
            @endif
        </div>

        <div class="px-3 pb-3"
             @if($hasCardAction && $cardAction && !$hasCardActionUrl)
                 wire:click="mountAction('{{ $cardAction }}', [], @js(['recordKey' => $record['id']]))"
             style="cursor: pointer;"
            @endif
        >
            {{-- Render card schema with compact spacing --}}
            @if(filled($record['schema']))
                @if($hasCardActionUrl)
                    <a {{ $cardActionHref }} class="block">
                        {{ $record['schema'] }}
                    </a>
                @else
                    {{ $record['schema'] }}
                @endif
            @endif
        </div>
    </div>
</div>
