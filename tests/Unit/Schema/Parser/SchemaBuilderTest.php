<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\SchemaBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $schemaBuilder;
    private OpenApiBuildContext $context;

    protected function setUp(): void
    {
        $this->context = new OpenApiBuildContext();
        $this->schemaBuilder = $this->context->schemaBuilder;
    }

    #[Test]
    public function build_schema_from_bool_true_returns_open_schema(): void
    {
        $schema = $this->schemaBuilder->buildSchema(true);

        self::assertInstanceOf(Schema::class, $schema);
        self::assertNull($schema->not);
    }

    #[Test]
    public function build_schema_from_bool_false_returns_closed_schema(): void
    {
        $schema = $this->schemaBuilder->buildSchema(false);

        self::assertInstanceOf(Schema::class, $schema);
        self::assertInstanceOf(Schema::class, $schema->not);
    }

    #[Test]
    public function build_schema_from_array_returns_typed_schema(): void
    {
        $schema = $this->schemaBuilder->buildSchema([
            'type' => 'object',
            'title' => 'User',
            'description' => 'A user record',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['id'],
        ]);

        self::assertSame('object', $schema->type);
        self::assertSame('User', $schema->title);
        self::assertSame('A user record', $schema->description);
        self::assertNotNull($schema->properties);
        self::assertArrayHasKey('id', $schema->properties);
        self::assertSame(['id'], $schema->required);
    }

    #[Test]
    public function build_schema_throws_on_invalid_input_type(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema, got string');

        $this->schemaBuilder->buildSchema('not-a-schema');
    }

    #[Test]
    public function build_schema_passes_document_version_to_converter(): void
    {
        $this->context->documentVersion = '3.0.0';

        $schema = $this->schemaBuilder->buildSchema([
            'type' => 'integer',
            'minimum' => 5,
            'exclusiveMinimum' => true,
        ]);

        self::assertSame(5.0, $schema->exclusiveMinimum);
        self::assertSame(5.0, $schema->minimum);
    }

    #[Test]
    public function build_discriminator_with_mapping(): void
    {
        $discriminator = $this->schemaBuilder->buildDiscriminator([
            'propertyName' => 'petType',
            'mapping' => [
                'dog' => '#/components/schemas/Dog',
                'cat' => '#/components/schemas/Cat',
            ],
            'defaultMapping' => 'dog',
        ]);

        self::assertInstanceOf(Discriminator::class, $discriminator);
        self::assertSame('petType', $discriminator->propertyName);
        self::assertSame('#/components/schemas/Dog', $discriminator->mapping['dog']);
        self::assertSame('#/components/schemas/Cat', $discriminator->mapping['cat']);
        self::assertSame('dog', $discriminator->defaultMapping);
    }

    #[Test]
    public function build_discriminator_with_empty_array(): void
    {
        $discriminator = $this->schemaBuilder->buildDiscriminator([]);

        self::assertNull($discriminator->propertyName);
        self::assertNull($discriminator->mapping);
        self::assertNull($discriminator->defaultMapping);
    }

    #[Test]
    public function build_xml_with_full_fields(): void
    {
        $xml = $this->schemaBuilder->buildXml([
            'name' => 'user',
            'namespace' => 'https://example.com/ns',
            'prefix' => 'ex',
            'attribute' => false,
            'wrapped' => false,
            'nodeType' => 'element',
        ]);

        self::assertInstanceOf(Xml::class, $xml);
        self::assertSame('user', $xml->name);
        self::assertSame('https://example.com/ns', $xml->namespace);
        self::assertSame('ex', $xml->prefix);
        self::assertSame('element', $xml->nodeType);
    }

    #[Test]
    public function build_xml_throws_on_invalid_node_type(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Invalid XML nodeType');

        $this->schemaBuilder->buildXml(['nodeType' => 'bogus']);
    }

    #[Test]
    public function build_xml_logs_deprecation_for_attribute_under_3_2(): void
    {
        $this->context->documentVersion = '3.2.0';

        $this->schemaBuilder->buildXml(['name' => 'user', 'attribute' => true]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_xml_logs_deprecation_for_wrapped_under_3_2(): void
    {
        $this->context->documentVersion = '3.2.0';

        $this->schemaBuilder->buildXml(['name' => 'user', 'wrapped' => true]);

        $this->addToAssertionCount(1);
    }
}
