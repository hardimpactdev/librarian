<?php

declare(strict_types=1);

use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;

use function Pest\Laravel\artisan;

uses(CreatesDocsFixtures::class);

describe('librarian:build', function (): void {
    beforeEach(function (): void {
        $this->deletePath('docs');
    });

    it('generates docs and passes when lint succeeds', function (): void {
        writeBuildDocsFixture($this);

        artisan('librarian:build')
            ->expectsOutput('Lint passed.')
            ->assertSuccessful();

        expect($this->readFile('docs/README.md'))->toContain('[Mission](mission.md)')
            ->and($this->readFile('docs/concepts.md'))->toContain('| Node | Node |');
    });

    it('generates docs and then reports lint findings', function (): void {
        writeBuildDocsFixture($this);
        $this->writeFile(
            'docs/domains/1_node/node.md',
            "# Node\n\n## Purpose\n\nDescribe the purpose.\n\n## Responsibilities\n\nTODO\n\n## Boundaries\n\nTBD\n"
        );
        $this->writeFile('docs/README.md', '# stale');

        artisan('librarian:build')
            ->expectsOutputToContain('librarian.scaffold_text docs/domains/1_node/node.md')
            ->assertFailed();

        expect($this->readFile('docs/README.md'))->toContain('[Mission](mission.md)');
    });
});

function writeBuildDocsFixture(object $test): void
{
    writeBuildDocsFixtureFile($test, 'docs/domains/1_node/node.md',
        "# Node\n\n## Purpose\n\nNode purpose.\n\n## Responsibilities\n\nNode responsibilities.\n\n## Boundaries\n\nNode boundaries.\n"
    );
    writeBuildDocsFixtureFile($test,
        'docs/domains/1_node/concepts.md',
        "# Node Concepts\n\n## Node\n\nA machine registered with the project.\n"
    );
    writeBuildDocsFixtureFile($test,
        'docs/mission.md',
        "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
    );
    writeBuildDocsFixtureFile($test,
        'docs/architecture.md',
        "# Architecture\n\n## Components\n\nSee [Node](domains/1_node/node.md).\n\n## Relationships\n\nNode connects to runtime concerns.\n\n## State\n\nState lives in the app.\n\n## Boundaries\n\nBoundaries are explicit.\n"
    );
    writeBuildDocsFixtureFile($test,
        'docs/tech-stack.md',
        "# Tech Stack\n\n## Runtime\n\nPHP 8.4.\n\n## Frameworks\n\nLaravel.\n\n## Storage\n\nPostgres.\n\n## Infrastructure\n\nQueues and workers.\n"
    );
}

function writeBuildDocsFixtureFile(object $test, string $path, string $contents): void
{
    $basePath = dirname((string) config('librarian.path'));
    $fullPath = $basePath.'/'.$path;

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0777, true);
    }

    file_put_contents($fullPath, $contents);
}
