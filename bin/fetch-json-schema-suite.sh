#!/bin/sh

set -eu

SUITE_REPO="json-schema-org/JSON-Schema-Test-Suite"
SUITE_DEFAULT_COMMIT="89e83a74014617ffbe79a56d83d14d054cd11417"
SUITE_COMMIT="${1:-$SUITE_DEFAULT_COMMIT}"

if ! printf '%s' "$SUITE_COMMIT" | grep -Eq '^[0-9a-f]{40}$'; then
    echo "fetch-json-schema-suite: invalid commit sha: $SUITE_COMMIT (expected 40 lowercase hex characters)" >&2
    exit 1
fi

PACKAGE_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
TARGET_DIR="$PACKAGE_ROOT/tests/Fixture/JsonSchemaTestSuite"
VERSION_FILE="$TARGET_DIR/VERSION"
LICENSE_FILE="$TARGET_DIR/LICENSE"
DRAFT_DIR="$TARGET_DIR/draft2020-12"
REMOTES_DIR="$TARGET_DIR/remotes"

if [ -f "$VERSION_FILE" ] && [ -f "$LICENSE_FILE" ] && [ -d "$DRAFT_DIR" ] && [ -n "$(find "$DRAFT_DIR" -name '*.json' -print -quit 2>/dev/null)" ]; then
    pinned=$(sed -n 's/^commit: //p' "$VERSION_FILE")
    if [ "$pinned" = "$SUITE_COMMIT" ]; then
        echo "fetch-json-schema-suite: already vendored at commit $SUITE_COMMIT"
        exit 0
    fi
fi

WORK_DIR=$(mktemp -d)
trap 'rm -rf "$WORK_DIR"' EXIT

archive="$WORK_DIR/suite.tar.gz"
curl -sSL --fail -o "$archive" "https://github.com/$SUITE_REPO/archive/$SUITE_COMMIT.tar.gz"

extract_dir="$WORK_DIR/src"
mkdir -p "$extract_dir"
tar -xzf "$archive" -C "$extract_dir" --strip-components=1

suite_draft_dir="$extract_dir/tests/draft2020-12"
suite_remotes_dir="$extract_dir/remotes"
suite_license_file="$extract_dir/LICENSE"

if [ ! -d "$suite_draft_dir" ] || [ ! -d "$suite_remotes_dir" ] || [ ! -f "$suite_license_file" ]; then
    echo "fetch-json-schema-suite: archive for $SUITE_COMMIT has no tests/draft2020-12, remotes/ or LICENSE" >&2
    exit 1
fi

rm -rf "$DRAFT_DIR" "$REMOTES_DIR"
mkdir -p "$TARGET_DIR"

cp -R "$suite_draft_dir" "$DRAFT_DIR"
cp -R "$suite_remotes_dir" "$REMOTES_DIR"
cp "$suite_license_file" "$LICENSE_FILE"

find "$DRAFT_DIR" -name '*.json' -exec chmod 644 {} +
find "$REMOTES_DIR" -name '*.json' -exec chmod 644 {} +
chmod 644 "$LICENSE_FILE"

cat > "$VERSION_FILE" <<EOF
source: $SUITE_REPO
url: https://github.com/$SUITE_REPO
commit: $SUITE_COMMIT
license: MIT
section: tests/draft2020-12
remotes: remotes/
note: upstream per-draft branches are consolidated into main; tests/draft2020-12 is the draft2020-12 suite
EOF

echo "fetch-json-schema-suite: vendored $SUITE_COMMIT"
echo "fetch-json-schema-suite: sections: $(find "$DRAFT_DIR" -name '*.json' | wc -l | tr -d ' ') files"
echo "fetch-json-schema-suite: remotes: $(find "$REMOTES_DIR" -name '*.json' | wc -l | tr -d ' ') files"
