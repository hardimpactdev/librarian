<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Generation;

use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Domains\Domain;
use HardImpact\Librarian\Domains\DomainRepository;

final readonly class ConceptsIndexGenerator
{
    public function __construct(
        private DocsFilesystem $filesystem,
        private DomainRepository $domains,
    ) {}

    public function render(): string
    {
        $lines = [
            '# Concepts',
            '',
            '| Concept | Domain | Details |',
            '| --- | --- | --- |',
        ];

        foreach ($this->domains->all() as $domain) {
            foreach ($this->conceptsForDomain($domain) as $concept) {
                $lines[] = sprintf(
                    '| %s | %s | [%s concepts](domains/%s/concepts.md#%s) |',
                    $concept,
                    $domain->title(),
                    $domain->title(),
                    $domain->directoryName,
                    $this->anchorFor($concept),
                );
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function conceptsForDomain(Domain $domain): array
    {
        $path = $this->filesystem->docsPath("domains/{$domain->directoryName}/concepts.md");

        if (! is_file($path)) {
            return [];
        }

        $concepts = [];

        foreach (preg_split('/\R/', file_get_contents($path) ?: '') ?: [] as $line) {
            if (! preg_match('/^\s*##\s+(.+?)\s*#*\s*$/u', $line, $matches)) {
                continue;
            }

            $concept = preg_replace('/\s+/u', ' ', trim($matches[1])) ?? '';

            if ($concept === '') {
                continue;
            }

            $concepts[] = $concept;
        }

        return $concepts;
    }

    private function anchorFor(string $heading): string
    {
        $anchor = mb_strtolower($heading);
        $anchor = preg_replace('/\s+/u', ' ', trim($anchor)) ?? '';
        $anchor = preg_replace('/[^a-z0-9 -]/u', '', $anchor) ?? '';
        $anchor = preg_replace('/ +/u', '-', $anchor) ?? '';
        $anchor = preg_replace('/-+/u', '-', $anchor) ?? '';

        return trim($anchor, '-');
    }
}
