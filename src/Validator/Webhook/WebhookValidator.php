<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Webhook;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;

final readonly class WebhookValidator
{
    public function __construct(
        private readonly RequestValidatorInterface $requestValidator,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        string $webhookName,
        OpenApiDocument $document,
    ): void {
        $webhook = $this->findWebhook($webhookName, $document);
        $operation = $this->extractOperation($request, $webhookName, $webhook);

        $requestPath = $request->getUri()->getPath();
        $this->requestValidator->validate($request, $operation, $requestPath);
    }

    private function findWebhook(string $webhookName, OpenApiDocument $document): PathItem
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
        PathItem $webhook,
    ): Operation {
        $method = strtolower($request->getMethod());

        $operation = $webhook->getOperation($method);

        if (null === $operation) {
            throw new UnknownWebhookException(
                sprintf('%s (method: %s)', $webhookName, $method),
            );
        }

        return $operation;
    }
}
