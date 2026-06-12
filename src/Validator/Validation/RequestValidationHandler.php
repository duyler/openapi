<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
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

    public function __construct(
        private readonly ValidationContext $context,
        private readonly PathFinder $pathFinder,
        private readonly bool $securityValidation = false,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->securityValidator = new SecurityValidator();
    }

    public function validate(ServerRequestInterface $request): Operation
    {
        $requestPath = $request->getUri()->getPath();
        $method = $request->getMethod();

        return $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(request: $request, path: $requestPath, method: $method),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                request: $request,
                path: $requestPath,
                method: $method,
                success: $success,
                duration: $duration,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                request: $request,
                path: $requestPath,
                method: $method,
                exception: $e,
            ),
            callback: function () use ($request, $requestPath, $method): Operation {
                $operation = $this->pathFinder->findOperation($requestPath, $method);

                $pathItem = $this->context->document->paths?->paths[$operation->path] ?? null;
                if (null === $pathItem) {
                    throw new BuilderException(sprintf('Path not found: %s', $operation->path));
                }

                $op = PathItemHelper::getOperation($pathItem, $method);
                if (null === $op) {
                    throw new BuilderException(
                        sprintf('Method not found: %s %s', $method, $operation->path),
                    );
                }

                $this->logger->info(sprintf('Validating request: %s %s', $method, $requestPath));

                $this->context->requestValidator->validate($request, $op, $operation->path);

                if ($this->securityValidation) {
                    $securityRequirements = $op->security ?? $this->context->document->security;

                    if (null !== $securityRequirements) {
                        $securitySchemes = $this->context->document->components?->securitySchemes ?? [];
                        $this->securityValidator->validate(
                            $request,
                            $operation->path,
                            $operation->method,
                            $securityRequirements,
                            $securitySchemes,
                        );
                    }
                }

                return $operation;
            },
            warningMessage: sprintf('Request validation failed: %s %s', $method, $requestPath),
        );
    }
}
