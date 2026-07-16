# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
[0.5.0]: https://github.com/duyler/openapi/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/duyler/openapi/compare/0.3.3...0.4.0
[0.3.3]: https://github.com/duyler/openapi/compare/0.3.2...0.3.3
