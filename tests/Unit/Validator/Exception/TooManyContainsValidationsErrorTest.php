<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;
use Duyler\OpenApi\Validator\Exception\TooManyContainsValidationsError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TooManyContainsValidationsErrorTest extends TestCase
{
    #[Test]
    public function keyword_is_contains(): void
    {
        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: '/items');

        self::assertSame('contains', $error->keyword());
    }

    #[Test]
    public function schemaPath_is_contains_pointer(): void
    {
        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: '/items');

        self::assertSame('/contains', $error->schemaPath());
    }

    #[Test]
    public function params_exposes_max_cap(): void
    {
        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: '/items');

        self::assertSame(['max' => 10_000], $error->params());
    }

    #[Test]
    public function message_reports_cap_and_dos_defense_intent(): void
    {
        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: '/items');

        self::assertSame(
            'Contains validation aborted after 10000 matching items (DoS defense)',
            $error->message(),
        );
    }

    #[Test]
    public function suggestion_points_to_maxItems_bound(): void
    {
        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: '/items');

        self::assertSame(
            'Constrain the array with maxItems to keep contains validation bounded',
            $error->suggestion(),
        );
    }

    #[Test]
    public function dataPath_passes_through_string_form(): void
    {
        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: '/items/0');

        self::assertSame('/items/0', $error->dataPath());
    }

    #[Test]
    public function dataPath_accepts_breadcrumb_and_renders_pointer(): void
    {
        $breadcrumb = (new Breadcrumb())->append('items')->appendIndex(0);

        $error = new TooManyContainsValidationsError(max: 10_000, dataPath: $breadcrumb);

        self::assertSame('/items/0', $error->dataPath());
    }
}
