#!/usr/bin/env bash
# Pure fixtures: no real package manager, privileges, or application writes.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${ROOT}/scripts/local_server_dependencies.sh"
source "${ROOT}/scripts/local_server_dependency_install.sh"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
fail() { echo "FAIL: $*" >&2; exit 1; }
# Real policy shape: reject local-only and mixed/untrusted version origins.
apt-cache() { cat "$work/policy"; }
cat > "$work/policy" <<'POLICY'
php8.3-cli:
  Installed: 8.3.6-0ubuntu0.24.04.5
  Candidate: 8.3.6-0ubuntu0.24.04.5
  Version table:
 *** 8.3.6-0ubuntu0.24.04.5 500
        500 http://archive.ubuntu.com/ubuntu noble-updates/main amd64 Packages
        100 /var/lib/dpkg/status
POLICY
[[ "$(install_candidate php8.3-cli)" == 8.3.6-0ubuntu0.24.04.5 ]] || fail official
cp "$work/policy" "$work/good"
sed -i 's@archive.ubuntu.com/ubuntu@ppa.example/ubuntu@' "$work/policy"
if install_candidate php8.3-cli; then fail 'accepted custom source'; fi
cp "$work/good" "$work/policy"
printf '%s\n' '        500 https://ppa.example/ubuntu noble/main amd64 Packages' >> "$work/policy"
if install_candidate php8.3-cli; then fail 'accepted mixed source'; fi
sed '/500 http/d' "$work/good" > "$work/policy"
if install_candidate php8.3-cli; then fail 'accepted local-only runtime'; fi
cp "$work/good" "$work/policy"
required_packages=(); optional_packages=()
install_package_status() { printf '%s' "${fixture_status:-}"; }
fixture_status=installed
if install_add_missing php8.3-mbstring > "$work/out"; then fail 'reinstalled disabled extension'; fi
grep -q 'No reinstall proposed' "$work/out" || fail 'missing config guidance'
fixture_status=half-configured
if install_add_missing php8.3-mbstring > "$work/out"; then fail 'accepted broken package state'; fi
fixture_status=''
install_add_missing php8.3-mbstring
install_add_missing php8.3-intl optional
[[ "${required_packages[*]}" == php8.3-mbstring && "${optional_packages[*]}" == php8.3-intl ]] || fail 'batch separation'
php_setup_platform() { echo generic; }
if install_assess_batch > "$work/out"; then fail 'accepted unsupported host'; fi
grep -q 'only Ubuntu 24.04' "$work/out" || fail 'unsupported guidance'
# Both public aliases must stay equivalent on this real host, without prompts.
set +e
"${ROOT}/v3" check-dependencies --install < /dev/null > "$work/first" 2>&1
first=$?
"${ROOT}/v3" dependency-check --install < /dev/null > "$work/second" 2>&1
second=$?
set -e
[[ "$first" == "$second" ]] && cmp "$work/first" "$work/second" || fail aliases
printf '%s\n' 'PASS: official origins, disabled/broken packages, separate batches, unsupported host, alias equivalence'
