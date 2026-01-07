<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Webhook;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Psr\Http\Message\ServerRequestInterface;

readonly class WebhookValidator
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        string $webhookName,
        OpenApiDocument $document,
    ): void {
        $webhook = $this->findWebhook($webhookName, $document);
        $operation = $this->extractOperation($request, $webhookName, $webhook);

        $this->requestValidator->validate($request, $operation, $webhookName);
    }

    private function findWebhook(string $webhookName, OpenApiDocument $document): object
    {
        $webhooks = $document->webhooks?->webhooks ?? [];

        if (false === isset($webhooks[$webhookName])) {
            throw new UnknownWebhookException($webhookName);
        }

        return $webhooks[$webhookName];
    }

    private function extractOperation(
        ServerRequestInterface $request,
        string $webhookName,
        object $webhook,
    ): Operation {
        $method = strtolower($request->getMethod());

        $operation = match ($method) {
            'get' => $webhook->get ?? null,
            'post' => $webhook->post ?? null,
            'put' => $webhook->put ?? null,
            'patch' => $webhook->patch ?? null,
            'delete' => $webhook->delete ?? null,
            'options' => $webhook->options ?? null,
            'head' => $webhook->head ?? null,
            'trace' => $webhook->trace ?? null,
            default => null,
        };

        if (null === $operation) {
            throw new UnknownWebhookException(
                sprintf('%s (method: %s)', $webhookName, $method),
            );
        }

        if (false === $operation instanceof Operation) {
            throw new UnknownWebhookException(
                sprintf('%s (invalid operation)', $webhookName),
            );
        }

        return $operation;
    }
}
