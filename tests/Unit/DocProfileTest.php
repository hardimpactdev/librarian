<?php

declare(strict_types=1);

use HardImpact\Librarian\Markdown\DocProfile;

it('treats the root concepts index as technical documentation', function (): void {
    expect(DocProfile::fromPath('concepts.md'))->toBe(DocProfile::Technical);
});
