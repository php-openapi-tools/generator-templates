<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Templates;

use FilesystemIterator;
use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Contract\Package;
use OpenAPITools\Representation;
use OpenAPITools\Utils\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function file;
use function implode;
use function rtrim;
use function strlen;
use function substr;
use function WyriHaximus\Twig\render;

use const DIRECTORY_SEPARATOR;

final readonly class Templates implements FileGenerator
{
    /** @return iterable<File> */
    public function generate(Package $package, Representation\Namespaced\Representation $representation): iterable
    {
        if ($package->templates === null) {
            return;
        }

        /** @var array<string, mixed> $vars */
        $vars                   = $package->templates->variables ?? [];
        $vars['package']        = $package;
        $vars['representation'] = $representation;

        $directory = rtrim($package->templates->dir, DIRECTORY_SEPARATOR);

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS,
                ),
            ) as $fileInfo
        ) {
            if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile()) {
                continue;
            }

            $fileName = $fileInfo->getPathname();

            $lines = file($fileName);
            if ($lines === false) {
                continue;
            }

            $contents = implode('', $lines);

            /**
             * The path is rendered as well as the contents, so a template can
             * name its output after the package it is being rendered for.
             */
            yield new File(
                '',
                render(substr($fileName, strlen($directory) + 1), $vars),
                render($contents, $vars),
                File::DO_NOT_LOAD_ON_WRITE,
            );
        }
    }
}
