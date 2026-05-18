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
