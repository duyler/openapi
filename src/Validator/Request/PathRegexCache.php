<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Internal\LruCache;
use InvalidArgumentException;

use function count;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function sprintf;
use function str_replace;
use function strlen;
use function substr_replace;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

final readonly class PathRegexCache
{
    private const int DEFAULT_MAX_SIZE = 256;

    private const string REGEX_DELIMITER = '#';

    private const string PLACEHOLDER_TOKEN_FORMAT = "\x01PH%d\x02";

    private const string VARNAME_FIRST_CHAR_CLASS = 'a-zA-Z_\x80-\xff';

    private const string VARNAME_TAIL_CHAR_CLASS = 'a-zA-Z0-9_.\x80-\xff';

    /** @var LruCache<string, string> */
    private LruCache $cache;

    /**
     * @param int|null $maxSize
     */
    public function __construct(?int $maxSize = null)
    {
        /** @var LruCache<string, string> */
        $this->cache = new LruCache($maxSize ?? self::DEFAULT_MAX_SIZE);
    }

    public function getOrCompute(string $template): string
    {
        $cachedRegex = $this->cache->get($template);

        if (null !== $cachedRegex) {
            return $cachedRegex;
        }

        $regex = $this->buildRegex($template);
        $this->cache->set($template, $regex);

        return $regex;
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    private function buildRegex(string $template): string
    {
        $placeholders = $this->extractPlaceholders($template);

        if (1 === preg_match('/\{[^}]*\}/', $placeholders['templated'])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid path parameter name in template: "%s"',
                $template,
            ));
        }

        $escaped = preg_quote($placeholders['templated'], self::REGEX_DELIMITER);

        foreach ($placeholders['map'] as $info) {
            $groupName = str_replace('.', '_', $info['name']);
            $segment = '+' === $info['operator']
                ? '(?P<' . $groupName . '>[^?\#]+)'
                : '(?P<' . $groupName . '>[^/]+)';
            $escaped = str_replace($info['token'], $segment, $escaped);
        }

        return self::REGEX_DELIMITER . '^' . $escaped . '$' . self::REGEX_DELIMITER;
    }

    /**
     * @return array{templated: string, map: list<array{token: string, name: string, operator: string}>}
     */
    private function extractPlaceholders(string $template): array
    {
        $pattern = sprintf(
            '/\{(?<operator>[+#.\/;?&]?)(?<name>[%s][%s]*)\}/',
            self::VARNAME_FIRST_CHAR_CLASS,
            self::VARNAME_TAIL_CHAR_CLASS,
        );

        /** @var list<array{fullMatch: string, offset: int, operator: string, name: string}> $matches */
        $matches = [];
        $result = preg_match_all($pattern, $template, $raw, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if (false !== $result && $result > 0) {
            /**
             * @var list<array{0: string, 1: int, operator: array{0: string, 1: int}, name: array{0: string, 1: int}}> $raw
             */
            foreach ($raw as $row) {
                /** @var int $offset */
                $offset = $row[0][1];
                $matches[] = [
                    'fullMatch' => $row[0][0],
                    'offset' => $offset,
                    'operator' => $row['operator'][0],
                    'name' => $row['name'][0],
                ];
            }
        }

        /** @var list<array{token: string, name: string, operator: string}> $map */
        $map = [];
        $templated = $template;

        for ($i = count($matches) - 1; $i >= 0; --$i) {
            $match = $matches[$i];
            $operator = $match['operator'];

            if ('' !== $operator && '+' !== $operator) {
                throw new InvalidArgumentException(sprintf(
                    'Unsupported path template operator: "%s"',
                    $operator,
                ));
            }

            $token = sprintf(self::PLACEHOLDER_TOKEN_FORMAT, $i);
            $map[] = ['token' => $token, 'name' => $match['name'], 'operator' => $operator];
            $templated = substr_replace(
                $templated,
                $token,
                $match['offset'],
                strlen($match['fullMatch']),
            );
        }

        return ['templated' => $templated, 'map' => array_reverse($map)];
    }
}
