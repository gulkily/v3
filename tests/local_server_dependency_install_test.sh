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
ubuntu_suite=jammy
if install_candidate php8.3-cli; then fail 'accepted Noble packages for Jammy'; fi
sed -i 's/noble/jammy/g' "$work/policy"
install_candidate php8.1-cli >/dev/null || fail 'rejected official Jammy source'
unset ubuntu_suite
if install_candidate php8.3-cli; then fail 'accepted Jammy packages for Noble'; fi
cp "$work/good" "$work/policy"
sed -i 's@archive.ubuntu.com/ubuntu@ppa.example/ubuntu@' "$work/policy"
if install_candidate php8.3-cli; then fail 'accepted custom source'; fi
cp "$work/good" "$work/policy"
printf '%s\n' '        500 https://ppa.example/ubuntu noble/main amd64 Packages' >> "$work/policy"
if install_candidate php8.3-cli; then fail 'accepted mixed source'; fi
sed '/500 http/d' "$work/good" > "$work/policy"
if install_candidate php8.3-cli; then fail 'accepted local-only runtime'; fi
printf '%s\n' '  Candidate: (none)' > "$work/policy"
if install_candidate php8.3-intl; then fail 'accepted unavailable package'; fi
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
grep -q 'Ubuntu 22.04/PHP 8.1 and 24.04/PHP 8.3' "$work/out" || fail 'unsupported guidance'
# Both public aliases must stay equivalent on this real host, without prompts.
set +e
"${ROOT}/v3" check-dependencies --install < /dev/null > "$work/first" 2>&1
first=$?
"${ROOT}/v3" dependency-check --install < /dev/null > "$work/second" 2>&1
second=$?
set -e
[[ "$first" == "$second" ]] && cmp "$work/first" "$work/second" || fail aliases
printf '%s\n' 'PASS: official origins, disabled/broken packages, separate batches, unsupported host, alias equivalence'
# The resolver must refuse removals, runtime switches, and pending dpkg repair.
unset -f php_setup_platform
source "${ROOT}/scripts/php_setup_guidance.sh"
dpkg() { printf '%s' "${fixture_audit:-}"; }
apt-get() { cat "$work/simulation"; }
selected_packages=(php8.3-mbstring)
printf '%s\n' 'Inst php8.3-mbstring (8.3.6-0ubuntu0.24.04.5 Ubuntu:24.04/noble-updates [amd64])' > "$work/simulation"
transaction=(); requested_versions=()
install_resolve > "$work/out"
[[ "${transaction[*]}" == 'php8.3-mbstring=8.3.6-0ubuntu0.24.04.5' ]] || fail 'unpinned transaction'
printf '%s\n' 'Remv php8.3-cli [8.3.6]' >> "$work/simulation"
if install_resolve > "$work/out"; then fail 'accepted removal'; fi
printf '%s\n' 'Inst php8.4-cli (8.4.1 Ubuntu:24.04/noble [amd64])' > "$work/simulation"
if install_resolve > "$work/out"; then fail 'accepted runtime switch'; fi
fixture_audit='Package left unconfigured'
if install_resolve > "$work/out"; then fail 'accepted unfinished dpkg state'; fi
if install_confirm 'EOF fixture' < /dev/null > "$work/out"; then fail 'accepted EOF'; fi
if install_confirm 'Decline fixture' <<< no > "$work/out"; then fail 'accepted decline'; fi
echo 'PASS: resolved version pinning, removal/runtime-switch refusal, dpkg state, decline/EOF'
(
  local_server_dependencies_check() { echo 'Result: READY'; }
  install_runtime_identity() { echo /usr/bin/php8.3; }
  original_runtime=/usr/bin/php8.3
  interrupted=0; package_active=0
  if install_reassess failed > "$work/out"; then fail 'masked package failure with readiness'; fi
  grep -q 'Package operation: failed' "$work/out" || fail 'lost package outcome'
  install_reassess succeeded > "$work/out" || fail 'healthy successful outcome'
  install_runtime_identity() { echo /custom/php; }
  if install_reassess succeeded > "$work/out"; then fail 'accepted changed runtime'; fi
  interrupted=1; package_active=1
  if install_reassess succeeded > "$work/out"; then fail 'accepted active transaction'; fi
  grep -q 'Readiness was not rechecked' "$work/out" || fail 'unsafe active recheck'
)
echo 'PASS: package outcome independent of readiness, runtime change, active-process recovery'
