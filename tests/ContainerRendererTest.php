<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers\Tests;

use Bigins\ScriptorMarkdownContainers\ContainerParser;
use Bigins\ScriptorMarkdownContainers\ContainerRenderer;
use Bigins\ScriptorMarkdownContainers\ContainerTypeRegistry;
use PHPUnit\Framework\TestCase;

final class ContainerRendererTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function render(string $source, array $config = []): string
    {
        $registry = ContainerTypeRegistry::fromConfig($config);
        $parser = new ContainerParser($registry);
        $renderer = new ContainerRenderer($registry, [FakeMarkdown::class, 'render']);

        return $renderer->renderDocument($parser->parse($source));
    }

    public function testNoteRendersDivWithBemClass(): void
    {
        $html = $this->render(":::note\nHello\n:::");

        self::assertStringContainsString('<div class="md-container md-container--note">', $html);
        self::assertStringContainsString('<p>Hello</p>', $html);
        self::assertStringEndsWith('</div>', trim($html));
        self::assertStringNotContainsString('MDCONTAINERPLACEHOLDER', $html);
    }

    public function testTextAroundContainerIsPreserved(): void
    {
        $html = $this->render("Before\n\n:::tip\nInside\n:::\n\nAfter");

        self::assertStringContainsString('<p>Before</p>', $html);
        self::assertStringContainsString('<div class="md-container md-container--tip">', $html);
        self::assertStringContainsString('<p>After</p>', $html);
    }

    public function testDetailsRendersSummary(): void
    {
        $html = $this->render(":::details \"Show more\"\nSecret\n:::");

        self::assertStringContainsString('<details class="md-container md-container--details">', $html);
        self::assertStringContainsString('<summary>Show more</summary>', $html);
        self::assertStringContainsString('<p>Secret</p>', $html);
        self::assertStringContainsString('</details>', $html);
    }

    public function testTitleRendersOnNonDetailsType(): void
    {
        $html = $this->render(":::warning \"Heads up\"\nBody\n:::");

        self::assertStringContainsString('<p class="md-container__title">Heads up</p>', $html);
    }

    public function testTitleIsHtmlEscaped(): void
    {
        $html = $this->render(":::note \"a <b> &amp\"\nx\n:::");

        self::assertStringContainsString('a &lt;b&gt; &amp;amp', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testNestedContainersRenderInnermostFirst(): void
    {
        $html = $this->render(":::warning\nOuter\n:::note\nInner\n:::\n:::");

        self::assertStringContainsString('<div class="md-container md-container--warning">', $html);
        self::assertStringContainsString('<div class="md-container md-container--note">', $html);
        self::assertStringContainsString('<p>Inner</p>', $html);

        $outerPos = strpos($html, 'md-container--warning');
        $innerPos = strpos($html, 'md-container--note');
        self::assertNotFalse($outerPos);
        self::assertNotFalse($innerPos);
        self::assertLessThan($innerPos, $outerPos, 'Outer wrapper should enclose the inner one');
    }

    public function testDocumentOrderIsPreservedAcrossContainersAndText(): void
    {
        // Regression: containers used to float to the top while the text
        // around them sank to the bottom, and text between two containers
        // was dropped entirely. Assert the rendered fragments appear in
        // source order.
        $html = $this->render("Intro\n\n:::warning\nA\n:::\n\nMiddle\n\n:::note\nB\n:::\n\nOutro");

        $posIntro   = strpos($html, '<p>Intro</p>');
        $posWarning = strpos($html, 'md-container--warning');
        $posMiddle  = strpos($html, '<p>Middle</p>');
        $posNote    = strpos($html, 'md-container--note');
        $posOutro   = strpos($html, '<p>Outro</p>');

        self::assertNotFalse($posIntro);
        self::assertNotFalse($posMiddle);
        self::assertNotFalse($posOutro);
        self::assertTrue(
            $posIntro < $posWarning
            && $posWarning < $posMiddle
            && $posMiddle < $posNote
            && $posNote < $posOutro,
            'Rendered fragments must follow source order',
        );
    }

    public function testClassOverrideFromConfig(): void
    {
        $html = $this->render(":::note\nHi\n:::", ['classes' => ['note' => 'uk-alert uk-alert-primary']]);

        self::assertStringContainsString('<div class="uk-alert uk-alert-primary">', $html);
    }

    public function testClassOverrideIsSanitized(): void
    {
        $html = $this->render(":::note\nHi\n:::", ['classes' => ['note' => 'ok"><script>bad']]);

        // The quote/angle-bracket breakout is stripped, so the only `">` left
        // is the legitimate end of the opening tag, not an injected one.
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('"><', $html);
        self::assertStringContainsString('<div class="okscriptbad">', $html);
    }

    public function testCustomPrefixFromConfig(): void
    {
        $html = $this->render(":::note \"T\"\nHi\n:::", ['prefix' => 'cb']);

        self::assertStringContainsString('<div class="cb cb--note">', $html);
        self::assertStringContainsString('<p class="cb__title">T</p>', $html);
    }

    public function testCustomTypeFromConfig(): void
    {
        $html = $this->render(":::aside\nSide\n:::", ['types' => ['aside' => ['tag' => 'div']]]);

        self::assertStringContainsString('<div class="md-container md-container--aside">', $html);
        self::assertStringContainsString('<p>Side</p>', $html);
    }

    public function testCustomDetailsTypeFromConfig(): void
    {
        $html = $this->render(":::faq \"Q?\"\nA\n:::", ['types' => ['faq' => ['tag' => 'details']]]);

        self::assertStringContainsString('<details class="md-container md-container--faq">', $html);
        self::assertStringContainsString('<summary>Q?</summary>', $html);
    }

    public function testDisabledBuiltinTypeFallsBackToText(): void
    {
        $html = $this->render(":::tip\nHi\n:::", ['types' => ['tip' => false]]);

        self::assertStringNotContainsString('md-container--tip', $html);
        self::assertStringContainsString(':::tip', $html);
    }
}
