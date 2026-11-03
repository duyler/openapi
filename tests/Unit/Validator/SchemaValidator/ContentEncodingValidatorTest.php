<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentEncodingValidator::class)]
#[Group('content-assertions')]
class ContentEncodingValidatorTest extends TestCase
{
    private ContentEncodingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = $this->createValidator(true);
    }

    #[Test]
    public function skip_when_content_encoding_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_data_is_not_string(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate(123, $schema);

        $this->expectNotToPerformAssertions();
    }

    public static function validEncodingDataProvider(): array
    {
        return [
            'base16' => ['base16', '666F6F626172'],
            'base16 lowercase' => ['base16', '666f6f626172'],
            'base32 padded' => ['base32', 'MZXW6==='],
            'base32 unpadded' => ['base32', 'MZXW6YTB'],
            'base32hex' => ['base32hex', 'CPNMUOJ1'],
            'base32hex padded' => ['base32hex', 'CO======'],
            'base64' => ['base64', 'SGVsbG8gd29ybGQ='],
            'base64 full quantum unpadded' => ['base64', 'Zm9vYmFy'],
            'base64url url safe chars' => ['base64url', '-_'],
            'base64url padded' => ['base64url', 'aGVsbG8-d29ybGQ='],
            'base64url unpadded' => ['base64url', 'Zm8'],
            'quoted-printable escape' => ['quoted-printable', '=E1'],
            'quoted-printable soft break' => ["quoted-printable", "line=\r\nnext"],
        ];
    }

    #[DataProvider('validEncodingDataProvider')]
    #[Test]
    public function valid_encoded_value_passes_in_assertion_mode(string $encoding, string $data): void
    {
        $schema = new Schema(type: 'string', contentEncoding: $encoding);

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    public static function invalidEncodingDataProvider(): array
    {
        return [
            'base16 odd length' => ['base16', '666'],
            'base16 non hex' => ['base16', 'GG'],
            'base32 lowercase' => ['base32', 'mzxw6ytb'],
            'base32 wrong padding' => ['base32', 'MZXW6YTB='],
            'base32hex letter beyond v' => ['base32hex', 'CPNMUOJ1W'],
            'base32hex wrong padding' => ['base32hex', 'CPNMUOJ1='],
            'base64 invalid characters' => ['base64', 'not-base64!!!'],
            'base64 unpadded' => ['base64', 'SGVsbG8'],
            'base64url base64 chars' => ['base64url', '+/'],
            'base64url partial padding' => ['base64url', 'Zg='],
            'quoted-printable non hex escape' => ['quoted-printable', '=X1'],
            'quoted-printable lowercase hex' => ['quoted-printable', '=e1'],
            'quoted-printable truncated escape' => ['quoted-printable', 'value='],
        ];
    }

    #[DataProvider('invalidEncodingDataProvider')]
    #[Test]
    public function invalid_encoded_value_throws_in_assertion_mode(string $encoding, string $data): void
    {
        $schema = new Schema(type: 'string', contentEncoding: $encoding);

        $this->expectException(InvalidContentEncodingException::class);

        $this->validator->validate($data, $schema);
    }

    #[Test]
    public function validate_valid_base64(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('SGVsbG8gd29ybGQ=', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_base64(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->expectException(InvalidContentEncodingException::class);

        $this->validator->validate('not-base64!!!', $schema);
    }

    #[Test]
    public function throw_error_for_non_base64_characters(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->expectException(InvalidContentEncodingException::class);

        $this->validator->validate('hello world', $schema);
    }

    #[Test]
    public function validate_empty_base64(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('', $schema);

        $this->expectNotToPerformAssertions();
    }

    public static function identityEncodingDataProvider(): array
    {
        return [
            '7bit' => ['7bit'],
            '8bit' => ['8bit'],
            'binary' => ['binary'],
        ];
    }

    #[DataProvider('identityEncodingDataProvider')]
    #[Test]
    public function identity_encodings_accept_any_string(string $encoding): void
    {
        $schema = new Schema(type: 'string', contentEncoding: $encoding);

        $this->validator->validate("\x00\x01 raw anything \xFF", $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unknown_encoding_passes_in_assertion_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'uuencode');

        $this->validator->validate('any data', $schema);

        $this->expectNotToPerformAssertions();
    }

    private function createValidator(bool $contentAssertionsEnabled): ContentEncodingValidator
    {
        return new ContentEncodingValidator(new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), new FormatRegistry()),
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            contentAssertionsEnabled: $contentAssertionsEnabled,
        ));
    }
}
