<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Parameters
 */
final class ParametersTest extends TestCase
{
    #[Test]
    public function can_create_parameters(): void
    {
        $schema = new Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: $schema,
        );

        $parameters = new Parameters(
            parameters: [$parameter],
        );

        self::assertCount(1, $parameters->parameters);
        self::assertSame('id', $parameters->parameters[0]->name);
    }

    #[Test]
    public function can_create_empty_parameters(): void
    {
        $parameters = new Parameters(
            parameters: [],
        );

        self::assertCount(0, $parameters->parameters);
    }

    #[Test]
    public function json_serialize_includes_parameters(): void
    {
        $schema = new Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: $schema,
        );

        $parameters = new Parameters(
            parameters: [$parameter],
        );

        $serialized = $parameters->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('parameters', $serialized);
        self::assertIsArray($serialized['parameters']);
        self::assertCount(1, $serialized['parameters']);
        self::assertIsArray($serialized['parameters'][0]);
    }
}
