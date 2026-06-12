<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function sprintf;

final readonly class ResponseValidationHandler
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ValidationContext $context,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
    }

    public function validate(ResponseInterface $response, Operation $operation): void
    {
        $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(path: $operation->path, method: $operation->method, response: $response),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                path: $operation->path,
                method: $operation->method,
                success: $success,
                duration: $duration,
                response: $response,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                path: $operation->path,
                method: $operation->method,
                exception: $e,
                response: $response,
            ),
            callback: function () use ($response, $operation): void {
                $pathItem = $this->context->document->paths?->paths[$operation->path] ?? null;
                if (null === $pathItem) {
                    throw new BuilderException(sprintf('Path not found: %s', $operation->path));
                }

                $op = PathItemHelper::getOperation($pathItem, $operation->method);
                if (null === $op) {
                    throw new BuilderException(
                        sprintf('Method not found: %s %s', $operation->method, $operation->path),
                    );
                }

                $this->logger->info(sprintf('Validating response: %s %s', $operation->method, $operation->path));

                $this->context->responseValidator->validate($response, $op);
            },
            warningMessage: sprintf('Response validation failed: %s %s', $operation->method, $operation->path),
        );
    }
}
