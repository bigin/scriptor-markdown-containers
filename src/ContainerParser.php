<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers;

/**
 * Line-based scanner that turns page Markdown into a tree of nodes.
 *
 * A container opens on a line of the form `:::name` or `:::name "Title"`
 * (the name must be a registered type) and closes on a bare `:::`. Because
 * the scanner recurses on each open fence rather than relying on a single
 * regex, containers nest. Lines inside a fenced code block (``` or ~~~) are
 * never interpreted as fences, so the container syntax can be documented in
 * code samples without being parsed.
 *
 * The returned tree is a flat list of nodes; each node is one of:
 *   ['text' => string]
 *   ['ctype' => string, 'title' => ?string, 'children' => array<node>]
 */
final class ContainerParser
{
    private const OPEN = '/^:::([A-Za-z0-9_-]+)[ \t]*(?:"([^"]*)")?[ \t]*$/';
    private const CLOSE = '/^:::[ \t]*$/';
    private const FENCE = '/^(`{3,}|~{3,})/';

    public function __construct(private readonly ContainerTypeRegistry $registry)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $index = 0;

        return $this->parseLevel($lines, $index, false);
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, array<string, mixed>>
     */
    private function parseLevel(array $lines, int &$index, bool $nested): array
    {
        $nodes = [];
        $text = [];
        $fence = null;
        $count = count($lines);

        $flush = static function () use (&$nodes, &$text): void {
            if ($text !== []) {
                $nodes[] = ['text' => implode("\n", $text)];
                $text = [];
            }
        };

        while ($index < $count) {
            $line = $lines[$index];
            $trimmed = trim($line);
            $fenceHit = preg_match(self::FENCE, ltrim($line), $fenceMatch) === 1;

            // Inside a fenced code block: copy lines verbatim until the
            // matching closing fence (same marker character).
            if ($fence !== null) {
                $text[] = $line;
                $index++;
                if ($fenceHit && $fenceMatch[1][0] === $fence) {
                    $fence = null;
                }
                continue;
            }

            if ($fenceHit) {
                $fence = $fenceMatch[1][0];
                $text[] = $line;
                $index++;
                continue;
            }

            if (preg_match(self::OPEN, $trimmed, $open) === 1 && $this->registry->has($open[1])) {
                $flush();
                $index++; // consume the opening fence
                $children = $this->parseLevel($lines, $index, true);
                $nodes[] = [
                    'ctype'    => $open[1],
                    'title'    => ($open[2] ?? '') !== '' ? $open[2] : null,
                    'children' => $children,
                ];
                continue;
            }

            if (preg_match(self::CLOSE, $trimmed) === 1) {
                if ($nested) {
                    $flush();
                    $index++; // consume the closing fence and pop a level
                    return $nodes;
                }
                // A stray close at the top level is literal text.
                $text[] = $line;
                $index++;
                continue;
            }

            $text[] = $line;
            $index++;
        }

        $flush();

        return $nodes;
    }
}
