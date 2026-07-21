# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- All exception classes shipped by the package now sanitize `__toString()`
  output to prevent disclosure of server filesystem paths, class names, and
  stack traces through `(string) $e` casts in PSR-15 middleware or PSR-3
  loggers (R3-SEC-INFO-LEAK-SYSTEMATIC, CWE-209, CWE-497; findings S-019).
  A new `Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait`
  provides the canonical `__toString()` override returning only
  `getMessage()`. The trait is applied directly to every standalone
  exception class and inherited transitively through
  `AbstractValidationError`. The two existing per-class overrides
  (`UnresolvableRefException`, `UnknownDiscriminatorValueException`) and
  the four duplicate `__toString()` methods on the discriminator exception
  family were consolidated into the single trait.
- Sensitive public readonly properties on category-B exception classes are
  now `protected readonly` and exposed only through explicit opt-in
  getters with a `bool $reveal = false` parameter (S-005, S-008, S-016,
  S-032). Affected classes and their previous public API:
  - `ExternalRefSecurityException::$ref` → `$e->ref(reveal: true)`
  - `UnresolvableRefException::$ref`, `::$reason`, `::$internalTrace` →
    `$e->ref(reveal: true)`, `$e->reason()`, `$e->internalTrace(reveal: true)`
  - `InvalidFormatException::$value` → `$e->value(reveal: true)`
    (`$format` stays public; it is a spec-declared keyword, not a value)
  - `MissingSecurityCredentialsError::$schemeName`, `::$schemeType`,
    `::$location` → `$e->schemeName(reveal: true)` etc.
  - `InvalidParameterException::$parameterName` →
    `$e->parameterName(reveal: true)`
  Default calls (`$e->value()`, `$e->schemeName()`, ...) return the
  literal string `'<redacted>'` so reflective serializers (Sentry, Bugsnag,
  `(array) $e`, `var_export`) cannot leak attacker-controlled values.
  Trusted-operator code (security auditors, verbose loggers via
  `DetailedFormatter(includeSensitiveValues: true)`,
  `JsonFormatter(includeSensitiveValues: true)`) must now pass
  `reveal: true` to read the underlying value. This is a breaking change.
- `MalformedStreamRecordException::$record` is now truncated to 256 bytes
  via `LogContextSanitizer::truncate()` in the constructor, preventing a
  multi-megabyte attacker-controlled streaming payload from being
  amplified verbatim into logs or error trackers (S-007, CWE-209).
  Control characters are escaped via the same helper to neutralise log
  injection. The truncated value remains public readonly because the
  sanitised form is useful for debugging user-facing stream parse errors.
- `FileExternalRefResolver` now passes `basename($absolutePath)` instead
  of the resolved absolute filesystem path when throwing
  `ExternalRefSecurityException` for non-regular files, failed path
  resolution, and detected path traversal (S-005 defence-in-depth).
  Concrete filesystem layout remains reachable only through the optional
  PSR-3 verbose logger at debug level.
- `UnresolvableRefException` constructor now truncates `$reason` and
  `$internalTrace` to 256 bytes via `LogContextSanitizer::truncate()`,
  preventing an attacker-controlled multi-megabyte `$ref` or navigation
  chain from being amplified into logs (S-008 defence-in-depth).
  Existing short ref strings (`#/components/schemas/...`) are unchanged.
- `ExternalRefSecurityException::$ref` is likewise truncated to 256 bytes
  in the constructor; the original full-length attacker input is not
  retained (S-005 defence-in-depth).
- `UriValidator` no longer interpolates the attacker-supplied scheme or
  port into `InvalidFormatException` messages (R3-SEC-021, CWE-209). The
  messages are now the generic strings `'Unsupported URI scheme'` and
  `'URI port out of range'`. The full URI value remains available via
  `$e->value(reveal: true)` for trusted-operator diagnostics.
- `ValidatorCompiler` now escapes attacker-controlled property names in
  generated-code throw messages via `var_export($key, true)` instead of
  raw string concatenation, and replaces every remaining
  `addslashes(...)` site in generated-code error strings with the same
  `var_export` + `sprintf('%s', ...)` pattern (R3-SEC-015, CWE-094 /
  CWE-133). Generated validators no longer interpolate attacker keys into
  single-quoted PHP string literals, which previously could produce
  parseable-but-malformed messages or escape-sequence injection when the
  key contained bytes that defeated `addslashes`.

### BREAKING
- Exception sanitisation changes listed under `### Security` above are
  breaking. Direct property access (`$e->value`, `$e->schemeName`,
  `$e->ref`, `$e->internalTrace`, `$e->parameterName`, `$e->schemeType`,
  `$e->location`) now throws `Error: Cannot access protected property`.
  Replace with the matching opt-in getter call (`$e->value(reveal: true)`,
  etc.). Trusted code that previously relied on `includeSensitiveValues: true`
  on `DetailedFormatter` / `JsonFormatter` continues to work — the
  formatters now call the opt-in getters internally.

- `ValidatorCompiler::generatePatternCheck` now emits an inlined defensive
  wrapper around `preg_match` for the `pattern` keyword, closing R3-SEC-001
  (CWE-1333, CWE-400; OWASP ASVS 4.0 V5.3.4). Previously the compiled
  validator called `preg_match` directly with PHP's default
  `pcre.backtrack_limit = 1_000_000`, bypassing the
  `PregExecutor::DEFAULT_MAX_BACKTRACKS = 10_000` cap that the runtime
  validator enforces. A spec-supplied catastrophic-backtracking pattern
  (e.g. `^(a+)+$`) matched against attacker-controlled data could burn
  hundreds of milliseconds of CPU per call inside a compiled validator,
  enabling CPU DoS. The fix inlines the
  `PregExecutor::match` semantics — capture previous limit, lower it to
  `10_000`, install a bounded-scope error handler, call `preg_match`,
  restore everything inside `try`/`finally` — so the generated class stays
  self-contained (no library call) and the standalone-validator contract
  documented in the README "Validator Compilation" section is preserved.
  `ValidatorCompiler::COMPILED_MAX_BACKTRACKS` is the explicit mirror of
  `PregExecutor::DEFAULT_MAX_BACKTRACKS`; update both together if the cap
  ever changes.
  Operators upgrading from a pre-fix release MUST flush their PSR-6
  `CompilationCache` pool (or restart long-running workers) to retire
  cached compiled validators that still contain the raw `preg_match`
  call. The cache key (introduced in R3-ARCH-001) does not incorporate
  the compiler version, so existing entries persist until their TTL
  expires (24h default) or the pool is cleared manually.
- `CompilationCache::generateKey()` now includes the document context
  for schemas that contain `$ref`, closing R3-SEC-004 (cross-document
  cache poisoning). Previously `SchemaToArrayConverter::toSnapshotArray`
  serialised `$ref` pointers as opaque strings, so two `OpenApiDocument`
  instances that exposed the same `#/components/schemas/...` pointer but
  resolved it to different target schemas produced identical cache keys.
  A second tenant sharing a PSR-6 pool would then silently reuse a stale
  compiled validator from the first tenant. The fix resolves
  `#/components/schemas/...` pointers against the supplied document
  before hashing (in-memory only, mirroring `ValidatorCompiler::resolveRefs`
  cycle detection), and additionally folds in a SHA-256 fingerprint of
  the document's entire `components.schemas` map so that two documents
  with identical resolved-target content but different sibling components
  still produce different keys (tenant isolation). Schemas that
  transitively contain a `$ref` (at the top level or nested in
  `properties` / `items`) without an explicit `$document` argument now
  throw `CompilationCacheException` (fail-closed) instead of silently
  hashing the literal pointer string. `CompilationCacheInterface::generateKey`
  signature changed to `(Schema, string $className, ?OpenApiDocument $document = null)`
  and `ValidatorCompiler::compileWithCache` gained a fourth optional
  `?OpenApiDocument $document` parameter that flows through to
  `generateKey`; existing three-argument callers continue to work for
  any schema that does not transitively contain a `$ref`.

### Added
- `EmailValidator` now accepts RFC 5321 domain literals
  (`user@[127.0.0.1]`, `user@[IPv6:2001:db8::1]`), quoted local parts
  (`"hello world"@example.com`, `"@"@example.com`, `"hello\"world"@example.com`),
  and SMTPUTF8 addresses (`用户@例子.广告`, `test@münchen.de`) per RFC 6531.
  Uses `idn_to_ascii` via ext-intl when available; falls back to a regex
  with `\p{L}\p{N}` Unicode classes when ext-intl is absent. Full RFC 5322
  grammar (comments, folding whitespace) remains intentionally out of scope;
  the validator targets the pragmatic 95% subset. (Closes R3-SPEC-010,
  C-010.)
- Registered 10 missing format validators in `BuiltinFormats`: `int32`,
  `int64`, `binary`, `password`, `idn-email`, `idn-hostname`, `iri`,
  `iri-reference`, `uri-reference`, `uri-template`, `regex`. With these
  registered, `enableStrictFormats()` now works on typical OpenAPI 3.2
  specifications without custom stubs — previously any spec using one of
  these formats fail-closed with
  `InvalidFormatException: 'Unknown format "int32" for type "integer" in strict mode'`.
  Numeric formats use a shared `IntegerRangeValidator` parameterised by
  `[min, max]`; `binary` and `password` are pass-through hint formats per
  OAS 3.2 §5.x; `idn-email` / `idn-hostname` / `iri` / `iri-reference`
  delegate to their ASCII counterparts and accept UTF-8 in the host/domain
  portions. (Closes R3-SPEC-013, C-013.)
- `ValidatorPool::forCoroutineRuntime(object $lock, ?int $maxSize = null)`
  named-constructor factory for explicit Swoole-coroutine and
  FrankenPHP-threaded-worker contracts (R3-CONCURRENCY-001). The factory
  delegates to the existing constructor with the same validation, but the
  required `object $lock` parameter makes the concurrency contract
  visible at the call site — `new ValidatorPool()` with an optional null
  lock remains the prefork-safe default and is unchanged.

### Changed
- **BC-break**: `format: int32` and `format: int64` are now registered as
  format validators via `IntegerRangeValidator`. Values outside the int32
  range `[-2147483648, 2147483647]` now throw `InvalidFormatException`
  from the format validator, which fires BEFORE the `maximum` / `minimum`
  keywords in the validation pipeline. Previously `format: int32` was a
  no-op (unregistered) and only the `maximum` keyword rejected out-of-
  range values. Existing tests that asserted `MaximumError::class` for
  schema `{ type: integer, format: int32, maximum: 2147483647 }` with
  value `2147483648` must now accept either `MaximumError` or
  `InvalidFormatException`. (Relates to R3-SPEC-013, C-013.)
- `CallbackValidator` (both the outer
  `Duyler\OpenApi\Validator\Validation\CallbackValidator` and the inner
  `Duyler\OpenApi\Validator\Callback\CallbackValidator`) now default the
  `$strictCallbackRuntimeTemplate` constructor parameter to `true`,
  closing R3-SEC-009 / R3-SEC-025 (CWE-918 SSRF, CWE-1188 Insecure
  Default Initialization; OWASP ASVS 4.0 V12.6.1). Previously the
  builder already enforced strict mode via its
  `strictCallbackRuntimeTemplate ?? true` fallback (SEC-09), but the
  class constructors themselves defaulted to `false`, so third-party
  code that instantiated either class directly — bypassing the builder —
  silently got the legacy wildcard behaviour for callback runtime
  expressions like `{$request.body#/callback_url}`. With an attacker-
  controlled request body this permitted SSRF (cloud-metadata
  endpoints, internal admin services, internal-network scanning) when
  the resolved URL was used for an outbound HTTP request. The fix
  makes the class default match the builder default (single source of
  truth). Callers that instantiate `Validation\CallbackValidator` or
  `Callback\CallbackValidator` directly without passing the flag
  explicitly will now observe stricter behaviour
  (`UnresolvableCallbackPathException` instead of a wildcard match).
  Builder callers are unaffected. To opt back into the legacy wildcard
  behaviour, pass `strictCallbackRuntimeTemplate: false` explicitly to
  the constructor (only when callback URLs are validated at the
  application level — see README section "Callbacks").
- `ValidatorPool`, `LibxmlSecuredContext`, and `PregExecutor` now carry
  an explicit `@danger NOT_THREAD_SAFE` marker in their class-level
  PHPDoc, and the README section "Long-Running Processes" enumerates
  the three unsafe classes with a per-class table (unsafe state,
  mitigation, affected runtimes) and dedicated paragraphs for the
  nested-factory deadlock (O-004), the libxml capture/restore race
  (O-006 / S-011), and the `pcre.backtrack_limit` /
  `pcre.recursion_limit` race (O-007 / S-020). The contract is now
  machine-checkable: `grep -lE '@danger\s+NOT_THREAD_SAFE' src/Validator`
  returns exactly the three files. This hardens the previously soft
  prefork-only / not-coroutine-safe contract without introducing
  runtime detection or BC-breaking required-lock semantics
  (R3-CONCURRENCY-001, R3-CONCURRENCY-002, R3-CONCURRENCY-003).

### Fixed
- `JsonParser` now enforces a configurable size cap (default 1 MB,
  `JsonParser::DEFAULT_MAX_SPEC_BYTES`) on input JSON before parsing,
  matching `YamlParser` behavior. Closes the SEC-18 gap for JSON
  (R3-SEC-014, CWE-400, CWE-770). The cap is wired through
  `OpenApiValidatorBuilder::withMaxSpecSize()` and applies to both YAML
  and JSON specs. The size check runs before `json_decode` so an
  attacker-controlled multi-GB payload never reaches the decoder.
- `TypeCoercer::coerceToType` now coerces non-string inputs (`bool`, `int`,
  `float`) through the same `coerceToInteger`/`coerceToNumber`/
  `coerceToBoolean`/`coerceToString` path as strings
  (R3-CORRECTNESS-005). Previously only `is_string($value)` inputs were
  routed through the typed coercion methods; everything else bypassed
  them via `normalizeValue`, making the `bool`/`int`/`float` branches in
  `AbstractCoercer` effectively dead code. For JSON request bodies parsed
  via `json_decode($json, true)` this meant that a field declared as
  `type: integer` but received as `bool true` (or as `float 5.0`) was
  silently normalised to its raw PHP value instead of being coerced to
  the schema-declared type. Scalar inputs (`bool`, `int`, `float`,
  `string`) now route through the schema-type match; non-scalar inputs
  (`resource`, `null` already handled earlier) continue to fall through
  to `normalizeValue`, preserving the existing behaviour for edge cases.
- `TypeCoercer::coerceUnionType` now catches `TypeMismatchError` and
  continues to the next type in the union instead of aborting on the
  first failure (R3-CORRECTNESS-006). For a union type such as
  `['integer', 'string']` with input `'abc'`, the previous behaviour
  propagated `TypeMismatchError` from `coerceToIntegerStrict('abc')` and
  the `'string'` branch was never reached. Union coercion is now
  first-match-wins across all declared types; if no type matches, the
  original value is returned via `normalizeValue` as before. The
  SEC-14 (float-to-int overflow) and SEC-15 (numeric-string precision)
  guards continue to throw `TypeMismatchError`, which is now caught and
  handled by the loop.
- `UriValidator` now accepts all RFC 3986 valid URIs including scheme-less
  forms (`mailto:`, `tel:`, `urn:`, `data:`, `magnet:?...`) and
  non-allowlisted schemes (`git+https`, `ssh`, `irc`, `magnet`, `bitcoin`,
  `chrome-extension`) (R3-SPEC-008, R3-SPEC-009). The previous
  implementation required `//authority` via a regex lookahead and enforced
  a closed scheme allowlist through the now-removed `Format\String\Enum\UriScheme`
  enum; both contradicted RFC 3986 §3 / §3.1 and JSON Schema 2020-12 §7.3.1
  (which delegate `format: uri` to RFC 3986 generic syntax without
  restricting the scheme set). The regex now implements all four
  hier-part alternatives (`"//" authority path-abempty`, `path-absolute`,
  `path-rootless`, `path-empty`); the redundant `filter_var(FILTER_VALIDATE_URL)`
  fallback has been removed because it rejected valid scheme-less URIs and
  IDN hosts.
- `UriValidator` error messages no longer disclose the URI scheme or port
  from user input (R3-SEC-021, CWE-209). The previous messages
  `'Unsupported URI scheme'` and `'URI port out of range'` have been
  replaced with the generic strings `'Invalid URI format'` and
  `'Invalid URI: port out of range'`. The full URI value remains available
  through `$e->value(reveal: true)` for trusted-operator diagnostics.
- `PatternValidator::validate()` now calls `RegexValidator::validate()`
  between `normalize()` and `pregExecutor()->match()`, closing R3-SEC-002
  (CWE-1333, CWE-400, CWE-770; OWASP ASVS 4.0 V5.3.4). The sibling
  validators `PatternPropertiesValidator` and `PropertyNamesValidator`
  already enforced `RegexValidator::MAX_PATTERN_LENGTH = 1024`, but
  `PatternValidator` (the consumer of the JSON-Schema `pattern` keyword
  on string instances) called only `normalize()`. An attacker controlling
  the spec (via `fromYamlString()` or a multi-tenant schema-validation
  service) could ship a megabyte-sized `pattern`; `PregExecutor` capped
  backtracking but not compile-time cost, so PCRE burned hundreds of
  milliseconds compiling the pattern on the first `validate()` call. The
  fix applies the length cap symmetrically with the other regex-consuming
  validators. Patterns longer than 1024 bytes now raise
  `InvalidPatternException` with `reason: 'Pattern exceeds maximum length
  of 1024 bytes'` at the first validation call instead of falling through
  to PCRE compilation.
- `PregExecutor` now sets `pcre.recursion_limit` (default
  `DEFAULT_MAX_RECURSION = 512`) alongside `pcre.backtrack_limit` for the
  duration of every `preg_match` / `preg_match_all` call, closing
  R3-SEC-017 (CWE-1333, CWE-400, CWE-770). Previously only
  `pcre.backtrack_limit` was bounded; `pcre.recursion_limit` was left at
  the PHP default (`100_000`), allowing deeply-nested patterns such as
  `(a|a)*b`, `(.*)*$`, or `((a+)*)+` to exhaust the C stack on systems
  with small main-thread stacks (Alpine Linux musl libc defaults to 2 MB,
  Windows PHP builds to 1 MB) and segfault the worker process — a
  reliable DoS independent of backtracking. The fix mirrors the existing
  capture/restore pattern used for `pcre.backtrack_limit`: the previous
  value is captured before the call and restored inside a `finally`
  block, including when `preg_match` itself throws. The Swoole-race
  caveat documented in R3-SEC-020 now applies symmetrically to both ini
  variables; it is re-documented in the `PregExecutor` class PHPDoc.
  `pcre.recursion_limit` is a separate mechanism from
  `pcre.backtrack_limit`: the latter bounds NFA backtracking while the
  former bounds the depth of recursion in PCRE's internal matcher. On
  PCRE2 10.x the recursion limit has less impact than on PCRE1 because
  JIT compilation uses its own stack, but it remains a realistic attack
  vector whenever the JIT falls back to the interpreter (certain
  patterns, `pcre.jit=0`, or stack-size-constrained runtimes).
- `SchemaSiblingMerger::merge()` now honours JSON Schema 2020-12 §8.2.3
  ALL OF semantics for the ten non-bound fields that previously used a
  sibling-wins strategy, closing R3-SPEC-006 (C-006). When a `$ref`
  carries sibling keywords, the referenced schema and the sibling must
  be evaluated as `allOf: [$ref, sibling]`; the previous implementation
  collapsed each side into a single value via `$sibling->field ??
  $resolved->field`, silently dropping the referenced schema's
  constraint. Concretely, `multipleOf: 3` (resolved) + `multipleOf: 5`
  (sibling) used to validate as `multipleOf: 5` (5 was applied, 3 was
  discarded), so an input like `10` passed despite not being a multiple
  of the resolved `3`. Same divergence existed for `pattern`, `format`,
  `type`, `items`, `contains`, `propertyNames`, `if`, `then`, `else`.
  The fix specialises each keyword per ALL OF semantics: `type` is
  intersected as a set (a single element collapses to string form;
  disjoint intersection wraps both sides into separate `allOf`
  sub-schemas so the validator rejects every value); identical `format`
  strings collapse to one while divergent formats wrap into `allOf`;
  `multipleOf` and `pattern` are always wrapped into `allOf` when both
  sides declare them (numeric LCM is intentionally not computed inline
  because it is undefined for non-integer multiples; regex conjunction
  via lookaheads risks catastrophic backtracking and downstream-consumer
  incompatibility); `items`, `contains`, `propertyNames` are merged
  recursively via `merge()` so both schemas' constraints apply;
  `if`/`then`/`else` triples are each wrapped into a separate `allOf`
  sub-schema when both sides declare any element of the triple so each
  conditional applicator applies independently per §10.2.2. The
  pre-existing sibling-wins test `merge_scalar_overrides_where_not_null`
  is updated to assert the new wrap-based output. Schemas that used
  `$ref + sibling` for narrowing (the common OpenAPI idiom
  `$ref: '#/$defs/Base', minLength: 5`) are unaffected because only one
  side declares the keyword.
- `SchemaSiblingMerger::mergeSchemaOrBool` now recursively merges two
  Schema instances instead of letting the sibling silently overwrite the
  resolved schema, closing R3-SPEC-019 (O-042). The previous sibling-
  wins strategy was correct for the boolean case (`false` is the
  stricter ALL OF result, `true` is a no-op) but lost the referenced
  schema's constraints when both sides were Schema instances. The fix
  applies uniformly to every Schema|bool|null keyword
  (`additionalProperties`, `unevaluatedProperties`, `contentSchema`,
  `not`, `items`, `contains`, `propertyNames`, `if`, `then`, `else`,
  `unevaluatedItems`): `false` still wins; `true` still inherits the
  other side; `null` still inherits the non-null side; two Schema
  instances now go through `merge()`. Behaviour is backwards-compatible
  with task 05 for the boolean paths.
- `Schema` now accepts `Schema|bool|null` for every schema-typed
  keyword: `items`, `contains`, `propertyNames`, `if`, `then`, `else`,
  `not`, `unevaluatedItems` join the already-typed `additionalProperties`,
  `unevaluatedProperties`, `contentSchema`, closing R3-SPEC-005 (C-005).
  JSON Schema 2020-12 §4.3.2 mandates that boolean schemas be accepted
  anywhere a Schema is accepted as a keyword value: `true` always passes,
  `false` always rejects. Previously these 8 keywords were typed
  `?Schema` only, so direct programmatic construction
  (`new Schema(items: false)`) threw `TypeError`, and the parser's
  `SchemaFromArrayConverter::schemaField` workaround rewrote `false`
  into `Schema(not: new Schema())` — losing the bool semantics in
  `SchemaSiblingMerger`, `ValidatorCompiler::resolveRefs`, and the
  round-trip serializer (`SchemaToArrayConverter` emitted
  `{"not":{}}` instead of `false`). The fix introduces a single
  `schemaOrBoolOrNull` parser helper that pass-throughs booleans, an
  `SchemaSiblingMerger::mergeSchemaOrBool` route for all 8 keywords
  (sibling-wins preserves `false` as the stricter ALL OF result), and
  `SchemaToArrayConverter` emits booleans directly so
  `fromArray(toArray(schema)) === schema`. Each runtime validator
  (`ItemsValidator`, `ItemsValidatorWithContext`, `ContainsValidator`,
  `PropertyNamesValidator`, `NotValidator`, `IfThenElseValidator`,
  `UnevaluatedItemsValidator`) handles both boolean branches with typed
  errors (`TypeMismatchError`, `ContainsMatchError`, `NotValidationError`,
  `MinContainsError`) per §11 of `php-best-practices.md`. The
  `ValidatorCompiler` rejects boolean-form variants of these 8 keywords
  with `UnsupportedKeywordException` (fail-closed) so the compiler never
  silently drops them; use the runtime validator for these schemas.
- `UnevaluatedPropertiesValidator` now consults every adjacent in-place
  applicator (`allOf`, `anyOf`, `oneOf`, `if`, `then`, `else`, `$ref`)
  in addition to `properties`, `patternProperties`, and
  `additionalProperties`, closing R3-SPEC-001 (false-positive
  `UnevaluatedPropertyError` on the common OpenAPI idiom
  `unevaluatedProperties: false` + `allOf` for inheritance). JSON
  Schema 2020-12 §10.3.4 requires `unevaluatedProperties` to consider
  every property that was successfully evaluated by any adjacent
  in-place applicator. The fix introduces mutable annotation-state on
  `ValidationContext` (`evaluatedPropertyNames: array<string, true>`)
  that every composition validator (`AllOfValidator`, `AnyOfValidator`,
  `OneOfValidatorWithContext`, `IfThenElseValidator`) populates through
  `forkForBranch()` + `mergeChildAnnotations()` on successful
  sub-validation, and that keyword validators (`PropertiesValidator`,
  `PropertiesValidatorWithContext`, `PatternPropertiesValidator`,
  `AdditionalPropertiesValidator`) populate directly via
  `markPropertyEvaluated()`. `NotValidator` deliberately contributes an
  empty annotation set per §10.3.4. `UnevaluatedPropertiesValidator`
  reads `$context->evaluatedPropertyNames()` and merges with its
  existing static analysis of `properties` / `patternProperties` /
  `additionalProperties`. The legacy stateless `SchemaValidator` path
  that passes `null` for the context falls back to static analysis
  only; this is a documented limitation (canonical entry point is
  `SchemaValidatorWithContext`).
- `UnevaluatedItemsValidator::getEvaluatedItemIndices` (renamed from
  `getEvaluatedItemsCount` to return a list of indices instead of a
  count) now treats `items` as evaluating every index `>=
  prefixItems count` when `items` is present, closing R3-SPEC-002.
  JSON Schema 2020-12 §10.3.1.2 requires `unevaluatedItems` to consider
  items at indices `>= prefixItems count` evaluated when `items` is
  present. The previous implementation checked `prefixItems` first and
  short-circuited the `items` check, silently over-rejecting every
  item past `prefixItems` in schemas like
  `prefixItems: [...]; items: {type: integer}; unevaluatedItems: false`
  on data `['foo', 1, 2, 3]`. The fix also drops the unused
  `PHP_INT_MAX` sentinel in favour of an explicit index set.
- `ContainsValidator` now registers every matched index in
  `ValidationContext` via `markItemEvaluated(int $index)` so that
  `UnevaluatedItemsValidator` recognises `contains`-validated items as
  evaluated, closing R3-SPEC-003. JSON Schema 2020-12 §11.2.1.3
  annotates `contains` as a `list<int>` of matched indices, not a
  boolean. The previous implementation re-computed the evaluated set
  from `prefixItems` / `items` only, so a schema like
  `contains: {type: integer, minimum: 0}; unevaluatedItems: false`
  on data `[1, 2, 'x']` rejected indices 0 and 1 even though they had
  already been validated against the `contains` schema. After the fix,
  `contains` annotations propagate through the shared context and
  `unevaluatedItems` only fails the truly unevaluated index 2.
- `UnevaluatedItemsValidator` now also consumes composition annotations
  (`allOf`, `anyOf`, `oneOf`, `if`, `then`, `else`, `$ref`) through the
  same `ValidationContext::evaluatedItemIndices()` channel used for
  `contains`, closing R3-SPEC-004. JSON Schema 2020-12 §11.1.1.3
  requires `unevaluatedItems` to consult every in-place applicator
  annotation. The previous implementation had no annotation channel at
  all and could not honour schemas like
  `unevaluatedItems: false; allOf: [{items: {type: integer}}]` on data
  `[1, 2, 3]`, rejecting every item as unevaluated. The
  `ItemsValidator` (legacy stateless path), `ItemsValidatorWithContext`
  (canonical path), and `PrefixItemsValidator` now register evaluated
  indices into the shared context, and `OneOfValidatorWithContext` was
  reordered to run before the stateless validator pass in
  `SchemaValidatorWithContext::doValidate` so that its annotations are
  visible when `unevaluatedItems` / `unevaluatedProperties` run.
- `ValidatorCompiler::generateConstCheck` now emits an inline
  `jsonEquals` call instead of a PHP strict `!==` comparison, closing
  R3-CORRECTNESS-001. JSON Schema 2020-12 §4.2.2 numeric equality
  treats `1` (int) and `1.0` (float) as equal, but PHP `1 !== 1.0` —
  so a compiled validator previously rejected `1.0` for `const: 1`
  while the runtime validator accepted it. The same fix covers
  object key-order: `const: {a:1,b:2}` and data `{b:2,a:1}` are now
  both accepted by the compiled validator (per §4.2.2 unordered-keys
  rule). Mixed int/float comparisons above 2^53 are rejected as
  unequal, matching `JsonEquals::SAFE_INT64_FLOAT_BOUNDARY`. The
  `jsonEquals` helper is inlined into the generated class only when
  the schema uses `const`, `enum`, or `uniqueItems`, so the
  standalone-validator contract (no library code is emitted) is
  preserved.
- `ValidatorCompiler::generateEnumCheck` now emits a linear-scan
  with the inline `jsonEquals` helper instead of
  `in_array($data, [...], true)`, closing R3-CORRECTNESS-002. PHP
  strict `in_array` suffers from the same `1 !== 1.0` divergence as
  the const check: a compiled validator previously rejected int `1`
  for `enum: [1.0, 2.0, 3.0]` while the runtime validator accepted
  it. After the fix, both validators agree on §4.2.2 numeric
  equality and §4.2.2 object-key-order equality for the top-level
  `enum` keyword (applies to top-level `enum` only; `items.enum`
  strict-equality is a known limitation for a follow-up task).
- `ValidatorCompiler::generateArrayCheck` for the `uniqueItems`
  keyword now emits a canonicalised-key lookup with an
  associative-array `isset` (O(n)) plus a `100000` unique-entry cap,
  closing R3-CORRECTNESS-003. The previous code used
  `json_encode($__item, JSON_THROW_ON_ERROR)` directly, which
  produced distinct keys for JSON-equal values (`json_encode(1)` is
  `'1'` while `json_encode(1.0)` is `'1.0'`), so a compiled
  validator accepted `[1, 1.0]` while the runtime validator rejected
  it. The same canonicalisation also covers reordered-key objects:
  `[{a:1,b:2}, {b:2,a:1}]` is now correctly detected as a duplicate
  via `ksort` on object keys before `json_encode`. The cap matches
  `ArrayLengthValidator::MAX_UNIQUE_CHECK = 100000` and the
  runtime's `TooManyItemsForUniqueCheckError` DoS defence. The
  O(n²) `in_array` lookup was replaced by an associative-array
  `isset` for O(n) enforcement.
- `ValidatorCompiler::generateArrayCheck` for the `uniqueItems`
  keyword now wraps the canonicalisation `json_encode` in
  `try { ... } catch (\JsonException $e) { throw new \RuntimeException(...); }`,
  closing R3-CORRECTNESS-013. `\JsonException` extends `\Exception`
  (not `\RuntimeException`), so callers that
  `catch (\RuntimeException $e)` previously missed the failure when
  the data contained a non-encodable value (resource or recursive
  array). The wrapper converts the `JsonException` to a generic
  `RuntimeException` with a chained `$previous` for trace
  preservation, matching the README "Compiler Limitations" contract
  that compiled validators only throw `RuntimeException`.
- `ValidatorCompiler::generatePatternCheck` now disambiguates a PCRE
  runtime error (`preg_match === false`, e.g. malformed pattern that
  passes `RegexValidator::normalize` but fails PCRE compilation, or
  `pcre.backtrack_limit` exceeded on a catastrophic-backtracking input)
  from a genuine no-match (`preg_match === 0`), closing the
  R3-PERF-001 disambiguation part. Previously the compiled validator
  emitted `if (false === preg_match(...)) throw new
  RuntimeException('Pattern validation failed')`, which conflated the two
  cases; backtrack-overflow surfaced in operator logs as "Pattern
  validation failed" — misleading because the data may have matched the
  pattern but PCRE ran out of resources. The new generated code throws
  `RuntimeException('PCRE error during pattern validation')` for the
  `false` branch and `RuntimeException('Pattern validation failed')` for
  the `0` branch, matching the disambiguation contract of the runtime
  `PregExecutor`.
- `CompilationCache::generateKey()` now incorporates the target PHP
  class name into the cache key, closing R3-ARCH-001 (cache-key
  collision when the same `Schema` is compiled under different class
  names). Previously the key depended only on the schema snapshot, so
  `compileWithCache($schema, 'UserValidator', $cache)` followed by
  `compileWithCache($schema, 'AdminValidator', $cache)` returned the
  first call's cached code; `require_once` then loaded a class still
  named `UserValidator`, and `new AdminValidator()` failed with
  `Fatal error: Class "AdminValidator" not found`. The fix SHA-256
  hashes the className and combines it with the schema hash and the
  document fingerprint through a second SHA-256 pass, keeping the
  returned key inside PSR-6 length and charset limits
  (`namespace.length + 1 + 64`) regardless of how long the className
  is. The internal in-memory WeakMap was widened from
  `WeakMap<Schema, string>` to `WeakMap<Schema, array<string, string>>`
  so the same `Schema` instance compiled under N class names or M
  documents keeps O(1) lookup over the `N * M` cache entries.
- `ValidatorPool::acquireLock()` and `ValidatorPool::releaseLock()` no
  longer re-check `method_exists($this->lock, 'lock'/'unlock')` on the
  hot path of every `getOrCreate()` call (R3-CONCURRENCY-004 / O-034).
  The constructor already validates that a non-null `$lock` exposes both
  methods, so the redundant guard was dead branch on the hot path. The
  body of both helpers now reads `if (null !== $this->lock) {
  $this->lock->lock(); }` — the constructor's invariant is trusted
  inside the class. No behavioural change for callers; pre-existing
  tests that passed lock-stubs with the methods continue to work.
- `OpenApiValidatorBuilder::generateCacheKeyFromFile` now incorporates
  a SHA-256 hash of the spec file contents into the `SchemaCache`
  cache key (previously: path + mtime + size). The new key format
  `openapi_spec_file_<sha256(realpath | sha256(content))>` prevents
  cache-poisoning via size-preserving or mtime-preserving spec
  tampering, where an attacker with write access to the spec file
  rewrites a constraint (e.g. `minLength: 50` → `minLength: 99`),
  pads the file to the original byte size, and then `touch -r`-aligns
  the mtime, yielding an identical cache key under the old metadata-
  only scheme and silently serving the tampered document from cache
  on the next build (R3-SEC-003, S-003, OWASP ASVS V8.1.3, CWE-349,
  CWE-1023). `loadSpecFromFile` now reads the spec file exactly once
  and reuses the content for both cache-key generation and parsing;
  `mtime` and `size` are intentionally excluded from the new key
  because they offered no defence once an attacker controls write
  access to the spec file. `generateCacheKeyFromString` (string-loaded
  specs) is unchanged.

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
- `UnresolvableRefException::getMessage()` no longer interpolates the
  attacker-controlled `$ref` value into the message (CWE-209, closes
  SEC-08-FULL). The ref is preserved on the readonly `$ref` property for
  programmatic access by trusted code (PSR-3 logger context, Sentry
  enrichment). Callers that parse `getMessage()` to extract the ref must
  switch to `$exception->ref` and `$exception->reason`. The `__toString()`
  override also returns only the safe reason, suppressing class name, file
  path, and stack trace emitted by default `Exception::__toString()`.
- `OpenApiValidatorBuilder::build()` now fails closed when a spec loaded
  via `fromYamlString()` or `fromJsonString()` contains an external
  `$ref` (any ref that does not start with `#/`) and
  `withExternalRefAllowedRoot()` has not been called, throwing
  `BuilderException` instead of leaving `FileExternalRefResolver` running
  with `allowedRoot = null` (closes STRING-SPEC-FAILOPEN — arbitrary file
  read via attacker-controlled spec). Specs loaded via `fromYamlFile()` /
  `fromJsonFile()` and specs that explicitly call
  `withExternalRefAllowedRoot()` are not affected (the auto-derived or
  explicit root keeps confinement intact). Specs that contain only
  internal JSON pointer refs (`#/...`) continue to build without an
  allowed root for backward compatibility. Direct `new RefResolver()`
  usage without the builder is unaffected and remains documented as
  unsafe for trusted specs. Discriminator `mapping` and
  `defaultMapping` values that resolve to external refs are also caught
  by this guard, because `DiscriminatorValidator` resolves them through
  `RefResolver` exactly like a regular `$ref`.
- Coercion is now strict by default: boolean coercion rejects unknown strings
  like 'admin' or 'foo' instead of silently casting them to true (SEC-13);
  integer coercion detects overflow before `(int)` cast and throws
  TypeMismatchError for values outside [PHP_INT_MIN, PHP_INT_MAX] (SEC-14);
  number coercion detects IEEE-754 precision loss via NumberStringNormalizer
  canonical string round-trip and rejects strings that cannot be represented
  exactly as a double, in both strict and non-strict paths (SEC-15). New
  `disableStrictCoercion()` builder method provides the opt-out for legacy
  loose-cast behavior.
- `ResponseHeadersValidator::coerceToNumber` now rejects numeric strings
  that lose precision when round-tripped through IEEE-754 double, extending
  SEC-15 protection to response headers (RESPONSE-HEADERS-COERCION). A
  header like `X-Total: "99999999999999999999999999"` now throws
  `TypeMismatchError` with reason `'String value loses precision when
  converted to float'` instead of silently collapsing to `1.0E+26`. A
  second guard rejects scientific-notation values whose exponent exceeds
  the representable range of a double (e.g. `1e999999999`), preventing
  algorithmic-DoS via unbounded `str_repeat` allocation in the
  canonical-form expansion; the bound is `abs(exponent) > 320` (PHP_FLOAT_MAX
  is approximately `1.8e308`). The precision-loss guard is now shared, so
  body and header coercion cannot drift on SEC-15 precision semantics.
  The `is_numeric` short-circuit and the `coerceToInteger` /
  `coerceToBoolean` paths are unchanged.
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
- `JsonEquals::equals()` now rejects equality for mixed int+float
  comparisons when the int side is outside the IEEE 754 safe integer
  range (`abs($int) > 2 ** 53`). Previously, `JsonEquals::equals(PHP_INT_MAX,
  (float) PHP_INT_MAX)` returned `true` because both operands rounded to
  the same double (`9.223372036854776E+18`), even though they are
  mathematically distinct (`PHP_INT_MAX = 9223372036854775807` vs
  `(float) PHP_INT_MAX = 9223372036854775808.0`). This caused false-positive
  equality in `uniqueItems`, `const`, and `enum` validation for int64
  values beyond the double-precision safe integer boundary. The fix
  introduces a `SAFE_INT64_FLOAT_BOUNDARY = 9007199254740992` (= 2^53)
  constant and an early `return false` when the int operand exceeds it,
  preserving the existing `1 == 1.0` semantics for values within the
  safe range. Int-vs-int and float-vs-float comparisons are unchanged.
  Closes PHP_INT_MAX-FLOAT-BOUNDARY.
- `AbstractCoercer::coerceToInteger()` (non-strict path) now rejects float
  values in the boundary range roughly `[PHP_INT_MAX - 1023, PHP_INT_MAX]`
  and the symmetric negative range by switching the overflow guard from
  `> PHP_INT_MAX` to `>= (float) PHP_INT_MAX` (and `<= (float) PHP_INT_MIN`
  on the negative side), with an explicit `(float)` cast to make the
  float-vs-float comparison semantics unambiguous. Previously, the implicit
  `int → float` coercion of `PHP_INT_MAX` rounded to `9223372036854775808.0`
  (= `PHP_INT_MAX + 1`), hiding boundary values from the check; the
  subsequent `(int) $value` cast then silently wrapped around to
  `PHP_INT_MIN` with a PHP 8.4 deprecation warning (will become a
  `TypeError` in PHP 9.0). The strict-mode `coerceToIntegerStrict` path
  was unaffected because it rejects all float inputs before the range
  check. Closes WEAKPHP-INT-CHECK.
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
- `JsonEquals::equals()` now performs order-independent comparison for JSON
  objects per JSON Schema 2020-12 section 4.2.2 (SPEC-03), uses direct `===`
  for integer-to-integer comparison to preserve int64 precision (SPEC-05),
  and explicitly handles bool vs int distinction (SPEC-04). Same int64 fix
  applied to `ArrayLengthValidator::itemKey()` and `EnumScalarCache::scalarKey()`.
- `minLength` and `maxLength` string validation now counts UTF-16 code units
  per JSON Schema 2020-12 section 4.2.1 instead of Unicode code points,
  correctly handling supplementary characters (emoji, CJK extensions) that
  require surrogate pairs in UTF-16 (SPEC-06). New `Utf16::length()` helper
  counts directly from UTF-8 bytes without mbstring dependency; compiler
  inlines the same logic for generated validators.
- `deepObject` parameter style now correctly handles bracket notation
  (e.g., `color[R]=100&color[G]=200`) via the existing QueryParser
  rather than attempting non-spec-compliant JSON decoding in
  ParameterDeserializer (SPEC-07).
- `SchemaSiblingMerger::merge()` now aligns scalar bounds, `nullable`,
  and composition keywords with JSON Schema 2020-12 §8.2.3 ALL OF
  semantics. Lower bounds (`minLength`, `minItems`, `minProperties`,
  `minimum`, `exclusiveMinimum`) use stricter-wins (`max`); upper bounds
  (`maxLength`, `maxItems`, `maxProperties`, `maximum`, `exclusiveMaximum`)
  use stricter-wins (`min`); `nullable` uses logical AND so a side that
  forbids null prevails; `anyOf` / `oneOf` declared on both sides are
  wrapped into a nested `allOf` entry so the merged result is
  `(resolved composition) AND (sibling composition)` instead of the wider
  disjunction produced by concatenation; `prefixItems` is now merged
  per-index via recursive `merge()` calls with leftover items from the
  longer side appended unchanged. `allOf` concatenation behaviour is
  preserved (semantically equivalent to ALL OF) (SPEC-02B).
- `ArrayLengthValidator::encodeArrayKey()` now canonicalizes array keys
  recursively before `json_encode`, so two JSON objects with identical
  content but different key order (e.g. `{"a":1,"b":2}` vs `{"b":2,"a":1}`)
  hash to the same uniqueItems key per JSON Schema 2020-12 §4.2.2 instance
  equality (SPEC-03B). Previously the `uniqueItems` validator treated them
  as distinct, producing false negatives on duplicate detection and
  diverging from the already-canonical `JsonEquals::equals()` behaviour
  fixed in SPEC-03. List arrays (`[1,2]` vs `[2,1]`) remain distinct
  because array order is significant per §4.2.2; the canonicalizer
  recurses into list elements but preserves their positions.
- Documentation: `.ai/guides/process-violations-review-work.md` now explicitly
  forbids working-tree mutation during review-work (cp/sed/patch/git checkout
  on src/ or tests/). Parallel review-work agents share the working tree;
  temporary mutations for anti-test experiments are visible to all agents and
  caused false FAIL verdicts in the partition-1 task-02 review session.
  Documented alternatives: `git worktree`, throwaway scripts in the pre-approved
  temp dir, and pure-read `git stash`/`git diff`/`git show`.
  (RACE-CONDITION-PROCESS)

### Changed
- BC: `TypeCoercer::coerce()` now defaults to strict coercion (`$strict = true`). Third-party callers that relied on the implicit non-strict default MUST pass `false` explicitly via `disableStrictCoercion()` builder method or by passing the fourth argument. (TYPECOERCER-DEFAULT-STRICT)

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
