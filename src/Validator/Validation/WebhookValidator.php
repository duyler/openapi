<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Webhook\WebhookValidator as InnerWebhookValidator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class WebhookValidator
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly InnerWebhookValidator $webhookValidator;

    public function __construct(
        private readonly ValidationContext $context,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->webhookValidator = new InnerWebhookValidator($context->requestValidator);
    }

    public function validate(ServerRequestInterface $request, string $webhookName): Operation
    {
        $method = $request->getMethod();

        return $this->withValidationEvents(
            request: $request,
            response: null,
            path: $webhookName,
            method: $method,
            callback: function () use ($request, $webhookName, $method): Operation {
                $this->webhookValidator->validate($request, $webhookName, $this->context->document);

                return new Operation($webhookName, $method);
            },
        );
    }
}
