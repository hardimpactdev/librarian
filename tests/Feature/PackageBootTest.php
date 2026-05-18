<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers package config and commands', function (): void {
    expect(config('librarian'))->toHaveKeys(['path', 'rules']);
    expect(config('librarian.path'))->toBe($this->app->basePath('docs'));

    expect(array_keys(Artisan::all()))->toContain(
        'librarian:build',
        'librarian:domain',
        'librarian:domains:normalize',
        'librarian:init',
        'librarian:lint',
    );
});
