<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\JsonSchemaSuite;

use Duyler\OpenApi\Test\Support\SuiteValidatorFactory;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ResourceFieldsParsingSmokeTest extends TestCase
{
    #[Test]
    public function document_with_defs_and_resource_keywords_parses_without_failure(): void
    {
        $schema = [
            '$id' => 'https://example.com/root',
            '$anchor' => 'nameAnchor',
            '$dynamicAnchor' => 'node',
            '$dynamicRef' => '#node',
            '$comment' => 'resource identification',
            '$vocabulary' => [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
            ],
            '$defs' => [
                'named' => ['type' => 'string'],
                'trueSchema' => true,
                'falseSchema' => false,
            ],
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
            ],
        ];

        $validator = SuiteValidatorFactory::forSchema($schema);

        $validator->validateSchema(['value' => 'ok'], SuiteValidatorFactory::subjectSchemaRef());

        $this->expectException(ValidationException::class);

        $validator->validateSchema(['value' => 42], SuiteValidatorFactory::subjectSchemaRef());
    }

    #[Test]
    public function defs_section_stays_pending_for_resolution_task(): void
    {
        self::assertArrayHasKey('defs.json', JsonSchemaSuiteTest::PENDING_SECTIONS);
        self::assertArrayNotHasKey('defs.json', JsonSchemaSuiteTest::ACTIVE_SECTIONS);
    }
}
