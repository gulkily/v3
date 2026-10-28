#!/usr/bin/env bash
# PHP-independent entry-point regressions: bash tests/local_server_dependency_checks_test.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE="$(mktemp -d)"
trap 'rm -rf "${FIXTURE}"' EXIT
mkdir "${FIXTURE}/bin"
for tool in bash dirname cat stty; do
  ln -s "$(command -v "${tool}")" "${FIXTURE}/bin/${tool}"
done
export V3_TEST_SERVER_MARKER="${FIXTURE}/server"
cat >"${FIXTURE}/bin/php" <<'EOF'
#!/usr/bin/env bash
[[ "${V3_TEST_PHP_BROKEN:-0}" == 0 ]] || exit 99
if [[ "${1:-}" == -r ]]; then
  printf '%s\n%s\n/fake/php\n%s\n' "${V3_TEST_PHP_VERSION:-80300}" 'test-runtime' "${V3_TEST_EXTENSIONS:-1111}"
elif [[ "${1:-}" != */check_local_server_dependencies.php ]]; then
  printf '%s\n' "$@" >"${V3_TEST_SERVER_MARKER}"
fi
EOF
cat >"${FIXTURE}/bin/git" <<'EOF'
#!/usr/bin/env bash
[[ "${V3_TEST_GIT_BROKEN:-0}" == 0 ]] || exit 99
printf '%s\n' 'git version test'
EOF
chmod +x "${FIXTURE}/bin/php" "${FIXTURE}/bin/git"
fail() { echo "FAIL: $*" >&2; exit 1; }
reject_both() {
  local message="$1" command
  for command in start check-dependencies; do
    if PATH="${FIXTURE}/bin" "${ROOT}/v3" "${command}" >"${FIXTURE}/out" 2>"${FIXTURE}/err"; then
      fail "${command} accepted ${message}"
    else
      [[ $? == 1 ]] || fail 'unexpected failure status'
    fi
    cat "${FIXTURE}/out" "${FIXTURE}/err" >"${FIXTURE}/combined"
    grep -Fq -- "${message}" "${FIXTURE}/combined" || fail "missing message: ${message}"
    [[ ! -e "${V3_TEST_SERVER_MARKER}" ]] || fail 'failed preflight started server'
  done
}
mv "${FIXTURE}/bin/git" "${FIXTURE}/git"
reject_both 'Git is not available on PATH'
if [[ -e /usr/bin/git ]]; then
  grep -Fq 'Git is already installed' "${FIXTURE}/combined" || fail 'PATH repair missing'
  if grep -Fq 'sudo apt-get install git' "${FIXTURE}/combined"; then fail 'PATH repair proposed duplicate installation'; fi
fi
mv "${FIXTURE}/git" "${FIXTURE}/bin/git"
export V3_TEST_GIT_BROKEN=1
reject_both 'Git is on PATH but cannot run'
unset V3_TEST_GIT_BROKEN
mv "${FIXTURE}/bin/php" "${FIXTURE}/php"
reject_both 'PHP CLI is not available on PATH'
grep -Fq '✓ OK: Bash' "${FIXTURE}/out" || fail 'available marker missing'
grep -Fq '✗ FAIL: PHP CLI' "${FIXTURE}/out" || fail 'missing marker missing'
grep -Fq '? SKIP: PDO SQLite driver' "${FIXTURE}/out" || fail 'uncheckable driver missing'
grep -Fq '✓ OK: Optional stty' "${FIXTURE}/out" || fail 'independent optional check skipped'
[[ ! -s "${FIXTURE}/err" ]] || fail 'report split across stdout and stderr'
report="$(cat "${FIXTURE}/out")"
[[ "${report}" == *'✗ FAIL: PHP CLI'*'? SKIP: PDO SQLite driver'*'Result: NOT READY'*'Recovery guidance'*'PHP CLI is not available on PATH.'* ]] \
  || fail 'missing-PHP report did not separate statuses, result, and guidance'
[[ "${report}" != *$'\033'* ]] || fail 'redirected report contained color escapes'
mv "${FIXTURE}/php" "${FIXTURE}/bin/php"
export V3_TEST_PHP_BROKEN=1
reject_both 'PHP is on PATH but cannot run'
unset V3_TEST_PHP_BROKEN
export V3_TEST_PHP_VERSION=80099
reject_both 'requires PHP 8.1+'
export V3_TEST_PHP_VERSION=unreadable
reject_both 'unreadable runtime identity'
unset V3_TEST_PHP_VERSION
PATH="${FIXTURE}/bin" "${ROOT}/v3" check-dependencies >"${FIXTURE}/out" 2>"${FIXTURE}/err"
grep -Fq 'OK: Git executable' "${FIXTURE}/out" || fail 'Git recovery missing'
grep -Fq 'minimum 8.1' "${FIXTURE}/out" || fail 'PHP recovery missing'
[[ ! -s "${FIXTURE}/err" && ! -e "${V3_TEST_SERVER_MARKER}" ]] || fail 'report launched server or emitted errors'
PATH="${FIXTURE}/bin" "${ROOT}/v3" start :8123 --quiet >"${FIXTURE}/out" 2>"${FIXTURE}/err"
grep -Fq -- '--address=127.0.0.1:8123' "${V3_TEST_SERVER_MARKER}" || fail 'recovery lost address'
grep -Fxq -- '--quiet' "${V3_TEST_SERVER_MARKER}" || fail 'recovery lost logging option'
[[ ! -s "${FIXTURE}/out" && ! -s "${FIXTURE}/err" ]] || fail 'healthy startup emitted preflight output'
if PATH="${FIXTURE}/bin" "${ROOT}/v3" check-dependencies --unknown >"${FIXTURE}/out" 2>"${FIXTURE}/err"; then
  fail 'unknown diagnostic argument accepted'
fi
echo 'PASS: shared executable/version checks, failures, recovery, and read-only report'

# Supported Ubuntu must not inspect package metadata for available extensions.
(
  source "${ROOT}/scripts/local_server_dependencies.sh"
  php_setup_platform() { printf '%s\n' ubuntu-24.04; }
  php_setup_extension_guidance() { printf '%s\n' "$1" >>"${FIXTURE}/guidance"; }
  PATH="${FIXTURE}/bin" local_server_dependencies_check startup
  [[ ! -e "${FIXTURE}/guidance" ]] || fail 'healthy startup prepared package recovery'
  PATH="${FIXTURE}/bin" local_server_dependencies_check report >"${FIXTURE}/out"
  [[ ! -e "${FIXTURE}/guidance" ]] || fail 'healthy report prepared package recovery'
  for pair in 0111:common 1011:sqlite3 1101:mbstring 1110:intl; do
    : >"${FIXTURE}/guidance"
    V3_TEST_EXTENSIONS="${pair%:*}" PATH="${FIXTURE}/bin" local_server_dependencies_check startup
    [[ "$(cat "${FIXTURE}/guidance")" == "${pair#*:}" ]] || fail 'missing extension recovery selected wrong package'
  done
)
echo 'PASS: package recovery prepared only for missing extensions'
