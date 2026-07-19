<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/** @internal */
final class OpenApiValidatorBuilderSpecLimitsTest extends TestCase
{
    #[Test]
    public function build_with_max_spec_size_throws_when_yaml_exceeds_limit(): void
    {
        $padding = str_repeat('a', 1024);
        $yaml = "openapi: 3.0.3\ninfo:\n  title: {$padding}\n  version: 1.0.0\npaths: {}\n";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withMaxSpecSize(256);

        $this->expectException(SpecTooLargeException::class);

        $builder->build();
    }

    #[Test]
    public function build_with_max_spec_depth_throws_when_yaml_exceeds_depth(): void
    {
        $lines = ['openapi: 3.0.3', 'info:', '  title: Test', '  version: 1.0.0', 'paths: {}', 'components:', '  schemas:', '    Deep:'];
        $current = '      ';
        for ($i = 0; $i < 30; ++$i) {
            $current .= '  ';
            $lines[] = $current . 'nested:';
        }
        $lines[] = $current . '  type: string';
        $yaml = implode("\n", $lines);

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withMaxSpecDepth(10);

        $this->expectException(SpecTooLargeException::class);

        $builder->build();
    }

    #[Test]
    public function build_with_default_limits_accepts_normal_yaml(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Normal API
  version: 1.0.0
paths: {}
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $this->assertSame('3.0.3', $validator->getDocument()->openapi);
    }

    #[Test]
    public function with_max_spec_size_zero_or_negative_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OpenApiValidatorBuilder::create()->withMaxSpecSize(0);
    }

    #[Test]
    public function with_max_spec_depth_zero_or_negative_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OpenApiValidatorBuilder::create()->withMaxSpecDepth(-1);
    }
}
