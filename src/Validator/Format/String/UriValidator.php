<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function filter_var;
use function in_array;
use function preg_match;
use function sprintf;
use function strtolower;

use const FILTER_VALIDATE_URL;

final readonly class UriValidator extends AbstractStringFormatValidator
{
    private const string URI_PATTERN = '/^'
        . '(?<scheme>[a-zA-Z][a-zA-Z0-9+\-.]*)'
        . ':(?=\\/\\/)'
        . '\\/\\/(?<host>\[[0-9a-fA-F:]+\]|[^\s:?#\/]+)'
        . '(?::(?<port>\d{1,5}))?'
        . '(?<path>\/[^\s?#]*)?'
        . '(?<query>\?[^\s#]*)?'
        . '(?<fragment>#[^\s]*)?'
        . '$/';

    private const array ALLOWED_SCHEMES = ['http', 'https', 'ftp', 'ftps', 'file', 'mailto', 'tel', 'data'];

    private const int MAX_PORT = 65535;

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (1 !== preg_match(self::URI_PATTERN, $data, $m)) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }

        $scheme = strtolower($m['scheme']);
        if (false === in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new InvalidFormatException('uri', $data, sprintf('Unsupported URI scheme: %s', $scheme));
        }

        if (isset($m['port']) && '' !== $m['port'] && (int) $m['port'] > self::MAX_PORT) {
            throw new InvalidFormatException('uri', $data, sprintf('Port out of range: %s', $m['port']));
        }

        if (false === filter_var($data, FILTER_VALIDATE_URL)) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }
    }
}
