<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @see https://github.com/relaticle/flowforge/issues/204
 */
test('a view registered without the panel builder compiles', function (string $path) {
    expect(Blade::compileString(file_get_contents($path)))->toBeString();
})->with(fn (): array => collect(Finder::create()->files()->in(__DIR__ . '/../../resources/views'))
    ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getRelativePathname() => [$file->getPathname()]])
    ->all());
