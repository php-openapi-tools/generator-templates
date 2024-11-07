<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Templates;

use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Contract\Package;
use OpenAPITools\Representation;
use OpenAPITools\Utils\File;
use WyriHaximus\SubSplitTools\Files;

final readonly class Templates implements FileGenerator
{
    public function __construct()
    {
    }

    /** @return iterable<File> */
    public function generate(Package $package, Representation\Namespaced\Representation $representation): iterable
    {
        if ($package->templates === null) {
            return [];
        }

        $vars                   = $package->templates->variables;
        $vars['package']        = $package;
        $vars['representation'] = $representation;

        foreach (Files::render($package->templates->dir, '', $vars) as $file) {
            yield new File(
                '',
                $file->fileName,
                $file->contents,
                File::DO_NOT_LOAD_ON_WRITE,
            );
        }
    }
}
