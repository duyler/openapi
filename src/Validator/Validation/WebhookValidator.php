<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Duyler\OpenApi\Validator\Validation\Internal\ValidatesSecurityTrait;
use Duyler\OpenApi\Validator\Webhook\WebhookValidator as InnerWebhookValidator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class WebhookValidator
{
    use EventDispatchingTrait;
    use ValidatesSecurityTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly InnerWebhookValidator $webhookValidator;
    private readonly SecurityValidator $securityValidator;

    public function __construct(
        private readonly ValidatorDependencies $context,
        private readonly bool $securityValidation = false,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->webhookValidator = new InnerWebhookValidator($context->requestValidator);
        $this->securityValidator = new SecurityValidator($context->securityVerboseLogger, $context->pregExecutor);
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
                $schemaOperation = $this->webhookValidator->validate(
                    $request,
                    $webhookName,
                    $this->context->document,
                );

                if ($this->securityValidation) {
                    $this->validateSecurity($request, $schemaOperation, $webhookName, $method);
                }

                return new Operation($webhookName, $method);
            },
        );
    }
}
