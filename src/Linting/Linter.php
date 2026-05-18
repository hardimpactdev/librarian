<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

use HardImpact\Librarian\Linting\Rules\CoreDocsStructureRule;
use HardImpact\Librarian\Linting\Rules\DomainDirectoriesRule;
use HardImpact\Librarian\Linting\Rules\DomainLandingPageRule;
use HardImpact\Librarian\Linting\Rules\GeneratedDocsAreCurrentRule;
use HardImpact\Librarian\Linting\Rules\LocalMarkdownLinksRule;
use HardImpact\Librarian\Linting\Rules\NoScaffoldPromptTextRule;
use HardImpact\Librarian\Linting\Rules\RequiredSpineRule;
use InvalidArgumentException;

final readonly class Linter
{
    public function __construct(
        private RequiredSpineRule $spine,
        private CoreDocsStructureRule $coreDocsStructure,
        private DomainDirectoriesRule $domainDirectories,
        private DomainLandingPageRule $domainLandingPage,
        private GeneratedDocsAreCurrentRule $generatedDocsAreCurrent,
        private LocalMarkdownLinksRule $localMarkdownLinks,
        private NoScaffoldPromptTextRule $noScaffoldPromptText,
    ) {}

    public function lint(?LintOptions $options = null): LintResult
    {
        $options ??= new LintOptions;
        $findings = [];

        foreach ($this->rules() as $rule) {
            if (! $options->includesRule($rule)) {
                continue;
            }

            array_push($findings, ...$rule->check());
        }

        $findings = array_values(array_filter(
            $findings,
            static fn (Finding $finding): bool => $options->includesFinding($finding),
        ));

        usort(
            $findings,
            static fn (Finding $left, Finding $right): int => [$left->path, $left->line ?? 0, $left->rule, $left->message]
                <=> [$right->path, $right->line ?? 0, $right->rule, $right->message],
        );

        return new LintResult($findings);
    }

    /**
     * @return list<Rule>
     */
    private function rules(): array
    {
        $rules = [
            $this->spine,
            $this->coreDocsStructure,
            $this->domainDirectories,
            $this->domainLandingPage,
            $this->generatedDocsAreCurrent,
            $this->localMarkdownLinks,
            $this->noScaffoldPromptText,
        ];

        foreach ((array) config('librarian.rules', []) as $rule) {
            $rules[] = $this->resolveConfiguredRule($rule);
        }

        return $rules;
    }

    private function resolveConfiguredRule(mixed $rule): Rule
    {
        if ($rule instanceof Rule) {
            return $rule;
        }

        if (is_string($rule)) {
            return $this->resolveRuleClass($rule);
        }

        if (is_array($rule)) {
            return $this->resolveRuleDefinition($rule);
        }

        throw new InvalidArgumentException('Configured Librarian rules must implement '.Rule::class.'.');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveRuleDefinition(array $definition): Rule
    {
        $rule = $definition['rule'] ?? null;
        $options = $definition['options'] ?? [];

        if (! is_string($rule)) {
            throw new InvalidArgumentException('Configured Librarian rule definitions must include a rule class string.');
        }

        if (! is_array($options)) {
            throw new InvalidArgumentException('Configured Librarian rule options must be an array.');
        }

        return $this->resolveRuleClass($rule, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveRuleClass(string $rule, array $options = []): Rule
    {
        $instance = $options === []
            ? app($rule)
            : app()->makeWith($rule, ['options' => $options]);

        if (! $instance instanceof Rule) {
            throw new InvalidArgumentException('Configured Librarian rules must implement '.Rule::class.'.');
        }

        return $instance;
    }
}
