<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

class TestCollapsedAllBoard extends TestCollapsibleBoard
{
    public bool $collapseAll = true;

    public array $collapsedIds = [];
}
