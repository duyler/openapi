<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Throwable;

trait EventDispatchingTrait
{
    /**
     * @template T
     *
     * @param callable(bool $success, float $duration): ValidationFinishedEvent $makeFinishedEvent
     * @param callable(ValidationException): ValidationErrorEvent $makeErrorEvent
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withValidationEvents(
        ValidationStartedEvent $startedEvent,
        callable $makeFinishedEvent,
        callable $makeErrorEvent,
        callable $callback,
        ?string $warningMessage = null,
    ): mixed {
        $startTime = microtime(true);

        $this->dispatchValidationEvent($startedEvent);

        try {
            $result = $callback();

            $this->dispatchValidationEvent(
                $makeFinishedEvent(true, microtime(true) - $startTime),
            );

            return $result;
        } catch (ValidationException $e) {
            $duration = microtime(true) - $startTime;

            $this->dispatchValidationEvent($makeFinishedEvent(false, $duration));

            if (null !== $warningMessage) {
                $this->logger->warning($warningMessage);
            }

            $this->dispatchValidationEvent($makeErrorEvent($e));

            throw $e;
        } catch (Throwable $e) {
            $this->dispatchValidationEvent($makeFinishedEvent(false, microtime(true) - $startTime));

            throw $e;
        }
    }
}
