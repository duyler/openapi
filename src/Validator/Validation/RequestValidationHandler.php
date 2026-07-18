<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Duyler\OpenApi\Validator\Server\ServerPathMatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function sprintf;

final readonly class RequestValidationHandler
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly SecurityValidator $securityValidator;
    private readonly ServerPathMatcher $serverPathMatcher;

    public function __construct(
        private readonly ValidatorDependencies $context,
        private readonly PathFinder $pathFinder,
        private readonly bool $securityValidation = false,
        private readonly bool $serverPathResolution = false,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->securityValidator = new SecurityValidator();
        $this->serverPathMatcher = new ServerPathMatcher(
            $context->document->servers?->servers ?? [],
        );
    }

    public function validate(ServerRequestInterface $request): Operation
    {
        $requestPath = $request->getUri()->getPath();
        $method = $request->getMethod();

        $matchedPath = $this->resolveMatchedPath($requestPath);

        return $this->withValidationEvents(
            request: $request,
            response: null,
            path: $requestPath,
            method: $method,
            callback: function () use ($request, $requestPath, $matchedPath, $method): Operation {
                $operation = $this->pathFinder->findOperation($matchedPath, $method);

                $op = $operation->schemaOperation;
                if (null === $op) {
                    throw new BuilderException(
                        sprintf('Operation schema unavailable: %s %s', $method, $operation->path),
                    );
                }

                $this->logger->info(sprintf('Validating request: %s %s', $method, $requestPath));

                $validatedRequest = $this->createValidatedRequest($request, $requestPath, $matchedPath);

                $this->context->requestValidator->validate($validatedRequest, $op, $operation->path);

                if ($this->securityValidation) {
                    $securityRequirements = $op->security ?? $this->context->document->security;

                    if (null !== $securityRequirements) {
                        $securitySchemes = $this->context->document->components?->securitySchemes ?? [];
                        $securityContext = new SecurityValidationContext(
                            request: $request,
                            path: $operation->path,
                            method: $operation->method,
                            securityRequirements: $securityRequirements,
                            securitySchemes: $securitySchemes,
                        );
                        $this->securityValidator->validate($securityContext);
                    }
                }

                return $operation;
            },
            warningMessage: sprintf('Request validation failed: %s %s', $method, $requestPath),
        );
    }

    private function resolveMatchedPath(string $requestPath): string
    {
        if (false === $this->serverPathResolution) {
            return $requestPath;
        }

        return $this->serverPathMatcher->matchPath($requestPath)?->strippedPath ?? $requestPath;
    }

    private function createValidatedRequest(
        ServerRequestInterface $request,
        string $requestPath,
        string $matchedPath,
    ): ServerRequestInterface {
        if ($matchedPath === $requestPath) {
            return $request;
        }

        return $request->withUri(
            $request->getUri()->withPath($matchedPath),
        );
    }
}
