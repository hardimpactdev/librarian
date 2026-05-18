<?php

declare(strict_types=1);

use HardImpact\Librarian\Docs\DocsConfig;
use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Domains\Domain;
use HardImpact\Librarian\Domains\DomainRenumberer;
use HardImpact\Librarian\Domains\DomainRepository;
use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;
use HardImpact\Librarian\Tests\Support\DomainRenameHook;

uses(CreatesDocsFixtures::class);

beforeEach(function (): void {
    $docsPath = docsRoot();

    if (is_dir($docsPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }
    }
});

describe('DomainRepository', function (): void {
    it('discovers domains in numeric order when directories are unsorted on disk', function (): void {
        mkdir(docsRoot('domains/20_advanced-topics'), 0777, true);
        mkdir(docsRoot('domains/2_quick-start'), 0777, true);
        mkdir(docsRoot('domains/10_reference'), 0777, true);

        $domains = app(DomainRepository::class)->all();

        expect($domains)
            ->toHaveCount(3)
            ->and(array_map(static fn (Domain $domain): string => $domain->directoryName, $domains))
            ->toBe([
                '2_quick-start',
                '10_reference',
                '20_advanced-topics',
            ])
            ->and(array_map(static fn (Domain $domain): int => $domain->order, $domains))
            ->toBe([2, 10, 20]);
    });

    it('finds a domain by slug without requiring the numeric prefix', function (): void {
        mkdir(docsRoot('domains/12_api-reference'), 0777, true);

        $domain = app(DomainRepository::class)->findBySlug('api-reference');

        expect($domain)
            ->not->toBeNull()
            ->slug->toBe('api-reference')
            ->directoryName->toBe('12_api-reference')
            ->path->toBe(docsRoot('domains/12_api-reference'))
            ->title()->toBe('Api Reference')
            ->landingPageName()->toBe('api-reference.md');
    });

    it('ignores invalid domain directories', function (): void {
        mkdir(docsRoot('domains/3_valid-domain'), 0777, true);
        mkdir(docsRoot('domains/01_invalid-leading-zero'), 0777, true);
        mkdir(docsRoot('domains/not-a-domain'), 0777, true);
        mkdir(docsRoot('domains/5_invalid_underscore'), 0777, true);

        $domains = app(DomainRepository::class)->all();

        expect($domains)
            ->toHaveCount(1)
            ->and($domains[0]->slug)->toBe('valid-domain');
    });

    it('fails fast when a planned domain source directory is missing during renumbering', function (): void {
        mkdir(docsRoot('domains/1_node'), 0777, true);

        expect(fn () => app(DomainRenumberer::class)->apply([
            '2_app' => '3_app',
        ]))->toThrow(RuntimeException::class, 'Missing planned domain directory');
    });

    it('restores moved domain directories when renumbering fails partway through', function (): void {
        mkdir(docsRoot('domains/1_node'), 0777, true);
        mkdir(docsRoot('domains/2_app'), 0777, true);

        expect(fn () => app(DomainRenumberer::class)->apply([
            '1_node' => '2_node',
            '3_missing' => '4_missing',
        ]))->toThrow(RuntimeException::class, 'Missing planned domain directory');

        expect(is_dir(docsRoot('domains/1_node')))->toBeTrue()
            ->and(is_dir(docsRoot('domains/2_app')))->toBeTrue()
            ->and(is_dir(docsRoot('domains/2_node')))->toBeFalse();

        $domainEntries = array_values(array_filter(
            scandir(docsRoot('domains')) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
        ));

        expect(array_filter(
            $domainEntries,
            static fn (string $entry): bool => str_starts_with($entry, '__tmp__'),
        ))->toBe([]);
    });

    it('restores finalized domain directories when the second rename phase fails partway through', function (): void {
        mkdir(docsRoot('domains/1_node'), 0777, true);
        mkdir(docsRoot('domains/2_app'), 0777, true);

        DomainRenameHook::$callback = static function (string $from, string $to): bool {
            $normalizedFrom = str_replace('\\', '/', $from);
            $normalizedTo = str_replace('\\', '/', $to);

            if (str_contains($normalizedFrom, '/docs/domains/__tmp__') && str_ends_with($normalizedTo, '/docs/domains/4_app')) {
                return false;
            }

            return \rename($from, $to);
        };

        expect(fn () => app(DomainRenumberer::class)->apply([
            '1_node' => '3_node',
            '2_app' => '4_app',
        ]))->toThrow(RuntimeException::class, 'Unable to rename domain directory');

        expect(is_dir(docsRoot('domains/1_node')))->toBeTrue()
            ->and(is_dir(docsRoot('domains/2_app')))->toBeTrue()
            ->and(is_dir(docsRoot('domains/3_node')))->toBeFalse()
            ->and(is_dir(docsRoot('domains/4_app')))->toBeFalse();

        $domainEntries = array_values(array_filter(
            scandir(docsRoot('domains')) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
        ));

        expect(array_filter(
            $domainEntries,
            static fn (string $entry): bool => str_starts_with($entry, '__tmp__') || str_starts_with($entry, '__rollback__'),
        ))->toBe([]);
    });
});

describe('DocsConfig', function (): void {
    it('reads the configured docs path and trims trailing slashes', function (): void {
        config()->set('librarian.path', '/tmp/project-docs///');

        $config = DocsConfig::fromConfig();

        expect($config->path)->toBe('/tmp/project-docs');
    });

    it('rejects an empty configured docs path', function (): void {
        config()->set('librarian.path', '///');

        expect(fn () => DocsConfig::fromConfig())
            ->toThrow(InvalidArgumentException::class, 'The librarian.path config value must not be empty.');
    });
});

describe('DocsFilesystem', function (): void {
    it('builds absolute docs paths and relative paths', function (): void {
        $filesystem = new DocsFilesystem(new DocsConfig(docsRoot()));

        expect($filesystem->docsPath())->toBe(docsRoot())
            ->and($filesystem->docsPath('domains/2_quick-start'))->toBe(docsRoot('domains/2_quick-start'))
            ->and($filesystem->relativePath(docsRoot('domains/2_quick-start/api-reference.md')))
            ->toBe('domains/2_quick-start/api-reference.md');
    });

    it('normalizes windows separators when building relative paths', function (): void {
        $filesystem = new DocsFilesystem(new DocsConfig('C:\\project\\docs'));

        expect($filesystem->docsPath('domains\\2_quick-start'))->toBe('C:/project/docs/domains/2_quick-start')
            ->and($filesystem->relativePath('C:\\project\\docs\\domains\\2_quick-start\\api-reference.md'))
            ->toBe('domains/2_quick-start/api-reference.md');
    });

    it('rejects paths outside the docs root when building a relative path', function (): void {
        $filesystem = new DocsFilesystem(new DocsConfig(docsRoot()));

        expect(fn () => $filesystem->relativePath('/tmp/outside-docs/api-reference.md'))
            ->toThrow(InvalidArgumentException::class, 'Path [/tmp/outside-docs/api-reference.md] is not inside the docs root ['.docsRoot().'].');
    });
});

function docsRoot(string $path = ''): string
{
    $root = str_replace('\\', '/', (string) config('librarian.path'));

    if ($path === '') {
        return $root;
    }

    return "{$root}/{$path}";
}
