<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers;

/**
 * Immutable definition of one container type.
 *
 * A type maps a fence name (the word after `:::`) to the HTML tag and CSS
 * class string used when it is rendered. `details` types render a
 * `<details>/<summary>` disclosure; every other type renders as its tag
 * (a `<div>` by default) carrying an optional title paragraph.
 */
final class ContainerType
{
    public function __construct(
        public readonly string $name,
        public readonly string $tag,
        public readonly string $class,
    ) {
    }

    public function isDetails(): bool
    {
        return $this->tag === 'details';
    }
}
