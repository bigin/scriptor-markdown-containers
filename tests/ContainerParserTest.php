<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers\Tests;

use Bigins\ScriptorMarkdownContainers\ContainerParser;
use Bigins\ScriptorMarkdownContainers\ContainerTypeRegistry;
use PHPUnit\Framework\TestCase;

final class ContainerParserTest extends TestCase
{
    private function parser(): ContainerParser
    {
        return new ContainerParser(ContainerTypeRegistry::withDefaults());
    }

    public function testPlainContentIsOneTextNode(): void
    {
        $nodes = $this->parser()->parse("Just a paragraph.\n\nAnd another.");

        self::assertSame([['text' => "Just a paragraph.\n\nAnd another."]], $nodes);
    }

    public function testSimpleContainer(): void
    {
        $nodes = $this->parser()->parse("Intro\n\n:::note\nBody line\n:::\n\nOutro");

        self::assertCount(3, $nodes);
        self::assertSame("Intro\n", $nodes[0]['text']);
        self::assertSame('note', $nodes[1]['ctype']);
        self::assertNull($nodes[1]['title']);
        self::assertSame([['text' => 'Body line']], $nodes[1]['children']);
        self::assertSame("\nOutro", $nodes[2]['text']);
    }

    public function testContainerTitleIsCaptured(): void
    {
        $nodes = $this->parser()->parse(":::details \"Click to expand\"\nHidden\n:::");

        self::assertSame('details', $nodes[0]['ctype']);
        self::assertSame('Click to expand', $nodes[0]['title']);
    }

    public function testNestedContainers(): void
    {
        $nodes = $this->parser()->parse(":::warning\nOuter\n:::note\nInner\n:::\n:::");

        self::assertCount(1, $nodes);
        $warning = $nodes[0];
        self::assertSame('warning', $warning['ctype']);
        self::assertSame('Outer', $warning['children'][0]['text']);
        self::assertSame('note', $warning['children'][1]['ctype']);
        self::assertSame([['text' => 'Inner']], $warning['children'][1]['children']);
    }

    public function testUnknownTypeIsLiteralText(): void
    {
        $nodes = $this->parser()->parse(":::bogus\nstuff\n:::");

        self::assertCount(1, $nodes);
        self::assertSame(":::bogus\nstuff\n:::", $nodes[0]['text']);
    }

    public function testFenceMarkersInsideCodeBlockAreNotParsed(): void
    {
        $source = "Docs:\n\n```\n:::note\nnot a real container\n:::\n```\n\nDone";
        $nodes = $this->parser()->parse($source);

        self::assertCount(1, $nodes);
        self::assertStringContainsString(':::note', $nodes[0]['text']);
        self::assertArrayNotHasKey('ctype', $nodes[0]);
    }

    public function testStrayCloseAtTopLevelIsText(): void
    {
        $nodes = $this->parser()->parse("text\n:::\nmore");

        self::assertCount(1, $nodes);
        self::assertSame("text\n:::\nmore", $nodes[0]['text']);
    }

    public function testTwoTopLevelContainersKeepTextBetweenThem(): void
    {
        // Regression: an earlier parser dropped the second container and the
        // text between it and the first, because of a by-reference flush
        // closure. The full sequence must survive in document order.
        $src = "Intro\n\n:::warning\nA\n:::\n\nMiddle\n\n:::note\nB\n:::\n\nOutro";
        $nodes = $this->parser()->parse($src);

        self::assertCount(5, $nodes);
        self::assertSame('Intro', trim($nodes[0]['text']));
        self::assertSame('warning', $nodes[1]['ctype']);
        self::assertSame('Middle', trim($nodes[2]['text']));
        self::assertSame('note', $nodes[3]['ctype']);
        self::assertSame('Outro', trim($nodes[4]['text']));
    }

    public function testSiblingContainerAfterNestedOneIsKept(): void
    {
        // The exact shape that regressed: a nested container, then a sibling
        // container at the top level after the outer one closes.
        $src = ":::warning\nOuter\n:::note\nInner\n:::\n:::\n\n:::details \"D\"\nHidden\n:::\n\nTail";
        $nodes = $this->parser()->parse($src);

        self::assertCount(3, $nodes);
        self::assertSame('warning', $nodes[0]['ctype']);
        self::assertSame('details', $nodes[1]['ctype']);
        self::assertSame('Hidden', trim($nodes[1]['children'][0]['text']));
        self::assertSame('Tail', trim($nodes[2]['text']));
    }

    public function testTildeCodeFenceIsRespected(): void
    {
        $source = "~~~\n:::note\nliteral\n:::\n~~~";
        $nodes = $this->parser()->parse($source);

        self::assertCount(1, $nodes);
        self::assertArrayNotHasKey('ctype', $nodes[0]);
    }
}
