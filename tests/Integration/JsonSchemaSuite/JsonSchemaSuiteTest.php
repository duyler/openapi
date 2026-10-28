<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\JsonSchemaSuite;

use Duyler\OpenApi\Test\Support\JsonSchemaSuiteCase;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteCaseLoader;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use Duyler\OpenApi\Test\Support\SuiteValidatorFactory;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

#[CoversNothing]
final class JsonSchemaSuiteTest extends TestCase
{
    public const array ACTIVE_SECTIONS = [
        'const.json',
        'default.json',
        'dependentRequired.json',
        'dependentSchemas.json',
        'maximum.json',
        'minimum.json',
        'exclusiveMaximum.json',
        'exclusiveMinimum.json',
        'minItems.json',
        'maxItems.json',
        'uniqueItems.json',
        'minContains.json',
        'maxContains.json',
        'additionalProperties.json',
        'propertyNames.json',
        'maxProperties.json',
        'if-then-else.json',
        'infinite-loop-detection.json',
        'anchor.json',
        'items.json',
        'optional/anchor.json',
        'optional/refOfUnknownKeyword.json',
        'optional/dependencies-compatibility.json',
        'optional/format-assertion.json',
        'optional/format/date.json',
        'optional/format/date-time.json',
        'optional/format/duration.json',
        'optional/format/email.json',
        'optional/format/hostname.json',
        'optional/format/idn-email.json',
        'optional/format/idn-hostname.json',
        'optional/format/ipv4.json',
        'optional/format/ipv6.json',
        'optional/format/iri.json',
        'optional/format/iri-reference.json',
        'optional/format/json-pointer.json',
        'optional/format/regex.json',
        'optional/format/relative-json-pointer.json',
        'optional/format/time.json',
        'optional/format/unknown.json',
        'optional/format/uri.json',
        'optional/format/uri-reference.json',
        'optional/format/uri-template.json',
        'optional/format/uuid.json',
        'optional/id.json',
        'optional/no-schema.json',
        'optional/unknownKeyword.json',
        'dynamicRef.json',
        'refRemote.json',
        'optional/dynamicRef.json',
        'allOf.json',
        'anyOf.json',
        'oneOf.json',
        'properties.json',
        'prefixItems.json',
        'boolean_schema.json',
        'content.json',
        'ref.json',
        'unevaluatedItems.json',
        'unevaluatedProperties.json',
    ];

    public const array PENDING_SECTIONS = [
        'type.json' => 'FS-40',
        'enum.json' => 'FS-40',
        'multipleOf.json' => 'FS-40',
        'minLength.json' => 'FS-40',
        'maxLength.json' => 'FS-40',
        'pattern.json' => 'FS-40',
        'contains.json' => 'FS-40',
        'required.json' => 'FS-40',
        'patternProperties.json' => 'FS-40',
        'minProperties.json' => 'FS-40',
        'not.json' => 'FS-40',
        'defs.json' => 'FS-40',
        'format.json' => 'FS-40',
        'vocabulary.json' => 'FS-40',
        'optional/bignum.json' => 'FS-40',
        'optional/cross-draft.json' => 'FS-40',
        'optional/float-overflow.json' => 'FS-40',
        'optional/non-bmp-regex.json' => 'FS-40',
    ];

    public const array EXCLUDED_SECTIONS = [
        'optional/ecmascript-regex.json',
        'optional/format/ecmascript-regex.json',
    ];

    public static function suiteCases(): array
    {
        $cases = [];

        foreach (JsonSchemaSuiteCaseLoader::loadDirectory(JsonSchemaSuiteLocator::draftDirectory()) as $index => $case) {
            $cases[sprintf('%s [%d] %s', $case->section, $index, $case->description)] = [$case];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('suiteCases')]
    public function suite_case(JsonSchemaSuiteCase $case): void
    {
        if (in_array($case->section, self::EXCLUDED_SECTIONS, true)) {
            self::markTestSkipped(sprintf('excluded: %s (PCRE is not ECMA-262)', $case->section));
        }

        $holderTask = self::PENDING_SECTIONS[$case->section] ?? null;

        if (null !== $holderTask) {
            self::markTestSkipped(sprintf('%s pending: %s', $holderTask, $case->section));
        }

        if (false === in_array($case->section, self::ACTIVE_SECTIONS, true)) {
            self::fail(sprintf(
                'Unclassified suite section: %s (add it to ACTIVE_SECTIONS or PENDING_SECTIONS)',
                $case->section,
            ));
        }

        $validator = SuiteValidatorFactory::forSchema($case->schema);

        $isValid = true;

        try {
            $validator->validateSchema($case->instance, SuiteValidatorFactory::subjectSchemaRef());
        } catch (ValidationException|InvalidFormatException) {
            $isValid = false;
        }

        self::assertSame(
            $case->expectedValid,
            $isValid,
            sprintf('%s: %s', $case->section, $case->description),
        );
    }
}
