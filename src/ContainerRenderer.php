<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers;

/**
 * Renders a parsed container tree to HTML.
 *
 * The Sanitizer runs Parsedown in safe mode, which escapes raw HTML in its
 * input. Injecting `<div>` wrappers into the Markdown source and rendering
 * would therefore escape them. Instead each container is swapped for a plain
 * alphanumeric placeholder that survives a Markdown pass verbatim; the
 * surrounding text is rendered in one pass, then the rendered container HTML
 * is spliced back in over its `<p>placeholder</p>` paragraph. Container
 * bodies recurse through the same routine, so the innermost containers are
 * rendered first.
 *
 * The container markup itself is assembled outside Markdown and never fed
 * back through it, so the wrapper tags reach the page intact while every
 * piece of author-supplied body text still passes through the safe-mode
 * Sanitizer.
 */
final class ContainerRenderer
{
    /** Placeholder stem: alphanumeric so Markdown leaves it untouched. */
    private const TOKEN = 'MDCONTAINERPLACEHOLDER';

    /** @var callable(string): string */
    private $renderMarkdown;

    /**
     * @param callable(string): string $renderMarkdown
     */
    public function __construct(
        private readonly ContainerTypeRegistry $registry,
        callable $renderMarkdown,
    ) {
        $this->renderMarkdown = $renderMarkdown;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    public function renderDocument(array $nodes): string
    {
        $chunks = [];
        $blocks = [];
        $i = 0;

        // Build a clean Markdown buffer: each node becomes one chunk and the
        // chunks are joined with a single blank line. Text nodes are stripped
        // of the leading/trailing newlines the parser leaves around container
        // boundaries, so a placeholder always lands as its own paragraph and
        // adjacent prose never collapses into an accidental indented-code or
        // run-on block.
        foreach ($nodes as $node) {
            if (isset($node['text'])) {
                $text = trim((string) $node['text'], "\n");
                if ($text !== '') {
                    $chunks[] = $text;
                }
                continue;
            }

            $token = self::TOKEN . ($i++) . 'X';
            $chunks[] = $token;
            $blocks[$token] = $this->renderContainer($node);
        }

        $html = ($this->renderMarkdown)(implode("\n\n", $chunks));

        foreach ($blocks as $token => $blockHtml) {
            $html = str_replace(
                ['<p>' . $token . '</p>', $token],
                [$blockHtml, $blockHtml],
                $html,
            );
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderContainer(array $node): string
    {
        $type = $this->registry->get((string) $node['ctype']);
        $inner = $this->renderDocument(is_array($node['children'] ?? null) ? $node['children'] : []);
        $title = isset($node['title']) ? (string) $node['title'] : '';

        if ($type->isDetails()) {
            $summary = $title !== ''
                ? '<summary>' . htmlspecialchars($title, ENT_QUOTES) . '</summary>'
                : '';

            return '<details class="' . $type->class . '">'
                . $summary . "\n" . $inner . "\n</details>";
        }

        $titleHtml = '';
        if ($title !== '') {
            $titleHtml = '<p class="' . $this->registry->titleClass() . '">'
                . htmlspecialchars($title, ENT_QUOTES) . "</p>\n";
        }

        return '<' . $type->tag . ' class="' . $type->class . '">' . "\n"
            . $titleHtml . $inner . "\n"
            . '</' . $type->tag . '>';
    }
}
