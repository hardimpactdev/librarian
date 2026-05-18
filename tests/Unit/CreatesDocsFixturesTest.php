<?php

declare(strict_types=1);

use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;

uses(CreatesDocsFixtures::class);

it('writes and reads docs fixtures relative to the application base path', function (): void {
    $this->writeFile('docs/overview.md', '# Overview');

    expect($this->readFile('docs/overview.md'))->toBe('# Overview');
});
