<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\CallbackContext;
use Duyler\OpenApi\Validator\Callback\CallbackValidator;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CallbackContextBoundaryTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function path_insertion_matches_resolved_path_with_context(): void
    {
        $validator = $this->createValidator(strict: true);
        $document = $this->document('onVersion', '/releases/{$request.path.version}', new PathItem(post: new SchemaOperation(operationId: 'onVersion')));
        $context = new CallbackContext(pathParams: ['version' => 'v2']);

        $operation = $validator->validate(
            $this->request('/releases/v2'),
            'onVersion',
            $document,
            $context,
        );

        self::assertSame('onVersion', $operation->operationId);
    }

    #[Test]
    public function path_insertion_with_missing_path_param_is_unresolvable_in_strict_mode(): void
    {
        $validator = $this->createValidator(strict: true);
        $document = $this->document('onVersion', '/releases/{$request.path.version}', new PathItem(post: new SchemaOperation(operationId: 'onVersion')));
        $context = new CallbackContext();

        $this->expectException(UnresolvableCallbackPathException::class);

        $validator->validate(
            $this->request('/releases/v2'),
            'onVersion',
            $document,
            $context,
        );
    }

    #[Test]
    public function body_expression_with_empty_context_is_unresolvable_in_strict_mode(): void
    {
        $validator = $this->createValidator(strict: true);
        $document = $this->document('onEvent', '{$request.body#/id}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext();

        $this->expectException(UnresolvableCallbackPathException::class);

        $validator->validate(
            $this->request('/abc'),
            'onEvent',
            $document,
            $context,
        );
    }

    #[Test]
    public function body_expression_with_empty_context_is_unresolvable_in_lax_mode(): void
    {
        $validator = $this->createValidator(strict: false);
        $document = $this->document('onEvent', '{$request.body#/id}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext();

        $this->expectException(UnresolvableCallbackPathException::class);

        $validator->validate(
            $this->request('/abc'),
            'onEvent',
            $document,
            $context,
        );
    }

    #[Test]
    public function method_expression_with_empty_context_is_unresolvable(): void
    {
        $validator = $this->createValidator(strict: true);
        $document = $this->document('onAny', '/hooks/{$method}', new PathItem(post: new SchemaOperation(operationId: 'onAny')));
        $context = new CallbackContext();

        $this->expectException(UnresolvableCallbackPathException::class);

        $validator->validate(
            $this->request('/hooks/post'),
            'onAny',
            $document,
            $context,
        );
    }

    private function createValidator(bool $strict): CallbackValidator
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        return new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
            strictCallbackRuntimeTemplate: $strict,
            pregExecutor: new PregExecutor(),
        );
    }

    private function document(string $callbackName, string $expression, PathItem $pathItem): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Callback Context Boundary', version: '1.0.0'),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([
                        $callbackName => [$expression => $pathItem],
                    ]),
                ],
            ),
            paths: new Paths([]),
        );
    }

    private function request(string $path): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest('POST', 'https://hooks.example' . $path);
    }
}
