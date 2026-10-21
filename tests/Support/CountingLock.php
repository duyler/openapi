<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

final class CountingLock
{
    public int $lockCount = 0;

    public int $unlockCount = 0;

    public function lock(): void
    {
        ++$this->lockCount;
    }

    public function unlock(): void
    {
        ++$this->unlockCount;
    }
}
