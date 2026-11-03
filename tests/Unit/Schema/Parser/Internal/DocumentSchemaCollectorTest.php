<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

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
use Duyler\OpenApi\Schema\Parser\Internal\DocumentSchemaCollector;
use Duyler\OpenApi\Schema\Parser\Internal\SchemaLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(DocumentSchemaCollector::class)]
#[CoversClass(SchemaLocation::class)]
final class DocumentSchemaCollectorTest extends TestCase
{
    #[Test]
    public function collects_pointers_for_every_document_context(): void
    {
        $collector = new DocumentSchemaCollector();

        $locations = $collector->collectWithLocations($this->documentCoveringAllContexts());

        self::assertSame([
            '#/paths/~1items/get/parameters/0/schema',
            '#/paths/~1items/get/parameters/0/content/application~1json/schema',
            '#/paths/~1items/get/responses/200/headers/X-Count/schema',
            '#/paths/~1items/get/responses/200/content/application~1json/schema',
            '#/paths/~1items/get/responses/200/links/Next/requestBody/content/application~1json/schema',
            '#/paths/~1items/get/callbacks/itemCreated/{$request.body#~1url}/post/requestBody/content/application~1json/schema',
            '#/paths/~1items/traceQuery/requestBody/content/application~1json/schema',
            '#/paths/~1items/parameters/0/schema',
            '#/webhooks/itemCreated/post/responses/200/content/application~1json/schema',
            '#/components/schemas/Item',
            '#/components/responses/NotFound/content/application~1json/schema',
            '#/components/parameters/LimitParam/schema',
            '#/components/requestBodies/ItemBody/content/application~1json/schema',
            '#/components/headers/X-RateLimit/schema',
            '#/components/links/CreateTagLink/requestBody/content/application~1json/schema',
            '#/components/callbacks/TagCreated/tagEvent/{$request.body#~1callbackUrl}/post/responses/200/content/application~1json/schema',
            '#/components/pathItems/Echo/get/responses/200/content/application~1json/schema',
            '#/components/mediaTypes/application~1json/schema',
            '#/components/mediaTypes/application~1json/itemSchema',
        ], $this->pointers($locations));
    }

    #[Test]
    public function collect_returns_schemas_of_the_collected_locations(): void
    {
        $collector = new DocumentSchemaCollector();
        $document = $this->documentCoveringAllContexts();

        $locations = $collector->collectWithLocations($document);
        $schemas = $collector->collect($document);

        self::assertSame(
            array_map(static fn(SchemaLocation $location): Schema => $location->schema, $locations),
            $schemas,
        );
    }

    #[Test]
    public function document_without_components_collects_only_path_schemas(): void
    {
        $schema = new Schema(type: 'string');
        $collector = new DocumentSchemaCollector();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Paths only', version: '1.0.0'),
            paths: new Paths(paths: [
                '/items' => new PathItem(get: new Operation(responses: new Responses(responses: [
                    '200' => new Response(description: 'OK', content: new Content(mediaTypes: [
                        'application/json' => new MediaType(schema: $schema),
                    ])),
                ]))),
            ]),
        );

        $locations = $collector->collectWithLocations($document);

        self::assertCount(1, $locations);
        self::assertSame('#/paths/~1items/get/responses/200/content/application~1json/schema', $locations[0]->pointer);
        self::assertSame($schema, $locations[0]->schema);
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<string>
     */
    private function pointers(array $locations): array
    {
        return array_map(static fn(SchemaLocation $location): string => $location->pointer, $locations);
    }

    private function documentCoveringAllContexts(): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'All contexts', version: '1.0.0'),
            paths: new Paths(paths: [
                '/items' => new PathItem(
                    get: new Operation(
                        parameters: new Parameters(parameters: [
                            new Parameter(
                                name: 'limit',
                                in: 'query',
                                schema: new Schema(type: 'integer'),
                                content: new Content(mediaTypes: [
                                    'application/json' => new MediaType(schema: new Schema(type: 'object')),
                                ]),
                            ),
                        ]),
                        responses: new Responses(responses: [
                            '200' => new Response(
                                description: 'OK',
                                headers: new Headers(headers: [
                                    'X-Count' => new Header(schema: new Schema(type: 'integer')),
                                ]),
                                content: new Content(mediaTypes: [
                                    'application/json' => new MediaType(schema: new Schema(type: 'array')),
                                ]),
                                links: new Links(links: [
                                    'Next' => new Link(
                                        operationId: 'nextItem',
                                        requestBody: new RequestBody(content: new Content(mediaTypes: [
                                            'application/json' => new MediaType(schema: new Schema(type: 'object')),
                                        ])),
                                    ),
                                ]),
                            ),
                        ]),
                        callbacks: new Callbacks(callbacks: [
                            'itemCreated' => [
                                '{$request.body#/url}' => new PathItem(post: new Operation(
                                    requestBody: new RequestBody(content: new Content(mediaTypes: [
                                        'application/json' => new MediaType(schema: new Schema(type: 'object')),
                                    ])),
                                )),
                            ],
                        ]),
                    ),
                    parameters: new Parameters(parameters: [
                        new Parameter(name: 'path-param', in: 'path', schema: new Schema(type: 'string')),
                    ]),
                    additionalOperations: [
                        'traceQuery' => new Operation(requestBody: new RequestBody(content: new Content(mediaTypes: [
                            'application/json' => new MediaType(schema: new Schema(type: 'object')),
                        ]))),
                    ],
                ),
            ]),
            webhooks: new Webhooks(webhooks: [
                'itemCreated' => new PathItem(post: new Operation(responses: new Responses(responses: [
                    '200' => new Response(description: 'OK', content: new Content(mediaTypes: [
                        'application/json' => new MediaType(schema: new Schema(type: 'object')),
                    ])),
                ]))),
            ]),
            components: new Components(
                schemas: ['Item' => new Schema(type: 'object')],
                responses: ['NotFound' => new Response(description: 'Not found', content: new Content(mediaTypes: [
                    'application/json' => new MediaType(schema: new Schema(type: 'object')),
                ]))],
                parameters: ['LimitParam' => new Parameter(name: 'limit', in: 'query', schema: new Schema(type: 'integer'))],
                requestBodies: ['ItemBody' => new RequestBody(content: new Content(mediaTypes: [
                    'application/json' => new MediaType(schema: new Schema(type: 'object')),
                ]))],
                headers: ['X-RateLimit' => new Header(schema: new Schema(type: 'integer'))],
                links: ['CreateTagLink' => new Link(
                    operationId: 'createTag',
                    requestBody: new RequestBody(content: new Content(mediaTypes: [
                        'application/json' => new MediaType(schema: new Schema(type: 'object')),
                    ])),
                )],
                callbacks: ['TagCreated' => new Callbacks(callbacks: [
                    'tagEvent' => [
                        '{$request.body#/callbackUrl}' => new PathItem(post: new Operation(
                            responses: new Responses(responses: [
                                '200' => new Response(description: 'OK', content: new Content(mediaTypes: [
                                    'application/json' => new MediaType(schema: new Schema(type: 'object')),
                                ])),
                            ]),
                        )),
                    ],
                ])],
                pathItems: ['Echo' => new PathItem(get: new Operation(responses: new Responses(responses: [
                    '200' => new Response(description: 'OK', content: new Content(mediaTypes: [
                        'application/json' => new MediaType(schema: new Schema(type: 'object')),
                    ])),
                ])))],
                mediaTypes: ['application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                    itemSchema: new Schema(type: 'string'),
                )],
            ),
        );
    }
}
