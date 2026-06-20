<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Event;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ValidationWarningEventTest extends TestCase
{
    private const string DEPRECATED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Deprecated Property API
  version: 1.0.0
paths:
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
          description: Created
components:
  schemas:
    Item:
      type: object
      properties:
        name:
          type: string
        oldField:
          type: string
          deprecated: true
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function warning_event_dispatched_when_request_body_uses_deprecated_property(): void
    {
        $warnings = [];
        $dispatcher = $this->createWarningCollector($warnings);

        $validator = $this->buildValidator(self::DEPRECATED_SPEC, $dispatcher);

        $validator->validateRequest($this->createPostRequest('{"name":"widget","oldField":"legacy"}'));

        self::assertCount(1, $warnings);

        $event = $warnings[0];
        self::assertSame('oldField', $event->propertyName);
        self::assertSame('Property "oldField" is deprecated', $event->message);
        self::assertStringContainsString('oldField', $event->propertyPath);
        self::assertNull($event->schemaRef);
    }

    #[Test]
    public function warning_event_not_dispatched_when_deprecated_property_omitted(): void
    {
        $warnings = [];
        $dispatcher = $this->createWarningCollector($warnings);

        $validator = $this->buildValidator(self::DEPRECATED_SPEC, $dispatcher);

        $validator->validateRequest($this->createPostRequest('{"name":"widget"}'));

        self::assertSame([], $warnings);
    }

    #[Test]
    public function warning_event_not_dispatched_when_schema_has_no_deprecated_properties(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: No Deprecated API
  version: 1.0.0
paths:
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
      responses:
        '201':
          description: Created
YAML;

        $warnings = [];
        $dispatcher = $this->createWarningCollector($warnings);

        $validator = $this->buildValidator($yaml, $dispatcher);

        $validator->validateRequest($this->createPostRequest('{"name":"widget"}'));

        self::assertSame([], $warnings);
    }

    #[Test]
    public function warning_event_dispatched_for_each_deprecated_property_used(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Multiple Deprecated API
  version: 1.0.0
paths:
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
          description: Created
components:
  schemas:
    Item:
      type: object
      properties:
        name:
          type: string
        legacyA:
          type: string
          deprecated: true
        legacyB:
          type: string
          deprecated: true
YAML;

        $warnings = [];
        $dispatcher = $this->createWarningCollector($warnings);

        $validator = $this->buildValidator($yaml, $dispatcher);

        $validator->validateRequest($this->createPostRequest('{"name":"widget","legacyA":"a","legacyB":"b"}'));

        self::assertCount(2, $warnings);
        self::assertSame('legacyA', $warnings[0]->propertyName);
        self::assertSame('legacyB', $warnings[1]->propertyName);
    }

    /**
     * @param list<ValidationWarningEvent> $warnings
     */
    private function createWarningCollector(array &$warnings): ArrayDispatcher
    {
        return new ArrayDispatcher([
            ValidationWarningEvent::class => [
                static function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);
    }

    private function buildValidator(string $yaml, ArrayDispatcher $dispatcher): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();
    }

    private function createPostRequest(string $body): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }
}
