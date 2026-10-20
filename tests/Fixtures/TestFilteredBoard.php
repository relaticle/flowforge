<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Relaticle\Flowforge\Board;

class TestFilteredBoard extends TestBoard
{
    public string $filtersLayout = 'Dropdown';

    public function board(Board $board): Board
    {
        return parent::board($board)->filters(
            [SelectFilter::make('priority')->options(['low' => 'Low', 'high' => 'High'])],
            constant(FiltersLayout::class . '::' . $this->filtersLayout),
        );
    }
}
