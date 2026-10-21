# Duyler OpenAPI Validator

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=duyler_openapi&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=duyler_openapi)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=duyler_openapi&metric=coverage)](https://sonarcloud.io/summary/new_code?id=duyler_openapi)
[![type-coverage](https://shepherd.dev/github/duyler/openapi/coverage.svg)](https://shepherd.dev/github/duyler/openapi)
[![psalm-level](https://shepherd.dev/github/duyler/openapi/level.svg)](https://shepherd.dev/github/duyler/openapi)
![PHP Version](https://img.shields.io/packagist/dependency-v/duyler/openapi/php?version=dev-main)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/duyler/openapi)

OpenAPI 3.2 validator for PHP 8.4+

## Features

- **OpenAPI 3.2 Support** - JSON Schema draft 2020-12 validation with known limitations (see Limitations)
- **JSON Schema Validation** - Full JSON Schema draft 2020-12 validation with 30 validators
- **PSR-7 Integration** - PSR-7 HTTP message validation (works with any PSR-7 implementation)
- **Request Validation** - Validate path parameters, query parameters, headers, cookies, and request body
- **Response Validation** - Validate status codes, headers, and response bodies
- **Multiple Content Types** - Support for JSON, form-data, multipart, text, and XML
- **Built-in Format Validators** - 26 built-in validators (email, UUID, date-time, URI, IPv4/IPv6, int32, int64, iri, uri-template, regex, etc.)
- **Custom Format Validators** - Easily register custom format validators
- **Discriminator Support** - Full support for polymorphic schemas with discriminators
- **Type Coercion** - Optional automatic type conversion
- **PSR-6 Caching** - Cache parsed OpenAPI documents for better performance
- **PSR-14 Events** - Subscribe to validation lifecycle events
- **Error Formatting** - Multiple error formatters (simple, detailed, JSON)
- **Webhooks Support** - Validate incoming webhook requests
- **Streaming Validation** - Validate NDJSON, SSE, and JSON Text Sequences responses
- **Schema Registry** - Manage multiple schema versions

## Installation

```bash
composer require duyler/openapi
```

## Quick Start

### Basic Usage

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

// Validate request
$operation = $validator->validateRequest($request);

// Validate response
$validator->validateResponse($response, $operation);
```

### Using the Validator Interface

The builder returns an `OpenApiValidatorInterface` instance. Use this interface for type-hinting in your services:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;

class UserService
{
    public function __construct(
        private readonly OpenApiValidatorInterface $validator,
    ) {}

    public function handleRequest(ServerRequestInterface $request): void
    {
        $operation = $this->validator->validateRequest($request);
        // $operation->path             matched template path, e.g. "/users/{id}"
        // $operation->method           matched HTTP method
        // $operation->operationId      operationId from the spec (nullable)
        // $operation->pathParameters   resolved values, e.g. ['id' => '42']
        // $operation->schemaOperation  Schema\Model\Operation reference (nullable)
        $userId = $operation->pathParameters['id'] ?? null;
        // ...
    }
}
```

The returned `Operation` is a `final readonly` value object implementing `Stringable`: `(string) $operation` yields the template form, e.g. `'GET /users/{id}'` (the path is the route template, not the concrete request URI), and `Operation::countPlaceholders(): int` returns the number of `{...}` placeholders.

The interface exposes the following methods:

| Method | Description |
|--------|-------------|
| `validateRequest(ServerRequestInterface $request): Operation` | Validate and return matched operation |
| `validateResponse(ResponseInterface $response, Operation $operation): void` | Validate response against operation |
| `validateSchema(mixed $data, string $schemaRef): void` | Validate data against a schema reference |
| `getFormattedErrors(ValidationException $e): string` | Format validation errors as string (**deprecated** in favor of `ErrorFormatterInterface::formatException()`, removed in 2.0) |
| `validateWebhook(ServerRequestInterface $request, string $name): Operation` | Validate webhook request |
| `validateCallback(ServerRequestInterface $request, string $name): Operation` | Validate callback request |
| `getDocument(): OpenApiDocument` | Returns the loaded OpenAPI document for introspection or routing maps (available after `build()`) |
| `resolveLink(string $linkName, array $responseData): ResolvedLink` | Resolve link parameters from response data (response body only) |
| `resolveLinkWithContext(string $linkName, LinkContext $context): ResolvedLink` | Resolve link parameters with full Runtime Expression support |
| `reset(): void` | Reset validator state for reuse |

`getDocument()` returns an immutable `OpenApiDocument` value object implementing `JsonSerializable` (use `json_encode()` for the canonical JSON). Its top-level fields mirror the OpenAPI 3.2 document structure (`info`, `servers`, `paths`, `webhooks`, `components`, `security`, `tags`, `externalDocs`), making it useful for routing maps and `SchemaRegistry` registration.

## Usage

### Loading OpenAPI Specifications

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

// From YAML file
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

// From JSON file
$validator = OpenApiValidatorBuilder::create()
    ->fromJsonFile('openapi.json')
    ->build();

// From YAML string
$yaml = file_get_contents('openapi.yaml');
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlString($yaml)
    ->build();

// From JSON string
$json = file_get_contents('openapi.json');
$validator = OpenApiValidatorBuilder::create()
    ->fromJsonString($json)
    ->build();
```

#### YAML Anchor and Alias Caps

Before parsing, the validator scans the raw YAML and enforces caps on anchors (`&name`, max 100), aliases (`*name`, max 1000), and alias chain depth (max 10). This blocks "billion laughs" expansion bombs before the YAML parser materialises them; exceeding any cap throws `SpecTooLargeException`. The scanner is conservative and may count a literal `&Word` sequence inside a double-quoted string as an anchor; treat such specs as trusted or pre-process them before parsing.

### External `$ref` Resolution

The validator supports external `$ref` references for `file://` URIs and relative-path refs. The builtin resolver loads the referenced YAML/JSON file, follows an optional JSON Pointer fragment (e.g. `components/user.yaml#/UserSchema`), and returns the referenced schema:

```yaml
# openapi.yaml
components:
  schemas:
    User:
      $ref: 'components/user.yaml#/UserSchema'
```

Only `file://` URIs and scheme-less relative paths are allowed; every other scheme — `http://`, `data://`, `phar://`, or any other PHP stream wrapper — is rejected with `ExternalRefSecurityException`, surfaced as `UnresolvableRefException`. To enable other schemes, inject a custom `ExternalRefResolverInterface` implementation:

```php
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Schema\ExternalRefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;

final class MyHttpExternalRefResolver implements ExternalRefResolverInterface
{
    public function resolve(string $ref): Schema
    {
        // fetch $ref over HTTP, return Schema
    }
}

$refResolver = new RefResolver(new MyHttpExternalRefResolver());
```

To defend against `../../../etc/passwd` style path traversal and symlink escapes, the builtin resolver accepts an optional `allowedRoot`: both the requested path and the root are resolved via `realpath()`, and any reference outside the root is refused:

```php
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;

$resolver = new FileExternalRefResolver(allowedRoot: '/var/specs');
```

When the spec is loaded with `fromYamlFile()` or `fromJsonFile()`, the builder automatically derives `allowedRoot` from the spec file's directory. Override it with `withExternalRefAllowedRoot()` when refs must reach outside the spec directory (the path must exist, otherwise the builder throws `BuilderException`):

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('/var/specs/openapi.yaml')
    ->withExternalRefAllowedRoot('/var/shared-components')
    ->build();
```

Specs loaded from a string (`fromYamlString()` / `fromJsonString()`) have no directory to derive a root from, so they **fail closed** at `build()` time when the spec contains an external `$ref` and no explicit root was set. External ref files are read with a default size cap of 10 MB (`withExternalRefMaxBytes()`); larger files throw `ExternalRefTooLargeException`, and non-regular files (device files, FIFOs, sockets) are rejected with `ExternalRefSecurityException`.

### PSR-7 Integration

The validator works with any PSR-7 implementation. The examples in this README use `nyholm/psr7` (installed as a dev dependency); substitute your preferred implementation (Guzzle PSR-7, Laminas Diactoros) in production:

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$factory = new Psr17Factory();
$request = $factory->createServerRequest('POST', '/users')
    ->withHeader('Content-Type', 'application/json')
    ->withBody($factory->createStream('{"name": "John"}'));

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$operation = $validator->validateRequest($request);
// $operation contains the matched path and method
```

### Webhooks

Validate webhook requests using the builder API:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$operation = $validator->validateWebhook($request, 'payment.webhook');
```

### Callbacks

Validate callback requests using the builder API:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$operation = $validator->validateCallback($request, 'myCallback');
```

> **Strict fail-closed default**: callback runtime expressions like `{$request.body#/callback_url}` reference the original triggering request body and cannot be resolved by the validator. The builder therefore fails closed by default: any callback expression that contains a runtime template throws `UnresolvableCallbackPathException` instead of being treated as a wildcard that accepts any URL. This prevents attacker-controlled runtime templates from bypassing path validation.

To opt back into the legacy wildcard behaviour, call `disableStrictCallbackRuntimeTemplate()`:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->disableStrictCallbackRuntimeTemplate()
    ->build();
```

> **SECURITY WARNING**: `disableStrictCallbackRuntimeTemplate()` disables the protection against SSRF via attacker-controlled callback URLs: declared security checks still pass against an arbitrary URL when the runtime template is unresolvable. Use it only when callback URLs are validated at the application level (an allowlist of permitted outbound hosts, signed callback URLs, or destination validation before any outbound request is issued).

The previous opt-in method `enableStrictCallbackRuntimeTemplate()` is retained as a deprecated no-op for backward compatibility: strict behaviour is now the default. The method will be removed in 2.0.

### Link Resolution

Resolve OpenAPI Link parameters from response data. Both methods return a `ResolvedLink` DTO exposing resolved `parameters`, `requestBody`, and the optional `server` override declared by the link.

```php
use Duyler\OpenApi\Validator\Link\LinkContext;

// Simple resolution (response body only)
$result = $validator->resolveLink('GetUserById', ['id' => 42, 'name' => 'John']);
$result->parameters;   // array<string, mixed>
$result->requestBody;  // mixed
$result->server;       // Server|null

// Full resolution with Runtime Expression support
$context = new LinkContext(
    body: ['id' => 42, 'name' => 'John'],
    headers: ['X-Request-Id' => 'abc123'],
    queryParams: ['page' => 1],
    url: 'https://api.example.com/users/42',
    method: 'GET',
    statusCode: 200,
    pathParams: ['userId' => 42],
    requestHeaders: ['X-Request-Id' => 'req-789'],
    requestBody: ['extra' => 'payload'],
);
$result = $validator->resolveLinkWithContext('GetUserById', $context);
```

`resolveLink()` populates only the response body context, so it can resolve `$response.body` expressions. Use `resolveLinkWithContext()` to supply the full request and response state and unlock all OpenAPI 3.2 §6.19.2 runtime expressions:

| Expression | Resolves from LinkContext |
|------------|---------------------------|
| `$url` | `url` |
| `$method` | `method` |
| `$statusCode` | `statusCode` |
| `$request.path.{name}` | `pathParams[{name}]` |
| `$request.query.{name}` | `queryParams[{name}]` |
| `$request.header.{name}` | `requestHeaders[{name}]` (case-insensitive, RFC 9110) |
| `$request.body` | `requestBody` (whole value) |
| `$request.body#/{pointer}` | `requestBody` navigated by JSON Pointer |
| `$response.body` | `body` (whole value) |
| `$response.body#/{pointer}` | `body` navigated by JSON Pointer |
| `$response.header` | `headers` (whole map) |
| `$response.header[.{name}|#/{name}]` | `headers` by name or JSON Pointer |
| `$response.query` | `queryParams` (whole map) |
| `$response.query[.{name}|#/{name}]` | `queryParams` by name or JSON Pointer |

Unsupported expressions are returned as the literal string so callers can distinguish them from values that legitimately resolve to null.

## Advanced Usage

### Custom Format Validators

Register custom format validators for domain-specific validation:

```php
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;

// Create a custom validator
class PhoneNumberValidator implements FormatValidatorInterface
{
    public function validate(mixed $data): void
    {
        if (!is_string($data) || !preg_match('/^\+?[1-9]\d{1,14}$/', $data)) {
            throw new InvalidFormatException(
                'phone',
                $data,
                'Value must be a valid E.164 phone number'
            );
        }
    }
}

// Register with the builder
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withFormat('string', 'phone', new PhoneNumberValidator())
    ->build();
```

Formats registered via `withFormat()` override built-in validators whenever both target the same `(type, format)` pair — the same mechanism is used to replace built-ins (see [Overriding Built-in Validators](#overriding-built-in-validators)).

### Type Coercion

Enable automatic type conversion for query parameters and request body:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableCoercion()  // Convert string "123" to integer 123
    ->build();
```

Coercion is strict by default and applies to request parameters (query, path, headers, cookies) and to JSON body values alike: strings are converted to their declared types, and non-string scalars produced by `json_decode(..., true)` are normalised too (`bool true` → `int 1` for `type: integer`, `int 42` → `"42"` for `type: string`). Union types such as `type: [integer, string]` try each branch in order and use the first successful coercion. `disableStrictCoercion()` restores the legacy lax casting behaviour; overflow and precision-loss guards remain active.

### Discriminator Validation

Validate polymorphic schemas with discriminators:

```php
$yaml = <<<YAML
openapi: 3.2.0
components:
  schemas:
    Pet:
      type: object
      required: [petType]
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
          dog: '#/components/schemas/Dog'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required: [petType, name]
      properties:
        petType: { type: string, enum: [cat] }
        name: { type: string }
    Dog:
      type: object
      required: [petType, name, breed]
      properties:
        petType: { type: string, enum: [dog] }
        name: { type: string }
        breed: { type: string }
YAML;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlString($yaml)
    ->build();

// Validates against Cat schema
$data = ['petType' => 'cat', 'name' => 'Fluffy'];
$validator->validateSchema($data, '#/components/schemas/Pet');
```

Candidate enumeration follows JSON Schema 2020-12: when a schema declares more than one composition keyword (`oneOf`, `anyOf`, `allOf`), candidates are collected from **all** of them, and a non-matching nested candidate does not abort the search — remaining candidates are tried before the validator gives up. The OpenAPI `defaultMapping` keyword is honoured as the final fallback for any unresolved discriminator value, regardless of whether `propertyName` is set.

### Event-Driven Validation

Subscribe to validation lifecycle events via PSR-14:

```php
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$dispatcher = new ArrayDispatcher([
    ValidationStartedEvent::class => [
        function (ValidationStartedEvent $event) {
            printf("Validation started: %s %s\n", $event->method, $event->path);
        },
    ],
    ValidationErrorEvent::class => [
        function (ValidationErrorEvent $event) {
            error_log(sprintf(
                "Validation failed for %s %s: %s",
                $event->method,
                $event->path,
                $event->exception->getMessage()
            ));
        },
    ],
]);

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEventDispatcher($dispatcher)
    ->build();
```

Available events:

| Event | Description |
|-------|-------------|
| `ValidationStartedEvent` | Dispatched before validation begins |
| `ValidationFinishedEvent` | Dispatched after validation completes |
| `ValidationErrorEvent` | Dispatched when validation fails |
| `ValidationWarningEvent` | Dispatched for non-fatal validation warnings |

Listeners can also be registered after construction. `ArrayDispatcher` is immutable: `listen()` returns a **new** instance, so reassign the result — chaining without reassignment silently discards the added listeners:

```php
$dispatcher = $dispatcher
    ->listen(ValidationStartedEvent::class, $myStartedListener)
    ->listen(ValidationErrorEvent::class, $myErrorListener);
```

### Schema Registry

Manage multiple API versions:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Registry\SchemaRegistry;

$validatorV1 = OpenApiValidatorBuilder::create()
    ->fromYamlFile('api-v1.yaml')
    ->build();
$validatorV2 = OpenApiValidatorBuilder::create()
    ->fromYamlFile('api-v2.yaml')
    ->build();

// The registry is immutable: register() returns a new instance and throws
// SchemaAlreadyRegisteredException on a duplicate name+version pair.
// registerOrReplace() is the explicit-overwrite counterpart (e.g. hot reload).
$registry = (new SchemaRegistry())
    ->register('api', '1.0.0', $validatorV1->getDocument())
    ->register('api', '2.0.0', $validatorV2->getDocument());

// get() returns null for a missing name or version; getOrFail() throws
// VersionNotFoundException. A call without a version returns the latest
// version (sorted by semver).
$schema = $registry->get('api', '1.0.0');
$latest = $registry->get('api');

// Introspection: getVersions(), has(), getNames(), and the count* helpers.
$versions = $registry->getVersions('api'); // ['1.0.0', '2.0.0']
```

### Validator Pool

The pool reuses validator instances through an LRU cache with a default capacity of 128 entries; the least recently used entry is evicted when the pool is full. It is safe to share in prefork models (PHP-FPM, RoadRunner, FrankenPHP non-threaded). Under Swoole coroutines or threaded FrankenPHP workers, concurrent `getOrCreate()` calls race on the check-then-act sequence: pass a lock (any object with `lock()` and `unlock()` methods, such as `Swoole\Lock`) or give each worker its own pool. The factory callable passed to `getOrCreate()` must be non-blocking (no I/O) and non-recursive — the lock is held for its entire duration, so suspending or recursing inside it deadlocks.

```php
use Duyler\OpenApi\Validator\ValidatorPool;

$pool = new ValidatorPool();            // default: 128 entries
$pool = new ValidatorPool(maxSize: 64); // custom capacity

// Swoole / threaded runtimes: pass a lock to serialize access
$pool = new ValidatorPool(maxSize: 128, lock: new \Swoole\Lock());

// Or use the named constructor to make the concurrency contract explicit
$pool = ValidatorPool::forCoroutineRuntime(new \Swoole\Lock(), maxSize: 128);

// Validators are automatically reused and evicted when capacity is exceeded
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withValidatorPool($pool)
    ->build();
```

## Configuration Options

### Builder Methods

| Method | Description | Default |
|--------|-------------|---------|
| `create()` | Static factory entry point; returns a fresh builder instance. | - |
| `build()` | Terminal method — materialises the spec, wires dependencies, and returns an `OpenApiValidatorInterface`. Call exactly once per builder instance. | - |
| `fromYamlFile(string $path)` | Load spec from YAML file | - |
| `fromJsonFile(string $path)` | Load spec from JSON file | - |
| `fromYamlString(string $content)` | Load spec from YAML string | - |
| `fromJsonString(string $content)` | Load spec from JSON string | - |
| `withCache(SchemaCache $cache)` | Enable PSR-6 caching | `null` |
| `withEventDispatcher(EventDispatcherInterface $dispatcher)` | Set PSR-14 event dispatcher | `null` |
| `withErrorFormatter(ErrorFormatterInterface $formatter)` | Set error formatter | `SimpleFormatter` |
| `withDetailedErrors(bool $includeSensitive)` | Use `DetailedFormatter`; `true` includes sensitive values in output | `DetailedFormatter` (sensitive values omitted) |
| `withSecurityVerboseLogging(LoggerInterface $logger)` | Debug-level logging of security validation details and external ref paths | `null` |
| `withFormat(string $type, string $format, FormatValidatorInterface $validator)` | Register custom format | - |
| `withValidatorPool(ValidatorPool $pool)` | Set custom validator pool | `new ValidatorPool()` |
| `withLogger(LoggerInterface $logger)` | Set PSR-3 logger | `null` |
| `withEmptyArrayStrategy(EmptyArrayStrategy $strategy)` | Set empty array validation strategy | `AllowBoth` |
| `enableCoercion()` | Enable type coercion | `false` |
| `disableStrictCoercion()` | Restore legacy lax type coercion (loose boolean/integer/number casting) | `true` (strict default) |
| `enableNullableAsType()` | Enable nullable validation | `true` |
| `disableNullableAsType()` | Disable nullable validation | `false` |
| `enableSecurityValidation()` | Enable security scheme validation for requests | `false` |
| `enableStrictFormats()` | Reject unknown format values instead of skipping | `false` |
| `enableReportDeprecated()` | Log deprecated schema elements via PSR-3 logger | `true` |
| `enableServerPathResolution()` | Strip server base path from request path before matching | `false` |
| `enableStrictCallbackRuntimeTemplate()` | Deprecated no-op: strict mode is now the default. Will be removed in 2.0 | `true` (effective) |
| `disableStrictCallbackRuntimeTemplate()` | Opt out of strict callback runtime template resolution. **SECURITY WARNING**: unresolvable callback expressions like `{$request.body#/callback_url}` are treated as wildcards that accept any URL, enabling SSRF via attacker-controlled callback URLs. Use only when callback URLs are validated at the application level. | `false` (opt-in legacy mode) |
| `withExternalRefAllowedRoot(string $path)` | Directory that external `file://` `$ref` references must stay inside | `null` (auto-derived from spec path for file-loaded specs) |
| `withExternalRefMaxBytes(int $bytes)` | Set max external ref file size | `10485760` (10 MB) |
| `withMaxSpecSize(int $bytes)` | Max allowed spec payload size in bytes (YAML and JSON) | `1048576` (1 MB) |
| `withMaxSpecDepth(int $depth)` | Max allowed spec nesting depth | `100` |
| `withMaxJsonBodySize(int $bytes)` | Max size for non-multipart request and response bodies (JSON, XML, text); larger bodies are rejected before being fully materialised | `10485760` (10 MB) |
| `withMaxMultipartBodySize(int $bytes)` | Max size for multipart request and response bodies (typically larger uploads) | `52428800` (50 MB) |
| `withMaxRegexBacktracks(int $maxBacktracks)` | Defensive `pcre.backtrack_limit` applied to schema `pattern` evaluation (ReDoS defence) | `10_000` |
| `withMaxStreamingRecords(int $max)` | Max number of records accepted from a single streaming response | `100000` |
| `enableStrictStreaming()` | Malformed stream records throw `MalformedStreamRecordException` instead of being logged and skipped | `false` |
| `disableStrictStreaming()` | Restore default fail-open streaming behaviour (malformed records logged and skipped) | `false` |

Deprecated reporting is enabled by default; without a PSR-3 logger the warnings go to `NullLogger` and produce no output. To suppress them, simply omit the logger (there is no `disableReportDeprecated()` method).

### EmptyArrayStrategy

When an OpenAPI schema defines a property as `type: array` and the value is an empty array `[]`, JSON does not distinguish between an empty array and an empty object. This strategy controls how the validator treats empty arrays:

| Strategy | Behavior |
|----------|----------|
| `AllowBoth` (default) | Empty arrays pass validation for both `array` and `object` types |
| `PreferArray` | Empty arrays are treated as arrays, not objects |
| `PreferObject` | Empty arrays are treated as objects, not arrays |
| `Reject` | Empty arrays are rejected for both `array` and `object` types |

```php
use Duyler\OpenApi\Validator\EmptyArrayStrategy;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
    ->build();
```

### Example Configuration

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;

$cachePool = new FilesystemAdapter();
$schemaCache = new SchemaCache($cachePool, 3600);

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withCache($schemaCache)           // Cache parsed specs
    ->withErrorFormatter(new DetailedFormatter())  // Detailed errors
    ->enableCoercion()                  // Auto type conversion
    ->build();
```

## PSR-15 Middleware

> **Note:** The middleware below is an example snippet, not a class shipped with this package. Copy it into your project and adapt it to your framework. The PSR-15 interfaces (`psr/http-server-middleware`) are required by your framework, not by this library.

Wrap the validator in a PSR-15 middleware to validate incoming requests before they reach your handlers. On validation failure, the middleware returns a `400 Bad Request` response with error details.

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly OpenApiValidatorInterface $validator,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $operation = $this->validator->validateRequest($request);
        } catch (ValidationException $e) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode([
                    'error' => 'Validation failed',
                    'details' => array_map(fn ($error) => [
                        'path' => $error->dataPath(),
                        'message' => $error->message(),
                    ], $e->getErrors()),
                ], JSON_PRETTY_PRINT),
            );
        } catch (Throwable $e) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['error' => 'Internal validation error'], JSON_PRETTY_PRINT),
            );
        }

        return $handler->handle($request->withAttribute('operation', $operation));
    }
}
```

Register the middleware with your framework's middleware pipeline:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$middleware = new ValidationMiddleware($validator);

// Register with any PSR-15 compatible framework or dispatcher
// Example with Mezzio:
// $pipeline->pipe(new ValidationMiddleware($validator));
```

## Supported JSON Schema Keywords

The validator supports the following JSON Schema draft 2020-12 keywords:

### Type Validation
- `type` - String, number, integer, boolean, array, object, null
- `enum` - Enumerated values
- `const` - Constant value
- `nullable` - Allows null values (default: enabled)

```yaml
properties:
  username:
    type: string
    nullable: true  # Allows null values
```

Nullable validation is enabled by default; call `disableNullableAsType()` to treat `nullable: true` as not allowing null values.

### String Validation
- `minLength` / `maxLength` - String length constraints
- `pattern` - Regular expression pattern
- `format` - Format validation (email, uri, uuid, date-time, etc.)

String length is counted in UTF-16 code units: supplementary characters count as 2 (surrogate pairs), matching JavaScript's `String.prototype.length`. This can differ from code-point counting (used by ajv by default) for schemas whose length thresholds fall around supplementary characters.

### Pattern Validation

All regular expressions in schemas are validated at parse time; an invalid pattern throws `InvalidPatternException`. Patterns are honoured on the `pattern`, `patternProperties`, and `propertyNames` keywords. Delimiters are added automatically, so both `^test$` and `/^test$/` work identically — whether the pattern comes from a spec or from a `new Schema(pattern: ...)` value object.

### Numeric Validation
- `minimum` / `maximum` - Range constraints
- `exclusiveMinimum` / `exclusiveMaximum` - Exclusive ranges
- `multipleOf` - Numeric division

`multipleOf` for int64 values (e.g. snowflake IDs up to `PHP_INT_MAX`) works without ext-bcmath via pure-PHP decimal modulus; the bcmath extension is used for the faster path when available.

### Array Validation
- `items` / `prefixItems` - Array item validation
- `minItems` / `maxItems` - Array length constraints
- `uniqueItems` - Unique item requirement
- `contains` / `minContains` / `maxContains` - Item presence validation

`uniqueItems`, `enum`, and `const` use JSON instance equality: `1` and `1.0` are equal, object key order is irrelevant, and `bool` is distinct from `int`. Arrays larger than 100 000 items are rejected for `uniqueItems` instead of being scanned for duplicates.

### Object Validation
- `properties` - Property definitions
- `required` - Required properties
- `additionalProperties` - Additional property rules
- `minProperties` / `maxProperties` - Property count constraints
- `patternProperties` - Pattern-based property validation
- `propertyNames` - Property name validation
- `dependentSchemas` - Conditional schema application

### Composition Keywords
- `allOf` - Must match all schemas
- `anyOf` - Must match at least one schema
- `oneOf` - Must match exactly one schema
- `not` - Must not match schema
- `if` / `then` / `else` - Conditional validation

### Advanced Keywords
- `$ref` - Schema references
- `discriminator` - Polymorphic schemas
- `unevaluatedProperties` / `unevaluatedItems` - Dynamic evaluation

## Error Handling

### Validation Exceptions

All validation errors throw `ValidationException` which contains detailed error information:

```php
use Duyler\OpenApi\Validator\Exception\ValidationException;

try {
    $operation = $validator->validateRequest($request);
} catch (ValidationException $e) {
    // Get array of validation errors
    $errors = $e->getErrors();

    foreach ($errors as $error) {
        printf(
            "Path: %s\nMessage: %s\nKeyword: %s\n\n",
            $error->dataPath(),
            $error->message(),
            $error->keyword()
        );
    }

    // Get formatted errors
    $formatted = $validator->getFormattedErrors($e);
    echo $formatted;
}
```

### Exception Sanitization

Every exception shipped by this package overrides `__toString()`, so `(string) $e` returns only the message — no file paths or stack traces. This makes exception messages safe to render into HTTP responses and logs.

Attacker-influenced values (the `InvalidFormatException` value, security scheme details, external `$ref` targets, parameter names) are stored non-public and exposed only through opt-in getters with a `bool $reveal = false` parameter; the default return is the literal string `'<redacted>'`. For example, `InvalidFormatException::value(reveal: true)` returns the raw value, and `MissingSecurityCredentialsError` exposes `schemeName(reveal: true)`, `schemeType(reveal: true)`, and `location(reveal: true)`:

```php
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;

try {
    $validator->validateSchema(['email' => 'not-an-email'], '#/components/schemas/User');
} catch (ValidationException $e) {
    /** @var InvalidFormatException $formatError */
    $formatError = $e->getErrors()[0];

    echo $formatError->message();           // safe to log or surface
    echo $formatError->value();             // '<redacted>' (default)
    echo $formatError->value(reveal: true); // 'not-an-email' (trusted operator only)
}
```

Error formatters omit raw values unless constructed with `includeSensitiveValues: true`, or the builder is configured with `withDetailedErrors(includeSensitive: true)` (see [Error Formatters](#error-formatters)).

### Validation Error Reference

All errors implement `ValidationErrorInterface` and provide `dataPath()`, `schemaPath()`, `keyword()`, `message()`, `params()`, and `suggestion()` methods.

> Note: The `getType()` method is deprecated in favor of `keyword()` and will be removed in 2.0. Both return the same validation keyword (e.g., `'type'`, `'minLength'`, `'format'`). Use `keyword()` in new code.

#### Type and Value Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `TypeMismatchError` | `type` | Data type doesn't match schema type |
| `EnumError` | `enum` | Value not in allowed enum |
| `ConstError` | `const` | Value doesn't match constant |
| `InvalidDataTypeException` | `invalid` | Invalid data type encountered |

#### Format Validation Errors

`InvalidFormatException` extends `AbstractValidationError` and is thrown by format validators rather than the schema validator.

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `InvalidFormatException` | `format` | Format validation failed (email, URI, etc.) |

#### String Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MinLengthError` | `minLength` | String length below minimum |
| `MaxLengthError` | `maxLength` | String length exceeds maximum |
| `PatternMismatchError` | `pattern` | Regular expression pattern violation |

#### Numeric Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MinimumError` | `minimum` / `exclusiveMinimum` | Value below minimum (inclusive/exclusive) |
| `MaximumError` | `maximum` / `exclusiveMaximum` | Value exceeds maximum (inclusive/exclusive) |
| `MultipleOfKeywordError` | `multipleOf` | Value is not a multiple of the specified number |

> Note: `MinimumError::keyword()` always returns `'minimum'` for both `minimum` and `exclusiveMinimum` violations. Similarly, `MaximumError::keyword()` always returns `'maximum'`. Use `schemaPath()` to distinguish between inclusive (`/minimum`, `/maximum`) and exclusive (`/exclusiveMinimum`, `/exclusiveMaximum`) constraints.

#### Array Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MinItemsError` | `minItems` | Array has fewer items than required |
| `MaxItemsError` | `maxItems` | Array has more items than allowed |
| `DuplicateItemsError` | `uniqueItems` | Array contains duplicate items |
| `ContainsMatchError` | `contains` | Array has no matching items for `contains` |
| `MinContainsError` | `minContains` | Too few items match `contains` |
| `MaxContainsError` | `maxContains` | Too many items match `contains` |

#### Object Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `RequiredError` | `required` | Required property is missing |
| `MinPropertiesError` | `minProperties` | Object has fewer properties than required |
| `MaxPropertiesError` | `maxProperties` | Object has more properties than allowed |
| `AdditionalPropertyError` | `additionalProperties` | Additional property present despite additionalProperties: false |
| `UnevaluatedPropertyError` | `unevaluatedProperties` | Property not allowed and not evaluated by any keyword |
| `ReadOnlyPropertyError` | `readOnly` | Read-only property was sent in a request payload |
| `WriteOnlyPropertyError` | `writeOnly` | Write-only property was returned in a response payload |

#### Composition Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `OneOfError` | `oneOf` | Data matches multiple schemas (should match exactly one) |
| `NotValidationError` | `not` | Data matches the schema forbidden by `not` |
| `DiscriminatorDataError` | `oneOf` | Discriminator validation received non-object data |

#### Discriminator Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `DiscriminatorMismatchException` | `discriminator` | Discriminator type doesn't match expected |
| `InvalidDiscriminatorValueException` | `discriminator` | Discriminator property has wrong type |
| `UnknownDiscriminatorValueException` | `discriminator` | Discriminator value not in mapping |
| `MissingDiscriminatorPropertyException` | `discriminator` | Required discriminator property is missing |

#### Security Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MissingSecurityCredentialsError` | `security` | Required security credentials missing from request |

#### HTTP, Request, and Schema Errors

These exceptions extend `RuntimeException`, `Exception`, or `InvalidArgumentException` directly and do not implement `ValidationErrorInterface`:

| Exception | Description |
|-----------|-------------|
| `BodyTooLargeException` | Request or response body exceeded the configured body-size cap; rejected before full materialisation |
| `MultipartBodyTooLargeException` | Multipart request yielded more than 1000 parts (part-count cap); extends `\RuntimeException` |
| `BuilderException` | Builder precondition failure: spec file unreadable, `withExternalRefAllowedRoot` path does not exist, or other builder-state violation |
| `ExternalRefSecurityException` | External `$ref` violates builtin resolver security policy (non-allowlisted scheme, path traversal outside the allowed root). Surfaced by `RefResolver` as `UnresolvableRefException` |
| `ExternalRefTooLargeException` | External `$ref` file exceeds the configured size limit (default 10 MB); extends `\RuntimeException` |
| `InvalidMultipleOfSchemaException` | Schema declares `multipleOf` ≤ 0 (mathematically unsatisfiable) |
| `InvalidParameterException` | Parameter value is malformed or invalid |
| `InvalidPatternException` | Invalid regex pattern in schema definition |
| `InvalidSchemaException` | Spec parsing failed (malformed OpenAPI document) |
| `InvalidUtf8Exception` | Input is not valid UTF-8 (RFC 8259 §8.1) |
| `MalformedStreamRecordException` | Streaming response record (NDJSON / SSE / JSON Text Sequence) failed to parse AND `enableStrictStreaming()` is on; under default fail-open streaming the record is logged and skipped instead |
| `MissingParameterException` | Required parameter is missing from request |
| `MissingRequestBodyException` | Request body is required but missing or empty |
| `NestedValidationError` | Validation failure nested inside a composition branch whose specific cause could not be narrowed to a single keyword (composition fallback) |
| `OperationNotFoundException` | Request path or method does not match any operation in the specification |
| `PathMismatchException` | Request path doesn't match any operation template |
| `PregRuntimeException` | PCRE runtime failure (backtrack limit, recursion limit, or JIT stack exhaustion) while evaluating a JSON Schema `pattern` |
| `RefResolutionException` | Failed to resolve `$ref` reference |
| `SchemaDepthExceededException` | Maximum schema nesting depth exceeded |
| `SpecTooLargeException` | Spec payload exceeded `maxSpecSize` / `maxSpecDepth`, or YAML anchor/alias caps were tripped. Carries only the metric, actual count, and cap — never the payload |
| `TooManyContainsValidationsError` | `contains` keyword evaluated more matching items than the internal cap (DoS defence on attacker-controlled arrays) |
| `TooManyErrorsError` | Composition validator (`oneOf` / `anyOf` / `allOf`) accumulated more errors than the internal cap (DoS defence on deeply-nested schemas) |
| `TooManyItemsForUniqueCheckError` | `uniqueItems: true` evaluated on an array larger than 100 000 items; guards against quadratic uniqueness scans |
| `TooManyRecordsException` | Streaming response (NDJSON / SSE / JSON Text Sequence) yielded more records than `maxStreamingRecords` (default 100 000) |
| `UndefinedResponseException` | Response status code not defined in spec |
| `UnknownLinkException` | `resolveLink()` / `resolveLinkWithContext()` invoked with a link name not declared under `components/links`; extends `\InvalidArgumentException` |
| `UnknownCallbackException` | `validateCallback()` invoked with a callback name not declared in the spec; extends `\InvalidArgumentException` |
| `UnknownWebhookException` | `validateWebhook()` invoked with a webhook name not declared in the spec; extends `\InvalidArgumentException` |
| `UnresolvableCallbackPathException` | Callback runtime template (e.g. `{$request.body#/callback_url}`) cannot be resolved in strict mode |
| `UnresolvableRefException` | `$ref` cannot be resolved against the spec or allowed external-ref root. `ExternalRefSecurityException` is surfaced through this class |
| `UnsupportedMediaTypeException` | Content-Type not supported by the operation |
| `UnsupportedSecuritySchemeException` | Spec declares a security scheme type this library does not validate (`oauth2`, `openIdConnect`, `http/basic`, `http/digest`, `mutualTLS`, or unknown). Extends `\RuntimeException`, **not** wrapped into `ValidationException` (see [Security Validation](#security-validation)) |
| `VersionNotFoundException` | Requested schema name or version is not registered (thrown by `SchemaRegistry::getOrFail()`) |
| `SchemaAlreadyRegisteredException` | Schema name+version pair is already registered (thrown by `SchemaRegistry::register()`; use `registerOrReplace()` for explicit overwrite) |
| `ServerVariableException` | Server URL template substitution failed: a required `{var}` was missing from the configured `ServerVariableOverride` map, or the request URL did not match any declared server |

### Error Formatters

Choose the appropriate error formatter for your use case:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;    // default
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;  // with suggestions
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;      // for API responses

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withErrorFormatter(new DetailedFormatter())
    ->build();
```

To format a `ValidationException` without holding a reference to the validator, call `ErrorFormatterInterface::formatException()` directly. This is the canonical replacement for the deprecated `OpenApiValidatorInterface::getFormattedErrors()` (removed in 2.0):

```php
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\ValidationException;

$formatter = new SimpleFormatter();

try {
    $operation = $validator->validateRequest($request);
} catch (ValidationException $e) {
    echo $formatter->formatException($e);
}
```

`ErrorFormatterInterface` exposes three methods; `formatException()` is the canonical entry point (the others are useful when you hold individual errors rather than a full exception):

| Method | Signature | Purpose |
|--------|-----------|---------|
| `format` | `(ValidationErrorInterface $error): string` | Render a single validation error |
| `formatMultiple` | `(array $errors): string` | Render a list of `ValidationErrorInterface` instances |
| `formatException` | `(ValidationException $exception): string` | Render every error carried by a `ValidationException` |

By default, `DetailedFormatter` and `JsonFormatter` omit the raw user-supplied value from `InvalidFormatException` errors to prevent accidental disclosure of secrets (passwords, tokens) through error messages into logs and API responses. The value remains accessible via `$exception->value(reveal: true)` for trusted programmatic access (see [Exception Sanitization](#exception-sanitization)). To include the raw value in formatted output (for debugging), construct the formatter with `includeSensitiveValues: true` or use `withDetailedErrors(includeSensitive: true)` on the builder.

## Built-in Format Validators

The following format validators are included:

### String Formats

| Format | Description | Example                                |
|--------|-------------|----------------------------------------|
| `date-time` | ISO 8601 date-time | `2026-01-15T10:30:00Z`                 |
| `date` | ISO 8601 date | `2026-01-15`                           |
| `time` | ISO 8601 time | `10:30:00Z`                            |
| `email` | Email address (RFC 5321 + RFC 6531 SMTPUTF8) | `user@example.com`, `用户@例子.广告`, `user@[127.0.0.1]` |
| `uri` | URI (RFC 3986 generic syntax) | `https://example.com`                  |
| `uuid` | UUID | `550e8400-e29b-41d4-a716-446655440000` |
| `hostname` | Hostname | `example.com`                          |
| `ipv4` | IPv4 address | `192.168.1.1`                          |
| `ipv6` | IPv6 address | `2001:db8::1`                          |
| `byte` | Base64-encoded data | `SGVsbG8gd29ybGQ=`                     |
| `duration` | ISO 8601 duration | `P3Y6M4DT12H30M5S`                     |
| `json-pointer` | JSON Pointer | `/path/to/value`                       |
| `relative-json-pointer` | Relative JSON Pointer | `1/property`                           |
| `binary` | Binary file data hint (pass-through, OAS 3.2 §5.x) | `<file content>` |
| `password` | Password hint (pass-through, OAS 3.2 §5.x) | `secret123!` |
| `idn-email` | Internationalized email (RFC 6531 SMTPUTF8) | `用户@例子.广告` |
| `idn-hostname` | Internationalized hostname (RFC 5890 IDNA2008) | `例え.テスト` |
| `iri` | Internationalized Resource Identifier (RFC 3987) | `http://例え.テスト/path` |
| `iri-reference` | Absolute or relative IRI (RFC 3987) | `/path`, `//host/path`, `?q=1` |
| `uri-reference` | Absolute or relative URI (RFC 3986 §4.1) | `/path`, `//host/path`, `?q=1`, `#frag` |
| `uri-template` | URI Template (RFC 6570, balanced expressions) | `https://api.example.com/users/{userId}` |
| `regex` | Regular expression pattern (ECMA-262 syntax via PCRE) | `^[a-z]+$` |

> The `time` format requires a UTC offset per RFC 3339 §5.6 (`Z`, `+HH:MM`, or `-HH:MM`). Time strings without an offset (e.g., `10:30:00`) are rejected with `InvalidFormatException`.

### Numeric Formats

| Format | Description | Example |
|--------|-------------|---------|
| `float` | Floating-point number | `3.14` |
| `double` | Double-precision number | `3.14159265359` |
| `int32` | Signed 32-bit integer (range `[-2147483648, 2147483647]`) | `42` |
| `int64` | Signed 64-bit integer (range `[PHP_INT_MIN, PHP_INT_MAX]`) | `9223372036854775807` |

### Overriding Built-in Validators

Replace built-in validators with custom implementations:

```php
$customEmailValidator = new class implements FormatValidatorInterface {
    public function validate(mixed $data): void
    {
        // Custom email validation logic
        if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidFormatException('email', $data, 'Invalid email');
        }
    }
};

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withFormat('string', 'email', $customEmailValidator)
    ->build();
```

## Migration from league/openapi-psr7-validator

### Key Differences

| Feature | league/openapi-psr7-validator | duyler/openapi             |
|---------|------------------------------|----------------------------|
| PHP Version | PHP 7.4+ | PHP 8.4+                   |
| OpenAPI Version | 3.0 | 3.0, 3.1, 3.2              |
| JSON Schema | Draft 7 | Draft 2020-12              |
| Builder Pattern | Fluent builder | Fluent builder (immutable) |
| Type Coercion | Enabled by default | Opt-in                     |
| Error Formatting | Basic | Multiple formatters        |

### Migration Examples

> **Warning:** Migration from `league/openapi-psr7-validator` requires rewriting your routing layer. `league` provided `OperationAddress` + `PathParams` utilities; `duyler/openapi` returns a simpler `Operation` DTO that already carries a resolved `pathParameters` map. Additionally, coercion is **opt-in** here (`enableCoercion()`), whereas `league` had it enabled by default — this is a behavioral breaking change for migrants.

#### Before (league/openapi-psr7-validator)

```php
use League\OpenAPIValidation\PSR7\ValidatorBuilder;

$builder = new ValidatorBuilder();
$builder->fromYamlFile('openapi.yaml');
$requestValidator = $builder->getRequestValidator();
$responseValidator = $builder->getResponseValidator();

// Request validation
$requestValidator->validate($request);

// Response validation
$responseValidator->validate($operationAddress, $response);
```

#### After (duyler/openapi)

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableCoercion()
    ->build();

// Request validation - path and method are automatically detected
$operation = $validator->validateRequest($request);

// Response validation
$validator->validateResponse($response, $operation);

// Schema validation
$validator->validateSchema($data, '#/components/schemas/User');
```

## Performance

Reuse a single validator instance across requests: building it (spec parsing and validator construction) is far more expensive than validating. Per-request validation cost is dominated by body size and schema complexity; for production sizing, measure against your own specs, hardware, and PHP version rather than relying on generic figures.

### Caching

Enable PSR-6 caching when the OpenAPI specification does not change between requests. This skips YAML/JSON parsing and schema construction on every build:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$cachePool = new FilesystemAdapter();
$schemaCache = new SchemaCache($cachePool, 3600); // TTL in seconds, default 3600

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withCache($schemaCache)
    ->build();
```

`SchemaCache` wraps any PSR-6 pool with a TTL and derives its keys from the spec content plus the builder settings that affect validation (size/depth caps, external-ref configuration), so a changed spec or configuration automatically produces a new key. The external-`$ref` confinement verdict for string-loaded specs is cached alongside the document under the same pool and TTL; a failing verdict is never cached. Entries can also be managed directly via `get()` / `set()` / `delete()` / `clear()` — useful for cache warming at deploy time or selective invalidation after a hot reload.

### Long-Running Processes

The validator instance is safe to reuse across requests in long-running processes that use the **prefork execution model** (PHP-FPM, RoadRunner, FrankenPHP non-threaded) — no extra configuration needed. Under **Swoole coroutines** or **FrankenPHP threaded workers**, shared mutable state requires care: give the validator pool a lock (`ValidatorPool::forCoroutineRuntime(new \Swoole\Lock())`) or use one pool per coroutine/worker, as described in [Validator Pool](#validator-pool). XML body validation uses libxml global state, and functions like `json_last_error()` and `DateTime::getLastErrors()` are process-global, so these code paths are not coroutine-safe. If the OpenAPI specification changes at runtime, rebuild the validator; old instances are garbage-collected when no longer referenced.

## Streaming Response Validation

The validator supports three streaming response formats. Each item in the stream is validated individually against the schema declared with the `itemSchema` keyword (or `schema` as fallback) in the media type definition:

### Supported Content Types

| Format | Content-Type | Specification |
|--------|-------------|---------------|
| JSON Lines / NDJSON | `application/jsonl` or `application/x-ndjson` | Newline-delimited JSON objects |
| Server-Sent Events | `text/event-stream` | W3C SSE specification |
| JSON Text Sequences | `application/json-seq` | RFC 7464 |

### OpenAPI Specification

```yaml
openapi: '3.2.0'
info:
  title: Streaming API
  version: '1.0.0'
paths:
  /logs:
    get:
      operationId: getLogs
      responses:
        '200':
          description: Log stream
          content:
            application/jsonl:
              itemSchema:
                type: object
                properties:
                  timestamp:
                    type: string
                    format: date-time
                  level:
                    type: string
                    enum: [debug, info, warn, error]
                  message:
                    type: string
                required: [timestamp, level, message]
```

### NDJSON / JSON Lines

Each line in the response body is a separate JSON object. Empty lines are skipped.

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$factory = new Psr17Factory();
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$request = $factory->createServerRequest('GET', '/logs');
$operation = $validator->validateRequest($request);

$body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"Started"}' . "\n"
    . '{"timestamp":"2024-01-01T00:00:01Z","level":"error","message":"Failed"}';

$response = $factory->createResponse(200)
    ->withHeader('Content-Type', 'application/jsonl')
    ->withBody($factory->createStream($body));

$validator->validateResponse($response, $operation);
```

### Server-Sent Events (SSE)

Each event consists of `event`, `data`, `id`, and `retry` fields; comment lines (starting with `:`) are ignored. The `data` field is automatically decoded from JSON when possible, and an event that carries `data:` but no `event:` field is assigned the W3C default event type `message`.

### JSON Text Sequences (RFC 7464)

Each record is prefixed with a record separator byte (`0x1E`), which avoids ambiguity with newlines inside JSON strings.

### Error Handling in Streams

By default, malformed records (invalid JSON) are logged and skipped: the parser yields `null` for such items and the validator skips them. Enable `enableStrictStreaming()` to throw `MalformedStreamRecordException` instead. A parsed item that fails schema validation always throws `ValidationException` immediately, and a stream yielding more than `withMaxStreamingRecords()` records (default 100 000) throws `TooManyRecordsException`.

## Limitations

### JSON Schema Coverage

The validator covers approximately 95% of JSON Schema draft 2020-12 keywords. The following are not fully supported:

- `$dynamicRef` / `$dynamicAnchor` - dynamic schema resolution
- `$recursiveRef` / `$recursiveAnchor` - recursive schema resolution
- `contentEncoding` / `contentMediaType` - **limited** support: base64 encoding and a fixed set of common media types (JSON, XML, text); custom encodings and media types are not supported.
- Custom vocabularies and keyword extensions

The boolean schema form (`true` / `false`) is supported everywhere a schema is expected: `true` always passes and `false` always rejects.

### `$ref` and null values (known limitation)

When a `properties` or `items` subschema is declared as `{$ref: '#/...'}` and only the **resolved target** allows `null` (via `type: [..., 'null']` rather than a `nullable: true` sibling on the `$ref` stub), a `null` value on that property or item is rejected as `InvalidDataTypeException` even though the resolved target allows it. Non-null values are unaffected. Workaround: declare `nullable: true` as a sibling of the `$ref` — the sibling flag and the target's own nullability are combined with AND semantics, per OpenAPI 3.x `nullable` sibling-extension rules.

### `unevaluatedProperties` / `unevaluatedItems`

Both keywords are honoured — including across `allOf` / `anyOf` / `oneOf`, `if`/`then`/`else`, `$ref`, `contains`, and discriminator-routed branches — through the canonical validator path returned by `OpenApiValidatorBuilder::build()`.

### Content Negotiation

Request body validation honours RFC 7231 §3.1.1.1 wildcard patterns declared in the OpenAPI specification. The most specific declaration wins:

1. Exact match (e.g., `application/json`)
2. Subtype wildcard (e.g., `application/*`)
3. Universal wildcard (`*/*`)

When a wildcard declaration matches, the request body is parsed according to the concrete `Content-Type` sent by the client, so a spec `application/*` with a request `Content-Type: application/json` is decoded as JSON. A request whose `Content-Type` does not match any declared media type is rejected with `UnsupportedMediaTypeException` (fail-closed).

Response body validation does not expand wildcards: media type matching uses literal string comparison, and a response `Content-Type` that does not match a declared media type simply skips response body validation.

### Security Validation

Security scheme validation is opt-in (`enableSecurityValidation()`) and performs **presence-only** checks: it verifies that required credentials are present in the request but does not verify their correctness. Token introspection, JWT verification, JWKS resolution, and OAuth flows are out of scope.

Supported scheme types:

- `http/bearer` - checks for the `Authorization: Bearer ...` header (case-insensitive scheme prefix)
- `apiKey` (`query`, `header`, `cookie`) - checks for the named parameter in the specified location

Any other scheme type declared in the spec (`http/basic`, `http/digest`, `oauth2`, `openIdConnect`, `mutualTLS`, or unknown) throws `UnsupportedSecuritySchemeException` at request-validation time. The exception extends `\RuntimeException` and is **not** wrapped into `ValidationException`, so catch it separately in your middleware.

The `security` keyword is a list of OR-alternatives, each an AND-dict of schemes. An AND list containing an unsupported scheme fails closed with `UnsupportedSecuritySchemeException`, even when a supported sibling in the same dict would have passed. In an OR list, supported alternatives are still tried; if none succeeds, the unsupported-scheme error is re-thrown.

By default, credential failures return a generic message (`'Authentication required: missing or invalid credentials'`) that does not reveal which scheme was checked or where the credential was expected. Provide a PSR-3 logger via `withSecurityVerboseLogging()` to get scheme details at debug level (declared OAuth2 scopes are logged there as well), or read them programmatically via the opt-in `reveal: true` getters (see [Exception Sanitization](#exception-sanitization)).

To validate OAuth2 / OpenID Connect tokens, remove those schemes from the spec and validate the token at the application layer — for example, in a PSR-15 middleware calling your introspection endpoint or JWKS verifier. The security validator has no extension point for custom scheme handlers; a missing handler is reported via the exception instead of silently bypassed.

## Requirements

- **PHP 8.4 or higher** - Uses modern PHP features (readonly classes, match expressions, etc.)
- **PSR-7 HTTP message** - `psr/http-message ^2.0`. Use any PSR-7 implementation (`nyholm/psr7`, `guzzle/psr7`, `laminas/laminas-diactoros`).
- **PSR-6 cache** - `psr/cache ^3.0` (e.g., `symfony/cache`, `cache/cache`)
- **PSR-14 events** - `psr/event-dispatcher ^1.0` (e.g., `symfony/event-dispatcher`)
- **PSR-3 logging** - `psr/log ^3.0` (included, optional to use via `withLogger()`)
- **YAML parser** - `symfony/yaml ^7.0 || ^8.0`

## Testing

```bash
# Run tests
make tests

# Run with coverage
make coverage

# Run static analysis
make psalm

# Fix code style
make cs-fix
```

## License

MIT
