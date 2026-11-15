<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;

class TestNumericGroupedBoard extends TestGroupedBoard
{
    public array $collapsedIds = ['1'];

    public function board(Board $board): Board
    {
        return parent::board($board)->columns([
            Column::make('1')->label('First'),
            Column::make('2')->label('Second'),
        ]);
    }
}
