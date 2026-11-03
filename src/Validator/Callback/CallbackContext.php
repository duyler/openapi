<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback;

/**
 * Triggering-request data for callback runtime expression evaluation. Do not log
 * this object wholesale — request headers may contain sensitive values (Authorization,
 * cookies); do not embed it or its values into exception messages.
 */
final readonly class CallbackContext
{
    /**
     * @param array<string, string|list<string>> $requestHeaders
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $pathParams
     */
    public function __construct(
        public mixed $requestBody = null,
        public array $requestHeaders = [],
        public array $queryParams = [],
        public array $pathParams = [],
        public string $method = '',
        public string $url = '',
    ) {}
}
