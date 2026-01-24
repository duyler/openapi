<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Psr15;

use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Psr15\Operation;
use Duyler\OpenApi\Psr15\ValidationMiddleware;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ValidationMiddleware::class)]
final class ValidationMiddlewareTest extends TestCase
{
    private MockObject&OpenApiValidatorInterface $validator;
    private MockObject&RequestHandlerInterface $handler;
    private MockObject&ServerRequestInterface $request;
    private MockObject&ResponseInterface $response;
    private ValidationMiddleware $middleware;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(OpenApiValidatorInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);

        $this->middleware = new ValidationMiddleware($this->validator);
    }

    #[Test]
    public function process_returns_response_on_successful_validation(): void
    {
        $operation = new Operation('/users', 'GET');

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willReturn($operation);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(Operation::class, $operation)
            ->willReturn($this->request);

        $this->validator->expects($this->once())
            ->method('validateResponse')
            ->with($this->response, $operation);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    #[Test]
    public function process_returns_422_on_request_validation_error(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willThrowException($exception);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertSame(422, $result->getStatusCode());
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));

        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Validation failed', $body['message']);
        $this->assertIsArray($body['errors']);
    }

    #[Test]
    public function process_returns_500_on_response_validation_error(): void
    {
        $operation = new Operation('/users', 'GET');

        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willReturn($operation);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(Operation::class, $operation)
            ->willReturn($this->request);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->validator->expects($this->once())
            ->method('validateResponse')
            ->with($this->response, $operation)
            ->willThrowException($exception);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertSame(500, $result->getStatusCode());
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));

        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Validation failed', $body['message']);
        $this->assertIsArray($body['errors']);
    }

    #[Test]
    public function process_calls_on_request_error_callback(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $customResponse = $this->createMock(ResponseInterface::class);
        $customResponse->method('getStatusCode')->willReturn(400);

        $callbackInvoked = false;
        $middleware = new ValidationMiddleware(
            $this->validator,
            function ($e, $req) use ($exception, $customResponse, &$callbackInvoked) {
                $callbackInvoked = true;
                $this->assertSame($exception, $e);
                $this->assertSame($this->request, $req);

                return $customResponse;
            },
        );

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willThrowException($exception);

        $result = $middleware->process($this->request, $this->handler);

        $this->assertTrue($callbackInvoked);
        $this->assertSame($customResponse, $result);
    }

    #[Test]
    public function process_calls_on_response_error_callback(): void
    {
        $operation = new Operation('/users', 'GET');

        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $customResponse = $this->createMock(ResponseInterface::class);
        $customResponse->method('getStatusCode')->willReturn(503);

        $callbackInvoked = false;
        $middleware = new ValidationMiddleware(
            $this->validator,
            null,
            function ($e, $req, $resp) use ($exception, $customResponse, &$callbackInvoked) {
                $callbackInvoked = true;
                $this->assertSame($exception, $e);
                $this->assertSame($this->request, $req);
                $this->assertSame($this->response, $resp);

                return $customResponse;
            },
        );

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willReturn($operation);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(Operation::class, $operation)
            ->willReturn($this->request);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->validator->expects($this->once())
            ->method('validateResponse')
            ->with($this->response, $operation)
            ->willThrowException($exception);

        $result = $middleware->process($this->request, $this->handler);

        $this->assertTrue($callbackInvoked);
        $this->assertSame($customResponse, $result);
    }

    #[Test]
    public function format_errors_formats_validation_errors_correctly(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willThrowException($exception);

        $result = $this->middleware->process($this->request, $this->handler);

        $body = json_decode($result->getBody()->getContents(), true);

        $this->assertIsArray($body['errors']);
        $this->assertCount(1, $body['errors']);

        $formattedError = $body['errors'][0];
        $this->assertArrayHasKey('path', $formattedError);
        $this->assertArrayHasKey('message', $formattedError);
        $this->assertArrayHasKey('type', $formattedError);
        $this->assertSame('/field', $formattedError['path']);
        $this->assertSame('type', $formattedError['type']);
    }

    #[Test]
    public function create_validation_error_response_has_correct_headers(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willThrowException($exception);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertTrue($result->hasHeader('Content-Type'));
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function create_validation_error_response_contains_all_required_fields(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/field',
            schemaPath: '#/properties/field',
        );

        $exception = new ValidationException('Validation failed', errors: [$error]);

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willThrowException($exception);

        $result = $this->middleware->process($this->request, $this->handler);

        $body = json_decode($result->getBody()->getContents(), true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('success', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('errors', $body);
        $this->assertFalse($body['success']);
        $this->assertSame('Validation failed', $body['message']);
        $this->assertIsArray($body['errors']);
    }

    #[Test]
    public function process_stores_operation_in_request_attributes(): void
    {
        $operation = new Operation('/users/{id}', 'GET');

        $this->validator->expects($this->once())
            ->method('validateRequest')
            ->with($this->request)
            ->willReturn($operation);

        $requestWithAttribute = $this->createMock(ServerRequestInterface::class);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(Operation::class, $operation)
            ->willReturn($requestWithAttribute);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttribute)
            ->willReturn($this->response);

        $this->validator->expects($this->once())
            ->method('validateResponse')
            ->with($this->response, $operation);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }
}
