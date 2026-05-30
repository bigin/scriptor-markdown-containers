<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers\Tests;

/**
 * A minimal stand-in for Scriptor's Sanitizer::markdown().
 *
 * The plugin never depends on a Markdown library directly: it renders through
 * the closure Scriptor injects (Sanitizer::markdown(), Parsedown safe mode).
 * For the parser/renderer unit tests we only need the structural property the
 * renderer relies on: a blank-line-separated block becomes a `<p>…</p>`
 * paragraph, so a lone placeholder line renders as `<p>placeholder</p>`.
 * Inline formatting is left untouched, which keeps assertions about structure
 * unambiguous. The safe-mode behaviour itself is covered separately by the
 * real-Parsedown integration test.
 */
final class FakeMarkdown
{
    public static function render(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        $blocks = preg_split('/\n{2,}/', $source) ?: [];
        $blocks = array_filter(array_map('trim', $blocks), static fn (string $b): bool => $b !== '');

        return implode("\n", array_map(static fn (string $b): string => '<p>' . $b . '</p>', $blocks));
    }
}
