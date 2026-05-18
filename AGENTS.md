# Librarian

Librarian is a Laravel package for strict project documentation structure. It
scaffolds, generates, and lints documentation so product intent, code, and tests
stay aligned.

## Current Shape

- Laravel-only package, not a standalone application.
- Namespace: `HardImpact\Librarian`.
- Commands are registered through `LibrarianServiceProvider` and exercised with
  Orchestra Testbench.
- The package supports Laravel 11, 12, and 13, with development currently
  resolved against Laravel 13.

## Development Rules

- Keep docs, tests, and code aligned. When changing documented behavior, update
  tests and implementation in the same slice.
- Prefer package-native tests through Pest and Orchestra Testbench.
- Keep Artisan command classes thin; move parsing, filesystem, generation, and
  linting behavior into focused services.
- Follow the project-local Boost and Spatie guidance below as the PHP/Laravel
  baseline.

## Verification

Run the narrowest useful check while developing:

```bash
vendor/bin/pest --filter=[Name]
vendor/bin/pint --dirty --format agent
```

Before handing off a broadly safe change, run:

```bash
composer quality-check
```

## Boost Usage In This Package

Boost is used here for generated AI guidelines and skills only. Do not configure
Boost MCP for this package unless the Testbench command path has been verified
for the target agent.

Refresh generated agent context with:

```bash
composer boost:update-agent-context
```

## AI Guideline Precedence

Librarian-specific instructions in this file override generated Laravel Boost
and Spatie guidance when they conflict. Treat Boost and Spatie as the baseline
for PHP, Laravel, Pest, and style conventions; treat Librarian's command
contracts and documentation spine as package-specific constraints.

===

<laravel-boost-guidelines>
=== .ai/core rules ===

# Librarian Package Development

Librarian is a Laravel package, not a full Laravel application. Prefer
Testbench-aware commands and package tests over app-only assumptions.

## Package Constraints

- Keep the package Laravel-only for v1.
- Support Laravel 11, 12, and 13 through package boundaries.
- Do not introduce app-only behavior that requires a consuming project database,
  route file, queue worker, or frontend build.
- Do not configure Laravel Boost MCP in this repo unless the command is verified
  through `vendor/bin/testbench`.

## Architecture

- Keep Artisan commands thin.
- Put documentation path behavior in `src/Docs`.
- Put domain ordering, renumbering, and scaffolding in `src/Domains`.
- Put generated docs rendering in `src/Generation`.
- Put lint rules and lint result modeling in `src/Linting`.
- Put markdown rewriting and segmentation behavior in `src/Markdown`.

## Documentation Contract

Librarian owns this required docs spine in consuming projects:

```text
docs/
  README.md
  mission.md
  architecture.md
  tech-stack.md
  concepts.md
  domains/
```

`docs/README.md` and `docs/concepts.md` are generated files. User-authored
project intent belongs in `mission.md`, `architecture.md`, `tech-stack.md`, and
domain docs under `docs/domains`.

## Development Flow

- For behavior changes, align docs, tests, and code in one slice.
- Add or update Pest tests before changing package behavior.
- Exercise Artisan behavior through Testbench.
- Run `composer quality-check` before handing off broad changes.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== spatie/guidelines-skills rules ===

# Project Coding Guidelines

- This codebase follows Spatie's coding guidelines.
- Always activate the `spatie-laravel-php` skill when writing, editing, reviewing, or formatting Laravel or PHP code.
- Always activate the `spatie-javascript` skill when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- Always activate the `spatie-version-control` skill when creating commits, branches, or managing Git operations.
- Always activate the `spatie-security` skill when configuring security, reviewing authentication, or setting up servers and databases.

</laravel-boost-guidelines>
