<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function sprintf;
use function substr_count;

final readonly class UriTemplateValidator extends AbstractStringFormatValidator
{
    private const string TEMPLATE_PATTERN = '/^(?:'
        . '[^{}]'
        . '|'
        . '\{[+#.\/;?&=,!@|]?[A-Za-z0-9_][A-Za-z0-9_.%=*,]*\}'
        . ')*$/u';

    private const int MAX_EXPRESSIONS = 1000;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri-template';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (1 !== $this->pregExecutor->match(self::TEMPLATE_PATTERN, $data)) {
            $reason = substr_count($data, '{') !== substr_count($data, '}')
                ? 'Unbalanced template expressions'
                : 'Invalid URI template';

            throw new InvalidFormatException('uri-template', $data, $reason);
        }

        if (substr_count($data, '{') > self::MAX_EXPRESSIONS) {
            throw new InvalidFormatException('uri-template', $data, sprintf('URI template exceeds maximum expression count (%d)', self::MAX_EXPRESSIONS));
        }
    }
}
