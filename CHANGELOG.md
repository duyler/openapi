# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Security
- Fixed XXE vulnerability in XmlBodyParser
- Added circular reference protection in RefResolver

### Fixed
- Fixed $ref handling in JsonParser for Parameter and Response
- Fixed empty array type ambiguity with configurable strategy

### Changed
- Refactored JsonParser and YamlParser to use shared OpenApiBuilder
- Removed Psalm global suppressions, improved type safety
- Added `final` modifier to all non-readonly classes
- Created psalm-baseline.xml for legitimate mixed type suppressions

### Added
- EmptyArrayStrategy enum for configurable empty array validation
- Security tests for XXE protection
- Comprehensive test coverage for ref resolution

## [Unreleased] - Breaking Changes

### Added

- Added `Operation` class for encapsulating path and method
- Added `PathFinder` for automatic operation detection
- Added `ValidationMiddleware` for PSR-15 support
- Added `ValidationMiddlewareBuilder` for fluent middleware creation
