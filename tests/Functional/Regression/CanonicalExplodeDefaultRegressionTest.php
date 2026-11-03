<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function parse_str;
use function parse_url;
use function sprintf;
use function str_replace;

use const PHP_URL_QUERY;

final class CanonicalExplodeDefaultRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.0.0
info:
  title: Canonical Explode Default
  version: '1'
paths:
  /tags:
    get:
      operationId: listTags
      parameters:
        - name: tags
          in: query
          style: form
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function implicit_explode_query_form_array_accepts_repeated_query_parameters(): void
    {
        $validator = $this->build();

        $operation = $validator->validateRequest($this->request('/tags?tags=php&tags=go'));

        self::assertSame('/tags', $operation->path);
    }

    #[Test]
    public function legacy_explode_defaults_reject_repeated_query_parameters(): void
    {
        $validator = $this->build(static fn(OpenApiValidatorBuilder $builder) => $builder->withLegacyExplodeDefaults());

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($this->request('/tags?tags=php&tags=go'));
    }

    #[Test]
    public function explicit_explode_false_rejects_repeated_query_parameters(): void
    {
        $spec = str_replace('          style: form', "          style: form\n          explode: false", self::SPEC);
        $validator = $this->build(spec: $spec);

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($this->request('/tags?tags=php&tags=go'));
    }

    #[Test]
    public function explicit_explode_true_accepts_repeated_query_parameters(): void
    {
        $spec = str_replace('          style: form', "          style: form\n          explode: true", self::SPEC);
        $validator = $this->build(spec: $spec);

        $operation = $validator->validateRequest($this->request('/tags?tags=php&tags=go'));

        self::assertSame('/tags', $operation->path);
    }

    private function build(?callable $configure = null, ?string $spec = null): OpenApiValidatorInterface
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString($spec ?? self::SPEC);

        $builder = null === $configure ? $builder : $configure($builder);

        try {
            return $builder->build();
        } catch (BuilderException $e) {
            self::fail(sprintf('Spec must build before request validation: %s', $e->getMessage()));
        }
    }

    private function request(string $uri): ServerRequestInterface
    {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return new Psr17Factory()
            ->createServerRequest('GET', 'http://localhost' . $uri)
            ->withQueryParams($query);
    }
}
