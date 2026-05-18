<?php

declare(strict_types=1);

use HardImpact\Librarian\Linting\Rules\BulletComplexityRule;
use HardImpact\Librarian\Linting\Rules\LongSectionStructureRule;
use HardImpact\Librarian\Linting\Rules\RequirementSmellRule;
use HardImpact\Librarian\Linting\Rules\SectionOpenerProseRule;
use HardImpact\Librarian\Linting\Rules\SentenceCaseHeadingRule;
use HardImpact\Librarian\Linting\Rules\TableProseComplexityRule;
use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;
use Illuminate\Support\Facades\Artisan;

uses(CreatesDocsFixtures::class);

describe('optional prose rules', function (): void {
    beforeEach(function (): void {
        $this->deletePath('docs');
        config()->set('librarian.rules', []);
    });

    it('does not run optional prose rules until they are configured', function (): void {
        writeOptionalProseDoc('docs/guide.md', "# Guide\n\nUse this as needed.\n");

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['issues'])->toBe(0);
    });

    it('reports ambiguous requirement smell phrases', function (): void {
        writeOptionalProseDoc('docs/guide.md', "# Guide\n\nUse this as needed.\n");

        config()->set('librarian.rules', [
            RequirementSmellRule::class,
        ]);

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/guide.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.requirement_smell',
                'message' => 'Ambiguous phrase `as needed`. Name the actor, condition, obligation, and observable result.',
            ]);
    });

    it('reports title-case function words in headings', function (): void {
        writeOptionalProseDoc('docs/guide.md', "# Guide\n\n## Inputs And Outputs\n\nInputs are listed here.\n");

        config()->set('librarian.rules', [
            SentenceCaseHeadingRule::class,
        ]);

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/guide.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.sentence_case_heading',
            ])
            ->and($payload['findings'][0]['message'])->toContain('capitalized: And');
    });

    it('reports long flat reader-facing sections', function (): void {
        writeOptionalProseDoc(
            'docs/guide.md',
            "# Guide\n\n## Flat section\n\nFirst paragraph names the behavior.\n\nSecond paragraph keeps describing the same behavior.\n\nThird paragraph keeps the same shape.\n\nFourth paragraph still avoids structure.\n\nFifth paragraph finishes without any structural break.\n",
        );

        config()->set('librarian.rules', [
            LongSectionStructureRule::class,
        ]);

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/guide.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.long_section_structure',
            ])
            ->and($payload['findings'][0]['message'])->toContain('5 paragraphs of flat prose');
    });

    it('reports multi-clause bullets', function (): void {
        writeOptionalProseDoc(
            'docs/guide.md',
            "# Guide\n\n- Keep the handoff visible when the runtime cannot confirm the state during repeated deployment reviews and operational checks.\n",
        );

        config()->set('librarian.rules', [
            BulletComplexityRule::class,
        ]);

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/guide.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.bullet_complexity',
            ])
            ->and($payload['findings'][0]['message'])->toContain('embedded conditional');
    });

    it('reports sections that open with structural content before prose', function (): void {
        writeOptionalProseDoc(
            'docs/guide.md',
            "# Guide\n\n## Ownership\n\n| Owner | Scope |\n| --- | --- |\n| Runtime | State |\n",
        );

        config()->set('librarian.rules', [
            SectionOpenerProseRule::class,
        ]);

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/guide.md',
                'line' => 3,
                'severity' => 'warning',
                'rule' => 'librarian.section_opener_prose',
            ])
            ->and($payload['findings'][0]['message'])->toContain('opens with a table');
    });

    it('reports dense table cells', function (): void {
        writeOptionalProseDoc(
            'docs/guide.md',
            "# Guide\n\n| Behavior | Detail |\n| --- | --- |\n| Runtime | This table cell keeps adding guidance about actor condition action observable result recovery ownership review handoff timing failure mode and reader decision points until it becomes paragraph prose hidden inside a table. |\n",
        );

        config()->set('librarian.rules', [
            TableProseComplexityRule::class,
        ]);

        $payload = lintOptionalProseDoc('docs/guide.md');

        expect($payload['warnings'])->toBe(1)
            ->and($payload['findings'][0])->toMatchArray([
                'path' => 'docs/guide.md',
                'line' => 5,
                'severity' => 'warning',
                'rule' => 'librarian.table_prose_complexity',
            ])
            ->and($payload['findings'][0]['message'])->toContain('Table cell has');
    });
});

function writeOptionalProseDoc(string $path, string $contents): void
{
    $basePath = dirname((string) config('librarian.path'));
    $fullPath = "{$basePath}/{$path}";

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0777, true);
    }

    file_put_contents($fullPath, $contents);
}

/**
 * @return array<string, mixed>
 */
function lintOptionalProseDoc(string $path): array
{
    Artisan::call('librarian:lint', [
        '--format' => 'agent',
        '--path' => $path,
        '--group' => 'prose',
    ]);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}
