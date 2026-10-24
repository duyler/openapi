<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidEncodingConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidEncodingConfigurationException::class)]
final class InvalidEncodingConfigurationExceptionTest extends TestCase
{
    #[Test]
    public function mixed_modes_message_states_the_mutual_exclusivity_rule(): void
    {
        $exception = InvalidEncodingConfigurationException::mixedModes();

        self::assertStringContainsString('MUST NOT be present', $exception->getMessage());
    }

    #[Test]
    public function missing_array_model_message_states_the_schema_requirement(): void
    {
        $exception = InvalidEncodingConfigurationException::missingArrayModel();

        self::assertStringContainsString('itemSchema or an array schema MUST be present', $exception->getMessage());
    }
}
