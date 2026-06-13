<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;
use function strtolower;

final readonly class CallbackValidator
{
    public function __construct(
        private readonly RequestValidatorInterface $requestValidator,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        string $callbackName,
        OpenApiDocument $document,
    ): void {
        $pathItems = $this->findCallbacks($callbackName, $document);
        $operation = $this->extractOperation($request, $callbackName, $pathItems);

        $requestPath = $request->getUri()->getPath();
        $this->requestValidator->validate($request, $operation, $requestPath);
    }

    /**
     * @return array<string, PathItem>
     */
    private function findCallbacks(string $callbackName, OpenApiDocument $document): array
    {
        $operationCallbacks = $document->components?->callbacks ?? [];

        if (isset($operationCallbacks[$callbackName])) {
            /** @var Callbacks $callback */
            $callback = $operationCallbacks[$callbackName];

            return $this->flattenPathItems($callback);
        }

        throw new UnknownCallbackException($callbackName);
    }

    /**
     * @return array<string, PathItem>
     */
    private function flattenPathItems(Callbacks $callbacks): array
    {
        $pathItems = [];

        foreach ($callbacks->callbacks as $expression => $items) {
            foreach ($items as $itemExpression => $pathItem) {
                $pathItems[$itemExpression] = $pathItem;
            }
        }

        return $pathItems;
    }

    private function extractOperation(
        ServerRequestInterface $request,
        string $callbackName,
        array $pathItems,
    ): Operation {
        $method = strtolower($request->getMethod());

        foreach ($pathItems as $expression => $pathItem) {
            if (false === $pathItem instanceof PathItem) {
                continue;
            }

            $operation = $pathItem->getOperation($method);

            if (null !== $operation) {
                return $operation;
            }
        }

        throw new UnknownCallbackException(
            sprintf('%s (method: %s)', $callbackName, $method),
        );
    }
}
