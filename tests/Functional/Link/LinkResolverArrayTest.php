<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Link;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkResolverArrayTest extends TestCase
{
    private const string LINK_ARRAY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Link Array Indexing API
  version: 1.0.0
paths: {}
components:
  links:
    GetObjectItem:
      operationId: getItem
      parameters:
        itemId: '$response.body#/id'
    GetFirstItem:
      operationId: getItem
      parameters:
        itemId: '$response.body#/0'
    GetOutOfRangeItem:
      operationId: getItem
      parameters:
        itemId: '$response.body#/99'
YAML;

    #[Test]
    public function resolves_object_property_via_json_pointer(): void
    {
        $validator = $this->buildValidator();

        $result = $validator->resolveLink('GetObjectItem', ['id' => 42, 'name' => 'first']);

        $this->assertSame(42, $result['parameters']['itemId']);
    }

    #[Test]
    public function resolves_first_array_element_via_json_pointer_index(): void
    {
        $validator = $this->buildValidator();

        $result = $validator->resolveLink('GetFirstItem', [['id' => 42], ['id' => 99]]);

        $this->assertSame(['id' => 42], $result['parameters']['itemId']);
    }

    #[Test]
    public function rejects_index_on_empty_array_with_exception(): void
    {
        $validator = $this->buildValidator();

        $this->expectException(RefResolutionException::class);

        $validator->resolveLink('GetFirstItem', []);
    }

    #[Test]
    public function rejects_out_of_bounds_index_with_exception(): void
    {
        $validator = $this->buildValidator();

        $this->expectException(RefResolutionException::class);

        $validator->resolveLink('GetOutOfRangeItem', [['id' => 42], ['id' => 99]]);
    }

    #[Test]
    public function returns_null_for_numeric_index_on_object_body(): void
    {
        $validator = $this->buildValidator();

        $result = $validator->resolveLink('GetFirstItem', ['id' => 42, 'name' => 'first']);

        $this->assertNull($result['parameters']['itemId']);
    }

    private function buildValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LINK_ARRAY_SPEC)
            ->build();
    }
}
