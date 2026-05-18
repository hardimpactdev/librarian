<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Markdown;

enum DocProfile: string
{
    case ReaderFacing = 'reader_facing';
    case Technical = 'technical';

    public static function fromPath(string $relativePath): self
    {
        $filename = strtolower(basename($relativePath));

        $technical = str_contains($relativePath, '/technical/')
            || str_contains($relativePath, '/abstractions/')
            || $filename === 'concepts.md'
            || $filename === 'rules.md';

        return $technical ? self::Technical : self::ReaderFacing;
    }
}
