<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;

/** @internal */
final readonly class ScalarSchemaSerializer
{
    public function extractWire(Schema $schema, string $name): mixed
    {
        return match ($name) {
            'title' => $schema->title,
            'description' => $schema->description,
            'deprecated' => $schema->deprecated ?: null,
            'readOnly' => $schema->readOnly ?: null,
            'writeOnly' => $schema->writeOnly ?: null,
            'type' => $schema->type,
            'nullable' => $schema->nullable ?: null,
            'multipleOf' => $schema->multipleOf,
            'maximum' => $schema->maximum,
            'exclusiveMaximum' => $schema->exclusiveMaximum,
            'minimum' => $schema->minimum,
            'exclusiveMinimum' => $schema->exclusiveMinimum,
            'maxLength' => $schema->maxLength,
            'minLength' => $schema->minLength,
            'pattern' => $schema->pattern,
            'example' => $schema->example,
            'examples' => $schema->examples,
            'enum' => $schema->enum,
            'format' => $schema->format,
            'contentEncoding' => $schema->contentEncoding,
            'contentMediaType' => $schema->contentMediaType,
            'jsonSchemaDialect' => $schema->jsonSchemaDialect,
            'discriminator' => $schema->discriminator,
            'xml' => $schema->xml,
            default => null,
        };
    }

    public function extractSnapshot(Schema $schema, FieldMetadata $field): mixed
    {
        if ('discriminator' === $field->name) {
            return $this->discriminatorToArray($schema->discriminator);
        }

        if ('xml' === $field->name) {
            return $this->xmlToArray($schema->xml);
        }

        if ($field->hasSentinel) {
            return $this->extractSentinel($schema, $field)?->value;
        }

        return $this->extractWire($schema, $field->name);
    }

    public function extractSentinel(Schema $schema, FieldMetadata $field): ?SentinelValue
    {
        return match ($field->name) {
            'default' => $schema->hasDefault ? new SentinelValue($schema->default) : null,
            'const' => $schema->hasConst ? new SentinelValue($schema->const) : null,
            default => null,
        };
    }

    public function discriminatorToArray(?Discriminator $discriminator): ?array
    {
        if (null === $discriminator) {
            return null;
        }

        return [
            'propertyName' => $discriminator->propertyName,
            'mapping' => $discriminator->mapping,
            'defaultMapping' => $discriminator->defaultMapping,
        ];
    }

    public function xmlToArray(?Xml $xml): ?array
    {
        if (null === $xml) {
            return null;
        }

        return [
            'name' => $xml->name,
            'namespace' => $xml->namespace,
            'prefix' => $xml->prefix,
            'attribute' => $xml->attribute,
            'wrapped' => $xml->wrapped,
            'nodeType' => $xml->nodeType,
        ];
    }
}
