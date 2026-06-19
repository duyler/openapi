<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use InvalidArgumentException;

use function count;
use function preg_match;
use function preg_quote;
use function preg_replace_callback;
use function sprintf;
use function str_replace;

/**
 * Instance-scoped LRU cache of compiled path-template regular expressions.
 *
 * Thread-safety: NOT thread-safe. In Swoole coroutines or threaded FrankenPHP,
 * each worker/coroutine must own its own instance (the default in
 * OpenApiValidatorBuilder — one PathRegexCache per built document).
 */
final class PathRegexCache
{
    private const int DEFAULT_MAX_SIZE = 256;

    private const string REGEX_DELIMITER = '#';

    private const string PLACEHOLDER_TOKEN_FORMAT = "\x01PH%d\x02";

    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, true> */
    private array $order = [];

    private readonly int $maxSize;

    /**
     * @param int|null $maxSize Maximum entries before LRU eviction. Null falls back to DEFAULT_MAX_SIZE.
     *
     * @throws InvalidArgumentException when $maxSize is less than 1
     */
    public function __construct(?int $maxSize = null)
    {
        $resolvedMaxSize = $maxSize ?? self::DEFAULT_MAX_SIZE;

        if (1 > $resolvedMaxSize) {
            throw new InvalidArgumentException(
                sprintf('Max size must be at least 1, got %d', $resolvedMaxSize),
            );
        }

        $this->maxSize = $resolvedMaxSize;
    }

    public function getOrCompute(string $template): string
    {
        if (isset($this->cache[$template])) {
            $this->touch($template);

            return $this->cache[$template];
        }

        $regex = $this->buildRegex($template);
        $this->cache[$template] = $regex;
        $this->order[$template] = true;

        if (count($this->cache) > $this->maxSize) {
            $evictedKey = array_key_first($this->order);
            unset($this->order[$evictedKey], $this->cache[$evictedKey]);
        }

        return $regex;
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->order = [];
    }

    /**
     * Build a delimited regular expression for an OpenAPI path template.
     *
     * Implements RFC 6570 Level 1 (`{name}`, matches `[^/]+`) and Level 2
     * reserved expansion (`{+name}`, matches `[^?#]+`). The fixed parts of the
     * template are escaped via `preg_quote` to prevent regex meta-characters
     * (`.`, `+`, `(`, ...) from being interpreted as regex syntax, which would
     * otherwise allow path-based ACL bypass (e.g. `/v1.0/users` matching
     * `/v1X0/users`). Parameter names are validated as legal PHP regex group
     * names; unsupported RFC 6570 operators are rejected fail-fast.
     *
     * @return string Fully-delimited regular expression (`#^...$#`)
     *
     * @throws InvalidArgumentException when the template contains an invalid
     *     parameter name or an unsupported RFC 6570 operator
     */
    private function buildRegex(string $template): string
    {
        /** @var array<string, array{name: string, operator: string}> $placeholders */
        $placeholders = [];
        $templated = preg_replace_callback(
            '/\{(?<operator>[+#.\/;?&]?)(?<name>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\}/',
            static function (array $m) use (&$placeholders): string {
                $operator = $m['operator'];
                $name = $m['name'];

                if ('' !== $operator && '+' !== $operator) {
                    throw new InvalidArgumentException(sprintf(
                        'Unsupported path template operator: "%s"',
                        $operator,
                    ));
                }

                $token = sprintf(self::PLACEHOLDER_TOKEN_FORMAT, count($placeholders));
                $placeholders[$token] = ['name' => $name, 'operator' => $operator];

                return $token;
            },
            $template,
        ) ?? $template;

        if (1 === preg_match('/\{[^}]*\}/', $templated)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid path parameter name in template: "%s"',
                $template,
            ));
        }

        $escaped = preg_quote($templated, self::REGEX_DELIMITER);

        foreach ($placeholders as $token => $info) {
            $pattern = '+' === $info['operator']
                ? '(?P<' . $info['name'] . '>[^?\#]+)'
                : '(?P<' . $info['name'] . '>[^/]+)';
            $escaped = str_replace($token, $pattern, $escaped);
        }

        return self::REGEX_DELIMITER . '^' . $escaped . '$' . self::REGEX_DELIMITER;
    }

    private function touch(string $key): void
    {
        unset($this->order[$key]);
        $this->order[$key] = true;
    }
}
