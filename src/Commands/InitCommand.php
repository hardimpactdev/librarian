<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Commands;

use HardImpact\Librarian\Docs\DocsFilesystem;
use Illuminate\Console\Command;

final class InitCommand extends Command
{
    protected $signature = 'librarian:init';

    protected $description = 'Create the Librarian documentation spine';

    public function handle(DocsFilesystem $filesystem): int
    {
        $conflicts = $this->existingTargets(
            $filesystem,
            [
                ...array_keys($this->fileTargets()),
                ...$this->directoryTargets(),
            ],
        );

        if ($conflicts !== []) {
            $this->line('Cannot initialize Librarian docs because target paths already exist:');

            foreach ($conflicts as $conflict) {
                $this->line("- docs/{$conflict}");
            }

            return self::FAILURE;
        }

        if (! is_dir($filesystem->docsPath())) {
            mkdir($filesystem->docsPath(), 0777, true);
        }

        foreach ($this->directoryTargets() as $path) {
            $absolutePath = $filesystem->docsPath($path);

            mkdir($absolutePath, 0777, true);
        }

        foreach ($this->fileTargets() as $path => $contents) {
            $absolutePath = $filesystem->docsPath($path);

            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0777, true);
            }

            file_put_contents($absolutePath, $contents);
        }

        $this->line('Librarian docs initialized.');
        $this->line('');
        $this->line("You can now start documenting your application. Whenever you're ready run `php artisan librarian:build` to ensure structural consistency in your documentation.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function fileTargets(): array
    {
        return [
            'README.md' => "# Documentation\n\n1. [Mission](mission.md)\n2. [Architecture](architecture.md)\n3. [Tech Stack](tech-stack.md)\n4. [Concepts](concepts.md)\n\n## Domains\n\n",
            'mission.md' => "# Mission\n\nDescribe what this project is and does in a few sentences.\n\n## Why\n\nDescribe why this project exists and the problem it solves.\n\n## How\n\nDescribe the product-level approach this project uses to solve the problem.\n\n## What\n\nDescribe the product or system being built and its core capabilities briefly.\n\nContinue to [Architecture](architecture.md) for the high-level system shape, or [Tech Stack](tech-stack.md) for concrete implementation choices.\n\n## Boundaries\n\nDescribe what this project intentionally does not do or accepts as a trade-off.\n",
            'architecture.md' => "# Architecture\n\nDescribe the high-level system design without naming specific technologies unless a technology is part of the product concept itself.\n\n## Components\n\nDescribe the main application components in technology-agnostic terms.\n\n## Relationships\n\nDescribe how the components relate to each other without coupling the design to specific tools.\n\n## State\n\nDescribe where important state lives at a system level.\n\n## Boundaries\n\nDescribe the boundaries each component must respect.\n",
            'tech-stack.md' => "# Tech Stack\n\nDescribe the concrete technologies, frameworks, protocols, services, and infrastructure choices used to implement the architecture.\n\n## Runtime\n\nDescribe the runtime environment.\n\n## Frameworks\n\nDescribe the frameworks and libraries the project relies on.\n\n## Storage\n\nDescribe the storage systems the project uses.\n\n## Infrastructure\n\nDescribe the infrastructure the project needs to run.\n",
            'concepts.md' => "# Concepts\n\n| Concept | Domain | Details |\n| --- | --- | --- |\n",
        ];
    }

    /**
     * @return list<string>
     */
    private function directoryTargets(): array
    {
        return [
            'domains',
        ];
    }

    /**
     * @param  list<string>  $targets
     * @return list<string>
     */
    private function existingTargets(DocsFilesystem $filesystem, array $targets): array
    {
        $conflicts = [];

        foreach ($targets as $target) {
            if (! file_exists($filesystem->docsPath($target))) {
                continue;
            }

            $conflicts[] = $target;
        }

        return $conflicts;
    }
}
