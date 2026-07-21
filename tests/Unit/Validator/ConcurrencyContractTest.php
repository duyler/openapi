<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\ValidatorPool;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

use function file_get_contents;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strlen;

final class ConcurrencyContractTest extends TestCase
{
    private const string NOT_THREAD_SAFE_MARKER = 'NOT_THREAD_SAFE';

    private const string SOURCE_DIRECTORY = __DIR__ . '/../../../src/Validator';

    private const string README_PATH = __DIR__ . '/../../../README.md';

    #[Test]
    public function validator_pool_class_carries_not_thread_safe_marker(): void
    {
        $docComment = new ReflectionClass(ValidatorPool::class)->getDocComment();

        self::assertNotFalse(
            $docComment,
            'ValidatorPool must carry a class-level PHPDoc block declaring the concurrency contract',
        );
        self::assertStringContainsString(
            '@danger ' . self::NOT_THREAD_SAFE_MARKER,
            $docComment,
            'ValidatorPool PHPDoc must open the contract with the canonical @danger NOT_THREAD_SAFE marker',
        );
        self::assertStringContainsString(
            'O-004',
            $docComment,
            'ValidatorPool PHPDoc must cite the nested-factory deadlock finding (O-004)',
        );
    }

    #[Test]
    public function libxml_secured_context_class_carries_not_thread_safe_marker(): void
    {
        $docComment = new ReflectionClass(LibxmlSecuredContext::class)->getDocComment();

        self::assertNotFalse(
            $docComment,
            'LibxmlSecuredContext must carry a class-level PHPDoc block declaring the concurrency contract',
        );
        self::assertStringContainsString(
            '@danger ' . self::NOT_THREAD_SAFE_MARKER,
            $docComment,
            'LibxmlSecuredContext PHPDoc must open the contract with the canonical @danger NOT_THREAD_SAFE marker',
        );
        self::assertStringContainsString('O-006', $docComment);
        self::assertStringContainsString('S-011', $docComment);
    }

    #[Test]
    public function preg_executor_class_carries_not_thread_safe_marker(): void
    {
        $docComment = new ReflectionClass(PregExecutor::class)->getDocComment();

        self::assertNotFalse(
            $docComment,
            'PregExecutor must carry a class-level PHPDoc block declaring the concurrency contract',
        );
        self::assertStringContainsString(
            '@danger ' . self::NOT_THREAD_SAFE_MARKER,
            $docComment,
            'PregExecutor PHPDoc must open the contract with the canonical @danger NOT_THREAD_SAFE marker',
        );
        self::assertStringContainsString('O-007', $docComment);
        self::assertStringContainsString('S-020', $docComment);
    }

    #[Test]
    public function validator_pool_for_coroutine_runtime_factory_rejects_lock_without_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ValidatorPool::forCoroutineRuntime(new stdClass());
    }

    #[Test]
    public function validator_pool_for_coroutine_runtime_factory_accepts_lock_with_methods(): void
    {
        $lock = $this->createCountingLock();

        $pool = ValidatorPool::forCoroutineRuntime($lock);

        self::assertInstanceOf(ValidatorPool::class, $pool);
    }

    #[Test]
    public function validator_pool_for_coroutine_runtime_factory_propagates_max_size(): void
    {
        $lock = $this->createCountingLock();

        $pool = ValidatorPool::forCoroutineRuntime($lock, maxSize: 64);

        $maxSizeProperty = new ReflectionProperty(ValidatorPool::class, 'maxSize');

        self::assertSame(64, $maxSizeProperty->getValue($pool));
    }

    #[Test]
    public function validator_pool_acquire_lock_invokes_lock_once_per_get_or_create(): void
    {
        $lock = $this->createCountingLock();
        $pool = ValidatorPool::forCoroutineRuntime($lock);

        $pool->getOrCreate('shared', static fn(): stdClass => new stdClass());
        $pool->getOrCreate('shared', static fn(): stdClass => new stdClass());

        self::assertSame(2, $lock->lockCount, 'lock() must be invoked once per getOrCreate call');
        self::assertSame(2, $lock->unlockCount, 'unlock() must be invoked once per getOrCreate call');
    }

    #[Test]
    public function validator_pool_release_lock_invoked_on_clear(): void
    {
        $lock = $this->createCountingLock();
        $pool = ValidatorPool::forCoroutineRuntime($lock);

        $pool->clear();

        self::assertSame(1, $lock->lockCount);
        self::assertSame(1, $lock->unlockCount);
    }

    #[Test]
    public function validator_pool_acquire_lock_does_not_re_check_method_existence(): void
    {
        // Anti-mutation regression for O-034: the redundant
        // `&& method_exists(...)` guard that combined the null-check with
        // a method-existence check on every hot-path call must stay gone.
        // The constructor owns the lock contract; the hot path delegates to
        // an `assert()` purely for static-analysis narrowing, which PHP
        // removes entirely under `zend.assertions=-1` in production.
        $source = file_get_contents(self::SOURCE_DIRECTORY . '/ValidatorPool.php');

        self::assertNotFalse($source, 'ValidatorPool.php source must be readable');

        self::assertStringNotContainsString(
            '&& method_exists($this->lock',
            $source,
            'ValidatorPool hot path must not gate lock() on a redundant method_exists guard; '
            . 'the constructor owns validation (O-034)',
        );
    }

    #[Test]
    public function readme_long_running_processes_section_enumerates_unsafe_classes(): void
    {
        $readme = file_get_contents(self::README_PATH);

        self::assertNotFalse($readme, 'README.md must be readable from the test suite');

        $sectionStart = strpos($readme, '### Long-Running Processes');
        self::assertNotFalse(
            $sectionStart,
            'README must keep the existing "### Long-Running Processes" section',
        );

        $nextSection = strpos($readme, "\n## ", $sectionStart);
        $nextSubsection = strpos($readme, "\n### ", $sectionStart + 1);

        if (false === $nextSection) {
            $sectionEnd = false === $nextSubsection ? strlen($readme) : $nextSubsection;
        } else {
            $sectionEnd = false === $nextSubsection ? $nextSection : min($nextSection, $nextSubsection);
        }

        $section = substr($readme, $sectionStart, $sectionEnd - $sectionStart);

        foreach (
            [
                'ValidatorPool',
                'LibxmlSecuredContext',
                'PregExecutor',
                self::NOT_THREAD_SAFE_MARKER,
                'forCoroutineRuntime',
                'O-004',
                'O-006',
                'S-011',
                'O-007',
                'S-020',
            ] as $requiredToken
        ) {
            self::assertTrue(
                str_contains($section, $requiredToken),
                sprintf(
                    'README "Long-Running Processes" section must enumerate the concurrency contract token "%s".',
                    $requiredToken,
                ),
            );
        }

        // Sanity bound: the section grew past its baseline ~40 lines to carry
        // the unsafe-class table and the three scenario paragraphs. Guard
        // against accidental truncation during README restructuring.
        self::assertGreaterThan(
            1_500,
            strlen($section),
            sprintf(
                'README "Long-Running Processes" section must carry the unsafe-class table and the three scenario '
                . 'paragraphs (O-004, O-006/S-011, O-007/S-020); got %d bytes.%s',
                strlen($section),
                str_repeat("\n", 1),
            ),
        );
    }

    private function createCountingLock(): object
    {
        return new class {
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
        };
    }
}
