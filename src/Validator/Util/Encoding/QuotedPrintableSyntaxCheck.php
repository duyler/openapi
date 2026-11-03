<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function substr_count;

final readonly class QuotedPrintableSyntaxCheck implements EncodingSyntaxCheck
{
    private const string VIOLATION_PATTERN = '/(?<violation>[^\x09\x0A\x0D\x20-\x7E]|=(?!\r\n|[0-9A-F]{2}))/';

    private const string LINE_BREAK = "\r\n";

    private const string CARRIAGE_RETURN = "\r";

    private const string LINE_FEED = "\n";

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    public function isValid(string $data): bool
    {
        if ($this->hasStrayLineBreak($data)) {
            return false;
        }

        return 0 === $this->pregExecutor->match(self::VIOLATION_PATTERN, $data);
    }

    private function hasStrayLineBreak(string $data): bool
    {
        return substr_count($data, self::CARRIAGE_RETURN) !== substr_count($data, self::LINE_BREAK)
            || substr_count($data, self::LINE_FEED) !== substr_count($data, self::LINE_BREAK);
    }
}
