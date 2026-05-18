<?php

declare(strict_types=1);

use HardImpact\Librarian\Markdown\MarkdownLinkRewriter;

describe('MarkdownLinkRewriter', function (): void {
    it('rewrites links that point to renamed domain directories', function (): void {
        $rewritten = app(MarkdownLinkRewriter::class)->rewrite(
            '[App](../domains/2_app/app.md) and [Node](domains/1_node/node.md)',
            [
                '2_app' => '3_app',
            ],
        );

        expect($rewritten)->toBe('[App](../domains/3_app/app.md) and [Node](domains/1_node/node.md)');
    });

    it('leaves non-markdown-link content untouched', function (): void {
        $contents = <<<'MD'
        [App](../domains/2_app/app.md)
        <https://example.com/domains/2_app/app.md>
        ![Diagram](../domains/2_app/diagram.png)
        MD;

        $rewritten = app(MarkdownLinkRewriter::class)->rewrite($contents, [
            '2_app' => '3_app',
        ]);

        expect($rewritten)->toBe(<<<'MD'
        [App](../domains/3_app/app.md)
        <https://example.com/domains/2_app/app.md>
        ![Diagram](../domains/2_app/diagram.png)
        MD);
    });

    it('skips external url targets in markdown links', function (): void {
        $rewritten = app(MarkdownLinkRewriter::class)->rewrite(
            '[External](https://example.com/domains/2_app/app.md) and [Local](../domains/2_app/app.md)',
            [
                '2_app' => '3_app',
            ],
        );

        expect($rewritten)->toBe('[External](https://example.com/domains/2_app/app.md) and [Local](../domains/3_app/app.md)');
    });
});
