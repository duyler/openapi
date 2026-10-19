<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Regression suite for issue #60: parameter coercion did not recurse into
 * `object` properties or `array` items, so a nested `integer` / `number` /
 * `boolean` was unsatisfiable — query, path, header and cookie values arrive
 * as strings, and coercion was the only path to a non-string leaf.
 *
 * Anti-test: removing the `object` / `array` arms from
 * {@see TypeCoercer::coerceToType()} makes every nested case below fail again.
 *
 * @internal
 */
final class NestedParameterCoercionRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.0.0
info: { title: Coercion API, version: '1' }
paths:
  /things:
    get:
      operationId: listThings
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
        - name: page
          in: query
          style: deepObject
          schema:
            type: object
            properties:
              limit:
                type: integer
        - name: filter
          in: query
          style: deepObject
          schema:
            type: object
            properties:
              enabled:
                type: boolean
        - name: ids
          in: query
          style: form
          explode: false
          schema:
            type: array
            items:
              type: integer
      responses:
        '200': { description: ok }
YAML;

    #[Test]
    public function top_level_integer_query_parameter_coerces(): void
    {
        $operation = $this->build()->validateRequest($this->request('/things?limit=10'));

        self::assertSame('/things', $operation->path);
    }

    #[Test]
    public function object_property_integer_query_parameter_coerces(): void
    {
        $operation = $this->build()->validateRequest($this->request('/things?page[limit]=3'));

        self::assertSame('/things', $operation->path);
    }

    #[Test]
    public function object_property_boolean_query_parameter_coerces(): void
    {
        $operation = $this->build()->validateRequest($this->request('/things?filter[enabled]=true'));

        self::assertSame('/things', $operation->path);
    }

    #[Test]
    public function array_items_integer_query_parameter_coerces(): void
    {
        $operation = $this->build()->validateRequest($this->request('/things?ids=1,2,3'));

        self::assertSame('/things', $operation->path);
    }

    private function build(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->enableCoercion()
            ->build();
    }

    private function request(string $uri): ServerRequestInterface
    {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return new Psr17Factory()
            ->createServerRequest('GET', 'http://localhost' . $uri)
            ->withQueryParams($query);
    }
}
