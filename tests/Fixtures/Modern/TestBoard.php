<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Exceptions\CardMoveRejectedException;
use Relaticle\Flowforge\Modern\BoardPage;

class TestBoard extends BoardPage
{
    public bool $showAllColumns = false;

    public bool $hideEverything = false;

    public bool $collapseEmpty = false;

    public array $collapsedIds = [];

    public bool $collapseAll = false;

    public ?string $collapsedIdsAs = null;

    public bool $collapsible = false;

    public bool $withColumnActions = false;

    public bool $withHeaderMenu = false;

    public bool $nullMenuBlock = false;

    public array $toggled = [];

    public array $toggledAlone = [];

    public bool $withAddColumn = false;

    public array $addedColumns = [];

    public array $addedColumnColors = [];

    public bool $withAddColumnColors = false;

    public ?string $addColumnDefault = null;

    public ?string $rejectColumnName = null;

    public ?string $addColumnTitle = null;

    public ?string $addColumnHint = null;

    public bool $withHeaderToolbar = false;

    public bool $withCreateAction = false;

    public bool $hideCreateAction = false;

    public bool $denyCreateAction = false;

    public bool $withSummary = false;

    public bool $nullSummary = false;

    public bool $namedSummary = false;

    public bool $htmlLabel = false;

    public bool $hexCompleted = false;

    public ?int $todoLimit = null;

    public ?string $denyMovesFor = null;

    public bool $recordPersist = false;

    public bool $failNextPersist = false;

    public int $lockWaitSeconds = 5;

    public bool $leftJoinState = false;

    public ?string $onlyPriority = null;

    public function getEloquentQuery(): Builder
    {
        return Task::query()
            ->when($this->leftJoinState, fn (Builder $query): Builder => $query
                ->leftJoin('task_states', 'task_states.task_id', '=', 'tasks.id')
                ->select('tasks.*', 'task_states.state_value'))
            ->when($this->onlyPriority, fn (Builder $query, string $priority): Builder => $query->where('tasks.priority', $priority));
    }

    public function board(Board $board): Board
    {
        return $board
            ->collapseEmptyColumns($this->collapseEmpty)
            ->collapsedColumns(match ($this->collapsedIdsAs) {
                'collection' => fn () => collect($this->collapsedIds),
                'enum' => fn (): array => [StatusEnum::Active, ...$this->collapsedIds],
                'closure' => fn (): array => $this->collapsedIds,
                default => $this->collapsedIds,
            })
            ->collapseAllColumns($this->collapseAll)
            ->collapsibleColumns($this->collapsible
                ? function (string $columnId, bool $collapsed, bool $alone): void {
                    $this->toggled[] = [$columnId, $collapsed];
                    $this->toggledAlone = $alone ? [...$this->toggledAlone, $columnId] : $this->toggledAlone;
                    $this->collapsedIds = $collapsed
                        ? [...$this->collapsedIds, $columnId]
                        : array_values(array_diff($this->collapsedIds, [$columnId]));
                }
                : null)
            ->addColumnUsing($this->withAddColumn ? $this->addColumnClosure(...) : null)
            ->addColumnColors($this->withAddColumnColors ? ['#ff5454' => 'Red', 'blue' => 'Blue'] : null)
            ->addColumnDefaultColor($this->addColumnDefault)
            ->addColumnLabel($this->addColumnTitle)
            ->addColumnPlaceholder($this->addColumnHint)
            ->headerToolbar($this->withHeaderToolbar)
            ->columnActions(match (true) {
                $this->withHeaderMenu => [Action::make('archiveColumn')->label('Archive column')->action(function (): void {})],
                $this->withColumnActions => [Action::make('renameColumn')->action(function (): void {}), Action::make('clearColumn')->action(function (): void {})],
                default => [],
            })
            ->columnHeaderMenu($this->withHeaderMenu
                ? fn (array $column): ?HtmlString => $this->nullMenuBlock
                    ? null
                    : new HtmlString('<div class="ff-test-menu-block">Block for ' . $column['label'] . '<input class="ff-test-menu-input" size="7" value="Backlog" data-autofocus></div>')
                : null)
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->searchable(['title'])
            ->createCardAction($this->withCreateAction
                ? CreateAction::make('createTask')
                    ->model(Task::class)
                    ->visible(! $this->hideCreateAction)
                    ->authorize(fn () => $this->denyCreateAction ? Response::deny('Not allowed') : true)
                    ->authorizationTooltip()
                    ->schema([TextInput::make('title')->required()])
                    ->mutateDataUsing(fn (array $data, array $arguments): array => [
                        ...$data,
                        'status' => $arguments['column'] ?? 'todo',
                        'order_position' => $this->getBoardPositionInColumn($arguments['column'] ?? 'todo', 'bottom'),
                    ])
                : null)
            ->columnSummary(match (true) {
                $this->nullSummary => fn (): ?string => null,
                $this->namedSummary => fn ($query, Column $column): string => $column->getName() . ':' . $query->count(),
                $this->withSummary => fn (Builder $columnQuery): string => $columnQuery->count() . ' shown',
                default => null,
            })
            ->columnIdentifier('status')
            ->positionIdentifier('order_position')
            ->columns([
                Column::make('todo')->label('To Do')->color('gray')->limit($this->todoLimit)->visible(fn () => ! $this->hideEverything),
                Column::make('hidden_column')->visible(fn () => $this->showAllColumns && ! $this->hideEverything)->label('Hidden Column'),
                Column::make('archived')->label('Archived Column')->hidden(true),
                Column::make('in_progress')->label($this->htmlLabel ? new HtmlString('<em>In Progress</em>') : 'In Progress')->color('blue')->visible(fn () => ! $this->hideEverything),
                Column::make('completed')->label('Completed')->color($this->hexCompleted ? '#FF5454' : 'green')->visible(fn () => ! $this->hideEverything),
                ...array_map(fn (string $name): Column => Column::make($name)->label($name), $this->addedColumns),
            ]);
    }

    protected function addColumnClosure(string $name, ?string $color): void
    {
        if ($name === $this->rejectColumnName) {
            throw ValidationException::withMessages(['name' => 'Taken']);
        }

        $this->addedColumns[] = $name;
        $this->addedColumnColors[] = $color;
    }

    protected function authorizeCardMove(Model $card, string $targetColumnId, ?string $toLaneId = null): void
    {
        abort_if($card->getAttribute('title') === $this->denyMovesFor, 403);
    }

    protected function persistCardMove(Model $card, mixed $columnValue, string $position): void
    {
        if ($this->failNextPersist) {
            $this->failNextPersist = false;

            throw CardMoveRejectedException::because('Boom');
        }

        parent::persistCardMove($card, $columnValue, $position);

        if ($this->recordPersist) {
            $card->update(['priority' => 'moved']);
        }
    }

    protected function getColumnLockWaitSeconds(): int
    {
        return $this->lockWaitSeconds;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportBoard')->label('Export board'),
        ];
    }
}
