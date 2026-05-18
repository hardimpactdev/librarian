<?php

declare(strict_types=1);

use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;
use HardImpact\Librarian\Linting\Rules\CompoundNounStackRule;
use HardImpact\Librarian\Linting\Rules\DocumentComplexityRule;
use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\artisan;

uses(CreatesDocsFixtures::class);

describe('librarian:lint', function (): void {
    beforeEach(function (): void {
        $this->deletePath('docs');
        config()->set('librarian.rules', []);
    });

    it('passes for valid docs', function (): void {
        writeValidDocs($this);

        artisan('librarian:lint')
            ->expectsOutput('Lint passed.')
            ->assertSuccessful();
    });

    it('allows core docs to include subsections inside required sections', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n### Gateway\n\nThe gateway owns durable configuration.\n\n### Node\n\nNodes run project workloads.\n\n## Relationships\n\nComponents communicate over explicit edges.\n\n## State\n\nState lives in durable stores.\n\n## Boundaries\n\nComponents respect their owning boundaries.\n"
        );

        artisan('librarian:lint')
            ->expectsOutput('Lint passed.')
            ->assertSuccessful();
    });

    it('fails when generated docs are stale', function (): void {
        writeValidDocs($this);
        $this->writeFile('docs/README.md', '# stale');

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.generated_docs docs/README.md')
            ->assertFailed();
    });

    it('fails when domain numbering is not contiguous', function (): void {
        writeValidDocs($this);

        rename($this->docsPath().'/domains/2_app', $this->docsPath().'/domains/3_app');

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.domain_directories docs/domains/3_app')
            ->assertFailed();
    });

    it('fails when a local markdown link target is missing and skips external links images and anchors', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n[Broken](domains/2_app/missing.md)\n\n## Relationships\n\n[External](https://example.com/docs)\n[Mail](mailto:test@example.com)\n[Anchor](#components)\n[Custom](obsidian://open)\n![Image](domains/1_node/diagram.png)\n\n## State\n\nState lives here.\n\n## Boundaries\n\nBoundaries live here.\n"
        );

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.links docs/architecture.md:5 Local markdown link target [domains/2_app/missing.md] does not exist.')
            ->assertFailed();
    });

    it('passes when a local markdown link includes a title', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/architecture.md',
            "# Architecture\n\n## Components\n\n[Node](domains/1_node/node.md \"Node\")\n\n## Relationships\n\nSee [App](domains/2_app/app.md#responsibilities).\n\n## State\n\nState lives in the app.\n\n## Boundaries\n\nBoundaries are explicit.\n"
        );

        artisan('librarian:lint')
            ->expectsOutput('Lint passed.')
            ->assertSuccessful();
    });

    it('fails when scaffold prompt text or placeholders remain in non-generated docs', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/domains/1_node/node.md',
            "# Node\n\n## Purpose\n\nDescribe the purpose.\n\n## Responsibilities\n\nTODO\n\n## Boundaries\n\nTBD\n"
        );

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.scaffold_text docs/domains/1_node/node.md')
            ->assertFailed();
    });

    it('fails when a required core docs section is empty', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/mission.md',
            "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
        );

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.core_docs_structure docs/mission.md:9 The required section [How] is empty.')
            ->assertFailed();
    });

    it('fails when the mission summary is empty', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/mission.md',
            "# Mission\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
        );

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.core_docs_structure docs/mission.md:1 Mission must include a summary between the H1 and the Why section.')
            ->assertFailed();
    });

    it('fails when a required domain section is empty', function (): void {
        writeValidDocs($this);

        $this->writeFile(
            'docs/domains/1_node/node.md',
            "# Node\n\n## Purpose\n\nNode purpose.\n\n## Responsibilities\n\n\n## Boundaries\n\nNode boundaries.\n\n## Notes\n\nExtra sections are allowed.\n"
        );

        artisan('librarian:lint')
            ->expectsOutputToContain('librarian.domain_landing_page docs/domains/1_node/node.md:7 The required section [Responsibilities] is empty.')
            ->assertFailed();
    });

    it('includes additive project rules from class names and instances in json output', function (): void {
        writeValidDocs($this);

        config()->set('librarian.rules', [
            TestLintRuleFromContainer::class,
            new class implements Rule
            {
                public function check(): array
                {
                    return [
                        new Finding(
                            path: 'docs/domains/2_app/app.md',
                            line: 7,
                            severity: FindingSeverity::Error,
                            rule: 'project.instance_rule',
                            message: 'Instance rule failed.',
                        ),
                    ];
                }
            },
        ]);

        $exitCode = Artisan::call('librarian:lint', ['--format' => 'json']);
        $output = Artisan::output();
        $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(1)
            ->and($payload['tool'])->toBe('librarian')
            ->and($payload['result'])->toBe('failed')
            ->and($payload['issues'])->toBe(2)
            ->and(array_column($payload['findings'], 'rule'))->toBe([
                'project.container_rule',
                'project.instance_rule',
            ]);
    });

    it('passes options to configured rule definitions', function (): void {
        writeValidDocs($this);

        config()->set('librarian.rules', [
            [
                'rule' => TestConfigurableLintRuleFromContainer::class,
                'options' => [
                    'message' => 'Configured rule failed.',
                ],
            ],
        ]);

        $exitCode = Artisan::call('librarian:lint', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(1)
            ->and($payload['issues'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'rule' => 'project.configurable_rule',
                'message' => 'Configured rule failed.',
            ]);
    });

    it('ships document complexity as an optional warning rule with threshold options', function (): void {
        writeValidDocs($this);
        writeDocsFixtureFile(
            $this,
            'docs/domains/1_node/node.md',
            "# Node\n\nThis sentence deliberately crosses the configured threshold for reader facing prose.\n",
        );

        $disabledExitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--path' => 'docs/domains/1_node/node.md',
            '--group' => 'complexity',
        ]);
        $disabledPayload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($disabledExitCode)->toBe(0)
            ->and($disabledPayload['issues'])->toBe(0);

        config()->set('librarian.rules', [
            [
                'rule' => DocumentComplexityRule::class,
                'options' => [
                    'reader_facing' => [
                        'max_sentence_words' => 5,
                        'max_paragraph_words' => 999,
                        'max_bullet_words' => 999,
                        'max_lix' => 999.0,
                    ],
                ],
            ],
        ]);

        $exitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--path' => 'docs/domains/1_node/node.md',
            '--group' => 'complexity',
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/domains/1_node/node.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.document_complexity',
            ])
            ->and($payload['findings'][0]['message'])->toContain('Sentence has');
    });

    it('ships compound noun stacks as an optional warning rule with accepted compound options', function (): void {
        writeValidDocs($this);
        writeDocsFixtureFile(
            $this,
            'docs/domains/1_node/node.md',
            "# Node\n\nThe alpha-beta control plane gateway is visible.\n",
        );

        $disabledExitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--path' => 'docs/domains/1_node/node.md',
            '--group' => 'prose',
        ]);
        $disabledPayload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($disabledExitCode)->toBe(0)
            ->and($disabledPayload['issues'])->toBe(0);

        config()->set('librarian.rules', [
            CompoundNounStackRule::class,
        ]);

        $exitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--path' => 'docs/domains/1_node/node.md',
            '--group' => 'prose',
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/domains/1_node/node.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.compound_noun_stack',
                'message' => 'Compound noun phrase "alpha-beta control plane gateway" stacks 3 modifiers before the head noun. Decompose into a sentence ("X that is Y by Z") instead.',
            ]);

        config()->set('librarian.rules', [
            [
                'rule' => CompoundNounStackRule::class,
                'options' => [
                    'accepted_compounds' => [
                        'alpha-beta',
                    ],
                ],
            ],
        ]);

        $acceptedExitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--path' => 'docs/domains/1_node/node.md',
            '--group' => 'prose',
        ]);
        $acceptedPayload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($acceptedExitCode)->toBe(0)
            ->and($acceptedPayload['issues'])->toBe(0);
    });

    it('does not fail on warnings unless strict mode is enabled', function (): void {
        writeValidDocs($this);

        config()->set('librarian.rules', [
            new class implements Rule
            {
                public function check(): array
                {
                    return [
                        new Finding(
                            path: 'docs/domains/1_node/node.md',
                            line: 3,
                            severity: FindingSeverity::Warning,
                            rule: 'project.warning_rule',
                            message: 'Warning rule failed.',
                        ),
                    ];
                }
            },
        ]);

        $exitCode = Artisan::call('librarian:lint');
        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('[warning] project.warning_rule docs/domains/1_node/node.md:3 Warning rule failed.')
            ->and($output)->toContain('1 Librarian lint issue(s) found: 0 error(s), 1 warning(s).');

        $strictExitCode = Artisan::call('librarian:lint', ['--strict' => true]);

        expect($strictExitCode)->toBe(1);
    });

    it('renders agent output with severity counts', function (): void {
        writeValidDocs($this);

        config()->set('librarian.rules', [
            new class implements Rule
            {
                public function check(): array
                {
                    return [
                        new Finding(
                            path: 'docs/domains/1_node/node.md',
                            line: 3,
                            severity: FindingSeverity::Warning,
                            rule: 'project.warning_rule',
                            message: 'Warning rule failed.',
                        ),
                    ];
                }
            },
        ]);

        $exitCode = Artisan::call('librarian:lint', ['--format' => 'agent']);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload)->toMatchArray([
                'tool' => 'librarian',
                'result' => 'passed',
                'issues' => 1,
                'errors' => 0,
                'warnings' => 1,
                'findings' => [
                    [
                        'path' => 'docs/domains/1_node/node.md',
                        'line' => 3,
                        'severity' => 'warning',
                        'rule' => 'project.warning_rule',
                        'message' => 'Warning rule failed.',
                    ],
                ],
            ]);
    });

    it('scopes lint findings to a requested docs path', function (): void {
        writeValidDocs($this);

        config()->set('librarian.rules', [
            new class implements Rule
            {
                public function check(): array
                {
                    return [
                        new Finding(
                            path: 'docs/domains/1_node/node.md',
                            line: 3,
                            severity: FindingSeverity::Error,
                            rule: 'project.node_rule',
                            message: 'Node rule failed.',
                        ),
                        new Finding(
                            path: 'docs/domains/2_app/app.md',
                            line: 3,
                            severity: FindingSeverity::Error,
                            rule: 'project.app_rule',
                            message: 'App rule failed.',
                        ),
                    ];
                }
            },
        ]);

        $exitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--path' => 'domains/1_node',
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(1)
            ->and($payload['issues'])->toBe(1)
            ->and($payload['findings'][0]['rule'])->toBe('project.node_rule');
    });

    it('only runs rules in the requested lint group', function (): void {
        writeValidDocs($this);

        config()->set('librarian.rules', [
            new class implements GroupedRule
            {
                public function group(): string
                {
                    return 'structure';
                }

                public function check(): array
                {
                    return [
                        new Finding(
                            path: 'docs/domains/1_node/node.md',
                            line: 3,
                            severity: FindingSeverity::Error,
                            rule: 'project.structure_rule',
                            message: 'Structure rule failed.',
                        ),
                    ];
                }
            },
            new class implements GroupedRule
            {
                public function group(): string
                {
                    return 'references';
                }

                public function check(): array
                {
                    return [
                        new Finding(
                            path: 'docs/domains/2_app/app.md',
                            line: 3,
                            severity: FindingSeverity::Error,
                            rule: 'project.references_rule',
                            message: 'References rule failed.',
                        ),
                    ];
                }
            },
        ]);

        $exitCode = Artisan::call('librarian:lint', [
            '--format' => 'agent',
            '--group' => 'references',
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(1)
            ->and($payload['issues'])->toBe(1)
            ->and($payload['findings'][0]['rule'])->toBe('project.references_rule');
    });

    it('rejects unsupported lint groups', function (): void {
        writeValidDocs($this);

        artisan('librarian:lint --group=unknown')
            ->expectsOutput('Unsupported lint group [unknown]. Supported groups: structure, contracts, references, complexity, prose.')
            ->assertFailed();
    });
});

function writeValidDocs(object $test): void
{
    writeDocsFixtureFile($test, 'docs/domains/1_node/node.md',
        "# Node\n\n## Purpose\n\nNode purpose.\n\n## Responsibilities\n\nNode responsibilities.\n\n## Boundaries\n\nNode boundaries.\n"
    );
    writeDocsFixtureFile($test,
        'docs/domains/1_node/concepts.md',
        "# Node Concepts\n\n## Node\n\nA machine registered with the project.\n"
    );
    writeDocsFixtureFile($test,
        'docs/domains/2_app/app.md',
        "# App\n\n## Purpose\n\nApp purpose.\n\n## Responsibilities\n\nApp responsibilities.\n\n## Boundaries\n\nApp boundaries.\n"
    );
    writeDocsFixtureFile($test,
        'docs/domains/2_app/concepts.md',
        "# App Concepts\n\n## App\n\nThe application runtime.\n"
    );
    writeDocsFixtureFile($test,
        'docs/mission.md',
        "# Mission\n\nLibrarian keeps docs, code, and tests aligned.\n\n## Why\n\nWe value maintainable software.\n\n## How\n\nTeams use shared docs.\n\n## What\n\nShared docs stay coherent.\n\n## Boundaries\n\nStructure over flexibility.\n"
    );
    writeDocsFixtureFile($test,
        'docs/architecture.md',
        "# Architecture\n\n## Components\n\nSee [Node](domains/1_node/node.md).\n\n## Relationships\n\nSee [App](domains/2_app/app.md#responsibilities).\n\n## State\n\nState lives in the app.\n\n## Boundaries\n\nBoundaries are explicit.\n"
    );
    writeDocsFixtureFile($test,
        'docs/tech-stack.md',
        "# Tech Stack\n\n## Runtime\n\nPHP 8.4.\n\n## Frameworks\n\nLaravel.\n\n## Storage\n\nPostgres.\n\n## Infrastructure\n\nQueues and workers.\n"
    );
    writeDocsFixtureFile($test,
        'docs/README.md',
        "# Documentation\n\n1. [Mission](mission.md)\n2. [Architecture](architecture.md)\n3. [Tech Stack](tech-stack.md)\n4. [Concepts](concepts.md)\n\n## Domains\n\n1. [Node](domains/1_node/node.md)\n2. [App](domains/2_app/app.md)\n"
    );
    writeDocsFixtureFile($test,
        'docs/concepts.md',
        "# Concepts\n\n| Concept | Domain | Details |\n| --- | --- | --- |\n| Node | Node | [Node concepts](domains/1_node/concepts.md#node) |\n| App | App | [App concepts](domains/2_app/concepts.md#app) |\n"
    );
}

function writeDocsFixtureFile(object $test, string $path, string $contents): void
{
    $basePath = dirname((string) config('librarian.path'));
    $fullPath = $basePath.'/'.$path;

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0777, true);
    }

    file_put_contents($fullPath, $contents);
}

final class TestLintRuleFromContainer implements Rule
{
    public function check(): array
    {
        return [
            new Finding(
                path: 'docs/domains/1_node/node.md',
                line: 3,
                severity: FindingSeverity::Error,
                rule: 'project.container_rule',
                message: 'Container rule failed.',
            ),
        ];
    }
}

final readonly class TestConfigurableLintRuleFromContainer implements Rule
{
    /**
     * @param  array{message?: string}  $options
     */
    public function __construct(
        private array $options = [],
    ) {}

    public function check(): array
    {
        return [
            new Finding(
                path: 'docs/domains/1_node/node.md',
                line: 3,
                severity: FindingSeverity::Error,
                rule: 'project.configurable_rule',
                message: $this->options['message'] ?? 'Default configurable rule failed.',
            ),
        ];
    }
}
