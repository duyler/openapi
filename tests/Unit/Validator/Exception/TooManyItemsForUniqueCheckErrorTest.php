<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;
use Duyler\OpenApi\Validator\Exception\TooManyItemsForUniqueCheckError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TooManyItemsForUniqueCheckErrorTest extends TestCase
{
    private const MAX = 100000;

    #[Test]
    public function keyword_returns_uniqueItems(): void
    {
        $exception = $this->build();

        self::assertSame('uniqueItems', $exception->keyword());
    }

    #[Test]
    public function schemaPath_returns_uniqueItems_pointer(): void
    {
        $exception = $this->build();

        self::assertSame('/uniqueItems', $exception->schemaPath());
    }

    #[Test]
    public function message_includes_max_and_dos_defense_marker(): void
    {
        $exception = $this->build();

        self::assertSame(
            'Unique-items check aborted after 100000 unique entries (DoS defense)',
            $exception->message(),
        );
    }

    #[Test]
    public function params_carry_max_entry(): void
    {
        $exception = $this->build();

        self::assertSame(['max' => self::MAX], $exception->params());
    }

    #[Test]
    public function suggestion_advises_maxItems(): void
    {
        $exception = $this->build();

        self::assertSame(
            'Constrain the array with maxItems to keep unique-items check bounded',
            $exception->suggestion(),
        );
    }

    #[Test]
    public function dataPath_accepts_breadcrumb_and_renders_as_string(): void
    {
        $breadcrumb = (new Breadcrumb())->append('items');

        $exception = new TooManyItemsForUniqueCheckError(
            max: self::MAX,
            dataPath: $breadcrumb,
        );

        self::assertSame('/items', $exception->dataPath());
    }

    private function build(): TooManyItemsForUniqueCheckError
    {
        return new TooManyItemsForUniqueCheckError(
            max: self::MAX,
            dataPath: '/items',
        );
    }
}
