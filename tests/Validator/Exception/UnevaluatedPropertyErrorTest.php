<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Tests\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnevaluatedPropertyErrorTest extends TestCase
{
    #[Test]
    public function error_message_includes_property_name(): void
    {
        $error = new UnevaluatedPropertyError('/', '/unevaluatedProperties', 'extraProp');

        self::assertSame('Property "extraProp" is not allowed and was not evaluated by any schema keyword.', $error->getMessage());
    }

    #[Test]
    public function error_contains_path_information(): void
    {
        $error = new UnevaluatedPropertyError('/object/0', '/unevaluatedProperties', 'unknown');

        self::assertSame('/object/0', $error->dataPath());
        self::assertSame('/unevaluatedProperties', $error->schemaPath());
    }

    #[Test]
    public function error_keyword_is_unevaluated_properties(): void
    {
        $error = new UnevaluatedPropertyError('/', '/unevaluatedProperties', 'prop');

        self::assertSame('unevaluatedProperties', $error->keyword());
    }

    #[Test]
    public function error_params_includes_property_name(): void
    {
        $error = new UnevaluatedPropertyError('/', '/unevaluatedProperties', 'myProperty');

        self::assertSame(['propertyName' => 'myProperty'], $error->params());
    }

    #[Test]
    public function error_has_suggestion(): void
    {
        $error = new UnevaluatedPropertyError('/', '/unevaluatedProperties', 'prop');

        self::assertSame('Remove the unevaluated property or adjust the schema to evaluate it', $error->suggestion());
    }
}
