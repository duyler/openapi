<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use ReflectionProperty;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * SEC-03: external file:// $ref references loaded through the builder
 * must be confined to a real directory by default. The builder
 * auto-derives allowedRoot from the spec file's realpath dirname so
 * that a trusted spec cannot read arbitrary files such as
 * /etc/passwd or composer.json via a crafted $ref.
 *
 * @internal
 */
final class ExternalRefAllowedRootTest extends TestCase
{
    private string $rootDir;

    private string $outsideDir;

    protected function setUp(): void
    {
        $base = 'extref_sec03_' . uniqid('', true);
        $this->rootDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $base;
        $this->outsideDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $base . '_outside';

        mkdir($this->rootDir . '/components', 0700, true);
        mkdir($this->outsideDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
        $this->removeDirectory($this->outsideDir);
    }

    /**
     * Default behaviour: fromYamlFile auto-derives allowedRoot from
     * dirname(realpath($path)). An external $ref inside the spec
     * directory resolves; an external $ref pointing outside is rejected
     * with ExternalRefSecurityException (surfaced by RefResolver as
     * UnresolvableRefException with the original exception as previous).
     */
    #[Test]
    public function from_yaml_file_sets_default_allowed_root(): void
    {
        $this->writeFile(
            $this->rootDir . '/components/user.yaml',
            <<<'YAML'
UserSchema:
  type: object
  required: [id]
  properties:
    id:
      type: integer
YAML,
        );

        $this->writeFile(
            $this->outsideDir . '/secret.yaml',
            <<<'YAML'
SecretSchema:
  type: object
  properties:
    token:
      type: string
YAML,
        );

        $specPath = $this->rootDir . '/openapi.yaml';
        $this->writeFile($specPath, sprintf(
            <<<'YAML'
openapi: 3.2.0
info:
  title: SEC-03 default root
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      $ref: '%s/components/user.yaml#/UserSchema'
    Secret:
      $ref: '%s/secret.yaml#/SecretSchema'
YAML,
            $this->rootDir,
            $this->outsideDir,
        ));

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($specPath)
            ->build();

        $validator->validateSchema(['id' => 1], '#/components/schemas/User');

        $caught = $this->captureUnresolvable(
            static fn() => $validator->validateSchema([], '#/components/schemas/Secret'),
        );

        $this->assertNotNull($caught, 'Outside-root external $ref must raise UnresolvableRefException.');
        $this->assertInstanceOf(
            ExternalRefSecurityException::class,
            $caught->getPrevious(),
            'UnresolvableRefException must wrap the original ExternalRefSecurityException.',
        );
    }

    /**
     * withExternalRefAllowedRoot explicitly overrides the auto-derived
     * default. The user's choice wins; the spec directory is no longer
     * the boundary.
     */
    #[Test]
    public function with_external_ref_allowed_root_overrides_default(): void
    {
        $sharedRoot = sys_get_temp_dir() . '/extref_sec03_shared_' . uniqid('', true);
        mkdir($sharedRoot, 0700, true);

        $this->writeFile(
            $sharedRoot . '/user.yaml',
            <<<'YAML'
UserSchema:
  type: object
  required: [id]
  properties:
    id:
      type: integer
YAML,
        );

        $this->writeFile(
            $this->outsideDir . '/secret.yaml',
            <<<'YAML'
SecretSchema:
  type: object
  properties:
    token:
      type: string
YAML,
        );

        $specPath = $this->rootDir . '/openapi.yaml';
        $this->writeFile($specPath, sprintf(
            <<<'YAML'
openapi: 3.2.0
info:
  title: SEC-03 override
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      $ref: '%s/user.yaml#/UserSchema'
    Secret:
      $ref: '%s/secret.yaml#/SecretSchema'
YAML,
            $sharedRoot,
            $this->outsideDir,
        ));

        try {
            $validator = OpenApiValidatorBuilder::create()
                ->fromYamlFile($specPath)
                ->withExternalRefAllowedRoot($sharedRoot)
                ->build();

            $validator->validateSchema(['id' => 7], '#/components/schemas/User');

            $caught = $this->captureUnresolvable(
                static fn() => $validator->validateSchema([], '#/components/schemas/Secret'),
            );

            $this->assertNotNull(
                $caught,
                'Outside the explicitly chosen allowedRoot must still be rejected.',
            );
            $this->assertInstanceOf(
                ExternalRefSecurityException::class,
                $caught->getPrevious(),
            );
        } finally {
            $this->removeDirectory($sharedRoot);
        }
    }

    /**
     * withExternalRefAllowedRoot must fail fast with a BuilderException
     * when the supplied directory does not exist on disk, instead of
     * silently allowing every path (the legacy null behaviour).
     */
    #[Test]
    public function with_external_ref_allowed_root_throws_when_path_missing(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('External ref allowed root does not exist');

        OpenApiValidatorBuilder::create()
            ->fromYamlString("openapi: 3.2.0\ninfo:\n  title: x\n  version: '1'\npaths: {}\n")
            ->withExternalRefAllowedRoot('/nonexistent/sec03/path-' . uniqid('', true));
    }

    /**
     * fromYamlFile must throw BuilderException at call time (not at
     * build()) when the spec file does not exist. This surfaces the
     * realpath failure before the auto-derived allowedRoot would be
     * computed from a non-existent path.
     */
    #[Test]
    public function from_yaml_file_throws_when_spec_missing(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec file does not exist');

        OpenApiValidatorBuilder::create()
            ->fromYamlFile('/nonexistent/sec03/spec-' . uniqid('', true) . '.yaml');
    }

    /**
     * BC: directly constructing RefResolver without a builder keeps
     * the legacy behaviour where allowedRoot is null and the
     * path-traversal check is disabled. This is the documented escape
     * hatch and must remain working. Direct usage is dangerous and
     * discouraged for trusted specs.
     */
    #[Test]
    public function explicit_null_allowed_root_disables_check_for_direct_resolver_usage(): void
    {
        $resolver = new RefResolver();

        $reflected = new ReflectionProperty(RefResolver::class, 'builtinFileResolver');

        $builtin = $reflected->getValue($resolver);

        $this->assertInstanceOf(FileExternalRefResolver::class, $builtin);

        $allowedRootReflection = new ReflectionProperty(FileExternalRefResolver::class, 'allowedRoot');

        /** @var FileExternalRefResolver $builtin */
        $this->assertNull(
            $allowedRootReflection->getValue($builtin),
            'Direct new RefResolver() must default to null allowedRoot for backward compatibility.',
        );
    }

    /**
     * BC: RefResolver accepts an explicit FileExternalRefResolver and
     * uses it as the builtin resolver. The builder relies on this
     * constructor parameter to inject allowedRoot.
     */
    #[Test]
    public function ref_resolver_accepts_injected_builtin_file_resolver(): void
    {
        $injected = new FileExternalRefResolver(allowedRoot: '/tmp');

        $resolver = new RefResolver(builtinFileResolver: $injected);

        $reflected = new ReflectionProperty(RefResolver::class, 'builtinFileResolver');

        $this->assertSame($injected, $reflected->getValue($resolver));
    }

    private function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    private function captureUnresolvable(callable $action): ?UnresolvableRefException
    {
        try {
            $action();
        } catch (UnresolvableRefException $e) {
            return $e;
        } catch (Throwable $e) {
            $this->fail(sprintf(
                'Expected UnresolvableRefException, got %s: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        return null;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        /** @var list<string> $entries */
        $entries = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($entries as $entry) {
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
