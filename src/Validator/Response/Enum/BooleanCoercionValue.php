<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Enum;

enum BooleanCoercionValue: string
{
    case True = 'true';
    case One = '1';
    case Yes = 'yes';
    case On = 'on';
    case False = 'false';
    case Zero = '0';
    case No = 'no';
    case Off = 'off';

    public function isTruthy(): bool
    {
        return match ($this) {
            self::True, self::One, self::Yes, self::On => true,
            self::False, self::Zero, self::No, self::Off => false,
        };
    }
}
