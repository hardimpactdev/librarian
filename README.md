# Librarian

Librarian gives Laravel projects a strict documentation structure for keeping product intent, code, and tests aligned.

## Installation

```bash
composer require hardimpactdev/librarian
php artisan vendor:publish --tag="librarian-config"
```

## Usage

```bash
php artisan librarian:init
php artisan librarian:domain node
php artisan librarian:domains:normalize
php artisan librarian:build
php artisan librarian:lint
```

`librarian:init` creates the required documentation spine. Use
`librarian:domain` to add ordered domain documentation, `librarian:build` to
regenerate package-owned docs and lint them, and `librarian:lint` in CI for a
read-only consistency check.

## Optional Rules

Projects can opt into additional rules without changing Librarian's required
spine. Register rule classes in `config/librarian.php`:

```php
use HardImpact\Librarian\Linting\Rules\BulletComplexityRule;
use HardImpact\Librarian\Linting\Rules\CompoundNounStackRule;
use HardImpact\Librarian\Linting\Rules\DocumentComplexityRule;
use HardImpact\Librarian\Linting\Rules\LongSectionStructureRule;
use HardImpact\Librarian\Linting\Rules\RequirementSmellRule;
use HardImpact\Librarian\Linting\Rules\SectionOpenerProseRule;
use HardImpact\Librarian\Linting\Rules\SentenceCaseHeadingRule;
use HardImpact\Librarian\Linting\Rules\TableProseComplexityRule;

return [
    'path' => base_path('docs'),

    'rules' => [
        DocumentComplexityRule::class,
        RequirementSmellRule::class,
        SentenceCaseHeadingRule::class,
        LongSectionStructureRule::class,
        BulletComplexityRule::class,
        SectionOpenerProseRule::class,
        TableProseComplexityRule::class,
        [
            'rule' => CompoundNounStackRule::class,
            'options' => [
                'accepted_compounds' => [
                    'project-owned',
                ],
            ],
        ],
    ],
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
