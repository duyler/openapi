<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RequestBodyXmlMappingTest extends TestCase
{
    private RequestBodyValidatorWithContext $validator;

    protected function setUp(): void
    {
        $this->validator = $this->createValidator(new ValidatorConfiguration());
    }

    #[Test]
    public function product_with_nulls_fixture_is_valid_against_union_schema(): void
    {
        $xml = <<<'XML_WRAP'
        <product>
            <description>Thing</description>
            <related xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="true"/>
        </product>
        XML_WRAP;

        $this->validator->validate($xml, 'application/xml', new RequestBody(
            required: true,
            content: new Content([
                'application/xml' => new MediaType(schema: $this->productSchema()),
            ]),
        ));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function omitted_non_nullable_attribute_fails_required_validation(): void
    {
        $xml = '<product><description>Thing</description>'
            . '<related xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="true"/></product>';

        $schema = new Schema(
            type: 'object',
            xml: new Xml(name: 'product'),
            required: ['count'],
            properties: [
                'count' => new Schema(type: 'number', xml: new Xml(nodeType: 'attribute')),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate($xml, 'application/xml', new RequestBody(
            required: true,
            content: new Content([
                'application/xml' => new MediaType(schema: $schema),
            ]),
        ));
    }

    #[Test]
    public function product_no_nulls_fixture_is_valid_and_maps_related_to_empty_object(): void
    {
        $schema = $this->productSchema();
        $xml = '<product count="42"><description>Thing</description><related></related></product>';

        $this->createValidator(new ValidatorConfiguration(coercion: true))->validate(
            $xml,
            'application/xml',
            new RequestBody(
                required: true,
                content: new Content(['application/xml' => new MediaType(schema: $schema)]),
            ),
        );

        $parsed = new XmlBodyParser()->parse($xml, $schema);

        $this->assertIsArray($parsed);
        $this->assertSame([], $parsed['related']);
    }

    private function createValidator(ValidatorConfiguration $configuration): RequestBodyValidatorWithContext
    {
        $pool = new ValidatorPool();
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
        );

        return new RequestBodyValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $pool,
                refResolver: new RefResolver(),
                statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
                formatRegistry: BuiltinFormats::create(),
                bodyParser: new BodyParser(
                    jsonParser: new JsonBodyParser(),
                    formParser: new FormBodyParser(new QueryParser()),
                    multipartParser: new MultipartBodyParser(),
                    textParser: new TextBodyParser(),
                    xmlParser: new XmlBodyParser(),
                ),
            ),
            configuration: $configuration,
        );
    }

    private function productSchema(): Schema
    {
        return new Schema(
            type: 'object',
            xml: new Xml(name: 'product'),
            required: ['count', 'description', 'related'],
            properties: [
                'count' => new Schema(type: ['number', 'null'], xml: new Xml(nodeType: 'attribute')),
                'rating' => new Schema(type: 'string', xml: new Xml(nodeType: 'attribute')),
                'description' => new Schema(type: 'string'),
                'related' => new Schema(type: ['object', 'null']),
            ],
        );
    }
}
