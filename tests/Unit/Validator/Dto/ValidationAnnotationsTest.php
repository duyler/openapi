<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Dto;

use Duyler\OpenApi\Validator\Dto\ValidationAnnotations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationAnnotations::class)]
final class ValidationAnnotationsTest extends TestCase
{
    #[Test]
    public function new_instance_is_empty(): void
    {
        $annotations = new ValidationAnnotations();

        $this->assertSame([], $annotations->toArray());
    }

    #[Test]
    public function with_appends_record_under_keyword(): void
    {
        $annotations = new ValidationAnnotations()
            ->with('x-meta', '/', ['a' => 1]);

        $this->assertSame([
            'x-meta' => [
                ['path' => '/', 'value' => ['a' => 1]],
            ],
        ], $annotations->toArray());
    }

    #[Test]
    public function with_accumulates_records_per_keyword_and_keeps_other_keywords(): void
    {
        $annotations = new ValidationAnnotations()
            ->with('x-a', '/', 1)
            ->with('x-b', '/users', 2)
            ->with('x-a', '/users/0', 3);

        $this->assertSame([
            'x-a' => [
                ['path' => '/', 'value' => 1],
                ['path' => '/users/0', 'value' => 3],
            ],
            'x-b' => [
                ['path' => '/users', 'value' => 2],
            ],
        ], $annotations->toArray());
    }

    #[Test]
    public function with_does_not_mutate_original_instance(): void
    {
        $original = new ValidationAnnotations();

        $updated = $original->with('x-meta', '/', 1);

        $this->assertSame([], $original->toArray());
        $this->assertNotSame($original, $updated);
    }

    #[Test]
    public function merge_appends_child_records_per_keyword(): void
    {
        $parent = new ValidationAnnotations()
            ->with('x-meta', '/', 'parent');

        $child = new ValidationAnnotations()
            ->with('x-meta', '/users', 'child')
            ->with('x-ok', '/users', 'branch');

        $merged = $parent->merge($child);

        $this->assertSame([
            'x-meta' => [
                ['path' => '/', 'value' => 'parent'],
                ['path' => '/users', 'value' => 'child'],
            ],
            'x-ok' => [
                ['path' => '/users', 'value' => 'branch'],
            ],
        ], $merged->toArray());
    }

    #[Test]
    public function merge_with_empty_child_keeps_parent_records(): void
    {
        $parent = new ValidationAnnotations()
            ->with('x-meta', '/', 'parent');

        $merged = $parent->merge(new ValidationAnnotations());

        $this->assertSame([
            'x-meta' => [
                ['path' => '/', 'value' => 'parent'],
            ],
        ], $merged->toArray());
    }

    #[Test]
    public function merge_does_not_mutate_operands(): void
    {
        $parent = new ValidationAnnotations()->with('x-meta', '/', 'parent');
        $child = new ValidationAnnotations()->with('x-ok', '/users', 'branch');

        $parent->merge($child);

        $this->assertSame(['x-meta' => [['path' => '/', 'value' => 'parent']]], $parent->toArray());
        $this->assertSame(['x-ok' => [['path' => '/users', 'value' => 'branch']]], $child->toArray());
    }
}
