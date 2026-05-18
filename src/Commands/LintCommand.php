<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Commands;

use HardImpact\Librarian\Commands\Concerns\InteractsWithLintOutput;
use HardImpact\Librarian\Docs\DocsConfig;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\Linter;
use HardImpact\Librarian\Linting\LintOptions;
use HardImpact\Librarian\Linting\LintResult;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class LintCommand extends Command
{
    use InteractsWithLintOutput;

    protected $signature = 'librarian:lint
        {--format=text : Output format: text, json, or agent}
        {--strict : Treat warnings as failures}
        {--path= : Limit findings to a path below the docs directory}
        {--group= : Limit linting to a rule group}';

    protected $description = 'Lint Librarian documentation without writing files';

    public function handle(Linter $linter, DocsConfig $docsConfig): int
    {
        $format = (string) $this->option('format');
        $strict = (bool) $this->option('strict');
        $group = $this->groupOption();

        if ($group === false) {
            return self::FAILURE;
        }

        try {
            $options = new LintOptions(
                path: $this->pathOption($docsConfig),
                group: $group,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = $linter->lint($options);

        if ($format === 'json') {
            $this->line($this->jsonOutput($result, $strict));

            return $result->passed($strict) ? self::SUCCESS : self::FAILURE;
        }

        if ($format === 'agent') {
            $this->line($this->agentOutput($result, $strict));

            return $result->passed($strict) ? self::SUCCESS : self::FAILURE;
        }

        if ($format !== 'text') {
            $this->error("Unsupported lint format [{$format}]. Supported formats: text, json, agent.");

            return self::FAILURE;
        }

        $this->renderLintResult($result);

        return $result->passed($strict) ? self::SUCCESS : self::FAILURE;
    }

    private function jsonOutput(LintResult $result, bool $strict): string
    {
        try {
            return json_encode([
                'tool' => 'librarian',
                'result' => $result->passed($strict) ? 'passed' : 'failed',
                'issues' => count($result->findings),
                'errors' => count($result->errors()),
                'warnings' => count($result->warnings()),
                'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $result->findings),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode lint output as JSON.', previous: $exception);
        }
    }

    private function groupOption(): string|false|null
    {
        $group = $this->option('group');

        if (! is_string($group) || trim($group) === '') {
            return null;
        }

        $group = trim($group);

        if (LintOptions::supportsGroup($group)) {
            return $group;
        }

        $this->error("Unsupported lint group [{$group}]. Supported groups: ".LintOptions::supportedGroups().'.');

        return false;
    }

    private function pathOption(DocsConfig $docsConfig): ?string
    {
        $path = $this->option('path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return $this->normalizePath($path, $docsConfig);
    }

    private function normalizePath(string $path, DocsConfig $docsConfig): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = preg_replace('#^\./#', '', $path) ?: $path;
        $path = rtrim($path, '/');

        if (str_starts_with($path, '/')) {
            return $this->normalizeAbsolutePath($path, $docsConfig);
        }

        if ($path === 'docs' || str_starts_with($path, 'docs/')) {
            return $path;
        }

        return 'docs/'.ltrim($path, '/');
    }

    private function normalizeAbsolutePath(string $path, DocsConfig $docsConfig): string
    {
        $docsRoot = str_replace('\\', '/', rtrim($docsConfig->path, '/'));

        if ($path === $docsRoot) {
            return 'docs';
        }

        if (str_starts_with($path, "{$docsRoot}/")) {
            return 'docs/'.ltrim(substr($path, strlen($docsRoot)), '/');
        }

        throw new InvalidArgumentException('Lint path must be inside the configured docs path.');
    }

    private function agentOutput(LintResult $result, bool $strict): string
    {
        $payload = [
            'tool' => 'librarian',
            'result' => $result->passed($strict) ? 'passed' : 'failed',
            'issues' => count($result->findings),
            'errors' => count($result->errors()),
            'warnings' => count($result->warnings()),
        ];

        if ($result->findings !== []) {
            $payload['findings'] = array_map(
                static fn (Finding $finding): array => self::agentFinding($finding),
                $result->findings,
            );
        }

        try {
            return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode lint output for agents.', previous: $exception);
        }
    }

    /**
     * @return array{path: string, line?: int, severity: string, rule: string, message: string}
     */
    private static function agentFinding(Finding $finding): array
    {
        $payload = [
            'path' => $finding->path,
            'severity' => $finding->severity->value,
            'rule' => $finding->rule,
            'message' => $finding->message,
        ];

        if ($finding->line !== null) {
            $payload['line'] = $finding->line;
        }

        return $payload;
    }
}
