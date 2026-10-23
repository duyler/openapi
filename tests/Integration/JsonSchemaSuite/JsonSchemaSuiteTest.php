<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\JsonSchemaSuite;

use Duyler\OpenApi\Test\Support\JsonSchemaSuiteCase;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteCaseLoader;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use Duyler\OpenApi\Test\Support\SuiteValidatorFactory;
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
        'anchor.json',
    ];

    public const array PENDING_SECTIONS = [
        'type.json' => 'FS-40',
        'enum.json' => 'FS-40',
        'multipleOf.json' => 'FS-40',
        'minLength.json' => 'FS-40',
        'maxLength.json' => 'FS-40',
        'pattern.json' => 'FS-40',
        'prefixItems.json' => 'FS-16',
        'items.json' => 'FS-07',
        'contains.json' => 'FS-40',
        'properties.json' => 'FS-16',
        'required.json' => 'FS-40',
        'patternProperties.json' => 'FS-40',
        'minProperties.json' => 'FS-40',
        'allOf.json' => 'FS-16',
        'anyOf.json' => 'FS-16',
        'oneOf.json' => 'FS-16',
        'not.json' => 'FS-40',
        'boolean_schema.json' => 'FS-02',
        'content.json' => 'FS-20',
        'default.json' => 'FS-05',
        'defs.json' => 'FS-40',
        'dependentRequired.json' => 'FS-17',
        'dependentSchemas.json' => 'FS-17',
        'dynamicRef.json' => 'FS-10',
        'format.json' => 'FS-25',
        'infinite-loop-detection.json' => 'FS-07',
        'ref.json' => 'FS-07',
        'refRemote.json' => 'FS-10',
        'unevaluatedItems.json' => 'FS-05',
        'unevaluatedProperties.json' => 'FS-05',
        'vocabulary.json' => 'FS-12',
        'optional/anchor.json' => 'FS-07',
        'optional/bignum.json' => 'FS-40',
        'optional/cross-draft.json' => 'FS-13',
        'optional/dependencies-compatibility.json' => 'FS-17',
        'optional/dynamicRef.json' => 'FS-10',
        'optional/float-overflow.json' => 'FS-40',
        'optional/format-assertion.json' => 'FS-25',
        'optional/format/date-time.json' => 'FS-25',
        'optional/format/date.json' => 'FS-25',
        'optional/format/duration.json' => 'FS-25',
        'optional/format/ecmascript-regex.json' => 'FS-25',
        'optional/format/email.json' => 'FS-25',
        'optional/format/hostname.json' => 'FS-25',
        'optional/format/idn-email.json' => 'FS-25',
        'optional/format/idn-hostname.json' => 'FS-25',
        'optional/format/ipv4.json' => 'FS-25',
        'optional/format/ipv6.json' => 'FS-25',
        'optional/format/iri-reference.json' => 'FS-25',
        'optional/format/iri.json' => 'FS-25',
        'optional/format/json-pointer.json' => 'FS-25',
        'optional/format/regex.json' => 'FS-25',
        'optional/format/relative-json-pointer.json' => 'FS-25',
        'optional/format/time.json' => 'FS-25',
        'optional/format/unknown.json' => 'FS-25',
        'optional/format/uri-reference.json' => 'FS-25',
        'optional/format/uri-template.json' => 'FS-25',
        'optional/format/uri.json' => 'FS-25',
        'optional/format/uuid.json' => 'FS-25',
        'optional/id.json' => 'FS-06',
        'optional/no-schema.json' => 'FS-13',
        'optional/non-bmp-regex.json' => 'FS-40',
        'optional/refOfUnknownKeyword.json' => 'FS-03',
        'optional/unknownKeyword.json' => 'FS-03',
    ];

    public const array EXCLUDED_SECTIONS = [
        'optional/ecmascript-regex.json',
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
        } catch (ValidationException) {
            $isValid = false;
        }

        self::assertSame(
            $case->expectedValid,
            $isValid,
            sprintf('%s: %s', $case->section, $case->description),
        );
    }
}
