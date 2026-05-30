<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers\Tests;

use Bigins\ScriptorMarkdownContainers\ContainerParser;
use Bigins\ScriptorMarkdownContainers\ContainerRenderer;
use Bigins\ScriptorMarkdownContainers\ContainerTypeRegistry;
use Parsedown;
use PHPUnit\Framework\TestCase;

/**
 * Renders through the real Parsedown in the exact configuration Scriptor's
 * Sanitizer::markdown() uses (safe mode), which is what the plugin sees in
 * production. The FakeMarkdown unit tests cannot exercise safe-mode HTML
 * escaping, fenced code blocks, or blank-line edge cases; this one does.
 */
final class SafeModeIntegrationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function render(string $source, array $config = []): string
    {
        if (!class_exists(Parsedown::class)) {
            self::markTestSkipped('erusev/parsedown not installed.');
        }

        $markdown = static function (string $value): string {
            $parser = new Parsedown();
            $parser->setSafeMode(true); // mirrors Imanager\Validation\Sanitizer::markdown()

            return $parser->text($value);
        };

        $registry = ContainerTypeRegistry::fromConfig($config);
        $renderer = new ContainerRenderer($registry, $markdown);

        return $renderer->renderDocument((new ContainerParser($registry))->parse($source));
    }

    public function testWrapperTagsSurviveSafeModeUnescaped(): void
    {
        $html = $this->render(":::note\nHello **world**.\n:::");

        // Safe mode would escape an injected <div>; the placeholder strategy
        // keeps the wrapper as a real tag while still rendering the body.
        self::assertStringContainsString('<div class="md-container md-container--note">', $html);
        self::assertStringNotContainsString('&lt;div', $html);
        self::assertStringContainsString('<strong>world</strong>', $html);
    }

    public function testCodeFenceAfterContainersIsLeftIntactAndDivsBalance(): void
    {
        $src = "Intro.\n\n"
             . ":::warning \"Careful\"\nBody.\n\n:::note\nNested.\n:::\n:::\n\n"
             . "Code:\n\n```\n:::note\nliteral\n:::\n```\n\n"
             . "Tail.";

        $html = $this->render($src);

        // The original bug: a code fence following containers dropped text,
        // reordered output, and left an unbalanced </div>.
        self::assertStringContainsString('<p>Intro.</p>', $html);
        self::assertStringContainsString('<p>Tail.</p>', $html);
        self::assertSame(
            substr_count($html, '<div'),
            substr_count($html, '</div>'),
            'Opening and closing <div> tags must balance',
        );
        self::assertStringNotContainsString('MDCONTAINERPLACEHOLDER', $html);

        // The fence content is literal code, not a parsed container.
        self::assertStringContainsString('<pre><code>', $html);
    }

    public function testNestedContainersProduceBalancedNesting(): void
    {
        $html = $this->render(":::warning\nOuter.\n:::note\nInner.\n:::\n:::");

        self::assertSame(2, substr_count($html, '<div'));
        self::assertSame(2, substr_count($html, '</div>'));
        self::assertStringContainsString('<p>Inner.</p>', $html);
    }

    public function testListInsideDetailsBodyRendersAsList(): void
    {
        $html = $this->render(":::details \"More\"\n- a\n- b\n:::");

        self::assertStringContainsString('<details class="md-container md-container--details">', $html);
        self::assertStringContainsString('<summary>More</summary>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<li>a</li>', $html);
    }

    public function testRawHtmlInBodyIsEscapedBySafeMode(): void
    {
        $html = $this->render(":::note\n<script>alert(1)</script>\n:::");

        self::assertStringContainsString('<div class="md-container md-container--note">', $html);
        self::assertStringNotContainsString('<script>', $html);
    }
}
