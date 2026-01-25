<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Psr15;

use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Closure;

use function assert;

use const JSON_THROW_ON_ERROR;

final readonly class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly OpenApiValidatorInterface $validator,
        private readonly ?Closure $onRequestError = null,
        private readonly ?Closure $onResponseError = null,
    ) {}

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            $operation = $this->validator->validateRequest($request);
        } catch (ValidationException $e) {
            if (null !== $this->onRequestError) {
                $result = ($this->onRequestError)($e, $request);
                assert($result instanceof ResponseInterface);

                return $result;
            }

            return $this->createValidationErrorResponse($e, 422);
        }

        $request = $request->withAttribute(Operation::class, $operation);

        $response = $handler->handle($request);

        try {
            $this->validator->validateResponse($response, $operation);
        } catch (ValidationException $e) {
            if (null !== $this->onResponseError) {
                $result = ($this->onResponseError)($e, $request, $response);
                assert($result instanceof ResponseInterface);

                return $result;
            }

            return $this->createValidationErrorResponse($e, 500);
        }

        return $response;
    }

    private function createValidationErrorResponse(ValidationException $e, int $status): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(
                json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->formatErrors($e),
                ], JSON_THROW_ON_ERROR),
            ));
    }

    private function formatErrors(ValidationException $e): array
    {
        $formatted = [];
        foreach ($e->getErrors() as $error) {
            $formatted[] = [
                'path' => $error->dataPath(),
                'message' => $error->getMessage(),
                'type' => $error->getType(),
            ];
        }

        return $formatted;
    }
}
