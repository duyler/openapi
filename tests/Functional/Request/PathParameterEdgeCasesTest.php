<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathParameterEdgeCasesTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function array_style_simple_three_items_matches_path_template(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Array Path API
  version: 1.0.0
paths:
  /users/{ids}:
    get:
      parameters:
        - name: ids
          in: path
          required: true
          style: simple
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/a,b,c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{ids}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function array_style_simple_three_items_satisfies_min_and_max_items(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Array Path API
  version: 1.0.0
paths:
  /users/{ids}:
    get:
      parameters:
        - name: ids
          in: path
          required: true
          style: simple
          schema:
            type: array
            items:
              type: string
            minItems: 3
            maxItems: 3
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/a,b,c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{ids}', $operation->path);
    }

    #[Test]
    public function array_style_simple_four_items_exceeds_max_items(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Array Path API
  version: 1.0.0
paths:
  /users/{ids}:
    get:
      parameters:
        - name: ids
          in: path
          required: true
          style: simple
          schema:
            type: array
            items:
              type: string
            maxItems: 3
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/a,b,c,d');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected MaxItemsError for 4 items exceeding maxItems: 3');
        } catch (MaxItemsError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(MaxItemsError::class, $caught);
        self::assertSame('maxItems', $caught->keyword());
        self::assertSame(4, $caught->params()['actual']);
        self::assertSame(3, $caught->params()['maxItems']);
    }

    #[Test]
    public function array_style_simple_single_item_violates_min_items(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Array Path API
  version: 1.0.0
paths:
  /users/{ids}:
    get:
      parameters:
        - name: ids
          in: path
          required: true
          style: simple
          schema:
            type: array
            items:
              type: string
            minItems: 2
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/a');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected MinItemsError for 1 item below minItems: 2');
        } catch (MinItemsError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(MinItemsError::class, $caught);
        self::assertSame('minItems', $caught->keyword());
        self::assertSame(1, $caught->params()['actual']);
        self::assertSame(2, $caught->params()['minItems']);
    }

    #[Test]
    public function encoded_slash_matches_single_segment_path_template(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: File API
  version: 1.0.0
paths:
  /files/{path}:
    get:
      parameters:
        - name: path
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/files/foo%2Fbar');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/files/{path}', $operation->path);
        $this->assertSame('/files/foo%2Fbar', $request->getUri()->getPath());
    }

    #[Test]
    public function encoded_slash_value_preserved_as_encoded_form(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: File API
  version: 1.0.0
paths:
  /files/{path}:
    get:
      parameters:
        - name: path
          in: path
          required: true
          schema:
            type: string
            const: 'foo%2Fbar'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/files/foo%2Fbar');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/files/{path}', $operation->path);
    }

    #[Test]
    public function encoded_slash_decoded_value_does_not_satisfy_const(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: File API
  version: 1.0.0
paths:
  /files/{path}:
    get:
      parameters:
        - name: path
          in: path
          required: true
          schema:
            type: string
            const: 'foo/bar'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/files/foo%2Fbar');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected ConstError when encoded slash does not match decoded const');
        } catch (ConstError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(ConstError::class, $caught);
        self::assertSame('const', $caught->keyword());
        self::assertSame('foo%2Fbar', $caught->params()['actual']);
        self::assertSame('foo/bar', $caught->params()['expected']);
    }

    #[Test]
    public function decoded_slash_does_not_match_single_segment_template(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: File API
  version: 1.0.0
paths:
  /files/{path}:
    get:
      parameters:
        - name: path
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/files/foo/bar');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected OperationNotFoundException when decoded slash creates extra path segments');
        } catch (OperationNotFoundException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(OperationNotFoundException::class, $caught);
        self::assertStringContainsString('No operation matches', $caught->getMessage());
    }

    #[Test]
    public function empty_segment_does_not_match_required_param_template(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Posts API
  version: 1.0.0
paths:
  /users/{userId}/posts/{postId}:
    get:
      parameters:
        - name: userId
          in: path
          required: true
          schema:
            type: string
        - name: postId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users//posts/123');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected OperationNotFoundException for empty segment in required path parameter');
        } catch (OperationNotFoundException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(OperationNotFoundException::class, $caught);
        self::assertStringContainsString('No operation matches', $caught->getMessage());
    }

    #[Test]
    public function non_empty_segment_matches_required_param_template(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Posts API
  version: 1.0.0
paths:
  /users/{userId}/posts/{postId}:
    get:
      parameters:
        - name: userId
          in: path
          required: true
          schema:
            type: string
        - name: postId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/42/posts/123');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{userId}/posts/{postId}', $operation->path);
    }

    #[Test]
    public function square_brackets_in_path_parse_without_regex_error(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Search API
  version: 1.0.0
paths:
  /search/{query}:
    get:
      parameters:
        - name: query
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search/[test]');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search/{query}', $operation->path);
        $this->assertSame('/search/%5Btest%5D', $request->getUri()->getPath());
    }

    #[Test]
    public function parentheses_in_path_parse_without_regex_error(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Search API
  version: 1.0.0
paths:
  /search/{query}:
    get:
      parameters:
        - name: query
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search/(test)');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search/{query}', $operation->path);
        $this->assertSame('/search/(test)', $request->getUri()->getPath());
    }

    #[Test]
    public function parentheses_value_extracted_unchanged_via_const_constraint(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Search API
  version: 1.0.0
paths:
  /search/{query}:
    get:
      parameters:
        - name: query
          in: path
          required: true
          schema:
            type: string
            const: '(test)'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search/(test)');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search/{query}', $operation->path);
    }

    #[Test]
    public function square_brackets_value_url_encoded_by_psr_via_const_constraint(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Search API
  version: 1.0.0
paths:
  /search/{query}:
    get:
      parameters:
        - name: query
          in: path
          required: true
          schema:
            type: string
            const: '%5Btest%5D'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search/[test]');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search/{query}', $operation->path);
    }

    #[Test]
    public function param_only_path_matches_root_template(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: ID API
  version: 1.0.0
paths:
  /{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
            const: '123'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/123');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function param_only_path_wrong_value_rejected_by_const(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: ID API
  version: 1.0.0
paths:
  /{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
            const: '123'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/abc');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected ConstError for path parameter value not matching const constraint');
        } catch (ConstError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(ConstError::class, $caught);
        self::assertSame('const', $caught->keyword());
        self::assertSame('abc', $caught->params()['actual']);
        self::assertSame('123', $caught->params()['expected']);
    }

    #[Test]
    public function param_only_path_pattern_rejects_invalid_value(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: ID API
  version: 1.0.0
paths:
  /{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
            pattern: '^[0-9]+$'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/abc');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected PatternMismatchError for path parameter value not matching pattern');
        } catch (PatternMismatchError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(PatternMismatchError::class, $caught);
        self::assertSame('pattern', $caught->keyword());
    }

    #[Test]
    public function ambiguous_paths_resolve_deterministically_across_repeated_requests(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Ambiguous API
  version: 1.0.0
paths:
  /{type}/info:
    get:
      parameters:
        - name: type
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
  /users/{action}:
    get:
      parameters:
        - name: action
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $firstOperation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users/info'),
        );
        $secondOperation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users/info'),
        );

        $this->assertSame($firstOperation->path, $secondOperation->path);
        $this->assertSame('/{type}/info', $firstOperation->path);
    }

    #[Test]
    public function ambiguous_paths_non_chosen_template_schema_does_not_apply(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Ambiguous API
  version: 1.0.0
paths:
  /{type}/info:
    get:
      parameters:
        - name: type
          in: path
          required: true
          schema:
            type: string
            const: 'users'
      responses:
        '200':
          description: OK
  /users/{action}:
    get:
      parameters:
        - name: action
          in: path
          required: true
          schema:
            type: string
            const: 'non-matching-failing-value'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users/info'),
        );

        $this->assertSame('/{type}/info', $operation->path);
    }

    #[Test]
    public function ambiguous_paths_chosen_template_value_passes_own_const(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Ambiguous API
  version: 1.0.0
paths:
  /{type}/info:
    get:
      parameters:
        - name: type
          in: path
          required: true
          schema:
            type: string
            const: 'users'
      responses:
        '200':
          description: OK
  /users/{action}:
    get:
      parameters:
        - name: action
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users/info'),
        );

        $this->assertSame('/{type}/info', $operation->path);
    }

    /**
     * RP-05: Path consisting only of a parameter `/{id}` (no static segments).
     *
     * The template compiles to regex `#^/(?P<id>[^/]+)$#`. A single-segment
     * value like `/123` matches and the parameter is extracted as "123".
     *
     * The extracted value is observed through a `const: '123'` constraint:
     * successful validation proves the parameter was extracted with the
     * expected value.
     */
    #[Test]
    public function rp_05_param_only_root_path_matches_single_segment_value(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: RP-05 ID API
  version: 1.0.0
paths:
  /{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
            const: '123'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/123'),
        );

        $this->assertSame('/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * RP-05 negative: `GET /` (root, no segment for the parameter).
     *
     * The compiled regex `#^/(?P<id>[^/]+)$#` requires at least one
     * non-slash character after the leading slash. An empty path value
     * fails to match, and the top-level validator raises
     * `OperationNotFoundException` ("Operation not found") because no
     * candidate template matches.
     */
    #[Test]
    public function rp_05_param_only_root_path_rejects_empty_value(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: RP-05 ID API
  version: 1.0.0
paths:
  /{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $caught = null;

        try {
            $validator->validateRequest(
                $this->psrFactory->createServerRequest('GET', '/'),
            );
            self::fail('Expected OperationNotFoundException when path parameter segment is empty');
        } catch (OperationNotFoundException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(OperationNotFoundException::class, $caught);
        self::assertStringContainsString('No operation matches', $caught->getMessage());
    }
}
