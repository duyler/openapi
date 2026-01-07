<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Webhook\Exception;

use InvalidArgumentException;

final class UnknownWebhookException extends InvalidArgumentException
{
    public function __construct(string $webhookName)
    {
        parent::__construct(
            sprintf('Unknown webhook: %s', $webhookName),
        );
    }
}
