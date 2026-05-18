<?php

declare(strict_types=1);

use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;
use HardImpact\Librarian\Tests\Support\DomainRenameHook;
use HardImpact\Librarian\Tests\Support\DomainScaffolderFileWriteHook;

use function Pest\Laravel\artisan;

uses(CreatesDocsFixtures::class);

beforeEach(function (): void {
    DomainScaffolderFileWriteHook::reset();
    DomainRenameHook::reset();

    $this->deletePath('docs');

    mkdir($this->docsPath().'/domains', 0777, true);
});

it('appends a domain with the next number', function (): void {
    artisan('librarian:domain node')
        ->expectsOutput('Domain `node` created.')
        ->expectsOutput('')
        ->expectsOutput('Document the domain, then run `php artisan librarian:build` to update generated documentation and check structural consistency.')
        ->assertSuccessful();

    expect($this->readFile('docs/domains/1_node/node.md'))->toBe(
        "# Node\n\n## Purpose\n\n## Responsibilities\n\n## Boundaries\n"
    );
});

it('inserts a domain before another domain and rewrites docs links', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    mkdir($this->docsPath().'/domains/2_app', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");
    $this->writeFile('docs/domains/2_app/app.md', "# App\n");
    $this->writeFile('docs/architecture.md', '[App](domains/2_app/app.md)');

    artisan('librarian:domain gateway --before=app')->assertSuccessful();

    expect(is_dir($this->docsPath().'/domains/2_gateway'))->toBeTrue()
        ->and(is_dir($this->docsPath().'/domains/3_app'))->toBeTrue()
        ->and($this->readFile('docs/architecture.md'))->toBe('[App](domains/3_app/app.md)');
});

it('inserts a domain after another domain and regenerates docs', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    mkdir($this->docsPath().'/domains/2_app', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");
    $this->writeFile('docs/domains/1_node/concepts.md', "# Concepts\n\n## Node\n");
    $this->writeFile('docs/domains/2_app/app.md', "# App\n");
    $this->writeFile('docs/domains/2_app/concepts.md', "# Concepts\n\n## App\n");
    $this->writeFile('docs/README.md', '# stale');
    $this->writeFile('docs/concepts.md', '# stale');
    $this->writeFile('docs/architecture.md', '[App](domains/2_app/app.md)');

    artisan('librarian:domain gateway --after=node')->assertSuccessful();

    expect(is_dir($this->docsPath().'/domains/1_node'))->toBeTrue()
        ->and(is_dir($this->docsPath().'/domains/2_gateway'))->toBeTrue()
        ->and(is_dir($this->docsPath().'/domains/3_app'))->toBeTrue()
        ->and($this->readFile('docs/architecture.md'))->toBe('[App](domains/3_app/app.md)')
        ->and($this->readFile('docs/README.md'))->toContain('domains/2_gateway/gateway.md');
});

it('rejects using before and after together', function (): void {
    artisan('librarian:domain gateway --before=node --after=app')
        ->expectsOutput('The --before and --after options cannot be used together.')
        ->assertFailed();
});

it('matches before and after targets by slug only', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");

    artisan('librarian:domain gateway --before=1_node')
        ->expectsOutput('Domain [1_node] does not exist.')
        ->assertFailed();
});

it('rejects invalid slugs', function (): void {
    artisan('librarian:domain Not_Valid')
        ->expectsOutput('The domain slug must be lowercase kebab-case.')
        ->assertFailed();
});

it('rejects duplicate slugs', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");

    artisan('librarian:domain node')
        ->expectsOutput('Domain [node] already exists.')
        ->assertFailed();
});

it('rejects missing before and after targets', function (): void {
    artisan('librarian:domain gateway --after=node')
        ->expectsOutput('Domain [node] does not exist.')
        ->assertFailed();
});

it('rewrites markdown docs and regenerates generated docs after insertion', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    mkdir($this->docsPath().'/domains/2_app', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");
    $this->writeFile('docs/domains/1_node/concepts.md', "# Concepts\n\n## Node\n");
    $this->writeFile('docs/domains/2_app/app.md', "# App\n");
    $this->writeFile('docs/domains/2_app/concepts.md', "# Concepts\n\n## App\n");
    $this->writeFile('docs/README.md', '# stale');
    $this->writeFile('docs/concepts.md', '# stale');
    $this->writeFile('docs/architecture.md', '[App](domains/2_app/app.md)');

    artisan('librarian:domain gateway --before=app')->assertSuccessful();

    expect($this->readFile('docs/README.md'))->toContain('domains/1_node/node.md', 'domains/2_gateway/gateway.md', 'domains/3_app/app.md')
        ->and($this->readFile('docs/concepts.md'))->toContain('domains/1_node/concepts.md#node', 'domains/3_app/concepts.md#app')
        ->and($this->readFile('docs/architecture.md'))->toBe('[App](domains/3_app/app.md)');
});

it('does not leave the new domain directory behind if renumbering fails before scaffold', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    mkdir($this->docsPath().'/domains/2_app', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");
    $this->writeFile('docs/domains/2_app/app.md', "# App\n");
    chmod($this->docsPath().'/domains', 0555);

    try {
        artisan('librarian:domain gateway --after=node')
            ->assertFailed();
    } finally {
        chmod($this->docsPath().'/domains', 0777);
    }

    expect(is_dir($this->docsPath().'/domains/2_gateway'))->toBeFalse()
        ->and(is_dir($this->docsPath().'/domains/1_node'))->toBeTrue()
        ->and(is_dir($this->docsPath().'/domains/2_app'))->toBeTrue();
});

it('restores rewritten markdown when generated docs writing fails after link rewrite', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    mkdir($this->docsPath().'/domains/2_app', 0777, true);
    mkdir($this->docsPath().'/README.md', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");
    $this->writeFile('docs/domains/2_app/app.md', "# App\n");
    $this->writeFile('docs/architecture.md', '[App](domains/2_app/app.md)');

    artisan('librarian:domain gateway --before=app')
        ->assertFailed();

    expect($this->readFile('docs/architecture.md'))->toBe('[App](domains/2_app/app.md)')
        ->and(is_dir($this->docsPath().'/domains/2_gateway'))->toBeFalse()
        ->and(is_dir($this->docsPath().'/domains/2_app'))->toBeTrue()
        ->and(is_dir($this->docsPath().'/domains/3_app'))->toBeFalse();
});

it('restores markdown even when inverse renumber rollback fails after generated docs writing fails', function (): void {
    mkdir($this->docsPath().'/domains/1_node', 0777, true);
    mkdir($this->docsPath().'/domains/2_app', 0777, true);
    mkdir($this->docsPath().'/README.md', 0777, true);
    $this->writeFile('docs/domains/1_node/node.md', "# Node\n");
    $this->writeFile('docs/domains/2_app/app.md', "# App\n");
    $this->writeFile('docs/architecture.md', '[App](domains/2_app/app.md)');

    DomainRenameHook::$callback = static function (string $from, string $to): bool {
        if (str_contains($from, '/docs/domains/__tmp__') && str_ends_with($to, '/docs/domains/2_app')) {
            return false;
        }

        return \rename($from, $to);
    };

    artisan('librarian:domain gateway --before=app')
        ->assertFailed();

    expect($this->readFile('docs/architecture.md'))->toBe('[App](domains/2_app/app.md)')
        ->and(is_dir($this->docsPath().'/domains/2_gateway'))->toBeFalse();
});

it('removes the new domain directory when scaffolding fails after creating it', function (): void {
    DomainScaffolderFileWriteHook::$callback = static function (string $filename, string $contents): int|false {
        if (str_ends_with($filename, '/domains/1_node/node.md')) {
            return false;
        }

        return file_put_contents($filename, $contents);
    };

    artisan('librarian:domain node')
        ->expectsOutputToContain('Unable to write domain landing page')
        ->assertFailed();

    expect(is_dir($this->docsPath().'/domains/1_node'))->toBeFalse();
});
