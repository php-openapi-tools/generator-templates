<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Templates;

use cebe\openapi\Reader;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Gatherer\Gatherer;
use OpenAPITools\Generator\Templates\Templates;
use OpenAPITools\Representation\Representation;
use OpenAPITools\TestData\DataSet;
use OpenAPITools\TestData\Provider;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use function file_get_contents;
use function is_string;

use const DIRECTORY_SEPARATOR;

final class TemplatesTest extends TestCase
{
    #[Test]
    #[DataProviderExternal(Provider::class, 'sets')]
    public function generate(DataSet $dataSet): void
    {
        $representation = self::loadSpec($dataSet->fileName);

        $package = new Package(
            new Package\Metadata(
                'GitHub',
                'Fully type safe generated GitHub REST API client',
                [],
            ),
            'api-clients',
            'github',
            'git@github.com:php-api-clients/github.git',
            'v0.2.x',
            null,
            new Package\Templates(
                __DIR__ . '/templates',
                [],
            ),
            new Package\Destination(
                'github',
                'src',
                'tests',
            ),
            new Namespace_(
                'ApiClients\Client\GitHub',
                'ApiClients\Tests\Client\GitHub',
            ),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(
                    true,
                    'etc/phpstan-extension.neon',
                ),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State(
                [
                    'composer.json',
                    'composer.lock',
                ],
            ),
            [],
        );

        $generatedFiles = [...(new Templates())->generate($package, $representation->namespace($package->namespace))];
        $files          = [];
        foreach ($generatedFiles as $generatedFile) {
            $files[$generatedFile->fqcn] = new File(
                $generatedFile->pathPrefix,
                $generatedFile->fqcn,
                is_string($generatedFile->contents) ? $generatedFile->contents : (new Standard())->prettyPrint([
                    new Node\Stmt\Declare_([
                        new Node\Stmt\DeclareDeclare('strict_types', new Node\Scalar\LNumber(1)),
                    ]),
                    $generatedFile->contents,
                ]),
                File::DO_NOT_LOAD_ON_WRITE,
            );
        }

//        self::assertSame([], $files);
        self::assertCount(3, $files);

        self::assertArrayHasKey('/.editorconfig', $files);
        self::assertArrayHasKey('/composer.json', $files);
        self::assertArrayHasKey('/README.md', $files);

        self::assertSame(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '.editorconfig'), $files['/.editorconfig']->contents);
        self::assertNotSame(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'composer.json'), $files['/composer.json']->contents);
        self::assertNotSame(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'README.md'), $files['/README.md']->contents);

        self::assertStringContainsString('"name": "api-clients/github"', $files['/composer.json']->contents);
        self::assertStringContainsString('"ApiClients\\\\Client\\\\GitHub": "src/"', $files['/composer.json']->contents);
        self::assertStringContainsString('"ApiClients\\\\Tests\\\\Client\\\\GitHub": "tests/"', $files['/composer.json']->contents);
        self::assertStringContainsString('"etc/phpstan-extension.neon"', $files['/composer.json']->contents);

        self::assertStringContainsString('### root', $files['/README.md']->contents);
        self::assertStringContainsString('$client->call(\'GET /\');', $files['/README.md']->contents);
        self::assertStringContainsString('$client->operations()->()->root(', $files['/README.md']->contents);
    }

    private static function loadSpec(string $dataSetName): Representation
    {
        return Gatherer::gather(
            Reader::readFromYamlFile($dataSetName),
            new Gathering(
                $dataSetName,
                null,
                new Gathering\Schemas(
                    true,
                    true,
                ),
            ),
        );
    }
}
