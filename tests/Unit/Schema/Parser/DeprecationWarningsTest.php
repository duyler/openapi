<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DeprecationWarningsTest extends TestCase
{
    #[Test]
    public function warns_on_nullable_in_v32_schema(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('nullable'));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'string',
                        'nullable' => true,
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function does_not_warn_on_nullable_in_v31_schema(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'string',
                        'nullable' => true,
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function warns_on_example_in_v32_media_type(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd(
                $this->stringContains('example'),
                $this->stringContains('MediaType Object'),
            ));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'example' => '{"id": 1}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function warns_on_allow_empty_value_in_v32_header(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd(
                $this->stringContains('allowEmptyValue'),
                $this->stringContains('Header Object'),
            ));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'headers' => [
                                    'X-Custom' => [
                                        'allowEmptyValue' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function warns_on_bool_exclusive_minimum_in_v32(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd(
                $this->stringContains('exclusiveMinimum'),
                $this->stringContains('bool'),
            ));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'minimum' => 10,
                        'exclusiveMinimum' => true,
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function warns_on_bool_exclusive_maximum_in_v32(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd(
                $this->stringContains('exclusiveMaximum'),
                $this->stringContains('bool'),
            ));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'maximum' => 100,
                        'exclusiveMaximum' => true,
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function does_not_warn_on_number_exclusive_minimum_in_v32(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method('warning')
            ->with($this->stringContains('exclusiveMinimum'));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'exclusiveMinimum' => 10,
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }

    #[Test]
    public function converts_bool_exclusive_minimum_in_v30(): void
    {
        $parser = new JsonParser();

        $json = json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'minimum' => 10,
                        'exclusiveMinimum' => true,
                    ],
                ],
            ],
        ]);

        $document = $parser->parse($json);
        $schema = $document->components->schemas['Test'];

        self::assertSame(10.0, $schema->exclusiveMinimum);
        self::assertSame(10.0, $schema->minimum);
    }

    #[Test]
    public function converts_bool_exclusive_maximum_in_v30(): void
    {
        $parser = new JsonParser();

        $json = json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'maximum' => 100,
                        'exclusiveMaximum' => true,
                    ],
                ],
            ],
        ]);

        $document = $parser->parse($json);
        $schema = $document->components->schemas['Test'];

        self::assertSame(100.0, $schema->exclusiveMaximum);
        self::assertSame(100.0, $schema->maximum);
    }

    #[Test]
    public function does_not_convert_when_bool_exclusive_is_false_in_v30(): void
    {
        $parser = new JsonParser();

        $json = json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'minimum' => 10,
                        'exclusiveMinimum' => false,
                    ],
                ],
            ],
        ]);

        $document = $parser->parse($json);
        $schema = $document->components->schemas['Test'];

        self::assertNull($schema->exclusiveMinimum);
        self::assertSame(10.0, $schema->minimum);
    }

    #[Test]
    public function number_exclusive_minimum_passes_through_in_v31(): void
    {
        $parser = new JsonParser();

        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'exclusiveMinimum' => 10,
                    ],
                ],
            ],
        ]);

        $document = $parser->parse($json);
        $schema = $document->components->schemas['Test'];

        self::assertSame(10.0, $schema->exclusiveMinimum);
    }

    #[Test]
    public function number_exclusive_maximum_passes_through_in_v32(): void
    {
        $parser = new JsonParser();

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'integer',
                        'exclusiveMaximum' => 100,
                    ],
                ],
            ],
        ]);

        $document = $parser->parse($json);
        $schema = $document->components->schemas['Test'];

        self::assertSame(100.0, $schema->exclusiveMaximum);
    }

    #[Test]
    public function multiple_deprecation_warnings_are_logged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(4))
            ->method('warning');

        $deprecationLogger = new DeprecationLogger($logger, true);
        $parser = new JsonParser($deprecationLogger);

        $json = json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'q',
                                'in' => 'query',
                                'allowEmptyValue' => true,
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'headers' => [
                                    'X-Custom' => [
                                        'allowEmptyValue' => true,
                                    ],
                                ],
                                'content' => [
                                    'application/json' => [
                                        'example' => '{"id": 1}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'string',
                        'nullable' => true,
                    ],
                ],
            ],
        ]);

        $parser->parse($json);
    }
}
