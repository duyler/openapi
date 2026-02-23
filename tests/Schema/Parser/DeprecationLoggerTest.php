<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DeprecationLoggerTest extends TestCase
{
    #[Test]
    public function logs_deprecation_warning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('allowEmptyValue'));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $deprecationLogger->warn('allowEmptyValue', 'Parameter Object', '3.2.0');
    }

    #[Test]
    public function does_not_log_when_disabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $deprecationLogger = new DeprecationLogger($logger, false);
        $deprecationLogger->warn('allowEmptyValue', 'Parameter Object', '3.2.0');
    }

    #[Test]
    public function warning_includes_alternative(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains("Use 'nodeType: \"attribute\"' instead"));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $deprecationLogger->warn(
            'attribute',
            'XML Object',
            '3.2.0',
            'nodeType: "attribute"',
        );
    }

    #[Test]
    public function warning_without_alternative(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd(
                $this->stringContains('allowEmptyValue'),
                $this->logicalNot($this->stringContains('Use')),
            ));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $deprecationLogger->warn('allowEmptyValue', 'Parameter Object', '3.2.0');
    }

    #[Test]
    public function uses_null_logger_by_default(): void
    {
        $deprecationLogger = new DeprecationLogger();

        $this->expectNotToPerformAssertions();
        $deprecationLogger->warn('allowEmptyValue', 'Parameter Object', '3.2.0');
    }

    #[Test]
    public function warning_contains_all_info(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd(
                $this->stringContains("'example'"),
                $this->stringContains('Schema Object'),
                $this->stringContains('3.2.0'),
                $this->stringContains("'examples in MediaType Object' instead"),
            ));

        $deprecationLogger = new DeprecationLogger($logger, true);
        $deprecationLogger->warn(
            'example',
            'Schema Object',
            '3.2.0',
            'examples in MediaType Object',
        );
    }

    #[Test]
    public function multiple_warnings_are_logged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('warning');

        $deprecationLogger = new DeprecationLogger($logger, true);
        $deprecationLogger->warn('allowEmptyValue', 'Parameter Object', '3.2.0');
        $deprecationLogger->warn('example', 'Schema Object', '3.2.0', 'examples in MediaType Object');
        $deprecationLogger->warn('wrapped', 'XML Object', '3.2.0');
    }
}
