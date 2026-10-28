<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function explode;
use function filter_var;
use function str_ends_with;
use function str_starts_with;
use function sprintf;
use function str_contains;
use function stripos;
use function strlen;
use function strpos;
use function substr;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final readonly class EmailValidator extends AbstractStringFormatValidator
{
    private const int MAX_EMAIL_LENGTH = 254;

    private const int MAX_LOCAL_PART_LENGTH = 64;

    private const string DOT_ATOM_LABEL_PATTERN = '/^[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+\z/';

    private const string SUB_DOMAIN_LABEL_PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\z/';

    private const string IPV4_LITERAL_PATTERN = '/^(?<s1>\d{1,3})\.(?<s2>\d{1,3})\.(?<s3>\d{1,3})\.(?<s4>\d{1,3})\z/';

    private const string IPV6_TAG = 'ipv6:';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    public function isValidAddressLiteralDomain(string $domainPart): bool
    {
        if (false === str_ends_with($domainPart, ']') || strlen($domainPart) < 3) {
            return false;
        }

        $content = substr($domainPart, 1, -1);

        if ('' === $content) {
            return false;
        }

        if (0 === stripos($content, self::IPV6_TAG)) {
            $ipv6 = substr($content, strlen(self::IPV6_TAG));

            return false !== filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        if (1 !== $this->pregExecutor->match(self::IPV4_LITERAL_PATTERN, $content, $matches)) {
            return false;
        }
        return array_all([$matches['s1'], $matches['s2'], $matches['s3'], $matches['s4']], fn($snum) => (int) $snum <= 255);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'email';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (strlen($data) > self::MAX_EMAIL_LENGTH) {
            throw new InvalidFormatException(
                'email',
                $data,
                sprintf('Email exceeds RFC 5321 max length (%d)', self::MAX_EMAIL_LENGTH),
            );
        }

        if (false === $this->isValidMailbox($data)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }

    private function isValidMailbox(string $data): bool
    {
        if ('' === $data) {
            return false;
        }

        if ('"' === $data[0]) {
            $localEnd = $this->findQuotedStringEnd($data, asciiOnly: true);

            if (null === $localEnd) {
                return false;
            }

            $localPart = substr($data, 0, $localEnd + 1);
            $domainPart = substr($data, $localEnd + 1);

            if ('' === $domainPart || '@' !== $domainPart[0]) {
                return false;
            }

            $domainPart = substr($domainPart, 1);
        } else {
            $atPosition = strpos($data, '@');

            if (false === $atPosition) {
                return false;
            }

            $localPart = substr($data, 0, $atPosition);
            $domainPart = substr($data, $atPosition + 1);

            if (false === $this->isValidDotAtom($localPart)) {
                return false;
            }
        }

        if ('' === $domainPart || true === str_contains($domainPart, '@')) {
            return false;
        }

        return $this->isValidDomain($domainPart);
    }

    private function isValidDotAtom(string $localPart): bool
    {
        if ('' === $localPart || strlen($localPart) > self::MAX_LOCAL_PART_LENGTH) {
            return false;
        }

        foreach (explode('.', $localPart) as $atom) {
            if ('' === $atom) {
                return false;
            }

            if (1 !== $this->pregExecutor->match(self::DOT_ATOM_LABEL_PATTERN, $atom)) {
                return false;
            }
        }

        return true;
    }

    private function isValidDomain(string $domainPart): bool
    {
        if (true === str_starts_with($domainPart, '[')) {
            return $this->isValidAddressLiteralDomain($domainPart);
        }
        return array_all(explode('.', $domainPart), fn($label) => 1 === $this->pregExecutor->match(self::SUB_DOMAIN_LABEL_PATTERN, $label));
    }
}
