<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Dto;

use Closure;
use Duyler\OpenApi\Validator\Dto\ContentValidationResult;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentValidationResult::class)]
class ContentValidationResultTest extends TestCase
{
    #[Test]
    public function eager_result_exposes_pipeline_outcome(): void
    {
        $error = $this->createError();
        $result = ContentValidationResult::eager(['a' => 1], true, [$error]);

        $this->assertSame(['a' => 1], $result->decodedValue());
        $this->assertTrue($result->isMediaTypeValid());
        $this->assertSame([$error], $result->schemaErrors());
        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function eager_result_without_errors_is_valid(): void
    {
        $result = ContentValidationResult::eager('payload', true, []);

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function lazy_result_defers_computation_until_first_read(): void
    {
        $computations = 0;
        $result = ContentValidationResult::lazy(
            $this->countingFactory($computations, ContentValidationResult::eager('payload', true, [])),
        );

        $this->assertSame(0, $computations);
        $this->assertSame('payload', $result->decodedValue());
        $this->assertSame(1, $computations);
    }

    #[Test]
    public function lazy_result_computes_once_for_every_accessor(): void
    {
        $computations = 0;
        $result = ContentValidationResult::lazy(
            $this->countingFactory($computations, ContentValidationResult::eager(null, false, [$this->createError()])),
        );

        $this->assertFalse($result->isMediaTypeValid());
        $this->assertCount(1, $result->schemaErrors());
        $this->assertFalse($result->isValid());
        $this->assertNull($result->decodedValue());

        $this->assertSame(1, $computations);
    }

    #[Test]
    public function lazy_result_can_resolve_to_another_lazy_result(): void
    {
        $inner = ContentValidationResult::lazy(
            static fn(): ContentValidationResult => ContentValidationResult::eager('nested', true, []),
        );
        $result = ContentValidationResult::lazy(
            static fn(): ContentValidationResult => $inner,
        );

        $this->assertSame('nested', $result->decodedValue());
        $this->assertTrue($result->isValid());
    }

    private function countingFactory(int &$computations, ContentValidationResult $computed): Closure
    {
        return static function () use (&$computations, $computed): ContentValidationResult {
            ++$computations;

            return $computed;
        };
    }

    private function createError(): TypeMismatchError
    {
        return new TypeMismatchError(
            expected: 'object',
            actual: 'string',
            dataPath: '/',
            schemaPath: '/contentSchema',
        );
    }
}
