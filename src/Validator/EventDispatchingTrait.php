<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
        ?ServerRequestInterface $request,
        ?ResponseInterface $response,
        string $path,
        string $method,
        callable $callback,
        ?string $warningMessage = null,
        ?string $schemaRef = null,
    ): mixed {
        $startTime = microtime(true);

        $this->dispatchValidationEvent(
            new ValidationStartedEvent(
                request: $request,
                path: $path,
                method: $method,
                response: $response,
                schemaRef: $schemaRef,
            ),
        );

        try {
            $result = $callback();

            $this->dispatchValidationEvent(
                $this->createFinishedEvent(
                    $request,
                    $response,
                    $path,
                    $method,
                    true,
                    microtime(true) - $startTime,
                    $schemaRef,
                ),
            );

            return $result;
        } catch (Throwable $e) {
            $this->dispatchValidationEvent(
                $this->createFinishedEvent(
                    $request,
                    $response,
                    $path,
                    $method,
                    false,
                    microtime(true) - $startTime,
                    $schemaRef,
                ),
            );

            if ($e instanceof ValidationException) {
                if (null !== $warningMessage) {
                    $this->logger->warning($warningMessage);
                }

                $this->dispatchValidationEvent(
                    new ValidationErrorEvent(
                        request: $request,
                        path: $path,
                        method: $method,
                        exception: $e,
                        response: $response,
                        schemaRef: $schemaRef,
                    ),
                );
            }

            throw $e;
        }
    }

    private function createFinishedEvent(
        ?ServerRequestInterface $request,
        ?ResponseInterface $response,
        string $path,
        string $method,
        bool $success,
        float $duration,
        ?string $schemaRef = null,
    ): ValidationFinishedEvent {
        return new ValidationFinishedEvent(
            request: $request,
            path: $path,
            method: $method,
            success: $success,
            duration: $duration,
            response: $response,
            schemaRef: $schemaRef,
        );
    }
}
