<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\Internal\DocumentSchemaCollector;
use Duyler\OpenApi\Schema\Parser\Internal\KnownSchemaKeywords;
use Duyler\OpenApi\Schema\Parser\Internal\OasBaseVocabulary;
use Duyler\OpenApi\Schema\Parser\Internal\UnknownKeywordGuard;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorRegistry;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(UnknownKeywordGuard::class)]
#[CoversClass(OasBaseVocabulary::class)]
#[CoversClass(DocumentSchemaCollector::class)]
final class UnknownKeywordGuardTest extends TestCase
{
    private const string TYPO_KEYWORD = 'requird';

    #[Test]
    public function typo_keyword_throws_with_keyword_name_in_message(): void
    {
        $guard = new UnknownKeywordGuard();

        try {
            $guard->guard($this->documentWithComponentSchema(
                new Schema(type: 'object', extras: [self::TYPO_KEYWORD => ['foo']]),
            ));

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                sprintf("Unknown schema keyword '%s' at #/components/schemas/Subject", self::TYPO_KEYWORD),
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function nested_subschema_keywords_are_checked(): void
    {
        $guard = new UnknownKeywordGuard();

        $document = $this->documentWithComponentSchema(new Schema(
            type: 'object',
            properties: [
                'child' => new Schema(type: 'string', extras: [self::TYPO_KEYWORD => true]),
            ],
        ));

        try {
            $guard->guard($document);

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                'at #/components/schemas/Subject/properties/child',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function schemas_in_path_contexts_are_checked(): void
    {
        $guard = new UnknownKeywordGuard();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Paths', version: '1.0.0'),
            paths: new Paths(paths: [
                '/items' => new PathItem(
                    post: new Operation(
                        requestBody: new RequestBody(content: new Content(mediaTypes: [
                            'application/json' => new MediaType(schema: new Schema(
                                type: 'object',
                                extras: [self::TYPO_KEYWORD => true],
                            )),
                        ])),
                        responses: new Responses(responses: [
                            '200' => new Response(description: 'OK'),
                        ]),
                    ),
                ),
            ]),
        );

        try {
            $guard->guard($document);

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                'at #/paths/~1items/post/requestBody/content/application~1json/schema',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function special_characters_in_pointer_segments_are_escaped(): void
    {
        $guard = new UnknownKeywordGuard();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Escaped pointers', version: '1.0.0'),
            components: new Components(schemas: [
                'Spe~cial/Name' => new Schema(
                    type: 'object',
                    properties: [
                        'foo/bar' => new Schema(type: 'string', extras: [self::TYPO_KEYWORD => true]),
                    ],
                ),
            ]),
        );

        try {
            $guard->guard($document);

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                'at #/components/schemas/Spe~0cial~1Name/properties/foo~1bar',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function vendor_prefix_keyword_is_legal(): void
    {
        $guard = new UnknownKeywordGuard();

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            extras: ['x-anything' => ['nested' => ['deep' => true]]],
        )));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function known_schema_keywords_are_legal(): void
    {
        $guard = new UnknownKeywordGuard();

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            required: ['name'],
            properties: ['name' => new Schema(type: 'string', minLength: 1)],
        )));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function external_docs_is_the_only_oas_base_vocabulary_extra_keyword(): void
    {
        $guard = new UnknownKeywordGuard();

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'string',
            extras: ['externalDocs' => ['url' => 'https://example.com/docs']],
        )));

        self::assertTrue(OasBaseVocabulary::isKnown('externalDocs'));
        self::assertFalse(OasBaseVocabulary::isKnown('discriminator'));
        self::assertFalse(OasBaseVocabulary::isKnown('example'));
        self::assertFalse(OasBaseVocabulary::isKnown('xml'));
        self::assertTrue(KnownSchemaKeywords::isKnown('discriminator'));
        self::assertTrue(KnownSchemaKeywords::isKnown('example'));
        self::assertTrue(KnownSchemaKeywords::isKnown('xml'));
    }

    #[Test]
    public function schema_inside_component_link_request_body_is_checked(): void
    {
        $guard = new UnknownKeywordGuard();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Component links', version: '1.0.0'),
            components: new Components(links: [
                'CreateTagLink' => new Link(
                    operationId: 'createTag',
                    requestBody: new RequestBody(content: new Content(mediaTypes: [
                        'application/json' => new MediaType(schema: new Schema(
                            type: 'object',
                            extras: [self::TYPO_KEYWORD => true],
                        )),
                    ])),
                ),
            ]),
        );

        try {
            $guard->guard($document);

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                'at #/components/links/CreateTagLink/requestBody/content/application~1json/schema',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function schemas_inside_component_callbacks_are_checked(): void
    {
        $guard = new UnknownKeywordGuard();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Component callbacks', version: '1.0.0'),
            components: new Components(callbacks: [
                'TagCreated' => new Callbacks(callbacks: [
                    'tagEvent' => [
                        '{$request.body#/callbackUrl}' => new PathItem(
                            post: new Operation(
                                requestBody: new RequestBody(content: new Content(mediaTypes: [
                                    'application/json' => new MediaType(schema: new Schema(
                                        type: 'object',
                                        extras: [self::TYPO_KEYWORD => true],
                                    )),
                                ])),
                                responses: new Responses(responses: [
                                    '200' => new Response(description: 'OK'),
                                ]),
                            ),
                        ),
                    ],
                ]),
            ]),
        );

        try {
            $guard->guard($document);

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                'at #/components/callbacks/TagCreated/tagEvent/{$request.body#~1callbackUrl}/post'
                . '/requestBody/content/application~1json/schema',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function schemas_inside_component_headers_are_checked(): void
    {
        $guard = new UnknownKeywordGuard();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Component headers', version: '1.0.0'),
            components: new Components(headers: [
                'X-RateLimit' => new Header(schema: new Schema(
                    type: 'integer',
                    extras: [self::TYPO_KEYWORD => true],
                )),
            ]),
        );

        try {
            $guard->guard($document);

            self::fail(sprintf('Expected %s', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                'at #/components/headers/X-RateLimit/schema',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function legacy_keywords_are_legal(): void
    {
        $guard = new UnknownKeywordGuard();

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            extras: [
                '$recursiveRef' => '#',
                '$recursiveAnchor' => true,
            ],
        )));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function registered_keyword_is_legal_without_allow_list(): void
    {
        $registry = new KeywordValidatorRegistry()
            ->with('even', $this->createKeywordValidator());

        $guard = new UnknownKeywordGuard(registry: $registry);

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'integer',
            extras: ['even' => true],
        )));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function allow_listed_keyword_is_legal(): void
    {
        $guard = new UnknownKeywordGuard(allowedUnknownKeywords: ['legacy-thing']);

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            extras: ['legacy-thing' => ['config']],
        )));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function allow_list_only_legalizes_listed_keywords(): void
    {
        $guard = new UnknownKeywordGuard(allowedUnknownKeywords: ['legacy-thing']);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('other-thing');

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            extras: ['legacy-thing' => 1, 'other-thing' => 2],
        )));
    }

    #[Test]
    public function strict_keywords_disabled_skips_guard(): void
    {
        $guard = new UnknownKeywordGuard(strictKeywords: false);

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            extras: [self::TYPO_KEYWORD => true],
        )));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function shared_subschema_instance_is_scanned_once(): void
    {
        $shared = new Schema(type: 'string');

        $guard = new UnknownKeywordGuard();

        $guard->guard($this->documentWithComponentSchema(new Schema(
            type: 'object',
            properties: [
                'a' => $shared,
                'b' => $shared,
            ],
            allOf: [$shared],
        )));

        $this->expectNotToPerformAssertions();
    }

    private function documentWithComponentSchema(Schema $schema): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Strict keywords', version: '1.0.0'),
            components: new Components(schemas: ['Subject' => $schema]),
        );
    }

    private function createKeywordValidator(): KeywordValidatorInterface
    {
        return new readonly class implements KeywordValidatorInterface {
            #[Override]
            public function appliesTo(Schema $schema): bool
            {
                return null !== ($schema->extras['even'] ?? null);
            }

            #[Override]
            public function validate(mixed $data, Schema $schema, ValidationContext $context): void {}
        };
    }
}
