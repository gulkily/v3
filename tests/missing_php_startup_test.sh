#!/usr/bin/env bash
# Standalone launcher regression check: bash tests/missing_php_startup_test.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE="$(mktemp -d)"
trap 'rm -rf "${FIXTURE}"' EXIT
mkdir "${FIXTURE}/bin"
for tool in bash dirname cat; do
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

# Capture each argument on its own line; a log-file path includes spaces.
cat >"${FIXTURE}/bin/php" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$@"
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
