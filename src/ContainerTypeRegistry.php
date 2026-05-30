<?php

declare(strict_types=1);

namespace Bigins\ScriptorMarkdownContainers;

/**
 * Holds the set of known container types and resolves their classes.
 *
 * Built-in types ship with theme-neutral BEM classes derived from a
 * configurable prefix (default `md-container`), so `:::warning` renders as
 * `md-container md-container--warning`. A site overrides any of this through
 * the `plugins.markdown_containers` config block:
 *
 *   'markdown_containers' => [
 *       'prefix'  => 'md-container',          // BEM base for derived classes
 *       'classes' => ['note' => 'uk-alert'],  // replace a type's full class
 *       'types'   => [
 *           'aside' => ['tag' => 'div'],      // add a custom type
 *           'tip'   => false,                 // disable a built-in type
 *       ],
 *   ]
 *
 * Class strings (whether derived or operator-supplied) are filtered down to
 * `[A-Za-z0-9 _-]` so a config value can never break out of the class
 * attribute.
 */
final class ContainerTypeRegistry
{
    public const DEFAULT_PREFIX = 'md-container';

    /** Built-in type names mapped to their HTML tag. */
    private const BUILT_IN = [
        'note'    => 'div',
        'info'    => 'div',
        'tip'     => 'div',
        'warning' => 'div',
        'danger'  => 'div',
        'details' => 'details',
    ];

    /**
     * @param array<string, ContainerType> $types
     */
    private function __construct(
        private readonly array $types,
        public readonly string $prefix,
    ) {
    }

    /**
     * Build a registry with the built-in types and the default prefix.
     */
    public static function withDefaults(): self
    {
        return self::fromConfig([]);
    }

    /**
     * Build a registry from a `plugins.markdown_containers` config array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $prefix = self::sanitizeClass((string) ($config['prefix'] ?? self::DEFAULT_PREFIX));
        if ($prefix === '') {
            $prefix = self::DEFAULT_PREFIX;
        }

        $tags = self::BUILT_IN;

        // types: add custom types or disable built-ins (name => false).
        $custom = $config['types'] ?? [];
        if (is_array($custom)) {
            foreach ($custom as $name => $definition) {
                $name = self::normalizeName((string) $name);
                if ($name === '') {
                    continue;
                }
                if ($definition === false || $definition === null) {
                    unset($tags[$name]);
                    continue;
                }
                $tag = 'div';
                if (is_array($definition) && isset($definition['tag'])) {
                    $tag = (string) $definition['tag'] === 'details' ? 'details' : 'div';
                }
                $tags[$name] = $tag;
            }
        }

        // classes: replace the full class string of a type.
        $overrides = $config['classes'] ?? [];
        $overrides = is_array($overrides) ? $overrides : [];

        $types = [];
        foreach ($tags as $name => $tag) {
            if (array_key_exists($name, $overrides)) {
                $class = self::sanitizeClass((string) $overrides[$name]);
            } else {
                $class = $prefix . ' ' . $prefix . '--' . $name;
            }
            $types[$name] = new ContainerType($name, $tag, $class);
        }

        return new self($types, $prefix);
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): ContainerType
    {
        if (!isset($this->types[$name])) {
            throw new \OutOfBoundsException(sprintf('Unknown container type "%s".', $name));
        }

        return $this->types[$name];
    }

    /**
     * The BEM element class used for a non-details container's title line.
     */
    public function titleClass(): string
    {
        return $this->prefix . '__title';
    }

    private static function normalizeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '', $name) ?? '';
    }

    private static function sanitizeClass(string $class): string
    {
        $class = preg_replace('/[^A-Za-z0-9 _-]+/', '', $class) ?? '';

        return trim((string) preg_replace('/\s+/', ' ', $class));
    }
}
