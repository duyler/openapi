#!/bin/sh

status=0

for entry in $(find tests -mindepth 1 -maxdepth 1); do
    name=${entry#tests/}
    case $name in
        Unit|Integration|Functional|Performance|Security|Support|Fixture) ;;
        *)
            echo "test-structure: unexpected top-level entry in tests/: $name" >&2
            status=1
            ;;
    esac
done

php_in_fixture=$(find tests/Fixture -name '*.php' 2>/dev/null)
if [ -n "$php_in_fixture" ]; then
    echo "test-structure: php files are forbidden under tests/Fixture/:" >&2
    echo "$php_in_fixture" >&2
    status=1
fi

literal_hits=$(grep -rniF -e '/../Fixture' -e '/Fixture/' -e '/fixtures/' -e '/Fixtures/' tests --include='*.php' 2>/dev/null | grep -v '^tests/Support/FixtureLocator.php:')
regex_hits=$(grep -rniE "/fixtures['\"]" tests --include='*.php' 2>/dev/null | grep -v '^tests/Support/FixtureLocator.php:')
if [ -n "$literal_hits" ] || [ -n "$regex_hits" ]; then
    echo "test-structure: forbidden fixture path references (use FixtureLocator::path):" >&2
    printf '%s\n' "$literal_hits" "$regex_hits" >&2
    status=1
fi

non_test_files=$(find tests/Unit tests/Integration tests/Functional tests/Performance tests/Security -name '*.php' ! -name '*Test.php' ! -name '*TestCase.php' ! -name '*Trait.php' 2>/dev/null)
if [ -n "$non_test_files" ]; then
    echo "test-structure: non-test php files inside test layers (must match *Test.php, *TestCase.php or *Trait.php):" >&2
    echo "$non_test_files" >&2
    status=1
fi

builder_in_unit=$(grep -rnF 'OpenApiValidatorBuilder' tests/Unit --include='*.php' 2>/dev/null)
if [ -n "$builder_in_unit" ]; then
    echo "test-structure: OpenApiValidatorBuilder is forbidden in Unit/ (public API entry belongs to Functional/):" >&2
    echo "$builder_in_unit" >&2
    status=1
fi

if [ "$status" -ne 0 ]; then
    echo "test-structure: FAILED" >&2
    exit 1
fi

echo "test-structure: OK"
