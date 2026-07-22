<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\CallbackValidator;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Security regression for P-029 / SEC-09: a callback expression like
 * `{$request.body#/callback_url}` references the original triggering request
 * and cannot be resolved by the validator, which makes path validation a
 * wildcard that accepts any URL. When the runtime template is attacker-
 * controlled, declared security checks on the callback pathItem still pass
 * against an arbitrary URL. Strict mode is the default since the SEC-09 fix
 * on three converging levels: (1) the `OpenApiValidatorBuilder::build()`
 * `?? true` fallback; (2) the `Validation\CallbackValidator` constructor
 * default (R3-SEC-009); (3) the inner `Callback\CallbackValidator`
 * constructor default (R3-SEC-009). Legacy wildcard behaviour is opt-in via
 * {@see OpenApiValidatorBuilder::disableStrictCallbackRuntimeTemplate()} or
 * by passing `strictCallbackRuntimeTemplate: false` explicitly to either
 * class constructor. The pre-fix opt-in method
 * {@see OpenApiValidatorBuilder::enableStrictCallbackRuntimeTemplate()} is
 * retained as a deprecated no-op for backward compatibility.
 *
 * @internal
 */
final class CallbackRuntimeTemplateSecurityTest extends TestCase
{
    private const string CALLBACK_RUNTIME_TEMPLATE_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Test
  version: 1.0.0
paths:
  /trigger:
    post:
      operationId: trigger
components:
  callbacks:
    myCallback:
      myCallback:
        '{$request.body#/callback_url}':
          post:
            operationId: myCallback
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function strict_mode_throws_on_runtime_template(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        $validator = new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
            strictCallbackRuntimeTemplate: true,
        );

        $pathItem = new PathItem(post: new SchemaOperation(operationId: 'myCallback'));

        $document = $this->createDocumentWithComponentCallback(
            'myCallback',
            ['{$request.body#/callback_url}' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        $this->expectException(UnresolvableCallbackPathException::class);
        $this->expectExceptionMessage('{$request.body#/callback_url}');

        $validator->validate($request, 'myCallback', $document);
    }

    #[Test]
    public function inner_validator_allows_wildcard_when_strict_explicitly_disabled(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        $validator = new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
            strictCallbackRuntimeTemplate: false,
        );

        $pathItem = new PathItem(post: new SchemaOperation(operationId: 'myCallback'));

        $document = $this->createDocumentWithComponentCallback(
            'myCallback',
            ['{$request.body#/callback_url}' => $pathItem],
        );

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        $resolved = $validator->validate($request, 'myCallback', $document);

        $this->assertSame('myCallback', $resolved->operationId);
    }

    #[Test]
    public function builder_strict_mode_is_default(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_RUNTIME_TEMPLATE_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        $this->expectException(UnresolvableCallbackPathException::class);
        $this->expectExceptionMessage('{$request.body#/callback_url}');

        $validator->validateCallback($request, 'myCallback');
    }

    /**
     * Anti-test guard: this assertion proves strict mode is on by default.
     * To verify, temporarily revert the fallback in
     * `OpenApiValidatorBuilder::build()` from `?? true` back to `?? false`
     * and re-run this test — it must fail because the validator would
     * resolve the runtime expression as a wildcard instead of throwing.
     * Restoring the `?? true` fallback makes it pass again.
     */
    #[Test]
    public function builder_unresolvable_runtime_expression_throws_by_default(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_RUNTIME_TEMPLATE_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/no-request-body-field-resolved-here');

        $this->expectException(UnresolvableCallbackPathException::class);

        $validator->validateCallback($request, 'myCallback');
    }

    #[Test]
    public function builder_disable_strict_allows_wildcard(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_RUNTIME_TEMPLATE_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        $operation = $validator->validateCallback($request, 'myCallback');

        $this->assertSame('myCallback', $operation->path);
    }

    #[Test]
    public function builder_enable_strict_is_backward_compatible_noop(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_RUNTIME_TEMPLATE_YAML)
            ->enableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/attacker-controlled-url');

        $this->expectException(UnresolvableCallbackPathException::class);

        $validator->validateCallback($request, 'myCallback');
    }

    /**
     * @param array<string, PathItem> $expressions
     */
    private function createDocumentWithComponentCallback(
        string $callbackName,
        array $expressions,
    ): OpenApiDocument {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            paths: new Paths(['/trigger' => new PathItem(post: new SchemaOperation(operationId: 'trigger'))]),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([$callbackName => $expressions]),
                ],
            ),
        );
    }
}
