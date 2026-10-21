<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use InvalidArgumentException;

use function abs;
use function bin2hex;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function pack;
use function random_bytes;

/** @internal */
final readonly class ScalarKeyCodec
{
    public const int SAFE_INT64_FLOAT_BOUNDARY = 9007199254740992;

    private const string NULL_KEY = 'null';

    private const string BOOL_PREFIX = 'b:';

    private const string STRING_PREFIX = 's:';

    private const string NUMBER_PREFIX = 'n:';

    private const string BIG_INT_PREFIX = 'n:i:';

    private const string NAN_KEY_PREFIX = 'nan:';

    private const int NAN_KEY_ENTROPY_BYTES = 8;

    public function encode(mixed $value): string
    {
        return match (true) {
            null === $value => self::NULL_KEY,
            is_bool($value) => self::BOOL_PREFIX . (string) (int) $value,
            is_int($value) => $this->encodeInt($value),
            is_float($value) => $this->encodeFloat($value),
            is_string($value) => self::STRING_PREFIX . $value,
            default => throw new InvalidArgumentException('Only scalar or null values can be encoded into a comparison key'),
        };
    }

    private function encodeInt(int $value): string
    {
        if (abs($value) > self::SAFE_INT64_FLOAT_BOUNDARY) {
            return self::BIG_INT_PREFIX . (string) $value;
        }

        return self::NUMBER_PREFIX . bin2hex(pack('E', (float) $value));
    }

    private function encodeFloat(float $value): string
    {
        if (is_nan($value)) {
            return self::NUMBER_PREFIX . self::NAN_KEY_PREFIX . bin2hex(random_bytes(self::NAN_KEY_ENTROPY_BYTES));
        }

        if (0.0 === $value) {
            return self::NUMBER_PREFIX . bin2hex(pack('E', 0.0));
        }

        return self::NUMBER_PREFIX . bin2hex(pack('E', $value));
    }
}
