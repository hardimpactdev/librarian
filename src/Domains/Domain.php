<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Domains;

final readonly class Domain
{
    public function __construct(
        public int $order,
        public string $slug,
        public string $directoryName,
        public string $path,
    ) {}

    public function title(): string
    {
        return (string) str($this->slug)
            ->replace('-', ' ')
            ->title();
    }

    public function landingPageName(): string
    {
        return "{$this->slug}.md";
    }
}
