<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Callback\CallbackValidator as InnerCallbackValidator;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class CallbackValidator
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly InnerCallbackValidator $callbackValidator;

    public function __construct(
        private readonly ValidationContext $context,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->callbackValidator = new InnerCallbackValidator($context->requestValidator);
    }

    public function validate(ServerRequestInterface $request, string $callbackName): Operation
    {
        $method = $request->getMethod();

        return $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(request: $request, path: $callbackName, method: $method),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                request: $request,
                path: $callbackName,
                method: $method,
                success: $success,
                duration: $duration,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                request: $request,
                path: $callbackName,
                method: $method,
                exception: $e,
            ),
            callback: function () use ($request, $callbackName, $method): Operation {
                $this->callbackValidator->validate($request, $callbackName, $this->context->document);

                return new Operation($callbackName, $method);
            },
        );
    }
}
