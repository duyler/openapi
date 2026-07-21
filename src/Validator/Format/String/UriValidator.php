<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class UriValidator extends AbstractStringFormatValidator
{
    private const string URI_PATTERN = '/(?J)^'
        . '(?<scheme>[A-Za-z][A-Za-z0-9+.\-]*)'
        . ':'
        . '(?:'
        . '\/\/(?<host>\[[0-9a-fA-F:]+\]|[^\s:?#\/]*)'
        . '(?::(?<port>\d{1,5}))?'
        . '(?<path>\/[^\s?#]*)?'
        . '|'
        . '(?<path>\/[^\s?#]*)'
        . '|'
        . '(?<path>[^\s?#\/][^\s?#]*)'
        . '|'
        . '(?<path>)'
        . ')'
        . '(?<query>\?[^\s#]*)?'
        . '(?<fragment>#[^\s]*)?'
        . '$/J';

    private const int MAX_PORT = 65535;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (1 !== $this->pregExecutor->match(self::URI_PATTERN, $data, $m)) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }

        $portValue = (string) ($m['port'] ?? '');
        if ('' !== $portValue && (int) $portValue > self::MAX_PORT) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI: port out of range');
        }
    }
}
