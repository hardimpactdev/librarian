<?php

declare(strict_types=1);

use HardImpact\Librarian\Generation\ConceptsIndexGenerator;
use HardImpact\Librarian\Generation\DocsReadmeGenerator;
use HardImpact\Librarian\Generation\GeneratedDocs;
use HardImpact\Librarian\Tests\Support\CreatesDocsFixtures;

uses(CreatesDocsFixtures::class);

beforeEach(function (): void {
    $this->deletePath('docs');
});

describe('DocsReadmeGenerator', function (): void {
    it('renders the canonical docs readme with discovered domains', function (): void {
        mkdir($this->docsPath().'/domains/2_product-strategy', 0777, true);
        mkdir($this->docsPath().'/domains/10_api-platform', 0777, true);

        $rendered = app(DocsReadmeGenerator::class)->render();

        expect($rendered)->toBe(
            "# Documentation\n\n1. [Mission](mission.md)\n2. [Architecture](architecture.md)\n3. [Tech Stack](tech-stack.md)\n4. [Concepts](concepts.md)\n\n## Domains\n\n1. [Product Strategy](domains/2_product-strategy/product-strategy.md)\n2. [Api Platform](domains/10_api-platform/api-platform.md)\n"
        );
    });
});

describe('ConceptsIndexGenerator', function (): void {
    it('renders a markdown table row for every concepts heading in every domain', function (): void {
        $this->writeFile(
            'docs/domains/2_product-strategy/concepts.md',
            "# Concepts\n\n  ## Event Storming ##\n\nShared language for modeling.\n\n## Product   Vision?   ##\n\nDirection and constraints.\n"
        );
        $this->writeFile(
            'docs/domains/10_api-platform/concepts.md',
            "# Concepts\n\n## Rate Limits & Quotas\n\nTraffic controls.\n\n## Café API-first!!!\n\nPublic contract before implementation.\n"
        );

        $rendered = app(ConceptsIndexGenerator::class)->render();

        expect($rendered)->toBe(
            "# Concepts\n\n| Concept | Domain | Details |\n| --- | --- | --- |\n| Event Storming | Product Strategy | [Product Strategy concepts](domains/2_product-strategy/concepts.md#event-storming) |\n| Product Vision? | Product Strategy | [Product Strategy concepts](domains/2_product-strategy/concepts.md#product-vision) |\n| Rate Limits & Quotas | Api Platform | [Api Platform concepts](domains/10_api-platform/concepts.md#rate-limits-quotas) |\n| Café API-first!!! | Api Platform | [Api Platform concepts](domains/10_api-platform/concepts.md#caf-api-first) |\n"
        );
    });
});

describe('GeneratedDocs', function (): void {
    it('writes the generated docs files and reports whether they are current', function (): void {
        mkdir($this->docsPath().'/domains/3_billing-ops', 0777, true);
        $this->writeFile(
            'docs/domains/3_billing-ops/concepts.md',
            "# Concepts\n\n## Invoice Lifecycle\n\nTrack invoices.\n"
        );

        $generatedDocs = app(GeneratedDocs::class);

        expect($generatedDocs->isCurrent())->toBeFalse();

        $generatedDocs->write();

        expect($this->readFile('docs/README.md'))->toBe(
            "# Documentation\n\n1. [Mission](mission.md)\n2. [Architecture](architecture.md)\n3. [Tech Stack](tech-stack.md)\n4. [Concepts](concepts.md)\n\n## Domains\n\n1. [Billing Ops](domains/3_billing-ops/billing-ops.md)\n"
        )->and($this->readFile('docs/concepts.md'))->toBe(
            "# Concepts\n\n| Concept | Domain | Details |\n| --- | --- | --- |\n| Invoice Lifecycle | Billing Ops | [Billing Ops concepts](domains/3_billing-ops/concepts.md#invoice-lifecycle) |\n"
        )->and($generatedDocs->isCurrent())->toBeTrue();

        $this->writeFile('docs/README.md', '# Stale');

        expect($generatedDocs->isCurrent())->toBeFalse();
    });
});
