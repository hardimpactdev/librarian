<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

final readonly class Finding
{
    public function __construct(
        public string $path,
        public ?int $line,
        public FindingSeverity $severity,
        public string $rule,
        public string $message,
    ) {}

    /**
     * @return array{path: string, line: int|null, severity: string, rule: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'line' => $this->line,
            'severity' => $this->severity->value,
            'rule' => $this->rule,
            'message' => $this->message,
        ];
    }
}
