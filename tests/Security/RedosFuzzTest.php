<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use Duyler\OpenApi\Validator\Format\String\UriValidator;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function hrtime;
use function str_repeat;

/**
 * PoC tests for ReDoS defenses (P-022, P-023, P-035). Each pathological input
 * must terminate within a defensive bound, demonstrating that the
 * PregExecutor wrapper caps pcre.backtrack_limit and that the urlencoded
 * validator no longer relies on a catastrophic regular expression.
 *
 * Time-based assertions use a generous margin so they remain stable on loaded
 * CI workers; the underlying guarantee is the runtime cap itself.
 *
 * @internal
 */
#[CoversClass(PregExecutor::class)]
#[CoversClass(PatternValidator::class)]
#[CoversClass(EmailValidator::class)]
#[CoversClass(UriValidator::class)]
#[CoversClass(ContentMediaTypeValidator::class)]
final class RedosFuzzTest extends TestCase
{
    private ValidatorPool $pool;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function redos_catastrophic_pattern_aborted_within_backtrack_limit(): void
    {
        $pregExecutor = new PregExecutor(maxBacktracks: 10_000);
        $validator = new PatternValidator(new ValidatorDependencies($this->pool, BuiltinFormats::create($pregExecutor), pregExecutor: $pregExecutor));

        $schema = new Schema(type: 'string', pattern: '^(a+)+$');
        $payload = str_repeat('a', 30) . 'b';

        $start = hrtime(true);
        try {
            $validator->validate($payload, $schema, ValidationContext::create(pool: $this->pool));
        } catch (PatternMismatchError|RuntimeException) {
        }
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertLessThan(50, $elapsedMs);
    }

    #[Test]
    public function redos_email_validator_aborts_on_pathological_input(): void
    {
        $pregExecutor = new PregExecutor(maxBacktracks: 10_000);
        $validator = new EmailValidator($pregExecutor);

        $payload = str_repeat('a', 100) . '@' . str_repeat('a', 100) . '.com';

        $start = hrtime(true);
        try {
            $validator->validate($payload);
        } catch (InvalidFormatException) {
        }
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertLessThan(50, $elapsedMs);
    }

    #[Test]
    public function redos_content_type_validator_aborts_on_pathological_urlencoded(): void
    {
        $pregExecutor = new PregExecutor(maxBacktracks: 10_000);
        $validator = new ContentMediaTypeValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: new FormatRegistry(), pregExecutor: $pregExecutor));

        $schema = new Schema(contentMediaType: 'application/x-www-form-urlencoded');
        $payload = str_repeat('a=1&', 50_000) . 'broken';

        $start = hrtime(true);
        try {
            $validator->validate($payload, $schema);
        } catch (RuntimeException) {
        }
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertLessThan(10, $elapsedMs);
    }

    #[Test]
    public function email_validation_still_works(): void
    {
        $validator = new EmailValidator(new PregExecutor());

        $succeeded = false;
        try {
            $validator->validate('user@example.com');
            $succeeded = true;
        } catch (InvalidFormatException) {
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function uri_validation_still_works(): void
    {
        $validator = new UriValidator(new PregExecutor());

        $succeeded = false;
        try {
            $validator->validate('https://example.com/path');
            $succeeded = true;
        } catch (InvalidFormatException) {
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function complex_pattern_with_moderate_backtracking_still_works(): void
    {
        $pregExecutor = new PregExecutor(maxBacktracks: 10_000);
        $validator = new PatternValidator(new ValidatorDependencies($this->pool, BuiltinFormats::create($pregExecutor), pregExecutor: $pregExecutor));

        $schema = new Schema(type: 'string', pattern: '/^[a-z0-9_-]{3,16}$/');

        $succeeded = false;
        try {
            $validator->validate('foo-bar_baz', $schema, ValidationContext::create(pool: $this->pool));
            $succeeded = true;
        } catch (PatternMismatchError|RuntimeException) {
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function urlencoded_validator_accepts_well_formed_payload(): void
    {
        $validator = new ContentMediaTypeValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: new FormatRegistry(), pregExecutor: new PregExecutor()));
        $schema = new Schema(contentMediaType: 'application/x-www-form-urlencoded');

        $succeeded = false;
        try {
            $validator->validate('name=John&age=30&city=NYC', $schema);
            $succeeded = true;
        } catch (RuntimeException) {
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function urlencoded_validator_rejects_payload_with_too_many_pairs(): void
    {
        $validator = new ContentMediaTypeValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: new FormatRegistry(), pregExecutor: new PregExecutor()));
        $schema = new Schema(contentMediaType: 'application/x-www-form-urlencoded');

        $payload = str_repeat('a=1&', 1_500);

        $this->expectException(RuntimeException::class);

        $validator->validate($payload, $schema);
    }
}
