<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\RegexValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegexPartialMatchTest extends TestCase
{
    #[Test]
    public function pattern_without_anchors_matches_substring(): void
    {
        $pattern = RegexValidator::normalize('test');
        $result = preg_match($pattern, 'aaa_test_bbb');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function pattern_with_anchors_rejects_substring(): void
    {
        $pattern = RegexValidator::normalize('^test$');
        $result = preg_match($pattern, 'aaa_test_bbb');

        $this->assertSame(0, $result);
    }

    #[Test]
    public function pattern_with_anchors_matches_exact_string(): void
    {
        $pattern = RegexValidator::normalize('^test$');
        $result = preg_match($pattern, 'test');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function pattern_without_anchors_matches_at_start(): void
    {
        $pattern = RegexValidator::normalize('test');
        $result = preg_match($pattern, 'test_suffix');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function pattern_without_anchors_matches_at_end(): void
    {
        $pattern = RegexValidator::normalize('test');
        $result = preg_match($pattern, 'prefix_test');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function pattern_without_anchors_matches_exact_string(): void
    {
        $pattern = RegexValidator::normalize('test');
        $result = preg_match($pattern, 'test');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function pattern_rejects_non_matching_string(): void
    {
        $pattern = RegexValidator::normalize('test');
        $result = preg_match($pattern, 'completely_different');

        $this->assertSame(0, $result);
    }
}
