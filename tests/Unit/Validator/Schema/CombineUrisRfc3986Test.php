<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RFC 3986 §5.4 conformance for RefResolver::combineUris().
 *
 * The base URI for the §5.4.1 / §5.4.2 examples is "http://a/b/c/d;p?q".
 *
 * @internal
 */
final class CombineUrisRfc3986Test extends TestCase
{
    private const string RFC_BASE = 'http://a/b/c/d;p?q';

    private const string FILE_BASE = 'file:///var/specs/api/v1/main.yaml';
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function rfc3986NormalExamples(): iterable
    {
        yield 'g:h (absolute URI returned as-is)' => ['g:h', 'g:h'];
        yield 'g' => ['g', 'http://a/b/c/g'];
        yield './g' => ['./g', 'http://a/b/c/g'];
        yield 'g/' => ['g/', 'http://a/b/c/g/'];
        yield '/g' => ['/g', 'http://a/g'];
        yield '//g' => ['//g', 'http://g'];
        yield '?y' => ['?y', 'http://a/b/c/d;p?y'];
        yield 'g?y' => ['g?y', 'http://a/b/c/g?y'];
        yield '#s' => ['#s', 'http://a/b/c/d;p?q#s'];
        yield 'g#s' => ['g#s', 'http://a/b/c/g#s'];
        yield '.' => ['.', 'http://a/b/c/'];
        yield '..' => ['..', 'http://a/b/'];
        yield 'empty ref returns base' => ['', self::RFC_BASE];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function rfc3986AbnormalExamples(): iterable
    {
        yield '../../../g' => ['../../../g', 'http://a/g'];
        yield '../../../../g' => ['../../../../g', 'http://a/g'];
        yield '/./g' => ['/./g', 'http://a/g'];
        yield '/../g' => ['/../g', 'http://a/g'];
        yield 'g.' => ['g.', 'http://a/b/c/g.'];
        yield 'g..' => ['g..', 'http://a/b/c/g..'];
        yield './../g' => ['./../g', 'http://a/b/g'];
        yield './g/.' => ['./g/.', 'http://a/b/c/g/'];
        yield 'g/./h' => ['g/./h', 'http://a/b/c/g/h'];
        yield 'g/../h' => ['g/../h', 'http://a/b/c/h'];
        yield 'g;x=1 ./y' => ['g;x=1/y', 'http://a/b/c/g;x=1/y'];
        yield 'g;x=1 with fragment' => ['g;x=1#s', 'http://a/b/c/g;x=1#s'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function fileSystemExamples(): iterable
    {
        yield 'relative component ref' => ['components/user.yaml', 'file:///var/specs/api/v1/components/user.yaml'];
        yield 'parent dir ref' => ['../shared/types.yaml', 'file:///var/specs/api/shared/types.yaml'];
        yield 'sibling dir' => ['../v2/types.yaml', 'file:///var/specs/api/v2/types.yaml'];
        yield 'root absolute file path' => ['/etc/passwd', 'file:///etc/passwd'];
    }

    #[Test]
    #[DataProvider('rfc3986NormalExamples')]
    public function rfc_3986_5_4_1_normal_examples(string $ref, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->combineUris(self::RFC_BASE, $ref));
    }

    #[Test]
    #[DataProvider('rfc3986AbnormalExamples')]
    public function rfc_3986_5_4_2_abnormal_examples(string $ref, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->combineUris(self::RFC_BASE, $ref));
    }

    #[Test]
    #[DataProvider('fileSystemExamples')]
    public function file_system_relative_resolution(string $ref, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->combineUris(self::FILE_BASE, $ref));
    }

    #[Test]
    public function absolute_uri_returned_untouched(): void
    {
        $absolute = 'https://api.example.com/v2/schema.json';

        $this->assertSame(
            $absolute,
            $this->resolver->combineUris(self::RFC_BASE, $absolute),
        );
    }

    #[Test]
    public function combines_with_only_fragment_preserves_path_and_query(): void
    {
        $this->assertSame(
            'http://a/b/c/d;p?q#frag',
            $this->resolver->combineUris(self::RFC_BASE, '#frag'),
        );
    }

    #[Test]
    public function combines_with_only_query_replaces_query(): void
    {
        $this->assertSame(
            'http://a/b/c/d;p?new',
            $this->resolver->combineUris(self::RFC_BASE, '?new'),
        );
    }
}
