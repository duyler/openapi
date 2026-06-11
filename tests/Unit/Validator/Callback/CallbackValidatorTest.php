<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Validator\Callback\CallbackValidator;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallbackValidatorTest extends TestCase
{
    private CallbackValidator $callbackValidator;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $requestValidator = $this->createMock(RequestValidator::class);
        $requestValidator->method('validate');

        $this->callbackValidator = new CallbackValidator($requestValidator);
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function throw_exception_for_unknown_callback(): void
    {
        $document = $this->createDocument(null);

        $request = $this->psrFactory->createServerRequest('POST', '/callback');

        $this->expectException(UnknownCallbackException::class);

        $this->callbackValidator->validate($request, 'nonexistent', $document);
    }

    #[Test]
    public function validate_existing_callback(): void
    {
        $operation = new Operation(operationId: 'myCallback');

        $pathItem = new PathItem(post: $operation);

        $callbacks = new Callbacks([
            'myCallback' => [
                '{$request.body#/callbackUrl}' => $pathItem,
            ],
        ]);

        $document = $this->createDocument($callbacks);

        $request = $this->psrFactory->createServerRequest('POST', '/callback');

        $this->callbackValidator->validate($request, 'myCallback', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_exception_for_unknown_method(): void
    {
        $operation = new Operation(operationId: 'myCallback');

        $pathItem = new PathItem(post: $operation);

        $callbacks = new Callbacks([
            'myCallback' => [
                '{$request.body#/callbackUrl}' => $pathItem,
            ],
        ]);

        $document = $this->createDocument($callbacks);

        $request = $this->psrFactory->createServerRequest('DELETE', '/callback');

        $this->expectException(UnknownCallbackException::class);

        $this->callbackValidator->validate($request, 'myCallback', $document);
    }

    private function createDocument(?Callbacks $callbacks): OpenApiDocument
    {
        $componentsCallbacks = null;

        if (null !== $callbacks) {
            $componentsCallbacks = ['myCallback' => $callbacks];
        }

        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                callbacks: $componentsCallbacks,
            ),
        );
    }
}
