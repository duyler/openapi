<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\AbstractParameterValidator;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\HeadersValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\Request\QueryParametersValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sprintf;

final class ContentParameterValidationTest extends TestCase
{
    private const string JSON_MEDIA_TYPE = 'application/json';

    #[Test]
    #[DataProvider('content_parameter_cases_provider')]
    public function content_parameter_is_validated_by_media_type_schema(
        string $location,
        Parameter $parameter,
        array $data,
        string $expectation,
    ): void {
        $caught = null;

        try {
            $this->validatorFor($location)->validate($data, [$parameter]);
        } catch (Throwable $e) {
            $caught = $e;
        }

        if ('valid' === $expectation) {
            self::assertNull(
                $caught,
                sprintf('%s content parameter must validate successfully, got: %s', $location, null === $caught ? '' : $caught->getMessage()),
            );

            return;
        }

        self::assertNotNull($caught, sprintf('%s content parameter must be rejected (case: %s)', $location, $expectation));

        if ('malformed' === $expectation) {
            self::assertInstanceOf(InvalidParameterException::class, $caught);
        }
    }

    public static function content_parameter_cases_provider(): array
    {
        $cases = [];

        foreach (['query', 'header', 'path', 'cookie'] as $location) {
            $cases["{$location} valid json validated by media type schema"] = [
                $location,
                self::contentParameter($location, self::tagObjectSchema()),
                ['filter' => '{"tag":"php"}'],
                'valid',
            ];
            $cases["{$location} json contradicting media type schema is rejected"] = [
                $location,
                self::contentParameter($location, self::tagObjectSchema()),
                ['filter' => '{"tag":123}'],
                'schema-invalid',
            ];
            $cases["{$location} malformed json is rejected"] = [
                $location,
                self::contentParameter($location, self::tagObjectSchema()),
                ['filter' => '{"tag":'],
                'malformed',
            ];
            $cases["{$location} json media type without schema parses without schema validation"] = [
                $location,
                self::contentParameter($location, null),
                ['filter' => '{"tag":"php"}'],
                'valid',
            ];
            $cases["{$location} non-json media type validates value as string"] = [
                $location,
                self::contentParameter($location, null, 'text/plain', new Schema(type: 'string')),
                ['filter' => 'raw text'],
                'valid',
            ];
        }

        return $cases;
    }

    #[Test]
    public function parameter_with_empty_content_map_builds_and_does_not_validate_the_value(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'query',
            content: new Content([]),
        );

        $caught = null;

        try {
            $this->validatorFor('query')->validate(['filter' => 'not even json'], [$parameter]);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            'An empty content map is legal after the content/schema XOR — the value passes through unvalidated.',
        );
    }

    #[Test]
    public function query_array_value_is_validated_directly_without_json_decode(): void
    {
        $parameter = self::contentParameter('query', new Schema(
            type: 'object',
            properties: ['tag' => new Schema(type: 'string')],
        ));

        $caught = null;

        try {
            $this->validatorFor('query')->validate(['filter' => ['php', 'go']], [$parameter]);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Already-parsed array value must be validated against the object schema directly (type mismatch expected)',
        );
    }

    private static function contentParameter(
        string $location,
        ?Schema $schema,
        string $mediaTypeKey = self::JSON_MEDIA_TYPE,
        ?Schema $mediaTypeSchema = null,
    ): Parameter {
        return new Parameter(
            name: 'filter',
            in: $location,
            content: new Content([
                $mediaTypeKey => new MediaType(schema: $schema ?? $mediaTypeSchema),
            ]),
        );
    }

    private static function tagObjectSchema(): Schema
    {
        return new Schema(
            type: 'object',
            properties: ['tag' => new Schema(type: 'string')],
            required: ['tag'],
        );
    }

    private function validatorFor(string $location): AbstractParameterValidator
    {
        $pool = new ValidatorPool();
        $schemaValidator = $this->schemaValidator($pool);
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        return match ($location) {
            'query' => new QueryParametersValidator($schemaValidator, $deserializer, $coercer, pool: $pool),
            'header' => new HeadersValidator($schemaValidator, $deserializer, $coercer, $pool),
            'cookie' => new CookieValidator($schemaValidator, $deserializer, $coercer, false, $pool),
            'path' => new PathParametersValidator($schemaValidator, $deserializer, $coercer, false, $pool),
        };
    }

    private function schemaValidator(ValidatorPool $pool): SchemaValidatorInterface
    {
        return new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        )->rootStatelessValidator();
    }
}
