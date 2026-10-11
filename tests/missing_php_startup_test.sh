#!/usr/bin/env bash
# Standalone launcher regression check: bash tests/missing_php_startup_test.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE="$(mktemp -d)"
trap 'rm -rf "${FIXTURE}"' EXIT
mkdir "${FIXTURE}/bin"
for tool in bash dirname cat git stty; do
  ln -s "$(command -v "${tool}")" "${FIXTURE}/bin/${tool}"
done
fail() { echo "FAIL: $*" >&2; exit 1; }

if PATH="${FIXTURE}/bin" "${ROOT}/v3" start --quiet >"${FIXTURE}/out" 2>"${FIXTURE}/err"; then
  fail 'startup succeeded without PHP'
else
  [[ $? == 1 ]] || fail 'unexpected missing-PHP exit status'
fi
[[ ! -s "${FIXTURE}/out" ]] || fail 'missing-PHP output went to stdout'
for expected in 'PHP CLI' 'PATH' 'Install' 'php --version' "${ROOT}/README.md" 'retry' 'same options'; do
  grep -Fq -- "${expected}" "${FIXTURE}/err" || fail "missing guidance: ${expected}"
done
if grep -Eq 'exec:|php:.*not found' "${FIXTURE}/err"; then
  fail 'shell-level PHP error remains'
fi
grep -Fq '### PHP setup' "${ROOT}/README.md" || fail 'setup reference is missing'
for command in help '--help' 'fdp sync --help'; do
  read -r -a arguments <<<"${command}"
  PATH="${FIXTURE}/bin" "${ROOT}/v3" "${arguments[@]}" >"${FIXTURE}/out" 2>"${FIXTURE}/err"
  [[ -s "${FIXTURE}/out" && ! -s "${FIXTURE}/err" ]] || fail "help failed: ${command}"
done

# Platform fixtures exercise the internal helper without launcher test overrides.
source "${ROOT}/scripts/php_setup_guidance.sh"
check_platform() {
  local expected="$1"
  shift
  printf '%s\n' "$@" >"${FIXTURE}/release"
  [[ "$(PATH="${FIXTURE}/bin" php_setup_platform "${FIXTURE}/release")" == "${expected}" ]] \
    || fail "incorrect platform for: $*"
}
cat >"${FIXTURE}/bin/uname" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' Linux
EOF
cat >"${FIXTURE}/bin/apt-get" <<'EOF'
#!/usr/bin/env bash
printf 'unexpected installation\n' >"${V3_TEST_INSTALL_MARKER}"
exit 99
EOF
cp "${FIXTURE}/bin/apt-get" "${FIXTURE}/bin/sudo"
chmod +x "${FIXTURE}/bin/"{uname,apt-get,sudo}
export V3_TEST_INSTALL_MARKER="${FIXTURE}/installation-attempt"
check_platform ubuntu-24.04 'ID=ubuntu' 'VERSION_ID="24.04"'
check_platform ubuntu-24.04 "ID='ubuntu'" "VERSION_ID='24.04'"
check_platform generic 'ID=ubuntu' 'VERSION_ID=22.04'
check_platform generic 'ID=debian' 'VERSION_ID=24.04'
check_platform generic 'ID=ubuntu'
check_platform generic 'ID=ubuntu' 'ID=ubuntu' 'VERSION_ID=24.04'
check_platform generic 'ID=ubuntu' 'VERSION_ID=24.04' 'VERSION_ID=24.04'
check_platform generic 'ID="ubuntu' 'VERSION_ID=24.04'
check_platform generic 'ID=$(touch injected)' 'VERSION_ID=24.04'
check_platform generic
[[ "$(PATH="${FIXTURE}/bin" php_setup_platform "${FIXTURE}/missing")" == generic ]] || fail 'missing release file'
[[ "$(PATH="${FIXTURE}/bin" php_setup_platform "${FIXTURE}/bin")" == generic ]] || fail 'non-file release input'
# Simulate a read failure even when the suite runs as root.
printf '%s\n' 'ID=ubuntu' 'VERSION_ID=24.04' >"${FIXTURE}/release"
(
  read() { line=''; return 1; }
  [[ "$(PATH="${FIXTURE}/bin" php_setup_platform "${FIXTURE}/release")" == generic ]]
) || fail 'release read failure'
(
  uname() { return 1; }
  [[ "$(php_setup_platform "${FIXTURE}/release")" == generic ]]
) || fail 'failed kernel detection'
(
  uname() { printf '%s\n' Darwin; }
  [[ "$(php_setup_platform "${FIXTURE}/release")" == generic ]]
) || fail 'unsupported kernel'
rm "${FIXTURE}/bin/apt-get"
[[ "$(PATH="${FIXTURE}/bin" php_setup_platform "${FIXTURE}/release")" == generic ]] || fail 'missing APT'
cp "${FIXTURE}/bin/sudo" "${FIXTURE}/bin/apt-get"
# A piped invocation must finish without consuming input or running installers.
if printf 'yes\n' | PATH="${FIXTURE}/bin" "${ROOT}/v3" start --quiet >"${FIXTURE}/out" 2>"${FIXTURE}/err"; then
  fail 'piped missing-PHP startup succeeded'
fi
[[ ! -s "${FIXTURE}/out" && ! -e "${V3_TEST_INSTALL_MARKER}" ]] || fail 'unexpected output or installation'
grep -Fq 'retry' "${FIXTURE}/err" || fail 'piped recovery guidance missing'
# Capture each argument on its own line; a log-file path includes spaces.
cat >"${FIXTURE}/bin/php" <<'EOF'
#!/usr/bin/env bash
if [[ "${1:-}" == -r ]]; then
  printf '80300\n8.3.0\n/fake/php\n'
elif [[ "${1:-}" != */check_local_server_dependencies.php ]]; then
  printf '%s\n' "$@"
fi
EOF
chmod +x "${FIXTURE}/bin/php"
check_forwarding() {
  local address="$1"
  local logging="$2"
  shift 2
  PATH="${FIXTURE}/bin" "${ROOT}/v3" start "$@" >"${FIXTURE}/out" 2>"${FIXTURE}/err"
  {
    printf '%s\n' "${ROOT}/scripts/dev_server.php" "--address=${address}"
    if [[ -n "${logging}" ]]; then
      printf '%s\n' "${logging}" "--log-file=${FIXTURE}/log with spaces.txt"
    fi
  } >"${FIXTURE}/expected"
  cmp "${FIXTURE}/expected" "${FIXTURE}/out" || fail 'startup arguments changed'
  [[ ! -s "${FIXTURE}/err" ]] || fail 'unexpected available-PHP guidance'
}
check_forwarding '127.0.0.1:8000' ''
check_forwarding '127.0.0.1:8123' '' 8123
check_forwarding '127.0.0.1:8123' '' :8123
check_forwarding '0.0.0.0:8000' '' --listen-all
check_forwarding '127.0.0.1:8124' '--quiet' '127.0.0.1:8124' --quiet "--log-file=${FIXTURE}/log with spaces.txt"
check_forwarding '127.0.0.1:8000' '--verbose' --verbose "--log-file=${FIXTURE}/log with spaces.txt"
echo 'PASS: missing PHP, recovery forwarding, and PHP-independent help'
