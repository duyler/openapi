<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function explode;
use function function_exists;
use function idn_to_ascii;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;

use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const INTL_IDNA_VARIANT_UTS46;

final readonly class HostnameValidator extends AbstractStringFormatValidator
{
    private const string HOSTNAME_PATTERN = '/^[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

    private const int MAX_HOSTNAME_LENGTH = 253;

    private const int MAX_LABEL_LENGTH = 63;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'hostname';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (strlen($data) > self::MAX_HOSTNAME_LENGTH) {
            throw new InvalidFormatException('hostname', $data, sprintf('Hostname must not exceed %d characters', self::MAX_HOSTNAME_LENGTH));
        }

        $normalized = $this->toAscii($data);

        if (1 !== $this->pregExecutor->match(self::HOSTNAME_PATTERN, $normalized)) {
            throw new InvalidFormatException('hostname', $data, 'Invalid hostname format');
        }

        $labels = explode('.', $normalized);
        foreach ($labels as $label) {
            if (strlen($label) > self::MAX_LABEL_LENGTH) {
                throw new InvalidFormatException('hostname', $data, sprintf('Each label must not exceed %d characters', self::MAX_LABEL_LENGTH));
            }

            if (str_starts_with($label, '-') || str_ends_with($label, '-')) {
                throw new InvalidFormatException('hostname', $data, 'Labels must not start or end with hyphen');
            }
        }
    }

    private function toAscii(string $hostname): string
    {
        if ('' === $hostname) {
            return $hostname;
        }

        if (function_exists('idn_to_ascii')) {
            $result = idn_to_ascii($hostname, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if (false !== $result) {
                return $result;
            }
        }

        return $hostname;
    }
}
