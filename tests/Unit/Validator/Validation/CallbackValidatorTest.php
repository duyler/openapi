<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Validation;

use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(CallbackValidator::class)]
final class CallbackValidatorTest extends TestCase
{
    private const string CALLBACK_RUNTIME_TEMPLATE_YAML_DOCUMENT_TRIGGER_PATH = '/trigger';

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * Anti-test guard: build the outer validator without the third
     * constructor argument. The constructor must default to strict
     * (fail-closed) so that direct-instantiation callers — frameworks that
     * use this library as a dependency and bypass OpenApiValidatorBuilder —
     * receive the same SSRF protection as builder callers. To verify,
     * temporarily revert the default in Validation\CallbackValidator
     * from `= true` back to `= false` and re-run this test — it must fail
     * because the validator would resolve the runtime expression as a
     * wildcard instead of throwing.
     */
    #[Test]
    public function outer_constructor_defaults_to_strict_when_called_without_flag(): void
    {
        $context = $this->buildDependencies(
            $this->buildDocumentWithCallback(),
        );

        $validator = new CallbackValidator($context);

        $request = $this->psrFactory->createServerRequest(
            'POST',
            '/attacker-controlled-url',
        );

        $this->expectException(UnresolvableCallbackPathException::class);
        $this->expectExceptionMessage('{$request.body#/callback_url}');

        $validator->validate($request, 'myCallback');
    }

    #[Test]
    public function outer_constructor_allows_lax_when_strict_false_passed_explicitly(): void
    {
        $context = $this->buildDependencies(
            $this->buildDocumentWithCallback(),
        );

        $validator = new CallbackValidator(
            $context,
            securityValidation: false,
            strictCallbackRuntimeTemplate: false,
        );

        $request = $this->psrFactory->createServerRequest(
            'POST',
            '/attacker-controlled-url',
        );

        $operation = $validator->validate($request, 'myCallback');

        $this->assertSame('myCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_delegates_to_inner_callback_validator_with_literal_path(): void
    {
        $context = $this->buildDependencies(
            $this->buildDocumentWithLiteralCallback('myCallback', '/cb/literal'),
        );
        $validator = new CallbackValidator($context, securityValidation: false);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/literal');

        $operation = $validator->validate($request, 'myCallback');

        $this->assertSame('myCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_returns_operation_with_callback_name_as_path(): void
    {
        $callbackName = 'orderEvent';
        $context = $this->buildDependencies(
            $this->buildDocumentWithLiteralCallback($callbackName, '/cb/event'),
        );
        $validator = new CallbackValidator($context, securityValidation: false);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/event');

        $operation = $validator->validate($request, $callbackName);

        $this->assertSame($callbackName, $operation->path);
        $this->assertNull($operation->operationId);
        $this->assertNull($operation->schemaOperation);
    }

    #[Test]
    public function validate_skips_security_validation_when_security_validation_false(): void
    {
        $securityScheme = new SecurityScheme(type: 'http', scheme: 'bearer');
        $callbackOperation = new SchemaOperation(
            operationId: 'myCallback',
            security: new SecurityRequirement([['bearerAuth' => []]]),
        );
        $document = $this->buildDocumentWithLiteralCallbackAndSecurity(
            'myCallback',
            '/cb/literal',
            $callbackOperation,
            $securityScheme,
        );
        $context = $this->buildDependencies($document);
        $validator = new CallbackValidator($context, securityValidation: false);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/literal');

        $operation = $validator->validate($request, 'myCallback');

        $this->assertSame('myCallback', $operation->path);
    }

    #[Test]
    public function validate_invokes_security_validation_when_security_validation_true(): void
    {
        $securityScheme = new SecurityScheme(type: 'http', scheme: 'bearer');
        $callbackOperation = new SchemaOperation(
            operationId: 'myCallback',
            security: new SecurityRequirement([['bearerAuth' => []]]),
        );
        $document = $this->buildDocumentWithLiteralCallbackAndSecurity(
            'myCallback',
            '/cb/literal',
            $callbackOperation,
            $securityScheme,
        );
        $context = $this->buildDependencies($document);
        $validator = new CallbackValidator($context, securityValidation: true);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/literal');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Security validation failed');

        $validator->validate($request, 'myCallback');
    }

    #[Test]
    public function validate_security_passes_when_authorization_header_present(): void
    {
        $securityScheme = new SecurityScheme(type: 'http', scheme: 'bearer');
        $callbackOperation = new SchemaOperation(
            operationId: 'myCallback',
            security: new SecurityRequirement([['bearerAuth' => []]]),
        );
        $document = $this->buildDocumentWithLiteralCallbackAndSecurity(
            'myCallback',
            '/cb/literal',
            $callbackOperation,
            $securityScheme,
        );
        $context = $this->buildDependencies($document);
        $validator = new CallbackValidator($context, securityValidation: true);

        $request = $this->psrFactory
            ->createServerRequest('POST', '/cb/literal')
            ->withHeader('Authorization', 'Bearer abc.def.ghi');

        $operation = $validator->validate($request, 'myCallback');

        $this->assertSame('myCallback', $operation->path);
    }

    #[Test]
    public function validate_skips_security_validation_when_security_requirements_null(): void
    {
        $callbackOperation = new SchemaOperation(operationId: 'myCallback');
        $document = $this->buildDocumentWithLiteralCallback('myCallback', '/cb/literal', $callbackOperation);
        $context = $this->buildDependencies($document);
        $validator = new CallbackValidator($context, securityValidation: true);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/literal');

        $operation = $validator->validate($request, 'myCallback');

        $this->assertSame('myCallback', $operation->path);
    }

    #[Test]
    public function validate_emits_started_and_finished_events_when_dispatcher_configured(): void
    {
        $dispatchedEvents = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(
                function (object $event) use (&$dispatchedEvents): object {
                    $dispatchedEvents[] = $event;

                    return $event;
                },
            );

        $document = $this->buildDocumentWithLiteralCallback('myCallback', '/cb/literal');
        $context = $this->buildDependencies($document, $dispatcher);
        $validator = new CallbackValidator($context, securityValidation: false);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/literal');

        $validator->validate($request, 'myCallback');

        $this->assertCount(2, $dispatchedEvents);
        $this->assertInstanceOf(ValidationStartedEvent::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(ValidationFinishedEvent::class, $dispatchedEvents[1]);
        $this->assertSame('myCallback', $dispatchedEvents[0]->path);
        $this->assertSame('POST', $dispatchedEvents[0]->method);
        $this->assertTrue($dispatchedEvents[1]->success);
    }

    #[Test]
    public function validate_propagates_unknown_callback_exception_from_inner_validator(): void
    {
        $document = $this->buildDocumentWithLiteralCallback('myCallback', '/cb/literal');
        $context = $this->buildDependencies($document);
        $validator = new CallbackValidator($context, securityValidation: false);

        $request = $this->psrFactory->createServerRequest('POST', '/cb/literal');

        $this->expectException(UnknownCallbackException::class);

        $validator->validate($request, 'missingCallback');
    }

    private function buildDocumentWithCallback(): OpenApiDocument
    {
        $callbackOperation = new SchemaOperation(operationId: 'myCallback');
        $callbackPathItem = new PathItem(post: $callbackOperation);

        $triggerOperation = new SchemaOperation(
            operationId: 'trigger',
            callbacks: new Callbacks([
                'myCallback' => [
                    '{$request.body#/callback_url}' => $callbackPathItem,
                ],
            ]),
        );

        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            paths: new Paths([
                self::CALLBACK_RUNTIME_TEMPLATE_YAML_DOCUMENT_TRIGGER_PATH => new PathItem(post: $triggerOperation),
            ]),
            components: new Components(
                callbacks: [
                    'myCallback' => new Callbacks([
                        'myCallback' => [
                            '{$request.body#/callback_url}' => $callbackPathItem,
                        ],
                    ]),
                ],
            ),
        );
    }

    private function buildDocumentWithLiteralCallback(
        string $callbackName,
        string $literalPath,
        ?SchemaOperation $operation = null,
    ): OpenApiDocument {
        $callbackOperation = $operation ?? new SchemaOperation(operationId: $callbackName);
        $callbackPathItem = new PathItem(post: $callbackOperation);

        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([
                        $callbackName => [$literalPath => $callbackPathItem],
                    ]),
                ],
            ),
        );
    }

    private function buildDocumentWithLiteralCallbackAndSecurity(
        string $callbackName,
        string $literalPath,
        SchemaOperation $operation,
        SecurityScheme $scheme,
    ): OpenApiDocument {
        $callbackPathItem = new PathItem(post: $operation);

        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([
                        $callbackName => [$literalPath => $callbackPathItem],
                    ]),
                ],
                securitySchemes: ['bearerAuth' => $scheme],
            ),
        );
    }

    private function buildDependencies(
        OpenApiDocument $document,
        ?EventDispatcherInterface $dispatcher = null,
    ): ValidatorDependencies {
        return new ValidatorDependencies(
            document: $document,
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            errorFormatter: new SimpleFormatter(),
            refResolver: new RefResolver(),
            eventDispatcher: $dispatcher,
        );
    }
}
