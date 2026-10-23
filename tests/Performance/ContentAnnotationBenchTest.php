<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Support\CountingContentDecoder;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function microtime;
use function sprintf;
use function str_repeat;
use function strlen;

#[CoversClass(StatelessValidatorDispatcher::class)]
final class ContentAnnotationBenchTest extends TestCase
{
    private const int PAYLOAD_BYTES = 2048;

    private const int ITERATIONS = 100;

    private const float BUDGET_MILLISECONDS = 500.0;

    #[Test]
    public function default_mode_never_decodes_large_payload(): void
    {
        $decoder = new CountingContentDecoder();
        $dependencies = $this->createDependencies(false, $decoder);
        $dispatcher = new StatelessValidatorDispatcher($dependencies->createFullList(), new ValidatorPool());
        $schema = new Schema(type: 'string', contentEncoding: 'base64', contentMediaType: 'application/json');
        $payload = str_repeat('!', self::PAYLOAD_BYTES);
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $this->assertGreaterThanOrEqual(1024, strlen($payload));

        $start = microtime(true);

        for ($iteration = 0; $iteration < self::ITERATIONS; ++$iteration) {
            $dispatcher->validate($payload, $schema, $context);
        }

        $duration = (microtime(true) - $start) * 1000.0;

        $this->assertSame(
            0,
            $decoder->calls,
            sprintf(
                '%d default-mode iterations on a %d-byte payload must not reach any decode point',
                self::ITERATIONS,
                self::PAYLOAD_BYTES,
            ),
        );

        $annotations = $context->annotations();
        $this->assertArrayHasKey('contentEncoding', $annotations);
        $this->assertArrayHasKey('contentMediaType', $annotations);
        $this->assertSame(['path' => '/', 'value' => 'base64'], $annotations['contentEncoding'][0]);
        $this->assertLessThan(
            self::BUDGET_MILLISECONDS,
            $duration,
            sprintf('%d annotation-only iterations on a %d-byte payload must stay within budget', self::ITERATIONS, self::PAYLOAD_BYTES),
        );
    }

    #[Test]
    public function assertion_mode_reaches_decoder_on_the_same_payload(): void
    {
        $decoder = new CountingContentDecoder();
        $dependencies = $this->createDependencies(true, $decoder);
        $dispatcher = new StatelessValidatorDispatcher($dependencies->createFullList(), new ValidatorPool());
        $schema = new Schema(type: 'string', contentEncoding: 'base64', contentMediaType: 'application/json');
        $payload = str_repeat('!', self::PAYLOAD_BYTES);
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        try {
            $dispatcher->validate($payload, $schema, $context);
            $this->fail('Expected InvalidContentEncodingException in assertion mode');
        } catch (InvalidContentEncodingException) {
            $this->addToAssertionCount(1);
        }

        $this->assertGreaterThan(0, $decoder->calls);
    }

    private function createDependencies(
        bool $contentAssertionsEnabled,
        CountingContentDecoder $decoder,
    ): SchemaValidatorDependencies {
        return new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            contentAssertionsEnabled: $contentAssertionsEnabled,
            contentDecoder: $decoder,
        );
    }
}
