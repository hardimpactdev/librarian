<?php

declare(strict_types=1);

use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;

use function Pest\Laravel\artisan;

uses(CreatesDocsFixtures::class);

beforeEach(function (): void {
    $this->deletePath('docs');
});

it('initializes the required docs spine with scaffold content', function (): void {
    artisan('librarian:init')
        ->expectsOutput('Librarian docs initialized.')
        ->expectsOutput('')
        ->expectsOutput("You can now start documenting your application. Whenever you're ready run `php artisan librarian:build` to ensure structural consistency in your documentation.")
        ->assertSuccessful();

    expect(is_dir($this->docsPath()))->toBeTrue()
        ->and(is_dir($this->docsPath().'/domains'))->toBeTrue()
        ->and($this->readFile('docs/README.md'))->toBe(
            "# Documentation\n\n1. [Mission](mission.md)\n2. [Architecture](architecture.md)\n3. [Tech Stack](tech-stack.md)\n4. [Concepts](concepts.md)\n\n## Domains\n\n"
        )
        ->and($this->readFile('docs/mission.md'))->toBe(
            "# Mission\n\nDescribe what this project is and does in a few sentences.\n\n## Why\n\nDescribe why this project exists and the problem it solves.\n\n## How\n\nDescribe the product-level approach this project uses to solve the problem.\n\n## What\n\nDescribe the product or system being built and its core capabilities briefly.\n\nContinue to [Architecture](architecture.md) for the high-level system shape, or [Tech Stack](tech-stack.md) for concrete implementation choices.\n\n## Boundaries\n\nDescribe what this project intentionally does not do or accepts as a trade-off.\n"
        )
        ->and($this->readFile('docs/architecture.md'))->toBe(
            "# Architecture\n\nDescribe the high-level system design without naming specific technologies unless a technology is part of the product concept itself.\n\n## Components\n\nDescribe the main application components in technology-agnostic terms.\n\n## Relationships\n\nDescribe how the components relate to each other without coupling the design to specific tools.\n\n## State\n\nDescribe where important state lives at a system level.\n\n## Boundaries\n\nDescribe the boundaries each component must respect.\n"
        )
        ->and($this->readFile('docs/tech-stack.md'))->toBe(
            "# Tech Stack\n\nDescribe the concrete technologies, frameworks, protocols, services, and infrastructure choices used to implement the architecture.\n\n## Runtime\n\nDescribe the runtime environment.\n\n## Frameworks\n\nDescribe the frameworks and libraries the project relies on.\n\n## Storage\n\nDescribe the storage systems the project uses.\n\n## Infrastructure\n\nDescribe the infrastructure the project needs to run.\n"
        )
        ->and($this->readFile('docs/concepts.md'))->toBe(
            "# Concepts\n\n| Concept | Domain | Details |\n| --- | --- | --- |\n"
        );
});

it('fails without writing files when any target path already exists', function (): void {
    $this->writeFile('docs/README.md', '# Existing documentation');

    artisan('librarian:init')
        ->expectsOutput('Cannot initialize Librarian docs because target paths already exist:')
        ->expectsOutput('- docs/README.md')
        ->assertFailed();

    expect($this->readFile('docs/README.md'))->toBe('# Existing documentation')
        ->and(is_dir($this->docsPath().'/domains'))->toBeFalse();
});

it('lists every conflicting target path before aborting', function (): void {
    $this->writeFile('docs/README.md', '# Existing documentation');
    $this->writeFile('docs/mission.md', '# Existing mission');
    mkdir($this->docsPath().'/domains', 0777, true);

    artisan('librarian:init')
        ->expectsOutput('Cannot initialize Librarian docs because target paths already exist:')
        ->expectsOutput('- docs/README.md')
        ->expectsOutput('- docs/mission.md')
        ->expectsOutput('- docs/domains')
        ->assertFailed();

    expect($this->readFile('docs/README.md'))->toBe('# Existing documentation')
        ->and($this->readFile('docs/mission.md'))->toBe('# Existing mission')
        ->and(is_file($this->docsPath().'/architecture.md'))->toBeFalse()
        ->and(is_file($this->docsPath().'/tech-stack.md'))->toBeFalse()
        ->and(is_file($this->docsPath().'/concepts.md'))->toBeFalse();
});
