<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Concurrency;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

final class ValidationContextPerRequestTest extends TestCase
{
    private const string NESTED_SCHEMA_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Nested Validation API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/User'
      responses:
        '201':
          description: User created
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Item'
      responses:
        '201':
          description: Item created
components:
  schemas:
    User:
      type: object
      required:
        - name
        - contact
      properties:
        name:
          type: string
          minLength: 3
        contact:
          $ref: '#/components/schemas/Contact'
    Contact:
      type: object
      required:
        - label
      properties:
        label:
          type: string
          minLength: 2
    Item:
      type: object
      required:
        - title
      properties:
        title:
          type: string
          minLength: 1
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function context_not_shared_between_requests(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NESTED_SCHEMA_YAML)
            ->build();

        $firstFailing = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'name' => 'Alice',
                'contact' => ['label' => 'x'],
            ])));

        try {
            $validator->validateRequest($firstFailing);
            self::fail('First request must fail validation with too-short label');
        } catch (ValidationException $e) {
            $firstErrors = $e->getErrors();
            self::assertNotEmpty($firstErrors);
            self::assertSame('/contact/label', $firstErrors[0]->dataPath());
        }

        $secondFailing = $this->factory
            ->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['title' => ''])));

        try {
            $validator->validateRequest($secondFailing);
            self::fail('Second request must fail validation with empty title');
        } catch (ValidationException $e) {
            $secondErrors = $e->getErrors();
            self::assertNotEmpty($secondErrors);
            self::assertSame(
                '/title',
                $secondErrors[0]->dataPath(),
                'Second request dataPath must reference /title, not /contact/label from the previous request — '
                . 'breadcrumbs must not leak across requests on a shared validator.',
            );
        }
    }

    #[Test]
    public function independent_breadcrumbs_under_simulated_concurrent_validations(): void
    {
        $pool = new ValidatorPool();

        $contextA = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
        ));
        $contextB = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
        ));

        $contextA->enterBreadcrumb('users');
        $contextB->enterBreadcrumb('items');

        self::assertSame('/users', $contextA->breadcrumbs->currentPath());
        self::assertSame('/items', $contextB->breadcrumbs->currentPath());

        $contextA->enterBreadcrumb('0');
        $contextB->enterBreadcrumb('title');

        self::assertSame('/users/0', $contextA->breadcrumbs->currentPath());
        self::assertSame('/items/title', $contextB->breadcrumbs->currentPath());

        $contextA->leaveBreadcrumb();
        $contextB->leaveBreadcrumb();

        self::assertSame('/users', $contextA->breadcrumbs->currentPath());
        self::assertSame('/items', $contextB->breadcrumbs->currentPath());

        $contextA->leaveBreadcrumb();
        $contextB->leaveBreadcrumb();

        self::assertSame('/', $contextA->breadcrumbs->currentPath());
        self::assertSame('/', $contextB->breadcrumbs->currentPath());
    }

    #[Test]
    public function independent_depth_counters_under_simulated_concurrent_validations(): void
    {
        $pool = new ValidatorPool();

        $contextA = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));
        $contextB = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        self::assertSame(0, $contextA->depth());
        self::assertSame(0, $contextB->depth());

        $contextA->incrementDepth();
        $contextA->incrementDepth();
        $contextB->incrementDepth();

        self::assertSame(2, $contextA->depth(), 'Context A depth must be 2 (two increments on A only).');
        self::assertSame(1, $contextB->depth(), 'Context B depth must be 1 (one increment on B only).');

        $contextA->decrementDepth();
        $contextB->decrementDepth();

        self::assertSame(1, $contextA->depth());
        self::assertSame(0, $contextB->depth());
    }

    #[Test]
    public function from_init_contexts_have_fully_independent_breadcrumbs(): void
    {
        $pool = new ValidatorPool();

        $contextA = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
        ));
        $contextB = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
        ));

        $contextA->enterBreadcrumb('a');
        $contextB->enterBreadcrumb('b');

        self::assertSame('/a', $contextA->breadcrumbs->currentPath(), 'Context A must never observe context B breadcrumb.');
        self::assertSame('/b', $contextB->breadcrumbs->currentPath(), 'Context B must never observe context A breadcrumb.');
    }
}
