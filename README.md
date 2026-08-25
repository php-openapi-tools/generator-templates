# generator-templates

Twig template file generator for [OpenAPI Tools](https://github.com/php-openapi-tools). Renders a directory of template files into generated package output using package metadata and the namespaced OpenAPI representation.

![Continuous Integration](https://github.com/php-openapi-tools/generator-templates/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/openapi-tools/generator-templates/v/stable.png)](https://packagist.org/packages/openapi-tools/generator-templates)
[![Total Downloads](https://poser.pugx.org/openapi-tools/generator-templates/downloads.png)](https://packagist.org/packages/openapi-tools/generator-templates/stats)
[![License](https://poser.pugx.org/openapi-tools/generator-templates/license.png)](https://packagist.org/packages/openapi-tools/generator-templates)

## Installation

To install via [Composer](https://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require openapi-tools/generator-templates
```

## Components

| Class | Purpose |
| --- | --- |
| `Templates` | `FileGenerator` that walks a template directory and yields rendered files |

`Templates` implements [`openapi-tools/contract`](https://github.com/php-openapi-tools/contract) `FileGenerator`. When a package has no template configuration (`Package::$templates` is `null`), the generator yields nothing.

## Usage

Configure a template directory on the package and register `Templates` in the generator list. Generation is typically run through [`openapi-tools/generator`](https://github.com/php-openapi-tools/generator):

```php
use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Generator\Templates\Templates;
use OpenAPITools\Generator\Schema\Schema;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;

$builderFactory = new BuilderFactory();

return new Configuration(
    new \OpenAPITools\Configuration\State('etc/state.json'),
    new Gathering('api.yaml', null, new Gathering\Schemas(true, true)),
    [
        new Package(
            new Package\Metadata('Example', 'Example API client', []),
            'api-clients',
            'example',
            null,
            null,
            null,
            new Package\Templates(__DIR__ . '/templates', []),
            new Package\Destination('example', 'src', 'tests'),
            new Namespace_(
                'ApiClients\Client\Example',
                'ApiClients\Tests\Client\Example',
            ),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(true, null),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State(['composer.json', 'composer.lock']),
            [
                new Schema($builderFactory),
                new Templates(),
            ],
        ),
    ],
);
```

You can also call the generator directly:

```php
use OpenAPITools\Generator\Templates\Templates;

foreach (new Templates()->generate($package, $representation->namespace($package->namespace)) as $file) {
    // $file->pathPrefix, $file->fqcn, $file->contents
}
```

Template output is emitted with `File::DO_NOT_LOAD_ON_WRITE`, because generated files are usually non-PHP assets such as `composer.json` or `README.md`.

## Template directory

Point `Package\Templates::$dir` at a directory on disk. `Templates` recursively walks every file beneath that directory (skipping `.` and `..`) and emits one generated file per template file.

Both the **relative output path** and the **file contents** are rendered through [Twig](https://twig.symfony.com/) via [`wyrihaximus/simple-twig`](https://github.com/WyriHaximus/simple-twig). That means template file names can contain Twig expressions, so output paths can depend on package data:

```
templates/
├── composer.json
├── README.md
└── {{ package.name }}.extra.md   → rendered path, e.g. example.extra.md
```

The path passed to the output `File` is the template path relative to the template directory, after rendering.

## Template variables

Every template receives at least these Twig variables:

| Variable | Type | Description |
| --- | --- | --- |
| `package` | `Package` | Full package configuration (metadata, namespaces, QA settings, destination, and so on) |
| `representation` | `Representation\Namespaced\Representation` | Namespaced OpenAPI representation for the current package |

Additional variables can be supplied through `Package\Templates::$variables`. Those entries are merged into the Twig context before rendering.

### `package`

Common `package` fields used in templates:

| Field | Description |
| --- | --- |
| `package.name` | Package name (for example `github`) |
| `package.vendor` | Vendor segment (for example `api-clients`) |
| `package.metadata.name` | Human-readable API name |
| `package.metadata.description` | Package description |
| `package.namespace.source` | Source PSR-4 namespace |
| `package.namespace.test` | Test PSR-4 namespace |
| `package.qa.phpstan.enabled` | Whether PHPStan is enabled for the generated package |
| `package.qa.phpstan.configFilePath` | Path to the PHPStan config included from `composer.json` |

See [`openapi-tools/contract`](https://github.com/php-openapi-tools/contract) `Package` for the full property list.

### `representation`

The namespaced representation exposes gathered API structure:

| Field | Description |
| --- | --- |
| `representation.client` | Client metadata, including `baseUrl` and `paths` |
| `representation.client.paths` | List of paths, each with `operations` |
| `representation.schemas` | Schema definitions for the package namespace |
| `representation.webHooks` | Webhook events for the package namespace |

Each operation exposes fields such as `operationId`, `summary`, `matchMethod`, `path`, `nameCamel`, `groupCamel`, `parameters`, and `externalDocs`. See [`openapi-tools/representation`](https://github.com/php-openapi-tools/representation) for the full object graph.

### Custom variables

Pass extra context for template logic that is not derived from the OpenAPI spec:

```php
new Package\Templates(__DIR__ . '/templates', [
    'requires' => [
        ['name' => 'api-clients/other-client', 'version' => '^1.0'],
    ],
    'requires-dev' => [],
    'suggests' => [],
]),
```

Templates can iterate over those values with standard Twig syntax:

```twig
{% for require in requires %}
    "{{ require.name }}": "{{ require.version }}",
{% endfor %}
```

## Examples

This repository includes example templates under [`tests/templates/`](tests/templates/) that generate:

| Output file | Template behaviour |
| --- | --- |
| `.editorconfig` | Copied verbatim (no Twig expressions) |
| `composer.json` | Package name, description, PSR-4 namespaces, optional PHPStan `extra`, and custom dependency lists |
| `README.md` | Badges, package metadata, and per-operation usage examples from `representation.client.paths` |

The [`TemplatesTest`](tests/TemplatesTest.php) asserts that all three files are generated and that Twig expressions are resolved against the GitHub API fixture from [`openapi-tools/test-data`](https://github.com/php-openapi-tools/test-data).

A minimal `composer.json` fragment:

```json
{
  "name": "api-clients/{{ package.name }}",
  "description": "{{ package.metadata.description }}",
  "autoload": {
    "psr-4": {
      "{{ package.namespace.source|trim('\\', 'left')|replace({'\\': '\\\\'}) }}": "src/"
    }
  }
}
```

An operation listing in `README.md` (see [`tests/templates/README.md`](tests/templates/README.md) for the full template):

```twig
{% for path in representation.client.paths %}
{% for operation in path.operations %}
### {{ operation.operationId }}

{{ operation.summary }}
{# ... usage examples for call() and operations() ... #}
{% endfor %}
{% endfor %}
```

## Testing

Tests use shared fixtures from [`openapi-tools/test-data`](https://github.com/php-openapi-tools/test-data).

```shell
make unit-testing
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
