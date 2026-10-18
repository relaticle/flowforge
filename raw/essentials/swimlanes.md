---
title: "Swimlanes"
description: "Group a board into lanes by assignee, priority or any other field."
canonical_url: "https://relaticle.github.io/essentials/swimlanes"
---
# Swimlanes

> Group a board into lanes by assignee, priority or any other field.

<callout type="info">

Swimlanes work on Modern boards only. A classic board ignores `groupings()`. See [Modern board](https://relaticle.github.io/essentials/integration-patterns#modern-board).

</callout>

## Group by

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Lane;

->groupings([
    Grouping::attribute('priority'),

    Grouping::make('assignee')
        ->label('Assignee')
        ->lanes(fn (Builder $boardQuery): array => User::query()
            ->whereIn('id', DB::table('task_user')
                ->whereIn('task_id', (clone $boardQuery)->select('tasks.id'))
                ->select('user_id'))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): Lane => Lane::make($user->id)
                ->label($user->name)
                ->avatar($user->avatar_url)
                ->query(fn (Builder $query): Builder => $query->whereHas(
                    'assignees',
                    fn (Builder $assignees): Builder => $assignees->whereKey($user->id),
                )))
            ->all())
        ->emptyLane('Unassigned', fn (Builder $query): Builder => $query->whereDoesntHave('assignees'))
        ->moveUsing(function (Task $card, Lane $from, Lane $to): void {
            if ($from->getName() !== Grouping::EMPTY_LANE) {
                $card->assignees()->detach($from->getName());
            }

            if ($to->getName() !== Grouping::EMPTY_LANE) {
                $card->assignees()->syncWithoutDetaching([$to->getName()]);
            }
        }),
])
```

Defining groupings adds a "Group by" button to the board. It names the active grouping and opens a menu of every grouping. It sits above the board under the filters. With `headerToolbar()` it moves into the page header, next to the filters and search. A standalone board has no page header, so with `headerToolbar()` it shows no picker, the same as its filters.

The choice lives in the URL as `?groupBy=assignee`, so a grouped view can be shared. Choosing "None" returns to the plain board.

A click on a lane's header collapses that lane, and another click opens it again. The chevron in front of the label is the keyboard control for the same toggle. Collapsed lanes stay collapsed while the board updates and open again on a reload.

## Lanes

- `lanes()` receives the board query with filters and search applied. Name the parameter `$boardQuery`, or type it as the Eloquent builder. Return the lanes in the order to show them.
- A `Lane` takes a name, which must be unique within the grouping, and a `query()` closure that narrows the board query to that lane. It also takes `label()`, `avatar()`, `color()` and `icon()`.
- `emptyLane()` adds a last lane for cards that match none of the others. The name `Grouping::EMPTY_LANE` identifies it in `moveUsing()`.
- `Grouping::attribute('priority')` builds one lane per distinct value, busiest first, plus an empty lane for `null`. A backed enum cast supplies the labels and colors when it implements Filament's `HasLabel` and `HasColor`.
- A card that matches two lanes appears in both. The move callback receives the lane the card left, so only that link changes.
- A lane with no cards in view is not shown.
- Ten lanes render at first, and "Show more lanes" loads ten more. Change the page size with `->lanesPerPage()`. Each cell shows `cardsPerColumn()` cards, then "Show N more".
- Each rendered lane loads its cards in one query, using window functions.

The paging state (`$visibleLaneCount`, `$cellCardLimits` and `$columnCardLimits`) is `#[Locked]`, so the browser cannot write it. `loadMoreItems()` clamps the count it loads into a lane cell to between 1 and `cardsPerColumn()`.

## Creating cards in lane mode

The header `+` from `createCardAction()` creates a card without knowing the lane. The card lands in whichever lane its data matches. A custom grouping without `emptyLane()` hides a card that matches no lane, so add `emptyLane()` to every custom grouping. Lane cells have no add row.

## Moving between lanes

- With `moveUsing()`, a drop into another lane runs the callback in the same transaction as the column move. Throw `CardMoveRejectedException::because('...')` to refuse. Both writes roll back, the user sees the message, and the card returns.
- The callback receives the card as `$card` or `$record`, or typed by its model class, plus the lane it left as `$from` and the lane it entered as `$to`.
- `Grouping::attribute()` writes the attribute itself, and writes `null` for the empty lane. An attribute qualified with another table's name, such as `'details.priority'`, is read-only.
- Without `moveUsing()`, the lanes are read-only. Cards move between columns inside their lane, the browser blocks a drop into another lane, and the server refuses it with a message.
- The server checks that the card sits in the lane it left, and refuses the move when the board has changed underneath the user.

## Limits and collapsed columns

- A column limit counts the whole column across every lane, and the header is shared.
- `collapseEmptyColumns()` applies to the plain board only. In lanes every column keeps its width, so every cell stays a drop target.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
