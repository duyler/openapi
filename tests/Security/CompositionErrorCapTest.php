<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TooManyErrorsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * P-031 regression: composition validators must cap accumulated errors at
 * 20 and emit a TooManyErrorsError summary rather than letting deeply
 * nested allOf / anyOf / oneOf schemas produce unbounded error arrays
 * that amplify response size and exhaust memory.
 *
 * @internal
 */
final class CompositionErrorCapTest extends TestCase
{
    private AllOfValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $this->validator = new AllOfValidator(new ValidatorDependencies(pool: $pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function composition_validator_caps_errors_at_twenty_with_summary(): void
    {
        $subSchemas = [];

        for ($i = 0; $i < 100; ++$i) {
            $subSchemas[] = new Schema(
                type: 'object',
                required: ['field' . $i],
            );
        }

        $schema = new Schema(allOf: $subSchemas);
        $data = [];
        $context = ValidationContext::create(pool: new ValidatorPool());

        $caught = null;

        try {
            $this->validator->validate($data, $schema, $context);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'AllOfValidator must throw when subschemas fail');

        $errors = $caught->getErrors();
        $hasSummary = false;
        $summaryCount = 0;

        foreach ($errors as $error) {
            if ($error instanceof TooManyErrorsError) {
                $hasSummary = true;
                ++$summaryCount;
                self::assertSame(20, $error->params()['max']);
            }
        }

        self::assertTrue(
            $hasSummary,
            'Composition validator must emit a TooManyErrorsError summary when the cap is reached (P-031)',
        );
        self::assertSame(1, $summaryCount, 'Exactly one summary error must be appended');
    }
}
