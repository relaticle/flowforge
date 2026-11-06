@props(['columnId', 'record', 'laneId' => null])

@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Relaticle\Flowforge\Components\CardTitle;
    use function Filament\Support\generate_href_html;

    $board = $this->getBoard();
    $recordActions = $board->getBoardRecordActions($record);
    $link = $board->getCardLink($recordActions);
    $visibleRecordActions = array_filter($recordActions, fn (Action | ActionGroup $action): bool => $action->isVisible());
    $href = $link['url'] ? generate_href_html($link['url'], $link['openInNewTab'], hasNestedClickEventHandler: true) : null;
    $mountAction = $link['action'];
    $isDraggable = $board->getPositionIdentifierAttribute() !== null;
    $hasCardTitle = $record['schema'] !== null
        && collect($record['schema']->getFlatComponents())->contains(fn (mixed $component): bool => $component instanceof CardTitle);
@endphp

<div
    @class([
        'ff-card',
        'ff-clickable' => ! $isDraggable && ($href || $mountAction),
    ])
    wire:key="card-{{ $laneId ?? 'board' }}-{{ $record['id'] }}"
    x-sortable-item="{{ $record['id'] }}"
    data-card-id="{{ $record['id'] }}"
    @if($isDraggable) x-sortable-handle @endif
    data-position="{{ $record['position'] ?? '' }}"
>
    @if (count($visibleRecordActions) > 0)
        <div class="ff-card-actions">
            <x-filament-actions::group :actions="$visibleRecordActions" />
        </div>
    @endif

    <div
        @class(['ff-card-body', 'ff-clickable' => $mountAction])
        @if($mountAction) wire:click="mountAction('{{ $mountAction }}', [], @js(['recordKey' => $record['id']]))" @endif
    >
        @unless ($hasCardTitle)
            <h4 class="ff-card-title">
                @if ($href)
                    <a {{ $href }} class="ff-card-link">{{ $record['title'] }}</a>
                @else
                    {{ $record['title'] }}
                @endif
            </h4>
        @endunless

        @if (filled($record['schema']))
            @if ($href)
                <a {{ $href }} class="ff-card-link">{{ $record['schema'] }}</a>
            @else
                {{ $record['schema'] }}
            @endif
        @endif
    </div>
</div>
