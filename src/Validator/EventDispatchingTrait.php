<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Internal\ValidationEventPayload;
use Throwable;

trait EventDispatchingTrait
{
    private function dispatchValidationEvent(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withValidationEvents(
        ValidationEventPayload $payload,
        callable $callback,
        ?string $warningMessage = null,
    ): mixed {
        $startTime = microtime(true);

        $this->dispatchValidationEvent(
            new ValidationStartedEvent(
                request: $payload->request,
                path: $payload->path,
                method: $payload->method,
                response: $payload->response,
                schemaRef: $payload->schemaRef,
            ),
        );

        try {
            $result = $callback();

            $this->dispatchValidationEvent(
                $this->createFinishedEvent(
                    $payload,
                    true,
                    microtime(true) - $startTime,
                ),
            );

            return $result;
        } catch (Throwable $e) {
            $this->dispatchValidationEvent(
                $this->createFinishedEvent(
                    $payload,
                    false,
                    microtime(true) - $startTime,
                ),
            );

            if ($e instanceof ValidationException) {
                if (null !== $warningMessage) {
                    $this->logger->warning($warningMessage);
                }

                $this->dispatchValidationEvent(
                    new ValidationErrorEvent(
                        request: $payload->request,
                        path: $payload->path,
                        method: $payload->method,
                        exception: $e,
                        response: $payload->response,
                        schemaRef: $payload->schemaRef,
                    ),
                );
            }

            throw $e;
        }
    }

    private function createFinishedEvent(
        ValidationEventPayload $payload,
        bool $success,
        float $duration,
    ): ValidationFinishedEvent {
        return new ValidationFinishedEvent(
            request: $payload->request,
            path: $payload->path,
            method: $payload->method,
            success: $success,
            duration: $duration,
            response: $payload->response,
            schemaRef: $payload->schemaRef,
        );
    }
}
