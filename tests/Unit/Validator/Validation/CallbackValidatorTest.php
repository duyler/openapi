<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class CallbackValidatorTest extends TestCase
{
    private const string CALLBACK_RUNTIME_TEMPLATE_YAML_DOCUMENT_TRIGGER_PATH = '/trigger';

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * Anti-test guard: build the outer validator without the third
     * constructor argument. The constructor must default to strict
     * (fail-closed) so that direct-instantiation callers — frameworks that
     * use this library as a dependency and bypass OpenApiValidatorBuilder —
     * receive the same SSRF protection as builder callers. To verify,
     * temporarily revert the default in Validation\CallbackValidator
     * from `= true` back to `= false` and re-run this test — it must fail
     * because the validator would resolve the runtime expression as a
     * wildcard instead of throwing.
     */
    #[Test]
    public function outer_constructor_defaults_to_strict_when_called_without_flag(): void
    {
        // Arrange: omit the third argument — the constructor must default
        // to strict (fail-closed), matching OpenApiValidatorBuilder.
        $context = $this->buildDependencies(
            $this->buildDocumentWithCallback(),
        );

        $validator = new CallbackValidator($context);

        $request = $this->psrFactory->createServerRequest(
            'POST',
            '/attacker-controlled-url',
        );

        $this->expectException(UnresolvableCallbackPathException::class);
        $this->expectExceptionMessage('{$request.body#/callback_url}');

        // Act + Assert: strict default throws on the runtime template.
        $validator->validate($request, 'myCallback');
    }

    #[Test]
    public function outer_constructor_allows_lax_when_strict_false_passed_explicitly(): void
    {
        // Arrange: explicit opt-out of strict mode — legacy wildcard
        // behaviour. This documents the BC path for callers that
        // intentionally validate callback URLs at the application level.
        $context = $this->buildDependencies(
            $this->buildDocumentWithCallback(),
        );

        $validator = new CallbackValidator(
            $context,
            securityValidation: false,
            strictCallbackRuntimeTemplate: false,
        );

        $request = $this->psrFactory->createServerRequest(
            'POST',
            '/attacker-controlled-url',
        );

        // Act: legacy wildcard accepts any path.
        $operation = $validator->validate($request, 'myCallback');

        // Assert: callback resolved through the runtime-template wildcard.
        $this->assertSame('myCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    private function buildDocumentWithCallback(): OpenApiDocument
    {
        $callbackOperation = new SchemaOperation(operationId: 'myCallback');
        $callbackPathItem = new PathItem(post: $callbackOperation);

        $triggerOperation = new SchemaOperation(
            operationId: 'trigger',
            callbacks: new Callbacks([
                'myCallback' => [
                    '{$request.body#/callback_url}' => $callbackPathItem,
                ],
            ]),
        );

        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            paths: new Paths([
                self::CALLBACK_RUNTIME_TEMPLATE_YAML_DOCUMENT_TRIGGER_PATH => new PathItem(post: $triggerOperation),
            ]),
            components: new Components(
                callbacks: [
                    'myCallback' => new Callbacks([
                        'myCallback' => [
                            '{$request.body#/callback_url}' => $callbackPathItem,
                        ],
                    ]),
                ],
            ),
        );
    }

    private function buildDependencies(OpenApiDocument $document): ValidatorDependencies
    {
        return new ValidatorDependencies(
            document: $document,
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            errorFormatter: new SimpleFormatter(),
            refResolver: new RefResolver(),
        );
    }
}
