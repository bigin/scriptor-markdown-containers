# scriptor-markdown-containers

Fenced-container syntax for [Scriptor](https://scriptor-cms.dev) page content.
Write `:::note`, `:::warning`, `:::details "Summary"` (and your own types) in a
page's Markdown and the plugin renders them as styled, theme-neutral blocks,
with nesting and code-fence awareness.

```markdown
:::warning "Heads up"
This ships to production on Friday.

:::note
You can nest containers.
:::
:::
```

renders to

```html
<div class="md-container md-container--warning">
<p class="md-container__title">Heads up</p>
<p>This ships to production on Friday.</p>
<div class="md-container md-container--note">
<p>You can nest containers.</p>
</div>
</div>
```

## Install

This package is not on Packagist, so tell Composer where to find it with a
one-time `repositories` entry, then require it:

```bash
composer config repositories.scriptor-markdown-containers \
  vcs https://github.com/bigin/scriptor-markdown-containers
composer require bigins/scriptor-markdown-containers:^0.1
```

The first command adds a VCS repository to your `composer.json`; without it
`composer require` reports *"Could not find a version of package …"*. Scriptor
ships a clean `composer.json` with no plugin repositories declared, so this
step is required when installing into Scriptor too.

The plugin is **stateless**: it only subscribes to the frontend
`ContentRendering` event and owns no database schema, so there is no
`bin/scriptor plugin:install` step. `composer remove` is all it takes to
uninstall.

In Docker, add it to the `SCRIPTOR_PLUGINS` build arg like any other plugin
(see Scriptor's install docs).

To get the default look, copy `assets/containers.css` into your theme (or copy
its rules). Sites that map containers onto an existing CSS framework do not
need it (see [Configuration](#configuration)).

## Syntax

A container **opens** on a line of exactly `:::name` or `:::name "Title"` and
**closes** on a line of exactly `:::`:

```markdown
:::tip
Body Markdown here. **Bold**, lists, links all work.
:::
```

- The name must be a registered type (see below). An unknown name is left as
  literal text, so a stray `:::foo` never silently disappears.
- The optional quoted string after the name is a **title** for block types and
  the **summary** for `details`. Titles cannot themselves contain a `"`.
- Containers **nest**. Open another container inside a body and close it with
  its own `:::`. A closing `:::` always matches the nearest open container.
- `:::` lines inside a fenced code block (` ``` ` or `~~~`) are **not** parsed,
  so you can document the syntax in code samples.

## Built-in types

| Name | Tag | Default class |
|------|-----|---------------|
| `note` | `div` | `md-container md-container--note` |
| `info` | `div` | `md-container md-container--info` |
| `tip` | `div` | `md-container md-container--tip` |
| `warning` | `div` | `md-container md-container--warning` |
| `danger` | `div` | `md-container md-container--danger` |
| `details` | `details` | `md-container md-container--details` |

Block types render an optional title as
`<p class="md-container__title">…</p>`. `details` renders the title as its
`<summary>`.

## Configuration

All keys are optional and live under
`$config['plugins']['markdown_containers']` in
`data/settings/custom.scriptor-config.php`:

```php
return [
    'plugins' => [
        'markdown_containers' => [
            // BEM base for derived classes (default: 'md-container').
            'prefix' => 'md-container',

            // Replace a type's full class string, e.g. map onto UIkit.
            'classes' => [
                'note'    => 'uk-alert',
                'warning' => 'uk-alert uk-alert-warning',
            ],

            // Add your own types, or disable a built-in with `false`.
            'types' => [
                'aside' => ['tag' => 'div'],
                'faq'   => ['tag' => 'details'],
                'tip'   => false,
            ],
        ],
    ],
];
```

- **`prefix`** changes the BEM base, so with `cb` a `:::warning` becomes
  `cb cb--warning` and titles use `cb__title`.
- **`classes`** replaces the *entire* class string for a type. Use it to map
  containers onto an existing framework instead of shipping the bundled CSS.
- **`types`** adds custom types (`'name' => ['tag' => 'div'|'details']`) or
  disables a built-in (`'name' => false`). A disabled type's `:::name` falls
  back to literal text.

Every class string, whether derived, prefixed, or operator-supplied, is
filtered to `[A-Za-z0-9 _-]`, so a config value can never break out of the
`class` attribute.

## Security

- Container **bodies** are rendered through Scriptor's `Sanitizer::markdown()`
  (Parsedown safe mode), the same pipeline as normal page content. Raw HTML in
  a body is escaped exactly as it would be outside a container.
- The wrapper tags are assembled outside Markdown and never fed back through
  it, so they survive safe mode intact while the body stays sanitised.
- Titles and summaries are escaped with `htmlspecialchars(…, ENT_QUOTES)`.

## How it works

`ContentRendering` fires once per page. The plugin returns early when the
content holds no `:::` marker (Scriptor then runs its normal Markdown pass) and
yields to any listener that already produced HTML (first writer wins).
Otherwise it parses the content into a tree of text and container nodes, swaps
each container for a placeholder that survives a Markdown pass, renders the
surrounding text once, then splices the assembled container HTML back in,
innermost first.

## Development

```bash
composer install
composer test   # or: vendor/bin/phpunit
```

Unit tests cover the parser and renderer with a Markdown stub; an integration
suite renders through real Parsedown in safe mode (the configuration Scriptor
uses) to guard the HTML-escaping and code-fence behaviour.

## License

MIT. See [LICENSE](LICENSE).
