<?php

declare(strict_types=1);

use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;
use HardImpact\Librarian\Tests\Support\DomainRenameHook;

use function Pest\Laravel\artisan;

uses(CreatesDocsFixtures::class);

describe('librarian:domains:normalize', function (): void {
    beforeEach(function (): void {
        $this->deletePath('docs');
        DomainRenameHook::reset();
    });

    it('normalizes domain numbers rewrites links and regenerates generated docs', function (): void {
        mkdir($this->docsPath().'/domains/2_node', 0777, true);
        mkdir($this->docsPath().'/domains/4_app', 0777, true);
        $this->writeFile(
            'docs/domains/2_node/node.md',
            "# Node\n\n## Purpose\n\nNodes.\n\n## Responsibilities\n\nHandle machines.\n\n## Boundaries\n\nNo apps.\n"
        );
        $this->writeFile(
            'docs/domains/2_node/concepts.md',
            "# Node Concepts\n\n## Node\n\nNodes.\n"
        );
        $this->writeFile(
            'docs/domains/4_app/app.md',
            "# App\n\n## Purpose\n\nApps.\n\n## Responsibilities\n\nOwn apps.\n\n## Boundaries\n\nNo nodes.\n"
        );
        $this->writeFile(
            'docs/domains/4_app/concepts.md',
            "# App Concepts\n\n## App\n\nApps.\n"
        );
        $this->writeFile(
            'docs/mission.md',
            "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
        );
        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n[App](domains/4_app/app.md)\n\n## Relationships\n\n[Node](domains/2_node/node.md)\n\n## State\n\nState lives here.\n\n## Boundaries\n\nBoundaries live here.\n"
        );
        $this->writeFile(
            'docs/tech-stack.md',
            "# Tech Stack\n\n## Runtime\n\nPHP 8.4.\n\n## Frameworks\n\nLaravel.\n\n## Storage\n\nPostgres.\n\n## Infrastructure\n\nQueues and workers.\n"
        );

        artisan('librarian:domains:normalize')
            ->expectsOutput('Lint passed.')
            ->assertSuccessful();

        expect(is_dir($this->docsPath().'/domains/1_node'))->toBeTrue()
            ->and(is_dir($this->docsPath().'/domains/2_app'))->toBeTrue()
            ->and(is_dir($this->docsPath().'/domains/2_node'))->toBeFalse()
            ->and(is_dir($this->docsPath().'/domains/4_app'))->toBeFalse()
            ->and($this->readFile('docs/architecture.md'))->toContain('domains/2_app/app.md')
            ->and($this->readFile('docs/README.md'))->toContain('domains/1_node/node.md', 'domains/2_app/app.md')
            ->and($this->readFile('docs/concepts.md'))->toContain('domains/1_node/concepts.md#node', 'domains/2_app/concepts.md#app');
    });

    it('returns failure after normalization when lint still finds documentation issues', function (): void {
        mkdir($this->docsPath().'/domains/2_node', 0777, true);
        mkdir($this->docsPath().'/domains/4_app', 0777, true);
        $this->writeFile(
            'docs/domains/2_node/node.md',
            "# Node\n\n## Purpose\n\nDescribe the purpose.\n\n## Responsibilities\n\nTODO\n\n## Boundaries\n\nTBD\n"
        );
        $this->writeFile(
            'docs/domains/4_app/app.md',
            "# App\n\n## Purpose\n\nApps.\n\n## Responsibilities\n\nOwn apps.\n\n## Boundaries\n\nNo nodes.\n"
        );
        $this->writeFile(
            'docs/mission.md',
            "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
        );
        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n[App](domains/4_app/app.md)\n\n## Relationships\n\nNode and app.\n\n## State\n\nState lives here.\n\n## Boundaries\n\nBoundaries live here.\n"
        );
        $this->writeFile(
            'docs/tech-stack.md',
            "# Tech Stack\n\n## Runtime\n\nPHP 8.4.\n\n## Frameworks\n\nLaravel.\n\n## Storage\n\nPostgres.\n\n## Infrastructure\n\nQueues and workers.\n"
        );

        artisan('librarian:domains:normalize')
            ->expectsOutputToContain('librarian.scaffold_text docs/domains/1_node/node.md')
            ->assertFailed();

        expect(is_dir($this->docsPath().'/domains/1_node'))->toBeTrue()
            ->and(is_dir($this->docsPath().'/domains/2_app'))->toBeTrue()
            ->and($this->readFile('docs/architecture.md'))->toContain('domains/2_app/app.md');
    });

    it('restores original domain numbering when generated docs writing fails after renumbering', function (): void {
        mkdir($this->docsPath().'/domains/2_node', 0777, true);
        mkdir($this->docsPath().'/domains/4_app', 0777, true);
        mkdir($this->docsPath().'/README.md', 0777, true);
        $this->writeFile(
            'docs/domains/2_node/node.md',
            "# Node\n\n## Purpose\n\nNodes.\n\n## Responsibilities\n\nHandle machines.\n\n## Boundaries\n\nNo apps.\n"
        );
        $this->writeFile(
            'docs/domains/4_app/app.md',
            "# App\n\n## Purpose\n\nApps.\n\n## Responsibilities\n\nOwn apps.\n\n## Boundaries\n\nNo nodes.\n"
        );
        $this->writeFile(
            'docs/mission.md',
            "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
        );
        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n[App](domains/4_app/app.md)\n\n## Relationships\n\n[Node](domains/2_node/node.md)\n\n## State\n\nState lives here.\n\n## Boundaries\n\nBoundaries live here.\n"
        );
        $this->writeFile(
            'docs/tech-stack.md',
            "# Tech Stack\n\n## Runtime\n\nPHP 8.4.\n\n## Frameworks\n\nLaravel.\n\n## Storage\n\nPostgres.\n\n## Infrastructure\n\nQueues and workers.\n"
        );

        artisan('librarian:domains:normalize')
            ->assertFailed();

        expect(is_dir($this->docsPath().'/domains/2_node'))->toBeTrue()
            ->and(is_dir($this->docsPath().'/domains/4_app'))->toBeTrue()
            ->and(is_dir($this->docsPath().'/domains/1_node'))->toBeFalse()
            ->and(is_dir($this->docsPath().'/domains/2_app'))->toBeFalse()
            ->and($this->readFile('docs/architecture.md'))->toContain('domains/4_app/app.md', 'domains/2_node/node.md');
    });

    it('restores markdown even when inverse renumber rollback fails after generated docs writing fails', function (): void {
        mkdir($this->docsPath().'/domains/2_node', 0777, true);
        mkdir($this->docsPath().'/domains/4_app', 0777, true);
        mkdir($this->docsPath().'/README.md', 0777, true);
        $this->writeFile(
            'docs/domains/2_node/node.md',
            "# Node\n\n## Purpose\n\nNodes.\n\n## Responsibilities\n\nHandle machines.\n\n## Boundaries\n\nNo apps.\n"
        );
        $this->writeFile(
            'docs/domains/4_app/app.md',
            "# App\n\n## Purpose\n\nApps.\n\n## Responsibilities\n\nOwn apps.\n\n## Boundaries\n\nNo nodes.\n"
        );
        $this->writeFile(
            'docs/mission.md',
            "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
        );
        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n[App](domains/4_app/app.md)\n\n## Relationships\n\n[Node](domains/2_node/node.md)\n\n## State\n\nState lives here.\n\n## Boundaries\n\nBoundaries live here.\n"
        );
        $this->writeFile(
            'docs/tech-stack.md',
            "# Tech Stack\n\n## Runtime\n\nPHP 8.4.\n\n## Frameworks\n\nLaravel.\n\n## Storage\n\nPostgres.\n\n## Infrastructure\n\nQueues and workers.\n"
        );

        DomainRenameHook::$callback = static function (string $from, string $to): bool {
            if (str_contains($from, '/docs/domains/__tmp__') && str_ends_with($to, '/docs/domains/4_app')) {
                return false;
            }

            return \rename($from, $to);
        };

        artisan('librarian:domains:normalize')
            ->assertFailed();

        expect($this->readFile('docs/architecture.md'))
            ->toContain('domains/4_app/app.md', 'domains/2_node/node.md');
    });
});
