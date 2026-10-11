#!/usr/bin/env bash
# Shared, read-only checks for the local PHP development server.
source "${ROOT}/scripts/php_setup_guidance.sh"
missing_php_startup_guidance() {
  cat >&2 <<EOF
Cannot start v3: PHP CLI is not available on PATH.
If PHP is already installed, add its executable's directory to PATH in this
terminal. Check that 'php --version' works before installing another copy.
EOF
  if [[ "$(php_setup_platform /etc/os-release)" == ubuntu-24.04 ]]; then
    cat >&2 <<'EOF'
Ubuntu 24.04 with APT: install PHP CLI, SQLite support, and mbstring manually:
  sudo apt-get update
  sudo apt-get install php8.3-cli php8.3-sqlite3 php8.3-mbstring
These commands require administrator privileges and change system packages.
In an existing root shell, omit sudo. v3 does not run these commands for you.
EOF
  else
    cat >&2 <<'EOF'
Install PHP CLI with SQLite/PDO SQLite and mbstring support using your system's
package manager or PHP installation instructions.
EOF
  fi
  cat >&2 <<EOF
Setup guide: ${ROOT}/README.md (PHP setup, #php-setup)
After manual setup, check 'php --version' in this terminal.
Then retry your ./v3 start command with the same options.
EOF
}

local_server_dependencies_check() {
  local mode="$1" failed=0 git_ready=1 runtime version_id version binary platform
  local output_fd=2
  [[ "${mode}" != report ]] || output_fd=1
  if ! command -v git >/dev/null 2>&1; then
    printf '%s\n' 'FAIL: Git is not available on PATH. Install Git or repair PATH, then retry the same command.' >&"${output_fd}"
    failed=1
    git_ready=0
  elif ! git --version >/dev/null 2>&1; then
    printf '%s\n' 'FAIL: Git is on PATH but cannot run. Check executable permissions and installation; this is not a repository or credential error.' >&"${output_fd}"
    failed=1
    git_ready=0
  elif [[ "${mode}" == report ]]; then
    printf '%s\n' 'OK: Git executable'
  fi
  if ! command -v php >/dev/null 2>&1; then
    missing_php_startup_guidance
    printf '%s\n' 'SKIP: PHP version and runtime capabilities cannot be checked without PHP CLI.' >&"${output_fd}"
    return 1
  fi
  if ! runtime="$(php -r 'echo PHP_VERSION_ID, "\n", PHP_VERSION, "\n", PHP_BINARY;' 2>/dev/null)"; then
    printf '%s\n' 'FAIL: PHP is on PATH but cannot run. Check permissions, CLI configuration, and installation; then retry.' >&"${output_fd}"
    return 1
  fi
  { IFS= read -r version_id; IFS= read -r version; IFS= read -r binary || true; } <<<"${runtime}"
  if [[ ! "${version_id}" =~ ^[0-9]{5,6}$ || -z "${binary}" ]]; then
    printf '%s\n' 'FAIL: PHP CLI returned an unreadable runtime identity. Check CLI configuration and php --version.' >&"${output_fd}"
    return 1
  fi
  if (( 10#${version_id} < 80100 )); then
    printf 'FAIL: PHP %s is unsupported; local startup requires PHP 8.1+. Select a supported PHP CLI on PATH and retry with the same options.\n' "${version}" >&"${output_fd}"
    return 1
  fi
  if [[ "${mode}" == report ]]; then
    printf 'OK: PHP %s (%s), minimum 8.1\n' "${version}" "${binary}"
  fi
  platform="$(php_setup_platform /etc/os-release)"
  if ! php "${ROOT}/scripts/check_local_server_dependencies.php" "${mode}" "${git_ready}" "${platform}"; then
    failed=1
  fi
  return "${failed}"
}
