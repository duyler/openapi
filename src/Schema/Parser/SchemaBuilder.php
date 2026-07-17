<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Validator\TypeFormatter;

use function implode;
use function is_array;
use function is_bool;
use function sprintf;

/**
 * Builds OpenAPI Schema / Discriminator / Xml objects.
 *
 * The heavy lifting of parsing a Schema wire array lives in
 * {@see SchemaFromArrayConverter} (Task 22, P-056). This builder keeps the
 * Schema entry point for the parser pipeline and exposes the standalone
 * Discriminator / Xml constructors that {@see ComponentsBuilder} and
 * {@see PathItemBuilder} need for non-schema object shapes.
 */
final readonly class SchemaBuilder
{
    private const string DEPRECATION_VERSION = '3.2.0';

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

    public function buildDiscriminator(array $data): Discriminator
    {
        return new Discriminator(
            propertyName: TypeHelper::asStringOrNull($data['propertyName'] ?? null),
            mapping: TypeHelper::asStringMapOrNull($data['mapping'] ?? null),
            defaultMapping: TypeHelper::asStringOrNull($data['defaultMapping'] ?? null),
        );
    }

    public function buildXml(array $data): Xml
    {
        if ($this->context->shouldWarnDeprecation()) {
            if (isset($data['attribute'])) {
                $this->context->deprecationLogger->warn(
                    'attribute',
                    'XML Object',
                    self::DEPRECATION_VERSION,
                    'nodeType: "attribute"',
                );
            }

            if (isset($data['wrapped'])) {
                $this->context->deprecationLogger->warn(
                    'wrapped',
                    'XML Object',
                    self::DEPRECATION_VERSION,
                );
            }
        }

        $xml = new Xml(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            namespace: TypeHelper::asStringOrNull($data['namespace'] ?? null),
            prefix: TypeHelper::asStringOrNull($data['prefix'] ?? null),
            attribute: TypeHelper::asBoolOrNull($data['attribute'] ?? null),
            wrapped: TypeHelper::asBoolOrNull($data['wrapped'] ?? null),
            nodeType: TypeHelper::asStringOrNull($data['nodeType'] ?? null),
        );

        if (null !== $xml->nodeType && !Xml::isValidNodeType($xml->nodeType)) {
            throw new InvalidSchemaException(
                sprintf(
                    'Invalid XML nodeType "%s". Must be one of: %s',
                    $xml->nodeType,
                    implode(', ', Xml::VALID_NODE_TYPES),
                ),
            );
        }

        return $xml;
    }
}
