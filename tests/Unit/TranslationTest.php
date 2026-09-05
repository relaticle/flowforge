<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('the German empty column hint keeps the card label capitalised', function () {
    app()->setLocale('de');

    $html = Blade::render('<x-flowforge::empty-column :pluralCardLabel="$label" />', [
        'label' => __('flowforge::flowforge.plural_card_label'),
    ]);

    expect($html)->toContain('Keine Datensätze in dieser Spalte');
});
