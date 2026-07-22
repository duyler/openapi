<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function filter_var;
use function function_exists;
use function idn_to_ascii;
use function sprintf;
use function strlen;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_IP;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const INTL_IDNA_VARIANT_UTS46;

final readonly class EmailValidator extends AbstractStringFormatValidator
{
    private const string LOCAL_PART_PATTERN = '(?<local>'
        . '(?<dotAtom>[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]{1,64})'
        . '|'
        . '(?<quoted>"(?:\\\\.|[ !#-\[\]-~]){0,150}")'
        . '|'
        . '(?<unicodeLocal>[\p{L}\p{N}.!#$%&\'*+\/=?^_`{|}~-]{1,64})'
        . ')';

    private const string DOMAIN_PATTERN = '(?<domain>'
        . '(?<dns>(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,})'
        . '|'
        . '\\[(?<ipLiteral>IPv6:[0-9a-fA-F:.]+|(?:\d{1,3}(?:\.\d{1,3}){3}))\\]'
        . '|'
        . '(?<unicodeDomain>(?:[\p{L}\p{N}](?:[\p{L}\p{N}\-]{0,61}[\p{L}\p{N}])?\.)+[\p{L}]{2,})'
        . ')';

    private const string EMAIL_PATTERN = '/^' . self::LOCAL_PART_PATTERN . '@' . self::DOMAIN_PATTERN . '$/u';

    private const int MAX_EMAIL = 254;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'email';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (strlen($data) > self::MAX_EMAIL) {
            throw new InvalidFormatException('email', $data, sprintf('Email exceeds RFC 5321 max length (%d)', self::MAX_EMAIL));
        }

        if (1 !== $this->pregExecutor->match(self::EMAIL_PATTERN, $data, $m)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }

        $this->dispatchByMatch($m, $data);
    }

    /**
     * @param array<array-key, mixed> $m named matches from EMAIL_PATTERN
     */
    private function dispatchByMatch(array $m, string $data): void
    {
        $ipLiteral = (string) ($m['ipLiteral'] ?? '');

        if ('' !== $ipLiteral) {
            $this->validateIpLiteral($ipLiteral, $data);

            return;
        }

        $unicodeDomain = (string) ($m['unicodeDomain'] ?? '');

        if ('' !== $unicodeDomain) {
            $this->validateSmtpUtf8((string) $m['local'], $unicodeDomain, $data);

            return;
        }

        $unicodeLocal = (string) ($m['unicodeLocal'] ?? '');

        if ('' !== $unicodeLocal) {
            $this->validateSmtpUtf8($unicodeLocal, (string) ($m['dns'] ?? ''), $data);

            return;
        }

        $quoted = (string) ($m['quoted'] ?? '');

        if ('' === $quoted && false === filter_var($data, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }

    private function validateIpLiteral(string $literal, string $data): void
    {
        if (1 === $this->pregExecutor->match('/^IPv6:(.+)$/', $literal, $ipv6Match)) {
            if (false === filter_var($ipv6Match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new InvalidFormatException('email', $data, 'Invalid email format');
            }

            return;
        }

        if (false === filter_var($literal, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }

    private function validateSmtpUtf8(string $local, string $domain, string $data): void
    {
        if ('' === $domain) {
            return;
        }

        if (false === function_exists('idn_to_ascii')) {
            return;
        }

        $ascii = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46, $info);

        if (false === $ascii || !isset($info['errors']) || 0 !== $info['errors']) {
            return;
        }

        if (1 === $this->pregExecutor->match('/[\x80-\xff]/', $local)) {
            return;
        }

        if (false === filter_var($local . '@' . $ascii, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }
}
