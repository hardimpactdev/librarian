<?php

declare(strict_types=1);

require_once __DIR__.'/Support/DomainScaffolderFunctions.php';
require_once __DIR__.'/Support/DomainRenameFunctions.php';

use HardImpact\Librarian\Tests\Support\DomainRenameHook;
use HardImpact\Librarian\Tests\Support\DomainScaffolderFileWriteHook;
use HardImpact\Librarian\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

beforeEach(function (): void {
    DomainScaffolderFileWriteHook::reset();
    DomainRenameHook::reset();
});
