<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Domains;

use HardImpact\Librarian\Docs\DocsFilesystem;

final readonly class DomainRepository
{
    private const string DIRECTORY_PATTERN = '/^(?<order>[1-9][0-9]*)_(?<slug>[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)$/';

    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    /**
     * @return array<int, Domain>
     */
    public function all(): array
    {
        $domainsPath = $this->filesystem->docsPath('domains');

        if (! is_dir($domainsPath)) {
            return [];
        }

        $domains = [];

        foreach (scandir($domainsPath) ?: [] as $directory) {
            if (in_array($directory, ['.', '..'], true)) {
                continue;
            }

            if (! is_dir("{$domainsPath}/{$directory}")) {
                continue;
            }

            if (! preg_match(self::DIRECTORY_PATTERN, $directory, $matches)) {
                continue;
            }

            $domains[] = new Domain(
                order: (int) $matches['order'],
                slug: $matches['slug'],
                directoryName: $directory,
                path: "{$domainsPath}/{$directory}",
            );
        }

        usort($domains, static fn (Domain $left, Domain $right): int => $left->order <=> $right->order);

        return $domains;
    }

    public function findBySlug(string $slug): ?Domain
    {
        foreach ($this->all() as $domain) {
            if ($domain->slug !== $slug) {
                continue;
            }

            return $domain;
        }

        return null;
    }
}
