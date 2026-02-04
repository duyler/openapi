<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PathParametersValidatorTest extends TestCase
{
    private PathParametersValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $this->validator = new PathParametersValidator($schemaValidator, $deserializer, $coercer);
    }

    #[Test]
    public function validate_required_path_param(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_param(): void
    {
        $params = [];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
            ),
        ];

        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('Missing required parameter "id" in path');

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function validate_with_schema(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_non_path_parameters(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'query',
                in: 'query',
                required: true,
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_path_parameters_valid(): void
    {
        $params = ['id' => '123', 'slug' => 'test-slug'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'slug',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_path_parameters_with_type_validation(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_path_parameters_with_format_validation(): void
    {
        $params = ['email' => 'test@example.com'];
        $parameterSchemas = [
            new Parameter(
                name: 'email',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', format: 'email'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_type(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function validate_path_parameters_empty_parameters(): void
    {
        $params = [];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: false,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_path_parameters_without_schema(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_path_parameters_multiple_params(): void
    {
        $params = ['userId' => '123', 'postId' => '456', 'commentId' => '789'];
        $parameterSchemas = [
            new Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'postId',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'commentId',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_missing_optional_path_parameters(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'optional',
                in: 'path',
                required: false,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_minimum_constraint(): void
    {
        $params = ['id' => '0'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer', minimum: 1),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function throw_error_for_maximum_constraint(): void
    {
        $params = ['id' => '101'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer', maximum: 100),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function validate_path_parameters_with_min_length(): void
    {
        $params = ['username' => 'john'];
        $parameterSchemas = [
            new Parameter(
                name: 'username',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', minLength: 3),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_min_length_constraint(): void
    {
        $params = ['username' => 'jo'];
        $parameterSchemas = [
            new Parameter(
                name: 'username',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', minLength: 3),
            ),
        ];

        $this->expectException(MinLengthError::class);

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function validate_path_parameters_with_max_length(): void
    {
        $params = ['username' => 'john'];
        $parameterSchemas = [
            new Parameter(
                name: 'username',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', maxLength: 10),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_max_length_constraint(): void
    {
        $params = ['username' => 'verylongusername'];
        $parameterSchemas = [
            new Parameter(
                name: 'username',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', maxLength: 10),
            ),
        ];

        $this->expectException(MaxLengthError::class);

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function validate_path_parameters_with_pattern(): void
    {
        $params = ['code' => 'ABC123'];
        $parameterSchemas = [
            new Parameter(
                name: 'code',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', pattern: '/^ABC[0-9]{3}$/'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_pattern_constraint(): void
    {
        $params = ['code' => 'invalid'];
        $parameterSchemas = [
            new Parameter(
                name: 'code',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string', pattern: '/^ABC[0-9]{3}$/'),
            ),
        ];

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate($params, $parameterSchemas);
    }
}
