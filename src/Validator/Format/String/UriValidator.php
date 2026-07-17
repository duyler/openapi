<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\Enum\UriScheme;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function filter_var;
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

        $scheme = strtolower((string) ($m['scheme'] ?? ''));
        if (null === UriScheme::tryFrom($scheme)) {
            throw new InvalidFormatException('uri', $data, sprintf('Unsupported URI scheme: %s', $scheme));
        }

        $portValue = (string) ($m['port'] ?? '');
        if ('' !== $portValue && (int) $portValue > self::MAX_PORT) {
            throw new InvalidFormatException('uri', $data, sprintf('Port out of range: %s', $portValue));
        }

        if (false === filter_var($data, FILTER_VALIDATE_URL)) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }
    }
}
