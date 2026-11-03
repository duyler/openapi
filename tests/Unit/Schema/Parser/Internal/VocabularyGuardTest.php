<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\LegacyKeywordWarningEvent;
use Duyler\OpenApi\Event\VocabularyDisabledWarningEvent;
use Duyler\OpenApi\Schema\Exception\VocabularyNotSupportedException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\Internal\LegacyKeywordDetector;
use Duyler\OpenApi\Schema\Parser\Internal\VocabularyGuard;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalker;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalkerInterface;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(VocabularyGuard::class)]
#[CoversClass(LegacyKeywordDetector::class)]
final class VocabularyGuardTest extends TestCase
{
    private const string UNKNOWN_VOCABULARY_URI = 'https://example.com/vocab/custom';

    public static function provideSupportedVocabularyUris(): Generator
    {
        foreach (VocabularyGuard::SUPPORTED_VOCABULARY_URIS as $uri) {
            foreach ([true, false] as $required) {
                yield sprintf('%s required=%s', $uri, $required ? 'true' : 'false') => [$uri, $required];
            }
        }
    }

    #[Test]
    #[DataProvider('provideSupportedVocabularyUris')]
    public function supported_vocabulary_uri_passes_without_events(string $uri, bool $required): void
    {
        $events = [];
        $dispatcher = self::createDispatcher($events);

        $guard = new VocabularyGuard();

        $guard->guard(self::documentWithComponentSchema(
            new Schema(type: 'object', vocabulary: [$uri => $required]),
        ), $dispatcher);

        self::assertSame([], $events);
    }

    #[Test]
    public function unsupported_vocabulary_uri_with_true_throws(): void
    {
        $guard = new VocabularyGuard();

        $this->expectException(VocabularyNotSupportedException::class);
        $this->expectExceptionMessage(self::UNKNOWN_VOCABULARY_URI);

        $guard->guard(self::documentWithComponentSchema(
            new Schema(type: 'object', vocabulary: [self::UNKNOWN_VOCABULARY_URI => true]),
        ));
    }

    #[Test]
    public function unsupported_vocabulary_uri_with_false_dispatches_warning(): void
    {
        $events = [];
        $dispatcher = self::createDispatcher($events);

        $guard = new VocabularyGuard();

        $guard->guard(self::documentWithComponentSchema(
            new Schema(type: 'object', vocabulary: [self::UNKNOWN_VOCABULARY_URI => false]),
        ), $dispatcher);

        self::assertCount(1, $events);
        self::assertInstanceOf(VocabularyDisabledWarningEvent::class, $events[0]);
        self::assertSame(self::UNKNOWN_VOCABULARY_URI, $events[0]->vocabularyUri);
    }

    #[Test]
    public function vocabulary_uri_comparison_is_byte_wise(): void
    {
        $guard = new VocabularyGuard();

        $this->expectException(VocabularyNotSupportedException::class);
        $this->expectExceptionMessage('https://json-schema.org/draft/2020-12/Vocab/core');

        $guard->guard(self::documentWithComponentSchema(
            new Schema(type: 'object', vocabulary: ['https://json-schema.org/draft/2020-12/Vocab/core' => true]),
        ));
    }

    #[Test]
    public function legacy_keywords_dispatch_migration_warnings_once_per_keyword(): void
    {
        $events = [];
        $dispatcher = self::createDispatcher($events);

        $document = self::documentWithComponentSchema(
            new Schema(
                type: 'object',
                extras: ['$recursiveRef' => '#/x'],
                properties: [
                    'a' => new Schema(type: 'string', extras: ['$recursiveRef' => '#/y']),
                    'b' => new Schema(type: 'string', extras: ['$recursiveAnchor' => true]),
                ],
            ),
        );

        $guard = new VocabularyGuard();

        $guard->guard($document, $dispatcher);

        self::assertCount(2, $events);
        self::assertInstanceOf(LegacyKeywordWarningEvent::class, $events[0]);
        self::assertSame('$recursiveRef', $events[0]->legacyKeyword);
        self::assertSame('$dynamicRef', $events[0]->migrationKeyword);
        self::assertInstanceOf(LegacyKeywordWarningEvent::class, $events[1]);
        self::assertSame('$recursiveAnchor', $events[1]->legacyKeyword);
        self::assertSame('$dynamicAnchor', $events[1]->migrationKeyword);
    }

    #[Test]
    public function guard_visits_every_schema_exactly_once(): void
    {
        $traversal = self::fullTraversalDocument();
        $events = [];
        $dispatcher = self::createDispatcher($events);
        $walker = $this->createCountingWalker($traversal['schemaCount']);

        $guard = new VocabularyGuard(walker: $walker);

        $guard->guard($traversal['document'], $dispatcher);

        self::assertCount(2, $events);
        self::assertInstanceOf(VocabularyDisabledWarningEvent::class, $events[0]);
        self::assertSame(self::UNKNOWN_VOCABULARY_URI, $events[0]->vocabularyUri);
        self::assertInstanceOf(LegacyKeywordWarningEvent::class, $events[1]);
        self::assertSame('$recursiveRef', $events[1]->legacyKeyword);
        self::assertSame('$dynamicRef', $events[1]->migrationKeyword);
    }

    #[Test]
    public function unsupported_optional_vocabulary_in_two_subschemas_dispatches_one_warning(): void
    {
        $events = [];
        $dispatcher = self::createDispatcher($events);

        $document = self::documentWithComponentSchema(
            new Schema(
                type: 'object',
                vocabulary: [self::UNKNOWN_VOCABULARY_URI => false],
                properties: [
                    'a' => new Schema(type: 'string', vocabulary: [self::UNKNOWN_VOCABULARY_URI => false]),
                    'b' => new Schema(type: 'string', vocabulary: [self::UNKNOWN_VOCABULARY_URI => false]),
                ],
            ),
        );

        $guard = new VocabularyGuard();

        $guard->guard($document, $dispatcher);

        self::assertCount(1, $events);
        self::assertInstanceOf(VocabularyDisabledWarningEvent::class, $events[0]);
        self::assertSame(self::UNKNOWN_VOCABULARY_URI, $events[0]->vocabularyUri);
    }

    #[Test]
    public function clean_document_without_dispatcher_passes(): void
    {
        $guard = new VocabularyGuard();

        $guard->guard(new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Empty', version: '1.0.0'),
        ));
        $guard->guard(self::documentWithComponentSchema(new Schema(type: 'object')));

        $this->expectNotToPerformAssertions();
    }

    private static function createDispatcher(array &$events): ArrayDispatcher
    {
        $events = [];

        return new ArrayDispatcher([
            VocabularyDisabledWarningEvent::class => [
                static function (VocabularyDisabledWarningEvent $event) use (&$events): void {
                    $events[] = $event;
                },
            ],
            LegacyKeywordWarningEvent::class => [
                static function (LegacyKeywordWarningEvent $event) use (&$events): void {
                    $events[] = $event;
                },
            ],
        ]);
    }

    private static function documentWithComponentSchema(Schema $schema): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Vocabulary', version: '1.0.0'),
            components: new Components(schemas: ['Subject' => $schema]),
        );
    }

    private static function content(Schema $schema): Content
    {
        return new Content(mediaTypes: ['application/json' => new MediaType(schema: $schema)]);
    }

    private static function parameter(string $name, ?Schema $schema): Parameter
    {
        return new Parameter(name: $name, in: 'query', schema: $schema);
    }

    private static function operationWithResponseSchema(Schema $schema): Operation
    {
        return new Operation(responses: new Responses(responses: [
            '200' => new Response(description: 'OK', content: self::content($schema)),
        ]));
    }

    /** @return array{document: OpenApiDocument, schemaCount: int} */
    private static function fullTraversalDocument(): array
    {
        $pathParamSchema = new Schema(type: 'string');
        $operationParamSchema = new Schema(type: 'string');
        $requestBodySchema = new Schema(type: 'string');
        $responseSchema = new Schema(type: 'string');
        $responseHeaderSchema = new Schema(type: 'string');
        $responseLinkSchema = new Schema(type: 'string');
        $sharedLinkSchema = new Schema(type: 'string');
        $additionalOperationSchema = new Schema(type: 'string');
        $callbackSchema = new Schema(type: 'string');
        $webhookSchema = new Schema(type: 'string');
        $componentParamSchema = new Schema(type: 'string');
        $componentRequestSchema = new Schema(type: 'string');
        $componentLinkSchema = new Schema(type: 'string');
        $componentCallbackSchema = new Schema(type: 'string');
        $componentPathItemSchema = new Schema(type: 'string');
        $componentMediaTypeSchema = new Schema(type: 'string');
        $componentMediaTypeItemSchema = new Schema(type: 'string');
        $componentResponseSchema = new Schema(type: 'string');
        $propertySchema = new Schema(
            type: 'string',
            vocabulary: [self::UNKNOWN_VOCABULARY_URI => false],
        );
        $defsSchema = new Schema(type: 'string', extras: ['$recursiveRef' => '#/defs']);
        $subjectSchema = new Schema(
            type: 'object',
            properties: [
                'a' => $propertySchema,
                'b' => new Schema(type: 'string', extras: ['$recursiveRef' => '#/b']),
            ],
            defs: ['D' => $defsSchema],
        );

        $schemaCount = 22;

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Traversal', version: '1.0.0'),
            paths: new Paths(paths: [
                '/items' => new PathItem(
                    additionalOperations: [
                        'COPY' => self::operationWithResponseSchema($additionalOperationSchema),
                    ],
                    parameters: new Parameters(parameters: [
                        self::parameter('limit', $pathParamSchema),
                        'limitRef',
                    ]),
                    get: new Operation(
                        parameters: new Parameters(parameters: [self::parameter('q', $operationParamSchema)]),
                        requestBody: new RequestBody(content: self::content($requestBodySchema)),
                        responses: new Responses(responses: [
                            '200' => new Response(
                                description: 'OK',
                                headers: new Headers(headers: [
                                    'X-Rate' => new Header(schema: $responseHeaderSchema),
                                ]),
                                content: self::content($responseSchema),
                                links: new Links(links: [
                                    'Item' => new Link(requestBody: new RequestBody(
                                        content: self::content($responseLinkSchema),
                                    )),
                                    'Empty' => new Link(),
                                ]),
                            ),
                        ]),
                        callbacks: new Callbacks(callbacks: [
                            'onEvent' => [
                                '{$request.body#/url}' => new PathItem(
                                    get: new Operation(
                                        responses: new Responses(responses: [
                                            '200' => new Response(
                                                description: 'OK',
                                                content: self::content($callbackSchema),
                                            ),
                                        ]),
                                    ),
                                ),
                            ],
                        ]),
                    ),
                ),
            ]),
            webhooks: new Webhooks([
                'itemCreated' => new PathItem(
                    post: new Operation(
                        responses: new Responses(responses: [
                            '200' => new Response(description: 'OK', content: self::content($webhookSchema)),
                        ]),
                    ),
                ),
            ]),
            components: new Components(
                schemas: ['Subject' => $subjectSchema],
                responses: ['R' => new Response(description: 'OK', content: self::content($componentResponseSchema))],
                parameters: [
                    'P' => self::parameter('limit', $componentParamSchema),
                    'P2' => self::parameter('offset', $sharedLinkSchema),
                ],
                requestBodies: ['RB' => new RequestBody(content: self::content($componentRequestSchema))],
                headers: ['H' => new Header(schema: $sharedLinkSchema)],
                links: ['L' => new Link(requestBody: new RequestBody(content: self::content($componentLinkSchema)))],
                callbacks: ['C' => new Callbacks(callbacks: [
                    'onEvent' => [
                        '{$request.body#/url}' => new PathItem(
                            get: new Operation(
                                responses: new Responses(responses: [
                                    '200' => new Response(
                                        description: 'OK',
                                        content: self::content($componentCallbackSchema),
                                    ),
                                ]),
                            ),
                        ),
                    ],
                ])],
                pathItems: ['PI' => new PathItem(
                    get: self::operationWithResponseSchema($componentPathItemSchema),
                )],
                mediaTypes: ['MT' => new MediaType(
                    schema: $componentMediaTypeSchema,
                    itemSchema: $componentMediaTypeItemSchema,
                )],
            ),
        );

        return ['document' => $document, 'schemaCount' => $schemaCount];
    }

    private function createCountingWalker(int $expectedVisits): SchemaSubschemaWalkerInterface
    {
        $walker = $this->createMock(SchemaSubschemaWalkerInterface::class);
        $walker->expects($this->exactly($expectedVisits))->method('iterateSubSchemas')->willReturnCallback(
            static fn(Schema $schema): Generator => new SchemaSubschemaWalker()->iterateSubSchemas($schema),
        );

        return $walker;
    }
}
