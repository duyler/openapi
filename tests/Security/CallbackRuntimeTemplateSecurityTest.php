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
