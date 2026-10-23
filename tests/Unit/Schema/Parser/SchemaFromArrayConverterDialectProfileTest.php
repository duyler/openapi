<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\Internal\ScalarSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SchemaFromArrayConverterDialectProfileTest extends TestCase
{
    #[Test]
    public function converter_forwards_openapi_30_profile_to_scalar_parser(): void
    {
        $profile = DialectProfile::forOpenApi30();
        $converter = new SchemaFromArrayConverter('3.0.0', dialectProfile: $profile);

        $parser = self::readScalarParser($converter);

        self::assertSame($profile, $parser->dialectProfile());
    }

    #[Test]
    public function converter_defaults_to_openapi_31_profile(): void
    {
        $converter = new SchemaFromArrayConverter();

        $parser = self::readScalarParser($converter);

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $parser->dialectProfile(),
        );
    }

    #[Test]
    public function scalar_parser_defaults_to_openapi_31_profile(): void
    {
        $parser = new ScalarSchemaKeywordParser();

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $parser->dialectProfile(),
        );
    }

    #[Test]
    public function scalar_parser_carries_injected_openapi_30_profile(): void
    {
        $profile = DialectProfile::forOpenApi30();

        $parser = new ScalarSchemaKeywordParser('3.0.0', dialectProfile: $profile);

        self::assertSame($profile, $parser->dialectProfile());
    }

    private static function readScalarParser(SchemaFromArrayConverter $converter): ScalarSchemaKeywordParser
    {
        $property = new ReflectionProperty($converter, 'scalarParser');

        return $property->getValue($converter);
    }
}
