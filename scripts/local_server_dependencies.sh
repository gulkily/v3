#!/usr/bin/env bash
# Shared, read-only checks for the local PHP development server.
source "${ROOT}/scripts/php_setup_guidance.sh"
source "${ROOT}/scripts/local_server_dependency_install.sh"
missing_php_startup_guidance() {
  if [[ "${1:-startup}" == report ]]; then
    printf '%s\n' 'PHP CLI is not available on PATH.' >&2
  else
    printf '%s\n' 'Cannot start v3: PHP CLI is not available on PATH.' >&2
  fi
  cat >&2 <<EOF
If PHP is already installed, add its executable's directory to PATH in this
terminal. Check that 'php --version' works before installing another copy.
EOF
  local php_series ubuntu_suite php_version_pattern platform
  platform="$(php_setup_platform /etc/os-release)"
  if php_setup_mapping "${platform}"; then
    if [[ -e /usr/bin/php || -e /usr/bin/php${php_series} ]] || dpkg-query -W -f='${db:Status-Status}\n' 'php*-cli' 2>/dev/null | grep -q '^installed$'; then
      printf '%s\n' 'A PHP CLI exists outside the selected PATH. Inspect /usr/bin/php and update-alternatives --display php; restore /usr/bin in PATH or select your existing CLI before retrying. No additional runtime is recommended.' >&2
      return
    fi
    if ! install_candidate "php${php_series}-cli" >/dev/null || ! install_candidate "php${php_series}-sqlite3" >/dev/null || ! install_candidate "php${php_series}-mbstring" >/dev/null; then
      printf '%s\n' "Ubuntu ${platform#ubuntu-}: package metadata is unavailable or unverified. Inspect apt-cache policy php${php_series}-cli and APT sources; run sudo apt-get update manually, then rerun ./v3 check-dependencies for a verified package recipe." >&2
      return
    fi
    cat >&2 <<EOF
Ubuntu ${platform#ubuntu-} with APT: install PHP CLI, SQLite support, and mbstring manually:
  ${cyan:-}sudo apt-get update${reset:-}
  ${cyan:-}sudo apt-get install php${php_series}-cli php${php_series}-sqlite3 php${php_series}-mbstring${reset:-}
These commands require administrator privileges and change system packages.
In an existing root shell, omit sudo. Startup and ordinary checks do not install.
For reviewed, opt-in setup, run ./v3 check-dependencies --install in a terminal.
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

# Reporting keeps one status per line and defers recovery until after the list.
local_server_dependency_line() {
  local status="$1" label="$2" guidance="${3:-}" symbol color
  if [[ "${mode}" == report ]]; then
    case "${status}" in
      OK) symbol='✓'; color="${green}" ;;
      FAIL) symbol='✗'; color="${red}" ;;
      WARN) symbol='!'; color="${amber}" ;;
      SKIP) symbol='?'; color="${dim}" ;;
    esac
    printf '  %s%s %s: %s%s\n' "${color}" "${symbol}" "${status}" "${label}" "${reset}"
    [[ -z "${guidance}" ]] || recovery+=("${label}: ${guidance}")
  elif [[ "${status}" == FAIL || "${status}" == WARN ]]; then
    printf '%s: %s. %s\n' "${status}" "${label}" "${guidance}" >&2
  fi
}

local_server_unchecked_php() {
  local capability
  for capability in 'PDO extension' 'PDO SQLite driver' 'mbstring extension' \
    'Server process execution' 'Local Git execution from PHP' \
    'Optional intl normalization' 'Optional PCNTL signal support' \
    'Optional terminal shell execution'; do
    local_server_dependency_line SKIP "${capability} (requires a usable PHP 8.1+ CLI)"
  done
}

local_server_report_finish() {
  local guidance
  if ! command -v stty >/dev/null 2>&1; then
    local_server_dependency_line WARN 'Optional stty is not available on PATH' \
      'Terminal layout/control uses plain logging. Install terminal utilities only if you want terminal enhancements.'
  else
    local_server_dependency_line OK 'Optional stty executable (terminal access depends on the session)'
  fi
  if [[ "${mode}" == report ]]; then
    if (( failed )); then
      printf '\n%sResult: NOT READY — required dependencies need attention.%s\n' "${red}" "${reset}"
    else
      printf '\n%sResult: READY — required dependencies are available.%s\n' "${green}" "${reset}"
    fi
    printf '%s\n' 'Checkout, database, permissions, and listening address are not validated.'
    [[ -z "${probe_details}" ]] || printf '\n%s\n' "${probe_details}"
    if (( ${#recovery[@]} || missing_php )); then
      printf '\n%sRecovery guidance%s\n' "${bold}" "${reset}"
      for guidance in ${recovery[@]+"${recovery[@]}"}; do printf '%s\n\n' "${guidance}"; done
      if (( missing_php )); then
        missing_php_startup_guidance report 2>&1
      fi
    fi
  fi
}

local_server_dependencies_check() {
  local mode="$1" failed=0 git_ready=1 runtime version_id version binary platform report_fd
  local missing_php=0 probe_details='' green='' red='' amber='' dim='' cyan='' bold='' reset=''
  local -a recovery=() extension_guidance=()
  local php_series ubuntu_suite php_version_pattern
  if [[ "${mode}" == report ]]; then
    if [[ -t 1 && -z "${NO_COLOR+x}" ]]; then
      green=$'\033[32m'; red=$'\033[31m'; amber=$'\033[33m'; dim=$'\033[90m'
      cyan=$'\033[1;36m'; bold=$'\033[1m'; reset=$'\033[0m'
    fi
    printf '%sLocal server dependencies%s\n' "${bold}" "${reset}"
    printf '%s\n\n' '✓ available   ✗ unavailable   ! optional limitation   ? not checked'
  fi
  if (( BASH_VERSINFO[0] < 4 || (BASH_VERSINFO[0] == 4 && BASH_VERSINFO[1] < 1) )); then
    local_server_dependency_line FAIL 'Local-server startup requires Bash 4.1+ for managed shutdown' \
      'Install/select a newer Bash and retry with the same options.'
    failed=1
    local_server_unchecked_php
    local_server_report_finish
    return 1
  fi
  local_server_dependency_line OK 'Bash 4.1+ launcher'
  platform="$(php_setup_platform /etc/os-release)"
  if [[ "${mode}" == report ]]; then printf 'Platform: %s\n' "${platform}"; fi
  if ! command -v git >/dev/null 2>&1; then
    local guidance='Install Git or repair PATH, then retry the same command.'
    if php_setup_mapping "${platform}"; then
      guidance+=$'\nUbuntu/APT (manual, administrator privileges; omit sudo in a root shell):\n  '
      guidance+="${cyan}sudo apt-get install git${reset}"
    fi
    local_server_dependency_line FAIL 'Git is not available on PATH' "${guidance}"
    failed=1; git_ready=0
  elif ! git --version >/dev/null 2>&1; then
    local_server_dependency_line FAIL 'Git is on PATH but cannot run' \
      'Check executable permissions and installation; this is not a repository or credential error.'
    failed=1; git_ready=0
  else
    local_server_dependency_line OK 'Git executable'
  fi
  if ! command -v php >/dev/null 2>&1; then
    failed=1
    if [[ "${mode}" == report ]]; then
      missing_php=1
      local_server_dependency_line FAIL 'PHP CLI is not available on PATH'
      local_server_dependency_line SKIP 'PHP version (requires PHP CLI)'
      local_server_unchecked_php
    else
      missing_php_startup_guidance
      printf '%s\n' 'SKIP: PHP version and runtime capabilities cannot be checked without PHP CLI.' >&2
    fi
    local_server_report_finish
    return 1
  fi
  if ! runtime="$(php -r 'echo PHP_VERSION_ID, "\n", PHP_VERSION, "\n", PHP_BINARY;' 2>/dev/null)"; then
    local_server_dependency_line FAIL 'PHP is on PATH but cannot run' \
      'Check permissions, CLI configuration, and installation; then retry.'
    failed=1
    local_server_unchecked_php
    local_server_report_finish
    return 1
  fi
  { IFS= read -r version_id; IFS= read -r version; IFS= read -r binary || true; } <<<"${runtime}"
  if [[ ! "${version_id}" =~ ^[0-9]{5,6}$ || -z "${binary}" ]]; then
    local_server_dependency_line FAIL 'PHP CLI returned an unreadable runtime identity' \
      'Check CLI configuration and php --version.'
    failed=1
    local_server_unchecked_php
    local_server_report_finish
    return 1
  fi
  if (( 10#${version_id} < 80100 )); then
    local_server_dependency_line FAIL "PHP ${version} is unsupported; local startup requires PHP 8.1+" \
      'Select a supported PHP CLI on PATH and retry with the same options.'
    failed=1
    local_server_unchecked_php
    local_server_report_finish
    return 1
  fi
  local_server_dependency_line OK "PHP ${version} (${binary}), minimum 8.1"
  if php_setup_mapping "${platform}"; then
    extension_guidance+=("$(php_setup_extension_guidance common pdo)" "$(php_setup_extension_guidance sqlite3 pdo_sqlite)" "$(php_setup_extension_guidance mbstring mbstring)" "$(php_setup_extension_guidance intl intl)")
  fi
  if [[ "${mode}" == report ]]; then
    # Keep status rows on the original stdout; collect runtime/recovery details in memory.
    exec {report_fd}>&1
    if ! probe_details="$(php "${ROOT}/scripts/check_local_server_dependencies.php" "${mode}" "${git_ready}" "${platform}" "${extension_guidance[@]}" 2>&1 1>&"${report_fd}")"; then
      failed=1
    fi
    exec {report_fd}>&-
  elif ! php "${ROOT}/scripts/check_local_server_dependencies.php" "${mode}" "${git_ready}" "${platform}" "${extension_guidance[@]}"; then
    failed=1
  fi
  local_server_report_finish
  return "${failed}"
}
