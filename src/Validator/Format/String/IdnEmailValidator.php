<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function explode;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

final readonly class IdnEmailValidator extends AbstractStringFormatValidator
{
    private const int MAX_EMAIL_LENGTH = 254;

    private const int MAX_LOCAL_PART_LENGTH = 64;

    private const string DOT_ATOM_LABEL_PATTERN = '/^[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~\x{80}-\x{10FFFF}-]+\z/u';

    private const string U_LABEL_PATTERN = '/^[A-Za-z0-9\x{80}-\x{10FFFF}-]+\z/u';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
        private readonly EmailValidator $emailValidator = new EmailValidator(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'idn-email';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (strlen($data) > self::MAX_EMAIL_LENGTH) {
            throw new InvalidFormatException('idn-email', $data, 'Invalid idn-email format');
        }

        if (false === $this->isValidMailbox($data)) {
            throw new InvalidFormatException('idn-email', $data, 'Invalid idn-email format');
        }
    }

    private function isValidMailbox(string $data): bool
    {
        if ('' === $data) {
            return false;
        }

        if ('"' === $data[0]) {
            $localEnd = $this->findQuotedStringEnd($data, asciiOnly: false);

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

            if (false === $this->isValidUtf8DotAtom($localPart)) {
                return false;
            }
        }

        if ('' === $domainPart || true === str_contains($domainPart, '@')) {
            return false;
        }

        return $this->isValidUtf8Domain($domainPart);
    }

    private function isValidUtf8DotAtom(string $localPart): bool
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

    private function isValidUtf8Domain(string $domainPart): bool
    {
        if (true === str_starts_with($domainPart, '[')) {
            return $this->emailValidator->isValidAddressLiteralDomain($domainPart);
        }

        foreach (explode('.', $domainPart) as $label) {
            if ('' === $label) {
                return false;
            }

            if (1 !== $this->pregExecutor->match(self::U_LABEL_PATTERN, $label)) {
                return false;
            }

            if (true === str_starts_with($label, '-') || true === str_ends_with($label, '-')) {
                return false;
            }
        }

        return true;
    }
}
