<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;
use function strtolower;

final readonly class CallbackValidator
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        string $callbackName,
        OpenApiDocument $document,
    ): void {
        $callbacks = $this->findCallbacks($callbackName, $document);
        $operation = $this->extractOperation($request, $callbackName, $callbacks);

        $requestPath = $request->getUri()->getPath();
        $this->requestValidator->validate($request, $operation, $requestPath);
    }

    /**
     * @return array<string, PathItem|array<string, PathItem>>
     */
    private function findCallbacks(string $callbackName, OpenApiDocument $document): array
    {
        $operationCallbacks = $document->components?->callbacks ?? [];

        if (isset($operationCallbacks[$callbackName])) {
            /** @var Callbacks $callback */
            $callback = $operationCallbacks[$callbackName];

            if (isset($callback->callbacks[$callbackName])) {
                return $callback->callbacks[$callbackName];
            }

            return $callback->callbacks;
        }

        throw new UnknownCallbackException($callbackName);
    }

    private function extractOperation(
        ServerRequestInterface $request,
        string $callbackName,
        array $callbacks,
    ): Operation {
        $method = strtolower($request->getMethod());

        foreach ($callbacks as $expression => $pathItem) {
            if (false === $pathItem instanceof PathItem) {
                continue;
            }

            $operation = match ($method) {
                'get' => $pathItem->get,
                'post' => $pathItem->post,
                'put' => $pathItem->put,
                'patch' => $pathItem->patch,
                'delete' => $pathItem->delete,
                'options' => $pathItem->options,
                'head' => $pathItem->head,
                'trace' => $pathItem->trace,
                default => null,
            };

            if (null !== $operation) {
                return $operation;
            }
        }

        throw new UnknownCallbackException(
            sprintf('%s (method: %s)', $callbackName, $method),
        );
    }
}
