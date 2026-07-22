<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\CallbackValidator;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class CallbackValidatorTest extends TestCase
{
    private CallbackValidator $callbackValidator;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        // Lax mode is opted into explicitly: the historical tests in this
        // file exercise the routing/matching/resolution logic of the
        // inner validator with runtime-template expressions treated as
        // wildcards. Strict-mode defaults are covered separately by
        // inner_constructor_defaults_to_strict_when_called_without_flag.
        $this->callbackValidator = new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
            strictCallbackRuntimeTemplate: false,
        );
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function inner_constructor_defaults_to_strict_when_called_without_flag(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        // Arrange: omit the third argument — the constructor must default
        // to strict (fail-closed) to match OpenApiValidatorBuilder.
        $validator = new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
        );

        $pathItem = new PathItem(post: new SchemaOperation(operationId: 'myCallback'));

        $document = $this->createDocumentWithComponentCallback(
            'myCallback',
            ['{$request.body#/callback_url}' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        $this->expectException(UnresolvableCallbackPathException::class);
        $this->expectExceptionMessage('{$request.body#/callback_url}');

        // Act + Assert: strict default throws on the runtime template.
        $validator->validate($request, 'myCallback', $document);
    }

    #[Test]
    public function inner_constructor_allows_lax_when_strict_false_passed_explicitly(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        // Arrange: explicit opt-out of strict mode — legacy wildcard behaviour.
        $validator = new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
            strictCallbackRuntimeTemplate: false,
        );

        $pathItem = new PathItem(post: new SchemaOperation(operationId: 'myCallback'));

        $document = $this->createDocumentWithComponentCallback(
            'myCallback',
            ['{$request.body#/callback_url}' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        // Act: legacy wildcard accepts any path.
        $resolved = $validator->validate($request, 'myCallback', $document);

        // Assert: runtime template falls through to wildcard match.
        $this->assertSame('myCallback', $resolved->operationId);
    }

    #[Test]
    public function resolve_runtime_expression_returns_operation_from_path_item(): void
    {
        $operation = new SchemaOperation(operationId: 'myCallback');
        $pathItem = new PathItem(post: $operation);

        $document = $this->createDocumentWithComponentCallback(
            'myCallback',
            ['{$request.body#/callbackUrl}' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/any-url');

        $resolved = $this->callbackValidator->validate($request, 'myCallback', $document);

        $this->assertSame('myCallback', $resolved->operationId);
    }

    #[Test]
    public function throw_for_unknown_callback_name(): void
    {
        $document = $this->createEmptyDocument();

        $request = $this->psrFactory->createServerRequest('POST', '/callback');

        $this->expectException(UnknownCallbackException::class);
        $this->expectExceptionMessage('Unknown callback: nonexistent');

        $this->callbackValidator->validate($request, 'nonexistent', $document);
    }

    #[Test]
    public function resolve_fixed_url_expression_when_request_url_matches(): void
    {
        $operation = new SchemaOperation(operationId: 'fixedCallback');
        $pathItem = new PathItem(post: $operation);

        $document = $this->createDocumentWithComponentCallback(
            'fixedCallback',
            ['/payment/callback' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/payment/callback');

        $resolved = $this->callbackValidator->validate($request, 'fixedCallback', $document);

        $this->assertSame('fixedCallback', $resolved->operationId);
    }

    #[Test]
    public function throw_when_fixed_url_expression_does_not_match_request_url(): void
    {
        $operation = new SchemaOperation(operationId: 'fixedCallback');
        $pathItem = new PathItem(post: $operation);

        $document = $this->createDocumentWithComponentCallback(
            'fixedCallback',
            ['/payment/callback' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/other/url');

        $this->expectException(UnknownCallbackException::class);
        $this->expectExceptionMessage('Unknown callback: fixedCallback (method: post)');

        $this->callbackValidator->validate($request, 'fixedCallback', $document);
    }

    #[Test]
    public function throw_when_callback_exists_but_method_not_defined(): void
    {
        $operation = new SchemaOperation(operationId: 'myCallback');
        $pathItem = new PathItem(post: $operation);

        $document = $this->createDocumentWithComponentCallback(
            'myCallback',
            ['{$request.body#/callbackUrl}' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('DELETE', '/callback');

        $this->expectException(UnknownCallbackException::class);
        $this->expectExceptionMessage('Unknown callback: myCallback (method: delete)');

        $this->callbackValidator->validate($request, 'myCallback', $document);
    }

    #[Test]
    public function resolve_inline_callback_defined_in_operation(): void
    {
        $callbackOperation = new SchemaOperation(operationId: 'inlineCallbackOp');
        $callbackPathItem = new PathItem(post: $callbackOperation);

        $operation = new SchemaOperation(
            operationId: 'triggerOperation',
            callbacks: new Callbacks([
                'inlineCallback' => ['{$request.body#/callbackUrl}' => $callbackPathItem],
            ]),
        );

        $document = $this->createDocumentWithInlineCallback($operation);

        $request = $this->psrFactory->createServerRequest('POST', '/callback');

        $resolved = $this->callbackValidator->validate($request, 'inlineCallback', $document);

        $this->assertSame('inlineCallbackOp', $resolved->operationId);
    }

    #[Test]
    public function throw_when_inline_callback_name_does_not_exist_in_operation(): void
    {
        $operation = new SchemaOperation(operationId: 'triggerOperation');
        $document = $this->createDocumentWithInlineCallback($operation);

        $request = $this->psrFactory->createServerRequest('POST', '/callback');

        $this->expectException(UnknownCallbackException::class);
        $this->expectExceptionMessage('Unknown callback: missingCallback');

        $this->callbackValidator->validate($request, 'missingCallback', $document);
    }

    #[Test]
    public function resolve_callback_via_path_item_ref_to_components(): void
    {
        $referencedOperation = new SchemaOperation(operationId: 'refOperation');
        $referencedPathItem = new PathItem(post: $referencedOperation);

        $refPathItem = new PathItem(ref: '#/components/pathItems/SharedCallback');

        $document = $this->createDocumentWithRefCallback(
            'refCallback',
            ['{$request.body#/url}' => $refPathItem],
            'SharedCallback',
            $referencedPathItem,
        );

        $request = $this->psrFactory->createServerRequest('POST', '/any');

        $resolved = $this->callbackValidator->validate($request, 'refCallback', $document);

        $this->assertSame('refOperation', $resolved->operationId);
    }

    #[Test]
    public function resolve_one_of_multiple_expressions_for_same_callback(): void
    {
        $primaryOperation = new SchemaOperation(operationId: 'primaryCallback');
        $primaryPathItem = new PathItem(post: $primaryOperation);

        $secondaryOperation = new SchemaOperation(operationId: 'secondaryCallback');
        $secondaryPathItem = new PathItem(post: $secondaryOperation);

        $document = $this->createDocumentWithComponentCallback(
            'multiExpression',
            [
                '/fixed/path' => $primaryPathItem,
                '{$request.body#/url}' => $secondaryPathItem,
            ],
        );

        $runtimeRequest = $this->psrFactory->createServerRequest('POST', '/arbitrary');

        $runtimeResolved = $this->callbackValidator->validate(
            $runtimeRequest,
            'multiExpression',
            $document,
        );
        $this->assertSame('secondaryCallback', $runtimeResolved->operationId);

        $fixedRequest = $this->psrFactory->createServerRequest('POST', '/fixed/path');

        $fixedResolved = $this->callbackValidator->validate(
            $fixedRequest,
            'multiExpression',
            $document,
        );
        $this->assertSame('primaryCallback', $fixedResolved->operationId);
    }

    #[Test]
    public function resolve_each_callback_independently_in_multiple_callback_operation(): void
    {
        $firstOperation = new SchemaOperation(operationId: 'firstCallbackOp');
        $firstPathItem = new PathItem(post: $firstOperation);

        $secondOperation = new SchemaOperation(operationId: 'secondCallbackOp');
        $secondPathItem = new PathItem(post: $secondOperation);

        $operation = new SchemaOperation(
            operationId: 'triggerOperation',
            callbacks: new Callbacks([
                'firstCallback' => ['{$request.body#/firstUrl}' => $firstPathItem],
                'secondCallback' => ['{$request.body#/secondUrl}' => $secondPathItem],
            ]),
        );

        $document = $this->createDocumentWithInlineCallback($operation);

        $firstRequest = $this->psrFactory->createServerRequest('POST', '/first');
        $firstResolved = $this->callbackValidator->validate(
            $firstRequest,
            'firstCallback',
            $document,
        );
        $this->assertSame('firstCallbackOp', $firstResolved->operationId);

        $secondRequest = $this->psrFactory->createServerRequest('POST', '/second');
        $secondResolved = $this->callbackValidator->validate(
            $secondRequest,
            'secondCallback',
            $document,
        );
        $this->assertSame('secondCallbackOp', $secondResolved->operationId);
    }

    #[Test]
    public function component_callback_takes_precedence_over_inline(): void
    {
        $componentOperation = new SchemaOperation(operationId: 'componentCallbackOp');
        $componentPathItem = new PathItem(post: $componentOperation);

        $inlineOperation = new SchemaOperation(operationId: 'inlineCallbackOp');
        $inlinePathItem = new PathItem(post: $inlineOperation);

        $triggerOperation = new SchemaOperation(
            operationId: 'triggerOperation',
            callbacks: new Callbacks([
                'sharedCallback' => ['{$request.body#/url}' => $inlinePathItem],
            ]),
        );

        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            paths: new Paths(['/trigger' => new PathItem(post: $triggerOperation)]),
            components: new Components(
                callbacks: [
                    'sharedCallback' => new Callbacks([
                        'sharedCallback' => [
                            '{$request.body#/url}' => $componentPathItem,
                        ],
                    ]),
                ],
            ),
        );

        $request = $this->psrFactory->createServerRequest('POST', '/any');

        $resolved = $this->callbackValidator->validate($request, 'sharedCallback', $document);

        $this->assertSame('componentCallbackOp', $resolved->operationId);
    }

    #[Test]
    public function resolve_fixed_full_url_expression_when_path_matches(): void
    {
        $operation = new SchemaOperation(operationId: 'fullUrlCallback');
        $pathItem = new PathItem(post: $operation);

        $document = $this->createDocumentWithComponentCallback(
            'fullUrlCallback',
            ['https://example.com/webhook' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/webhook');

        $resolved = $this->callbackValidator->validate($request, 'fullUrlCallback', $document);

        $this->assertSame('fullUrlCallback', $resolved->operationId);
    }

    #[Test]
    public function throw_when_fixed_full_url_expression_path_does_not_match(): void
    {
        $operation = new SchemaOperation(operationId: 'fullUrlCallback');
        $pathItem = new PathItem(post: $operation);

        $document = $this->createDocumentWithComponentCallback(
            'fullUrlCallback',
            ['https://example.com/webhook' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/other/path');

        $this->expectException(UnknownCallbackException::class);
        $this->expectExceptionMessage('Unknown callback: fullUrlCallback (method: post)');

        $this->callbackValidator->validate($request, 'fullUrlCallback', $document);
    }

    #[Test]
    public function throw_when_ref_points_to_unsupported_location(): void
    {
        $refPathItem = new PathItem(ref: '#/components/schemas/Foo');

        $document = $this->createDocumentWithComponentCallback(
            'refCallback',
            ['{$request.body#/url}' => $refPathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/any');

        $this->expectException(RefResolutionException::class);
        $this->expectExceptionMessage('Unsupported callback $ref');

        $this->callbackValidator->validate($request, 'refCallback', $document);
    }

    #[Test]
    public function throw_when_ref_points_to_nonexistent_path_item(): void
    {
        $refPathItem = new PathItem(ref: '#/components/pathItems/Missing');

        $document = $this->createDocumentWithComponentCallback(
            'refCallback',
            ['{$request.body#/url}' => $refPathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/any');

        $this->expectException(RefResolutionException::class);
        $this->expectExceptionMessage('not found in components.pathItems');

        $this->callbackValidator->validate($request, 'refCallback', $document);
    }

    private function createEmptyDocument(): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(),
        );
    }

    /**
     * @param array<string, PathItem> $expressions
     */
    private function createDocumentWithComponentCallback(
        string $callbackName,
        array $expressions,
    ): OpenApiDocument {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([$callbackName => $expressions]),
                ],
            ),
        );
    }

    private function createDocumentWithInlineCallback(SchemaOperation $operation): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            paths: new Paths(['/trigger' => new PathItem(post: $operation)]),
        );
    }

    /**
     * @param array<string, PathItem> $expressions
     */
    private function createDocumentWithRefCallback(
        string $callbackName,
        array $expressions,
        string $pathItemName,
        PathItem $referencedPathItem,
    ): OpenApiDocument {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([$callbackName => $expressions]),
                ],
                pathItems: [$pathItemName => $referencedPathItem],
            ),
        );
    }
}
