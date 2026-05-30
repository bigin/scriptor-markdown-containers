<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers;

use Imanager\Validation\Sanitizer;
use Scriptor\Boot\Events\Frontend\ContentRendering;
use Scriptor\Boot\Plugin\Plugin as ScriptorPlugin;
use Scriptor\Boot\Plugin\PluginContext;

/**
 * Adds fenced-container syntax (`:::note`, `:::warning`, `:::details`, …) to
 * Scriptor page content.
 *
 * Stateless: it only subscribes to the frontend `ContentRendering` event and
 * owns no schema, so a plain `composer require` installs it and a
 * `composer remove` removes it. No `bin/scriptor plugin:install` step.
 *
 * Configuration (optional) is read from
 * `$config['plugins']['markdown_containers']`; see
 * {@see ContainerTypeRegistry} for the supported keys.
 */
final class Plugin implements ScriptorPlugin
{
    private ?Sanitizer $sanitizer = null;

    private ?ContainerTypeRegistry $registry = null;

    public function name(): string
    {
        return 'bigins/scriptor-markdown-containers';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(PluginContext $context): void
    {
        $this->sanitizer = $context->container()->get(Sanitizer::class);

        /** @var array<string, mixed> $config */
        $config = (array) $context->container()->get('scriptor.config');
        $plugins = (array) ($config['plugins'] ?? []);
        $own = $plugins['markdown_containers'] ?? [];
        $this->registry = ContainerTypeRegistry::fromConfig(is_array($own) ? $own : []);

        $context->subscribe(ContentRendering::class, [$this, 'onContentRendering']);
    }

    public function onContentRendering(ContentRendering $event): void
    {
        // First-writer-wins: another listener already rendered the content.
        if ($event->html !== null) {
            return;
        }

        if ($this->sanitizer === null || $this->registry === null) {
            return;
        }

        $content = (string) ($event->page->content ?? '');

        // No fence markers: leave $html null so Site falls back to its own
        // Sanitizer::markdown() pass. Nothing for us to do.
        if (!str_contains($content, ':::')) {
            return;
        }

        $sanitizer = $this->sanitizer;
        $parser = new ContainerParser($this->registry);
        $renderer = new ContainerRenderer(
            $this->registry,
            static fn (string $markdown): string => $sanitizer->markdown($markdown),
        );

        $event->html = $renderer->renderDocument($parser->parse($content));
    }
}
