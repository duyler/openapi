# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- **BREAKING**: Inverted the default of strict callback runtime template
  resolution from opt-in to opt-out, closing SEC-09 (SSRF via attacker-
  controlled callback URLs). `OpenApiValidatorBuilder::build()` now resolves
  the `strictCallbackRuntimeTemplate` config with a `?? true` fallback, so
  callback expressions that use runtime templates such as
  `{$request.body#/callback_url}` throw
  `Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException`
  by default instead of being silently treated as wildcards that accept any
  URL. Applications that previously relied on the wildcard behaviour must
  call `OpenApiValidatorBuilder::disableStrictCallbackRuntimeTemplate()` to
  opt back into the legacy mode; this opt-out is documented as unsafe when
  the resolved callback URL is used for outbound HTTP requests without
  application-level validation. The previous opt-in method
  `OpenApiValidatorBuilder::enableStrictCallbackRuntimeTemplate()` is
  retained as a `@deprecated` no-op (`return $this;`) for backward
  compatibility and will be removed in 2.0. The `BuilderConfig`
  field type (`?bool = null`) and its `merge()` semantics are unchanged.
  Migration: callers that explicitly invoked `enableStrictCallbackRuntimeTemplate()`
  keep working (the call is now a no-op, the default already enables strict
  mode); callers that relied on the implicit wildcard default must add
  `disableStrictCallbackRuntimeTemplate()` and validate callback URLs
  through an application-level allowlist.
- Hardened `FileExternalRefResolver` scheme policy from blacklist to strict
  whitelist: only `file://` URIs and scheme-less relative paths are now
  accepted. Every other scheme (`php://`, `phar://`, `data://`,
  `compress.zlib://`, `zip://`, `expect://`, `ssh2://`, `rar://`, `ogg://`,
  `glob://`, plus previously denied `http/https/ftp/ftps/gopher/netcat`) is
  rejected with `ExternalRefSecurityException`. Closes SEC-01 (LFI/SSRF/RCE
  via PHP stream wrappers) and SEC-24 (`data://` inline JSON injection).
- `ExternalRefSecurityException` message no longer interpolates the original
  `$ref` (it surfaces only the offending scheme), mitigating path-disclosure
  risk at the resolver layer.
- Hardened `FileExternalRefResolver` default behaviour through the builder:
  `OpenApiValidatorBuilder::fromYamlFile()` / `fromJsonFile()` now auto-derive
  `allowedRoot = dirname(realpath($specPath))` so external `$ref` resolution is
  confined to the spec directory by default (closes SEC-03 arbitrary file read
  via the default resolver). New `withExternalRefAllowedRoot(string $path)`
  builder method allows overriding the root explicitly. Direct `new RefResolver()`
  usage preserves the legacy null-`allowedRoot` behaviour for backward compatibility
  (documented as unsafe for trusted specs).
- Replaced `file_get_contents` with a `fopen` + `fstat` + `fread` + `fclose`
  pattern in `FileExternalRefResolver::resolve()`. Files are now opened once
  after the realpath-bound check, validated as regular files (rejecting
  `/dev/null`, `/dev/zero`, FIFOs, and other non-regular files), and read in
  bounded chunks with a default 10 MB cap. Closes SEC-04 (TOCTOU window
  between realpath and file_get_contents), SEC-05 (DoS via unbounded file
  read), and adds the `ExternalRefTooLargeException` + `withExternalRefMaxBytes(int)`
  builder method for explicit size configuration.
- Removed raw user-supplied values from `InvalidFormatException::params()` output to
  prevent accidental disclosure of secrets (passwords, tokens, API keys) through error
  formatters into logs and API responses (CWE-532, closes SEC-06). The value remains
  accessible via the `$exception->value` property for programmatic access. An opt-in
  debug mode is available through `withDetailedErrors(includeSensitive: true)` on the
  builder, or by constructing `new DetailedFormatter(includeSensitiveValues: true)` /
  `new JsonFormatter(includeSensitiveValues: true)` directly.
- `MissingSecurityCredentialsError` now emits a generic caller-safe message
  (`'Authentication required: missing or invalid credentials'`) by default,
  preventing reconnaissance of API security scheme names, types, and parameter
  locations by unauthenticated callers (CWE-209, closes SEC-07). Scheme details
  remain accessible programmatically via `$error->schemeName` / `$error->schemeType`
  / `$error->location` and are logged at `debug` level when an opt-in PSR-3 logger
  is provided via `withSecurityVerboseLogging()`. Additionally, all
  `FileExternalRefResolver` exception messages no longer interpolate absolute
  filesystem paths (closes SEC-08); paths are available only via the opt-in logger
  or the `ExternalRefSecurityException::$ref` property for programmatic access.
- Coercion is now strict by default: boolean coercion rejects unknown strings
  like 'admin' or 'foo' instead of silently casting them to true (SEC-13);
  integer coercion detects overflow before `(int)` cast and throws
  TypeMismatchError for values outside [PHP_INT_MIN, PHP_INT_MAX] (SEC-14);
  number coercion detects IEEE-754 precision loss via NumberStringNormalizer
  canonical string round-trip and rejects strings that cannot be represented
  exactly as a double, in both strict and non-strict paths (SEC-15). New
  `disableStrictCoercion()` builder method provides the opt-out for legacy
  loose-cast behavior.
- `http/bearer` Authorization scheme matching is now case-insensitive per RFC 6750
  §2.1 (accepts `BEARER`, `Bearer`, `bearer`, `BeArEr` etc.), and a trailing-space-only
  header like `Bearer ` (without token) is rejected. The regex `^bearer\s+\S+` replaces
  the case-sensitive `str_starts_with` check, routed through PregExecutor for backtrack
  protection (SEC-16).
- `JsonBodyParser::parse()` and `JsonParser::parseContent()` now reject JSON
  payloads containing invalid UTF-8 byte sequences via `mb_check_encoding()`
  before `json_decode()` is invoked, throwing
  `Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception`. The check runs
  after BOM strip and before the empty-body short-circuit in
  `JsonBodyParser`, matching the RFC 8259 §8.1 requirement that JSON be
  UTF-8. PHP's `json_decode()` does not enforce UTF-8 validity on all
  sequences (notably overlong encodings such as `\xC0\x80` on older PHP
  versions), so explicit validation is required (closes SEC-17).
- `YamlParser::parseContent()` now enforces a size cap (default 1 MB) before
  invoking `Symfony\Component\Yaml\Yaml::parse()` and a nesting depth cap
  (default 100) after parsing, throwing
  `Duyler\OpenApi\Validator\Exception\SpecTooLargeException` on overflow.
  Defends against billion-laughs-style YAML that can cause stack overflow or
  OOM during parsing (closes SEC-18). New builder methods
  `withMaxSpecSize(int $bytes)` and `withMaxSpecDepth(int $depth)` provide
  explicit overrides; defaults are exposed as
  `YamlParser::DEFAULT_MAX_SPEC_BYTES` and `YamlParser::DEFAULT_MAX_SPEC_DEPTH`.
  The `OpenApiBuilder::__construct` is no longer `final` so subclasses can
  accept additional constructor parameters via LSP-compatible signatures;
  the constructor body that initialises `OpenApiBuildContext` is unchanged.
- Leap second (`:60`) in `date-time` and `time` formats is now restricted to
  UTC end-of-day only (e.g., `23:59:60Z` accepted; `23:59:60+05:00` rejected),
  preventing ambiguous timezone-dependent leap second interpretation (SEC-20,
  SEC-21).

### Fixed
- JSON Pointer escape sequences (`~1` for `/`, `~0` for `~`) are now decoded
  when resolving `$ref` fragments in both `RefResolver` and
  `FileExternalRefResolver`, per RFC 6901 section 3 (SPEC-01). Previously,
  `$ref: '#/paths/~1user/get'` would fail to resolve because the literal
  `/user` key was never matched.
- `$ref` siblings are now evaluated alongside the resolved schema per JSON
  Schema 2020-12 section 8.2.3. Previously, sibling keywords like `description`,
  `title`, `minLength`, `enum`, and `properties` next to a `$ref` were silently
  ignored (SPEC-02). A new `SchemaSiblingMerger` class merges resolved and sibling
  schemas with documented merge strategies (sibling-wins for scalars, union for
  required/allOf, intersection for enum, shallow merge for properties/maps).

## [0.5.0] - 2026-07-18

This release focuses on defense-in-depth security hardening, spec-compliance
bug fixes, and internal decomposition of the two largest god classes. The
public API is preserved with two additions: `Operation` now carries resolved
path parameters and the optional `enableStrictCallbackRuntimeTemplate()` mode
fail-closes on unresolvable callback expressions.

### Security
- Closed CWE-94 code injection via type field in `ValidatorCompiler`; generated
  code now rejects class-name injection.
- Added `PregExecutor` wrapper enforcing a defensive `pcre.backtrack_limit`
  (configurable via `withMaxRegexBacktracks()`) to prevent ReDoS on
  attacker-controlled JSON-Schema `pattern` fields.
- Capped request body and streaming payload sizes via `withMaxJsonBodySize()`
  and `withMaxMultipartBodySize()`; streaming depth limit and opt-in
  `enableStrictStreaming()` mode raise `MalformedStreamRecordException` instead
  of silently skipping bad records.
- Replaced xxHash64 cache keys with SHA-256 and `spl_object_id` with `WeakMap`
  to resist hash-collision poisoning and object-id reuse.
- Closed auth fail-open in `CookieValidator` and added opt-in
  `enableStrictCallbackRuntimeTemplate()` to reject unresolvable callback
  runtime expressions (e.g. `{$request.body#/callback_url}`).
- `UnresolvableRefException::getMessage()` no longer discloses the internal
  circular-reference traversal path; the path is preserved in a readonly
  `$internalTrace` property for safe logging.
- `AbstractCompositionalValidator` caps accumulated errors at 20;
  `ContainsValidator` caps validations at 10 000;
  `ArrayLengthValidator` aborts `uniqueItems` after 100 000 entries.
- `XmlBodyParser` enforces a 1 MB default `maxXmlBytes` limit (configurable).
- Added `LogContextSanitizer` to truncate and escape untrusted strings before
  they enter PSR-3 context, mitigating log injection.
- Added builtin `FileExternalRefResolver` with SSRF protection: network
  schemes (`http://`, `https://`, `ftp://`, `ftps://`) are denied by default;
  optional `allowedRoot` defends against path traversal and symlink escapes.

### Added
- `Operation` DTO now exposes resolved `pathParameters`, `operationId`, and
  nullable `schemaOperation` reference; existing call sites keep working via
  defaults.
- `OpenApiValidatorInterface::getDocument()` promoted to the public interface.
- `resolveLinkWithContext()` and `LinkContext` for full OpenAPI 3.2 Runtime
  Expression support (`$url`, `$method`, `$statusCode`, `$request.*`,
  `$response.*`).
- Strict streaming and strict callback runtime template modes (see Security).
- Magic-value enums (`UriScheme`, `ContentMediaType`, `BooleanCoercionValue`,
  `XmlNodeType`) replace stringly-typed comparisons.

### Changed
- Decomposed `Schema` god class into focused constraint sub-DTOs
  (`ArrayConstraints`, `CompositionConstraints`, `NumericConstraints`,
  `ObjectConstraints`, `StringConstraints`).
- Decomposed `OpenApiBuilder` god class into focused builder modules
  (`ComponentsBuilder`, `InfoBuilder`, `PathItemBuilder`, `SchemaBuilder`,
  `SecuritySchemeBuilder`) resolved lazily through `OpenApiBuildContext`.
- Introduced `ValidatorDependencies` DTO and a lazy registry for
  `ValidatorFactory` (open-closed principle).
- `ValidationContext` is now per-request and renamed to avoid the name clash
  with `Error\ValidationContext`.
- Split overloaded boolean-flag builder methods into focused
  `enable*`/`disable*` pairs.
- Consolidated ~660 lines of redundant PHPDoc, dead defensive guards, and
  unreachable branches across `src/`. Public API signatures preserved.

### Performance
- Reuse `SchemaValidator` on the hot path; cached discriminator and `$ref`
  detection.
- `RefResolver` cache, `PathFinder` trie accumulator, and route sort
  precompilation.
- Coercion hot path, `EnumScalarCache` HashSet, and pattern normalization
  caches.
- `PathFinder` migrated from linear scan to a segment-based trie.

### Fixed
- Restored libxml external entity loader and removed global-state mutation
  from `ValidatorPool::reset()` (Swoole / FrankenPHP threaded workers).
- SSE parser now handles no-colon lines per WHATWG and supports dotted
  RFC 6570 variable names.
- Nine JSON Schema / OAS spec compliance bugs: whole-float acceptance for
  `type: integer`, `multipleOf` epsilon equivalence, `const`/`enum` numeric
  equality, `required` / `allowEmptyValue` orthogonality, and more.
- Five composition validator bugs in `allOf` / `anyOf` / `oneOf` / `not`:
  typed-error propagation, `$ref` resolution, and discriminator double
  validation.
- Replaced `@` operator in `PatternValidator` with explicit error handling;
  added `TypeFormatter` utility for typed error messages.
- `SchemaRegistry` semantics refined; `PathFinder` exception naming aligned
  with public API.
- README parity fixes: qualified performance claims, documented PHP runtime
  caveats, and aligned public API examples.

### Deprecated
- `ValidationErrorInterface::getType()` is deprecated in favor of `keyword()`.
  Both return the same value; `getType()` will be removed in 2.0.

### Tests
- Added concurrency tests for Swoole, FrankenPHP threaded, and RoadRunner
  prefork runtimes.
- Added ReDoS, billion-laughs, deep-nesting, and memory-exhaustion security
  regression tests.
- Added cache-invalidation and alternate PSR-7 implementation coverage
  (Guzzle, Laminas Diactoros).

## [0.4.1] - 2026-07-16

### Fixed
- OAS 3.1 type-array null handling: `SchemaValueNormalizer` now recognizes
  `type: [string, "null"]` as nullable in all validator call sites
  (bug A1 from PR #25 analysis).
- `FormatValidator` no longer crashes when `null` is the first element in a
  type-array; it uses the `firstStringType()` helper (bug A2).
- `BodyParser` now parses `application/vnd.api+json` (JSON:API) as JSON
  instead of treating it as a raw string (bug B1).

### Added
- `SchemaValueNormalizer::typeIncludesNull()` helper for OAS 3.1 type-array
  null detection.
- TypeValidator regression tests for `nullableAsType` + type-array interaction.

## [0.4.0] - 2026-07-15

### Security
- Hardened XML parsing against XXE: deny-all libxml entity loader, internal
  error state save/restore, and non-network loading.
- Removed global-state races in date/time and JSON parsing by using
  `DateTimeImmutable`/regex and `JSON_THROW_ON_ERROR` with capped depth,
  eliminating reliance on shared error globals.
- Hardened request and stream parsing: case-insensitive `Content-Type`,
  rejection of `q-value=0`, multipart boundary extraction from the header,
  UTF-8 BOM stripping, line/record length limits, query/cookie pair-count
  limits, and untrusted JSON depth limits.
- Added optional lock support to `ValidatorPool` for Swoole/coroutine runtimes,
  and converted static regex and path caches to instance-bound LRU caches with
  `reset()` clearing.
- Prevented injection and traversal: `preg_quote` in path-template regex,
  `var_export`/class-name validation in compiled validator codegen, correct
  RFC 6901 escaping in JSON Pointer resolution, and discriminator mapping by
  schema name.
- Hardened schema coercion and numeric handling: strict integer/number/boolean
  coercion, whole-float acceptance for `type: integer`, numeric equality in
  `const`/`enum`, `multipleOf` greater-than-zero and zero guards, and
  orthogonal `required`/`allowEmptyValue` handling.
- Bounded memory and error output: capped additional-property errors, O(n)
  hash-map deduplication for `uniqueItems`, and bounded streaming buffers.
- Replaced MD5 with xxHash64 for cache key generation.

### Added
- Opt-in security validation for `http/bearer` and `apiKey` (header/query/cookie)
  schemes via `enableSecurityValidation()`.
- Opt-in server path resolution via `enableServerPathResolution()`; supports
  server variables, relative URLs, and longest-prefix matching.
- Full Runtime Expression support in links: `resolveLinkWithContext()` with
  `LinkContext` for `$response.header`, `$response.query`, `$url`, `$method`,
  and `$statusCode`.
- `validateWebhook()` and `validateCallback()` public methods for webhook and
  callback request validation.
- `ContentEncodingValidator` and `ContentMediaTypeValidator` for base64 and
  JSON/XML/text media types.
- `ExampleValidator` for optional, non-blocking validation of `example`/`examples`.
- `ValidatorCompiler` guards against unsupported keywords and supports
  `readOnly`/`writeOnly` validation.
- OpenAPI 3.1+ `nullable` conversion to `type: [..., "null"]` while preserving
  OpenAPI 3.0 boolean behavior.
- Server URL validation and strict format mode (`enableStrictFormats()`) with
  PSR-3 warnings.
- Runtime deprecation warnings and version-aware `exclusiveMinimum`/
  `exclusiveMaximum` handling.
- Validation lifecycle events (`ValidationStartedEvent`,
  `ValidationFinishedEvent`, `ValidationErrorEvent`) and PSR-3 logger
  integration.
- `PathItem::getOperation()` for consolidated HTTP method routing.
- `ValidatorPool` LRU cache with configurable size and `clear()` for hot reload.
- `ExternalRefResolverInterface` for custom external `$ref` resolution.
- `SchemaRegistry::getOrFail()` for fail-fast schema lookups.
- Boolean schema support (`type: boolean`).

### Changed
- Refactored `OpenApiValidator` into a facade backed by specialized handlers;
  public interface preserved.
- `OpenApiValidatorBuilder` now returns `OpenApiValidatorInterface` and delegates
  to a shared internal `with()` method.
- `BuilderException` now extends `RuntimeException`.
- Added `Schema::withOverrides()` for non-destructive schema overrides.
- Extracted `CompilationCacheInterface` and `RequestValidatorInterface`.
- Coercion and security validation now use internal context DTOs.
- Event dispatching extracted into a shared trait.
- Added `final` to concrete classes and updated PHPDoc/version annotations.

### Performance
- Cached regex patterns in `PathRegexCache` and `RegexValidator` with
  instance-bound LRU eviction.
- Shared `ValidatorRegistry` and `StatelessValidatorRegistry`; cached enum checks
  and error merging.
- Optimized `ValidatorPool::touch()` to O(1) and rewrote `RefResolver::navigate()`
  iteratively.
- `PathFinder` uses a segment-based trie instead of linear scan;
  `PathParser::tryMatchPath()` avoids exception-based control flow.
- Made `BreadcrumbManager` and `ValidationContext` mutable for the hot
  validation path.
- Moved stateless validators and body parsers to constructors for reuse.
- Streaming parsers read PSR-7 streams in bounded chunks.
- Cache schema hashes, placeholder counts, and compiled validator results once
  per call.

### Fixed
- `SchemaValidatorAdapter` now passes `ErrorValidationContext` so
  `disableNullableAsType()` and `withEmptyArrayStrategy()` are honored for plain
  schemas.
- Parameter validators now propagate `nullableAsType` and `emptyArrayStrategy`
  through `ParameterValidationConfig`.
- `PrefixItemsValidator` no longer re-validates remaining items; `ContainsValidator`
  was merged with range handling.
- `ResponseTypeCoercer` preserves null properties and additional properties;
  `coerceToArray` returns non-arrays as-is.
- `NotValidator`, `OneOfValidator`, and `PropertiesValidator` propagate typed
  errors into `ValidationException`.
- `AdditionalPropertiesValidator` emits `AdditionalPropertyError` instead of
  `UnevaluatedPropertyError`, and caps error count.
- `JsonBodyParser` returns `null` for empty bodies; `CookieValidator` preserves
  flag-style cookies; `QueryParser` handles RFC 6570 form/explode semantics,
  literal key names, and nesting limits.
- `ParameterDeserializer` adds deepObject style support and `FormBodyParser`
  delegates to `QueryParser`.
- `EmailValidator` requires a TLD, `HostnameValidator` supports IDN and label
  rules, `ByteValidator` accepts URL-safe base64, `UriValidator` uses RFC 3986
  scheme/port checks, and `UuidValidator` supports RFC 9562 v6/v7/v8 and nil
  UUID.
- `XmlBodyParser` preserves attributes, repeated elements, empty elements, and
  mixed content.
- `ResponseHeadersValidator` fails closed on unknown boolean values and coerces
  empty header values to objects when requested.
- `ResponseValidationHandler` falls back to webhooks for webhook response cycles.
- Streaming: SSE handles CRLF/CR/LF line endings, default event name, multi-line
  data, and retry; NDJSON supports CRLF; BOM stripped from all JSON/XML streams.
- `ValidatorCompiler` now matches runtime behavior for `type: integer` and
  `multipleOf`, caches schema hashes once, guards TTL, and wraps JSON exceptions.
- `RefResolver` enforces recursion depth limits, resolves parameters/responses,
  and handles external refs via optional `ExternalRefResolverInterface`.
- `SchemaValidatorAdapter` routes discriminator and `$ref` schemas through
  `SchemaValidatorWithContext`.
- `$ref` resolution in `allOf`/`oneOf` compositions and callback runtime
  expressions fixed.
- Wildcard media types expanded for request bodies; Content-Type matching is
  case-insensitive.
- Query parameters treat `required` and `allowEmptyValue` as orthogonal.
- Added required request body check and eliminated discriminator double
  validation.
- `LinkResolver` handles out-of-bounds list indices in link resolution.

## [0.3.3] - 2026-05-16

### Changed
- Set `symfony/yaml` requirement to `^7.0`.

[Unreleased]: https://github.com/duyler/openapi/compare/0.5.0...HEAD
[0.5.0]: https://github.com/duyler/openapi/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/duyler/openapi/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/duyler/openapi/compare/0.3.3...0.4.0
[0.3.3]: https://github.com/duyler/openapi/compare/0.3.2...0.3.3
