<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\PathFinder;

final class SpecBuilders
{
    public static function documentWithTitle(string $title, string $openapiVersion = '3.1.0'): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: $openapiVersion,
            info: new InfoObject(
                title: $title,
                version: '1.0.0',
            ),
        );
    }

    public static function pathFinder(string $yaml): PathFinder
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        return new PathFinder($document);
    }
}
