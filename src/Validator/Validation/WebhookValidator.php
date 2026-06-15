<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
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
    private readonly SecurityValidator $securityValidator;

    public function __construct(
        private readonly ValidationContext $context,
        private readonly bool $securityValidation = false,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->webhookValidator = new InnerWebhookValidator($context->requestValidator);
        $this->securityValidator = new SecurityValidator();
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

    private function validateSecurity(
        ServerRequestInterface $request,
        SchemaOperation $operation,
        string $webhookName,
        string $method,
    ): void {
        $securityRequirements = $operation->security ?? $this->context->document->security;

        if (null === $securityRequirements) {
            return;
        }

        $securitySchemes = $this->context->document->components?->securitySchemes ?? [];

        $securityContext = new SecurityValidationContext(
            request: $request,
            path: $webhookName,
            method: $method,
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        );

        $this->securityValidator->validate($securityContext);
    }
}
