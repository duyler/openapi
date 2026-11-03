<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\CallbackContext;
use Duyler\OpenApi\Validator\Callback\CallbackValidator;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Link\LinkContext;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CallbackRuntimeExpressionTest extends TestCase
{
    private CallbackValidator $strictValidator;
    private CallbackValidator $laxValidator;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->strictValidator = $this->createValidator(strict: true);
        $this->laxValidator = $this->createValidator(strict: false);
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function body_pointer_expression_matches_resolved_path_with_context(): void
    {
        $document = $this->document('onEvent', '{$request.body#/id}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext(requestBody: ['id' => '/abc']);

        $operation = $this->strictValidator->validate(
            $this->request('/abc'),
            'onEvent',
            $document,
            $context,
        );

        self::assertSame('onEvent', $operation->operationId);
    }

    #[Test]
    public function composite_expression_with_body_insertion_matches(): void
    {
        $document = $this->document('onDone', '/items/{$request.body#/id}/done', new PathItem(post: new SchemaOperation(operationId: 'onDone')));
        $context = new CallbackContext(requestBody: ['id' => 'e7']);

        $operation = $this->strictValidator->validate(
            $this->request('/items/e7/done'),
            'onDone',
            $document,
            $context,
        );

        self::assertSame('onDone', $operation->operationId);
    }

    #[Test]
    public function method_insertion_matches_resolved_path_with_context(): void
    {
        $document = $this->document('onPost', '/{$method}', new PathItem(post: new SchemaOperation(operationId: 'onPost')));
        $context = new CallbackContext(method: 'post');

        $operation = $this->strictValidator->validate(
            $this->request('/post'),
            'onPost',
            $document,
            $context,
        );

        self::assertSame('onPost', $operation->operationId);
    }

    #[Test]
    public function url_expression_matches_when_url_path_equals_request_path(): void
    {
        $document = $this->document('onNotify', '{$url}', new PathItem(post: new SchemaOperation(operationId: 'onNotify')));
        $context = new CallbackContext(url: 'https://hooks.example/notify');

        $operation = $this->strictValidator->validate(
            $this->request('/notify'),
            'onNotify',
            $document,
            $context,
        );

        self::assertSame('onNotify', $operation->operationId);
    }

    #[Test]
    public function query_insertion_matches_resolved_path_with_context(): void
    {
        $document = $this->document('onEvent', '/{$request.query.event}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext(queryParams: ['event' => 'done']);

        $operation = $this->strictValidator->validate(
            $this->request('/done'),
            'onEvent',
            $document,
            $context,
        );

        self::assertSame('onEvent', $operation->operationId);
    }

    #[Test]
    public function header_insertion_matches_resolved_path_with_context(): void
    {
        $document = $this->document('onTrace', '/{$request.header.X-Trace}', new PathItem(post: new SchemaOperation(operationId: 'onTrace')));
        $context = new CallbackContext(requestHeaders: ['x-trace' => 'trace-7']);

        $operation = $this->strictValidator->validate(
            $this->request('/trace-7'),
            'onTrace',
            $document,
            $context,
        );

        self::assertSame('onTrace', $operation->operationId);
    }

    #[Test]
    public function false_query_value_stringifies_to_empty_and_is_unresolvable(): void
    {
        $document = $this->document('onFlag', '/items/{$request.query.flag}', new PathItem(post: new SchemaOperation(operationId: 'onFlag')));
        $context = new CallbackContext(queryParams: ['flag' => false]);

        $this->expectException(UnresolvableCallbackPathException::class);

        $this->laxValidator->validate(
            $this->request('/items/'),
            'onFlag',
            $document,
            $context,
        );
    }

    #[Test]
    public function true_query_value_stringifies_into_the_path(): void
    {
        $document = $this->document('onFlag', '/items/{$request.query.flag}', new PathItem(post: new SchemaOperation(operationId: 'onFlag')));
        $context = new CallbackContext(queryParams: ['flag' => true]);

        $operation = $this->strictValidator->validate(
            $this->request('/items/1'),
            'onFlag',
            $document,
            $context,
        );

        self::assertSame('onFlag', $operation->operationId);
    }

    #[Test]
    public function unresolvable_pointer_with_context_throws_even_in_lax_mode(): void
    {
        $document = $this->document('onEvent', '{$request.body#/unknown}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext(requestBody: ['id' => 'abc']);

        $this->expectException(UnresolvableCallbackPathException::class);

        $this->laxValidator->validate(
            $this->request('/abc'),
            'onEvent',
            $document,
            $context,
        );
    }

    #[Test]
    public function response_expression_with_context_is_unresolvable(): void
    {
        $document = $this->document('onEvent', '{$response.body#/id}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext(requestBody: ['id' => 'abc']);

        $this->expectException(UnresolvableCallbackPathException::class);

        $this->strictValidator->validate(
            $this->request('/abc'),
            'onEvent',
            $document,
            $context,
        );
    }

    #[Test]
    public function status_code_expression_with_context_is_unresolvable(): void
    {
        $document = $this->document('onEvent', '{$statusCode}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext();

        $this->expectException(UnresolvableCallbackPathException::class);

        $this->strictValidator->validate(
            $this->request('/200'),
            'onEvent',
            $document,
            $context,
        );
    }

    #[Test]
    public function same_query_expression_resolves_identically_for_link_and_callback(): void
    {
        $linkResolved = new LinkResolver()->resolve(
            new Link(parameters: ['tag' => '$request.query.tag']),
            new LinkContext(queryParams: ['tag' => 'done']),
        );

        $document = $this->document('onEvent', '/{$request.query.tag}', new PathItem(post: new SchemaOperation(operationId: 'onEvent')));
        $context = new CallbackContext(queryParams: ['tag' => 'done']);

        $operation = $this->strictValidator->validate(
            $this->request('/done'),
            'onEvent',
            $document,
            $context,
        );

        self::assertSame('done', $linkResolved->parameters['tag']);
        self::assertSame($linkResolved->parameters['tag'], 'done');
        self::assertSame('onEvent', $operation->operationId);
    }

    private function createValidator(bool $strict): CallbackValidator
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $requestValidator->method('validate');

        return new CallbackValidator(
            $requestValidator,
            new PathRegexCache(),
            strictCallbackRuntimeTemplate: $strict,
            pregExecutor: new PregExecutor(),
        );
    }

    private function document(string $callbackName, string $expression, PathItem $pathItem): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Callback Runtime Expressions', version: '1.0.0'),
            components: new Components(
                callbacks: [
                    $callbackName => new Callbacks([
                        $callbackName => [$expression => $pathItem],
                    ]),
                ],
            ),
            paths: new Paths([]),
        );
    }

    private function request(string $path): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest('POST', 'https://hooks.example' . $path);
    }
}
