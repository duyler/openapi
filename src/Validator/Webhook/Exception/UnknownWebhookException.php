<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Webhook\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use InvalidArgumentException;

use function sprintf;

final class UnknownWebhookException extends InvalidArgumentException
{
    use SanitizableExceptionTrait;

    public function __construct(string $webhookName)
    {
        parent::__construct(
            sprintf('Unknown webhook: %s', $webhookName),
        );
    }
}
