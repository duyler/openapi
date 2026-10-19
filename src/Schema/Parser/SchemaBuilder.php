<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\TypeFormatter;

use function is_array;
use function is_bool;
use function sprintf;

final readonly class SchemaBuilder
{
    public function __construct(private OpenApiBuildContext $context) {}

    public function buildSchema(mixed $data): Schema
    {
        if (is_bool($data) || is_array($data)) {
            return new SchemaFromArrayConverter(
                $this->context->documentVersion,
                $this->context->deprecationLogger,
            )->fromArray($data);
        }

        throw new InvalidSchemaException(
            sprintf('Expected array or boolean for schema, got %s', TypeFormatter::format($data)),
        );
    }
}
