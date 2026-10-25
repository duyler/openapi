<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Security\Internal\ApiKeyHandler;
use Duyler\OpenApi\Validator\Security\Internal\ApiKeyValidator;
use Duyler\OpenApi\Validator\Security\Internal\BasicAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\BearerAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\BearerAuthValidator;
use Duyler\OpenApi\Validator\Security\Internal\DigestAuthHandler;

/**
 * Immutable ordered registry of security scheme handlers.
 *
 * The registry has no key slot: a handler claims schemes through its supports()
 * method, and findSupporting() returns the first handler in list order (prepended handlers first)
 * whose supports() returns true. with() prepends a handler ahead of the current
 * list, so a user handler registered over the built-in set wins the lookup over
 * it. withBase() merges a user registry with a base registry keeping the user
 * handlers ahead of the base ones, which is the ordered-list counterpart of the
 * keyed registries where the user entry wins over the base entry.
 */
final readonly class SecuritySchemeHandlerRegistry
{
    /**
     * @param list<SecuritySchemeHandlerInterface> $handlers
     */
    public function __construct(
        private readonly array $handlers = [],
    ) {}

    public static function buildIn(PregExecutor $pregExecutor): self
    {
        return new self([
            new BasicAuthHandler($pregExecutor),
            new DigestAuthHandler($pregExecutor),
            new BearerAuthHandler(new BearerAuthValidator($pregExecutor)),
            new ApiKeyHandler(new ApiKeyValidator()),
        ]);
    }

    public function with(SecuritySchemeHandlerInterface $handler): self
    {
        return new self([$handler, ...$this->handlers]);
    }

    public function withBase(self $base): self
    {
        return new self([...$this->handlers, ...$base->handlers]);
    }

    public function findSupporting(SecurityScheme $scheme): ?SecuritySchemeHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($scheme)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @return list<SecuritySchemeHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
