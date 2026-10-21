<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use function array_pop;
use function implode;
use function is_int;
use function is_string;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * @internal
 * @psalm-type UriParts = array<string, int|string|null>
 */
final readonly class UriResolver
{
    /**
     * @param UriParts $base
     * @param UriParts $relative
     */
    public function resolveRelativeAgainstBase(array $base, array $relative, string $baseUri): string
    {
        $hadAuthority = isset($base['host']) || str_contains($baseUri, '://');

        if (isset($relative['host'])) {
            $base['host'] = $relative['host'];
            unset($base['user'], $base['pass'], $base['port']);
            $base['path'] = $this->removeDotSegments($this->stringValue($relative['path'] ?? null) ?? '');
            $base = $this->replaceQueryAndFragment($base, $relative);

            return $this->buildUri($base, true);
        }

        $basePath = $this->stringValue($base['path'] ?? null) ?? '';
        $relativePath = $this->stringValue($relative['path'] ?? null) ?? '';

        if ('' === $relativePath) {
            if (isset($relative['query'])) {
                $base['query'] = $relative['query'];
            }
            $base = $this->applyFragment($base, $relative);

            return $this->buildUri($base, $hadAuthority);
        }

        $base['path'] = $this->removeDotSegments($this->mergePaths($basePath, $relativePath));
        $base = $this->replaceQueryAndFragment($base, $relative);

        return $this->buildUri($base, $hadAuthority);
    }

    /**
     * @param UriParts $base
     * @param UriParts $relative
     * @return UriParts
     */
    public function replaceQueryAndFragment(array $base, array $relative): array
    {
        if (isset($relative['query'])) {
            $base['query'] = $relative['query'];
        } else {
            unset($base['query']);
        }

        return $this->applyFragment($base, $relative);
    }

    /**
     * @param UriParts $base
     * @param UriParts $relative
     * @return UriParts
     */
    public function applyFragment(array $base, array $relative): array
    {
        unset($base['fragment']);
        if (isset($relative['fragment'])) {
            $base['fragment'] = $relative['fragment'];
        }

        return $base;
    }

    public function mergePaths(string $basePath, string $relativePath): string
    {
        if (str_starts_with($relativePath, '/')) {
            return $relativePath;
        }

        if ('' === $basePath) {
            return $relativePath;
        }

        $lastSlash = strrpos($basePath, '/');
        if (false === $lastSlash) {
            return $relativePath;
        }

        return substr($basePath, 0, $lastSlash + 1) . $relativePath;
    }

    public function removeDotSegments(string $path): string
    {
        $step = new DotSegmentStep($path, []);

        while ('' !== $step->remainingInput) {
            $step = $this->applyDotSegmentRules($step->remainingInput, $step->stack);
        }

        return implode('', $step->stack);
    }

    /** @param UriParts $parts */
    public function buildUri(array $parts, bool $forceAuthority = false): string
    {
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : null;
        $uri = null === $scheme ? '' : $scheme . ':';
        $uri .= $this->renderAuthority($parts, $forceAuthority);
        $uri .= $this->renderPathQueryFragment($parts);

        return $uri;
    }

    /** @param UriParts $parts */
    private function renderAuthority(array $parts, bool $forceAuthority): string
    {
        $hasHost = isset($parts['host']) && is_string($parts['host']);
        if (false === $hasHost && false === $forceAuthority) {
            return '';
        }

        $uri = '//' . $this->renderUserInfo($parts);

        if ($hasHost) {
            $uri .= $this->stringValue($parts['host'] ?? null) ?? '';
        }

        $port = is_int($parts['port'] ?? null) ? $parts['port'] : null;
        if (null !== $port) {
            $uri .= ':' . $port;
        }

        return $uri;
    }

    /** @param UriParts $parts */
    private function renderUserInfo(array $parts): string
    {
        $user = $this->stringValue($parts['user'] ?? null);
        if (null === $user) {
            return '';
        }

        $pass = $this->stringValue($parts['pass'] ?? null);
        $userInfo = $user;
        if (null !== $pass) {
            $userInfo .= ':' . $pass;
        }

        return $userInfo . '@';
    }

    /** @param UriParts $parts */
    private function renderPathQueryFragment(array $parts): string
    {
        $uri = '';
        $path = $this->stringValue($parts['path'] ?? null);
        if (null !== $path) {
            $uri .= $path;
        }

        $query = $this->stringValue($parts['query'] ?? null);
        if (null !== $query) {
            $uri .= '?' . $query;
        }

        $fragment = $this->stringValue($parts['fragment'] ?? null);
        if (null !== $fragment) {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    private function stringValue(int|string|null $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param list<string> $stack
     */
    private function applyDotSegmentRules(string $input, array $stack): DotSegmentStep
    {
        return $this->removeDotPrefix($input, $stack)
            ?? $this->removeCurrentSegment($input, $stack)
            ?? $this->removeParentSegment($input, $stack)
            ?? $this->removeLoneDotSegment($input, $stack)
            ?? $this->pushNextSegment($input, $stack);
    }

    /**
     * @param list<string> $stack
     */
    private function removeDotPrefix(string $input, array $stack): ?DotSegmentStep
    {
        if (str_starts_with($input, '../')) {
            return new DotSegmentStep(substr($input, 3), $stack);
        }

        if (str_starts_with($input, './')) {
            return new DotSegmentStep(substr($input, 2), $stack);
        }

        return null;
    }

    /**
     * @param list<string> $stack
     */
    private function removeCurrentSegment(string $input, array $stack): ?DotSegmentStep
    {
        if ('/.' === $input) {
            return new DotSegmentStep('/', $stack);
        }

        if (str_starts_with($input, '/./')) {
            return new DotSegmentStep('/' . substr($input, 3), $stack);
        }

        return null;
    }

    /**
     * @param list<string> $stack
     */
    private function removeParentSegment(string $input, array $stack): ?DotSegmentStep
    {
        if ('/..' === $input) {
            array_pop($stack);

            return new DotSegmentStep('/', $stack);
        }

        if (str_starts_with($input, '/../')) {
            array_pop($stack);

            return new DotSegmentStep('/' . substr($input, 4), $stack);
        }

        return null;
    }

    /**
     * @param list<string> $stack
     */
    private function removeLoneDotSegment(string $input, array $stack): ?DotSegmentStep
    {
        if ('.' === $input || '..' === $input) {
            return new DotSegmentStep('', $stack);
        }

        return null;
    }

    /**
     * @param list<string> $stack
     */
    private function pushNextSegment(string $input, array $stack): DotSegmentStep
    {
        $segmentEnd = $this->findNextSlashOffset($input);
        $stack[] = substr($input, 0, $segmentEnd);

        return new DotSegmentStep(substr($input, $segmentEnd), $stack);
    }

    private function findNextSlashOffset(string $input): int
    {
        $nextSlash = strpos($input, '/', 1);
        if (false === $nextSlash) {
            return strlen($input);
        }

        return $nextSlash;
    }
}

final readonly class DotSegmentStep
{
    /**
     * @param list<string> $stack
     */
    public function __construct(
        public string $remainingInput,
        public array $stack,
    ) {}
}
