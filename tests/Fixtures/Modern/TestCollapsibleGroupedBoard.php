<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

class TestCollapsibleGroupedBoard extends TestGroupedBoard
{
    public bool $collapsible = true;

    public array $collapsedIds = ['in_progress'];

    public ?int $inProgressLimit = 20;
}
