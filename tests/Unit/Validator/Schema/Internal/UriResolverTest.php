<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Validator\Schema\Internal\UriResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RFC 3986 §5.4 conformance for {@see UriResolver}.
 *
 * Golden-master derived from the official RFC 3986 §5.4.1 / §5.4.2
 * test vectors published at https://datatracker.ietf.org/doc/html/rfc3986#section-5.4.
 * Base URI for all RFC examples is "http://a/b/c/d;p?q".
 *
 * @internal
 */
final class UriResolverTest extends TestCase
{
    private const string RFC_BASE = 'http://a/b/c/d;p?q';

    private const string FILE_BASE = 'file:///var/specs/api/v1/main.yaml';

    private UriResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new UriResolver();
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
        $result = $this->combineViaResolver(self::RFC_BASE, $ref);

        self::assertSame($expected, $result);
    }

    #[Test]
    #[DataProvider('rfc3986AbnormalExamples')]
    public function rfc_3986_5_4_2_abnormal_examples(string $ref, string $expected): void
    {
        $result = $this->combineViaResolver(self::RFC_BASE, $ref);

        self::assertSame($expected, $result);
    }

    #[Test]
    #[DataProvider('fileSystemExamples')]
    public function file_system_relative_resolution(string $ref, string $expected): void
    {
        $result = $this->combineViaResolver(self::FILE_BASE, $ref);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function absolute_uri_returned_untouched(): void
    {
        $absolute = 'https://api.example.com/v2/schema.json';

        $result = $this->combineViaResolver(self::RFC_BASE, $absolute);

        self::assertSame($absolute, $result);
    }

    #[Test]
    public function combines_with_only_fragment_preserves_path_and_query(): void
    {
        $result = $this->combineViaResolver(self::RFC_BASE, '#frag');

        self::assertSame('http://a/b/c/d;p?q#frag', $result);
    }

    #[Test]
    public function combines_with_only_query_replaces_query(): void
    {
        $result = $this->combineViaResolver(self::RFC_BASE, '?new');

        self::assertSame('http://a/b/c/d;p?new', $result);
    }

    #[Test]
    public function remove_dot_segments_normalises_path(): void
    {
        self::assertSame('/a/b/c', $this->resolver->removeDotSegments('/a/b/c'));
        self::assertSame('/a/c', $this->resolver->removeDotSegments('/a/b/../c'));
        self::assertSame('/a/c', $this->resolver->removeDotSegments('/a/./b/../c'));
        self::assertSame('/', $this->resolver->removeDotSegments('/a/b/../..'));
        self::assertSame('/', $this->resolver->removeDotSegments('/.'));
        self::assertSame('/', $this->resolver->removeDotSegments('/..'));
        self::assertSame('', $this->resolver->removeDotSegments('.'));
        self::assertSame('', $this->resolver->removeDotSegments('..'));
    }

    #[Test]
    public function merge_paths_with_absolute_relative_returns_relative(): void
    {
        $result = $this->resolver->mergePaths('/a/b/c/', '/absolute');

        self::assertSame('/absolute', $result);
    }

    #[Test]
    public function merge_paths_with_empty_base_returns_relative(): void
    {
        $result = $this->resolver->mergePaths('', 'relative');

        self::assertSame('relative', $result);
    }

    #[Test]
    public function merge_paths_combines_base_dir_with_relative(): void
    {
        $result = $this->resolver->mergePaths('/a/b/c', 'd.json');

        self::assertSame('/a/b/d.json', $result);
    }

    #[Test]
    public function merge_paths_without_slash_in_base_returns_relative(): void
    {
        $result = $this->resolver->mergePaths('noslash', 'd.json');

        self::assertSame('d.json', $result);
    }

    #[Test]
    public function build_uri_renders_full_components(): void
    {
        $parts = [
            'scheme' => 'https',
            'user' => 'alice',
            'pass' => 's3cret',
            'host' => 'example.com',
            'port' => 8443,
            'path' => '/api/v2',
            'query' => 'a=1',
            'fragment' => 'top',
        ];

        self::assertSame(
            'https://alice:s3cret@example.com:8443/api/v2?a=1#top',
            $this->resolver->buildUri($parts),
        );
    }

    #[Test]
    public function build_uri_without_scheme_omits_scheme_prefix(): void
    {
        $parts = ['host' => 'example.com', 'path' => '/p'];

        self::assertSame('//example.com/p', $this->resolver->buildUri($parts));
    }

    #[Test]
    public function build_uri_with_force_authority_renders_empty_host(): void
    {
        $parts = ['path' => '/p'];

        self::assertSame('///p', $this->resolver->buildUri($parts, true));
    }

    #[Test]
    public function build_uri_with_user_only_renders_at_sign(): void
    {
        $parts = ['user' => 'alice', 'host' => 'h'];

        self::assertSame('//alice@h', $this->resolver->buildUri($parts));
    }

    #[Test]
    public function apply_fragment_replaces_fragment(): void
    {
        $result = $this->resolver->applyFragment(
            ['fragment' => 'old', 'path' => '/p'],
            ['fragment' => 'new'],
        );

        self::assertSame('new', $result['fragment']);
    }

    #[Test]
    public function apply_fragment_without_new_fragment_clears_old(): void
    {
        $result = $this->resolver->applyFragment(
            ['fragment' => 'old', 'path' => '/p'],
            [],
        );

        self::assertArrayNotHasKey('fragment', $result);
    }

    #[Test]
    public function replace_query_and_fragment_replaces_query_when_present(): void
    {
        $result = $this->resolver->replaceQueryAndFragment(
            ['query' => 'old', 'path' => '/p'],
            ['query' => 'new'],
        );

        self::assertSame('new', $result['query']);
    }

    #[Test]
    public function replace_query_and_fragment_drops_query_when_absent(): void
    {
        $result = $this->resolver->replaceQueryAndFragment(
            ['query' => 'old', 'path' => '/p'],
            [],
        );

        self::assertArrayNotHasKey('query', $result);
    }

    /**
     * Mirrors RefResolver::combineUris() — splits parse_url() guard logic
     * then delegates to {@see UriResolver::resolveRelativeAgainstBase()}.
     */
    private function combineViaResolver(string $baseUri, string $relativeRef): string
    {
        if ('' === $relativeRef) {
            return $baseUri;
        }

        $relative = parse_url($relativeRef);
        if (false === $relative || isset($relative['scheme'])) {
            return $relativeRef;
        }

        $base = parse_url($baseUri);
        if (false === $base) {
            return $baseUri;
        }

        return $this->resolver->resolveRelativeAgainstBase($base, $relative, $baseUri);
    }
}
