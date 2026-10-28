<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\PregExecutor;

use function explode;
use function filter_var;
use function min;
use function strlen;
use function strpos;
use function str_contains;
use function str_starts_with;
use function substr;
use function substr_count;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final readonly class UriGrammar
{
    private const int MAX_PORT = 65535;

    private const string ASCII_PRINTABLE_PATTERN = '/^[\x21-\x7E]+\z/';

    private const string CONTROL_OR_SPACE_PATTERN = '/[\x00-\x1F\x7F ]/';

    private const string SCHEME_PATTERN = '/^([A-Za-z][A-Za-z0-9+.\-]*):(.*)\z/s';

    private const string PERCENT_ENCODING = '%[0-9A-Fa-f]{2}';

    private const string UNRESERVED = "[A-Za-z0-9\\-._~]";

    private const string SUB_DELIMS = "[!$&'()*+,;=]";

    private const string UCSCHAR = '[\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}'
        . '\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}'
        . '\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}'
        . '\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}'
        . '\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}]';

    private const string IPRIVATE = '[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}]';

    private const string IRI_ALLOWED_PATTERN = '/^(?:[\x21-\x7E]|' . self::UCSCHAR . '|' . self::IPRIVATE . ')+\z/u';

    private const string IPV_FUTURE_PATTERN = "/^v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&'()*+,;=:]+\z/";

    private const string PORT_PATTERN = '/^:\d*\z/';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    public function isAbsoluteUri(string $value, bool $allowUcsChar): bool
    {
        if (false === $this->hasAllowedByteSpace($value, $allowUcsChar)) {
            return false;
        }

        $parts = $this->splitFragmentAndQuery($value);
        $remainder = $parts[0];

        if (false === $this->isQueryAndFragmentValid($parts[1], $parts[2], $allowUcsChar)) {
            return false;
        }

        if (1 !== $this->pregExecutor->match(self::SCHEME_PATTERN, $remainder, $schemeMatch)) {
            return false;
        }

        return $this->isValidHierPart((string) $schemeMatch[2], $allowUcsChar);
    }

    public function isUriReference(string $value, bool $allowUcsChar): bool
    {
        if ('' === $value) {
            return true;
        }

        if (true === $this->isAbsoluteUri($value, $allowUcsChar)) {
            return true;
        }

        if (false === $this->hasAllowedByteSpace($value, $allowUcsChar)) {
            return false;
        }

        $parts = $this->splitFragmentAndQuery($value);
        $remainder = $parts[0];

        if (false === $this->isQueryAndFragmentValid($parts[1], $parts[2], $allowUcsChar)) {
            return false;
        }

        return $this->isValidRelativePart($remainder, $allowUcsChar);
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function splitFragmentAndQuery(string $value): array
    {
        $fragment = null;
        $query = null;
        $remainder = $value;

        $fragmentPosition = $this->indexOf($remainder, '#');

        if (null !== $fragmentPosition) {
            $fragment = substr($remainder, $fragmentPosition + 1);
            $remainder = substr($remainder, 0, $fragmentPosition);
        }

        $queryPosition = $this->indexOf($remainder, '?');

        if (null !== $queryPosition) {
            $query = substr($remainder, $queryPosition + 1);
            $remainder = substr($remainder, 0, $queryPosition);
        }

        return [$remainder, $query, $fragment];
    }

    private function indexOf(string $value, string $needle): ?int
    {
        $position = strpos($value, $needle);

        return false === $position ? null : $position;
    }

    private function isQueryAndFragmentValid(?string $query, ?string $fragment, bool $allowUcsChar): bool
    {
        if (null !== $query
            && 1 !== $this->pregExecutor->match('/^(?:' . $this->pcharClass($allowUcsChar) . '|[\\/\\?]'
                . ($allowUcsChar ? '|' . self::IPRIVATE : '') . ')*\z/u', $query)) {
            return false;
        }

        if (null !== $fragment
            && 1 !== $this->pregExecutor->match('/^(?:' . $this->pcharClass($allowUcsChar) . '|[\\/\\?])*\z/u', $fragment)) {
            return false;
        }

        return true;
    }

    private function hasAllowedByteSpace(string $value, bool $allowUcsChar): bool
    {
        if (true === $allowUcsChar) {
            return 1 !== $this->pregExecutor->match(self::CONTROL_OR_SPACE_PATTERN, $value)
                && 1 === $this->pregExecutor->match(self::IRI_ALLOWED_PATTERN, $value);
        }

        return 1 === $this->pregExecutor->match(self::ASCII_PRINTABLE_PATTERN, $value);
    }

    private function isValidHierPart(string $hierPart, bool $allowUcsChar): bool
    {
        if (true === str_starts_with($hierPart, '//')) {
            return $this->isAuthorityPathValid(substr($hierPart, 2), $allowUcsChar);
        }

        if (true === str_starts_with($hierPart, '/')) {
            return $this->isPathAbsolute($hierPart, $allowUcsChar);
        }

        if ('' === $hierPart) {
            return true;
        }

        return $this->isPathRootless($hierPart, $allowUcsChar);
    }

    private function isValidRelativePart(string $remainder, bool $allowUcsChar): bool
    {
        if (true === str_starts_with($remainder, '//')) {
            return $this->isAuthorityPathValid(substr($remainder, 2), $allowUcsChar);
        }

        if (true === str_starts_with($remainder, '/')) {
            return $this->isPathAbsolute($remainder, $allowUcsChar);
        }

        if ('' === $remainder) {
            return true;
        }

        return $this->isPathNoScheme($remainder, $allowUcsChar);
    }

    private function isAuthorityPathValid(string $authorityAndPath, bool $allowUcsChar): bool
    {
        $delimiterPosition = $this->firstDelimiterPosition($authorityAndPath);
        $authority = substr($authorityAndPath, 0, $delimiterPosition);
        $path = substr($authorityAndPath, $delimiterPosition);

        if (false === $this->isValidAuthority($authority, $allowUcsChar)) {
            return false;
        }

        if ('' === $path) {
            return true;
        }

        if (false === str_starts_with($path, '/')) {
            return false;
        }
        return array_all(explode('/', substr($path, 1)), fn($segment) => true === $this->isSegmentValid($segment, $allowUcsChar, false));
    }

    private function isPathAbsolute(string $path, bool $allowUcsChar): bool
    {
        if ('/' === $path) {
            return true;
        }

        $segments = explode('/', substr($path, 1));

        if ('' === $segments[0]) {
            return false;
        }
        return array_all($segments, fn($segment) => true === $this->isSegmentValid($segment, $allowUcsChar, false));
    }

    private function isPathRootless(string $path, bool $allowUcsChar): bool
    {
        foreach (explode('/', $path) as $segment) {
            if (false === $this->isSegmentValid($segment, $allowUcsChar, false)) {
                return false;
            }
        }

        return '' !== $path;
    }

    private function isPathNoScheme(string $path, bool $allowUcsChar): bool
    {
        $segments = explode('/', $path);

        if ('' === $segments[0] || true === str_contains($segments[0], ':')) {
            return false;
        }

        if (false === $this->isSegmentValid($segments[0], $allowUcsChar, true)) {
            return false;
        }

        unset($segments[0]);
        return array_all($segments, fn($segment) => true === $this->isSegmentValid($segment, $allowUcsChar, false));
    }

    private function firstDelimiterPosition(string $value): int
    {
        $positions = [];

        foreach (['/', '?', '#'] as $delimiter) {
            $position = $this->indexOf($value, $delimiter);

            if (null !== $position) {
                $positions[] = $position;
            }
        }

        return [] === $positions ? strlen($value) : min($positions);
    }

    private function isValidAuthority(string $authority, bool $allowUcsChar): bool
    {
        $atSignCount = substr_count($authority, '@');

        if ($atSignCount > 1) {
            return false;
        }

        $hostPort = $authority;

        if (1 === $atSignCount) {
            $parts = explode('@', $authority, 2);
            $hostPort = $parts[1] ?? '';

            if (false === $this->isValidUserinfo($parts[0], $allowUcsChar)) {
                return false;
            }
        }

        return $this->isValidHostPort($hostPort, $allowUcsChar);
    }

    private function isValidHostPort(string $hostPort, bool $allowUcsChar): bool
    {
        if (true === str_starts_with($hostPort, '[')) {
            $closingPosition = strpos($hostPort, ']');

            if (false === $closingPosition) {
                return false;
            }

            $hostLiteral = substr($hostPort, 1, $closingPosition - 1);
            $portPart = substr($hostPort, $closingPosition + 1);

            if ('' !== $portPart && false === str_starts_with($portPart, ':')) {
                return false;
            }

            if (false === $this->isValidIpLiteral($hostLiteral)) {
                return false;
            }

            return $this->isValidPort(substr($portPart, 1));
        }

        $colonPosition = strrpos($hostPort, ':');

        $host = $hostPort;
        $portPart = '';

        if (false !== $colonPosition) {
            $host = substr($hostPort, 0, $colonPosition);
            $portPart = substr($hostPort, $colonPosition + 1);
        }

        if (true === str_contains($host, '[') || true === str_contains($host, ']')) {
            return false;
        }

        if (false === $this->isValidRegName($host, $allowUcsChar)) {
            return false;
        }

        return $this->isValidPort($portPart);
    }

    private function isValidIpLiteral(string $literal): bool
    {
        if ('' === $literal) {
            return false;
        }

        if (1 === $this->pregExecutor->match(self::IPV_FUTURE_PATTERN, $literal)) {
            return true;
        }

        return false !== filter_var($literal, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    private function isValidPort(string $portPart): bool
    {
        if ('' === $portPart) {
            return true;
        }

        if (1 !== $this->pregExecutor->match(self::PORT_PATTERN, ':' . $portPart)) {
            return false;
        }

        return (int) $portPart <= self::MAX_PORT;
    }

    private function isValidUserinfo(string $userinfo, bool $allowUcsChar): bool
    {
        $characterClass = '(?:' . self::UNRESERVED . '|' . self::PERCENT_ENCODING . '|' . self::SUB_DELIMS . '|:';

        if (true === $allowUcsChar) {
            $characterClass .= '|' . self::UCSCHAR . '|' . self::IPRIVATE;
        }

        $characterClass .= ')';

        return 1 === $this->pregExecutor->match('/^(?:' . $characterClass . ')*\z/u', $userinfo);
    }

    private function isValidRegName(string $host, bool $allowUcsChar): bool
    {
        $characterClass = '(?:' . self::UNRESERVED . '|' . self::PERCENT_ENCODING . '|' . self::SUB_DELIMS;

        if (true === $allowUcsChar) {
            $characterClass .= '|' . self::UCSCHAR;
        }

        $characterClass .= ')';

        return 1 === $this->pregExecutor->match('/^(?:' . $characterClass . ')*\z/u', $host);
    }

    private function isSegmentValid(string $segment, bool $allowUcsChar, bool $noColon): bool
    {
        $characterClass = '(?:' . self::UNRESERVED . '|' . self::PERCENT_ENCODING . '|' . self::SUB_DELIMS;

        $characterClass .= true === $noColon ? '|@' : '|[:@]';

        if (true === $allowUcsChar) {
            $characterClass .= '|' . self::UCSCHAR;
        }

        $characterClass .= ')';

        return 1 === $this->pregExecutor->match('/^(?:' . $characterClass . ')*\z/u', $segment);
    }

    private function pcharClass(bool $allowUcsChar): string
    {
        $class = self::UNRESERVED . '|' . self::PERCENT_ENCODING . '|' . self::SUB_DELIMS . '|[:@]';

        if (true === $allowUcsChar) {
            $class .= '|' . self::UCSCHAR;
        }

        return $class;
    }
}
