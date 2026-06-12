<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiscriminatorExceptionsTest extends TestCase
{
    #[Test]
    public function discriminator_mismatch_to_string_returns_message(): void
    {
        $exception = new DiscriminatorMismatchException(
            expectedType: 'Cat',
            actualType: 'Dog',
            propertyName: 'petType',
        );

        self::assertSame('Discriminator expects Cat at property "petType", but got Dog', (string) $exception);
    }

    #[Test]
    public function invalid_discriminator_value_to_string_returns_message(): void
    {
        $exception = new InvalidDiscriminatorValueException(
            propertyName: 'type',
            expectedType: 'string',
            actualType: 'integer',
        );

        self::assertSame('Discriminator property "type" must be string, but got integer', (string) $exception);
    }

    #[Test]
    public function missing_discriminator_property_to_string_returns_message(): void
    {
        $exception = new MissingDiscriminatorPropertyException(
            propertyName: 'petType',
        );

        self::assertSame('Missing required discriminator property "petType"', (string) $exception);
    }

    #[Test]
    public function unknown_discriminator_value_to_string_returns_message(): void
    {
        $schema = new Schema(
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Cat', 'dog' => '#/components/schemas/Dog'],
            ),
        );

        $exception = new UnknownDiscriminatorValueException(
            value: 'bird',
            schema: $schema,
        );

        self::assertStringContainsString('Unknown discriminator value "bird"', (string) $exception);
    }

    #[Test]
    public function unknown_discriminator_value_without_mapping_to_string(): void
    {
        $schema = new Schema(
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $exception = new UnknownDiscriminatorValueException(
            value: 'bird',
            schema: $schema,
        );

        self::assertStringContainsString('Unknown discriminator value "bird"', (string) $exception);
    }
}
