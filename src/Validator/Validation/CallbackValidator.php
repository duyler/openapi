<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Validator\Callback\CallbackValidator as InnerCallbackValidator;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class CallbackValidator
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly InnerCallbackValidator $callbackValidator;
    private readonly SecurityValidator $securityValidator;

    /**
     * @param bool $strictCallbackRuntimeTemplate Defaults to true (fail-closed):
     *     callback expressions containing runtime templates
     *     (e.g. `{$request.body#/callback_url}`) that cannot be resolved by
     *     the validator throw {@see UnresolvableCallbackPathException}
     *     instead of being treated as wildcards that accept any URL. This
     *     matches the `OpenApiValidatorBuilder` default and prevents SSRF
     *     via attacker-controlled runtime templates when the resolved URL
     *     is used for an outbound HTTP request. Pass false explicitly only
     *     when callback URLs are validated at the application level (see
     *     README section "Callbacks" for the security implications).
     */
    public function __construct(
        private readonly ValidatorDependencies $context,
        private readonly bool $securityValidation = false,
        private readonly bool $strictCallbackRuntimeTemplate = true,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->callbackValidator = new InnerCallbackValidator(
            $context->requestValidator,
            $context->pathRegexCache,
            $this->strictCallbackRuntimeTemplate,
        );
        $this->securityValidator = new SecurityValidator($context->securityVerboseLogger, $context->pregExecutor);
    }

    public function validate(ServerRequestInterface $request, string $callbackName): Operation
    {
        $method = $request->getMethod();

        return $this->withValidationEvents(
            request: $request,
            response: null,
            path: $callbackName,
            method: $method,
            callback: function () use ($request, $callbackName, $method): Operation {
                $schemaOperation = $this->callbackValidator->validate(
                    $request,
                    $callbackName,
                    $this->context->document,
                );

                if ($this->securityValidation) {
                    $this->validateSecurity($request, $schemaOperation, $callbackName, $method);
                }

                return new Operation($callbackName, $method);
            },
        );
    }

    private function validateSecurity(
        ServerRequestInterface $request,
        SchemaOperation $operation,
        string $callbackName,
        string $method,
    ): void {
        $securityRequirements = $operation->security ?? $this->context->document->security;

        if (null === $securityRequirements) {
            return;
        }

        $securitySchemes = $this->context->document->components?->securitySchemes ?? [];

        $securityContext = new SecurityValidationContext(
            request: $request,
            path: $callbackName,
            method: $method,
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        );

        $this->securityValidator->validate($securityContext);
    }
}
