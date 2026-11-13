<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

class TestCollapsibleBoard extends TestBoard
{
    public bool $collapsible = true;

    public bool $withCreateAction = true;

    public bool $withColumnActions = true;

    public array $collapsedIds = ['completed'];
}
