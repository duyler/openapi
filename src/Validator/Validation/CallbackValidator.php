<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Validator\Callback\CallbackValidator as InnerCallbackValidator;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
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
            request: $request,
            response: null,
            path: $callbackName,
            method: $method,
            callback: function () use ($request, $callbackName, $method): Operation {
                $this->callbackValidator->validate($request, $callbackName, $this->context->document);

                return new Operation($callbackName, $method);
            },
        );
    }
}
