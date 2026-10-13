#!/usr/bin/env bash
# Internal helper; release information is data, never executable shell input.
php_setup_platform() {
  local release_file="$1" kernel line key value
  local distribution='' version='' seen_id=0 seen_version=0 valid=1
  if ! kernel="$(uname -s 2>/dev/null)" || [[ "${kernel}" != Linux ]] \
    || ! command -v apt-get >/dev/null 2>&1 \
    || [[ ! -f "${release_file}" || ! -r "${release_file}" ]]; then
    printf '%s\n' generic
    return
  fi
  while IFS= read -r line || [[ -n "${line}" ]]; do
    case "${line}" in
      ID=*|VERSION_ID=*)
        key="${line%%=*}"
        value="${line#*=}"
        if [[ "${value}" == \"*\" || "${value}" == \'*\' ]]; then
          value="${value:1:${#value}-2}"
        fi
        if [[ ! "${value}" =~ ^[a-zA-Z0-9._-]+$ ]]; then valid=0; fi
        if [[ "${key}" == ID ]]; then
          (( seen_id == 0 )) || valid=0
          seen_id=1; distribution="${value}"
        else
          (( seen_version == 0 )) || valid=0
          seen_version=1; version="${value}"
        fi
        ;;
    esac
  done <"${release_file}" 2>/dev/null || valid=0
  if [[ "${valid}" == 1 && "${distribution}" == ubuntu && ( "${version}" == 24.04 || "${version}" == 22.04 ) ]]; then
    printf 'ubuntu-%s\n' "${version}"
  else
    printf '%s\n' generic
  fi
}

# One distribution mapping shared by guidance and the package coordinator.
php_setup_mapping() {
  case "$1" in
    ubuntu-22.04) php_series=8.1; php_version_pattern='^801[0-9][0-9]$'; ubuntu_suite=jammy ;;
    ubuntu-24.04) php_series=8.3; php_version_pattern='^803[0-9][0-9]$'; ubuntu_suite=noble ;;
    *) return 1 ;;
  esac
}

php_setup_extension_guidance() {
  local suffix="$1" module="$2" package="php${php_series}-${1}" status
  if ! install_runtime_identity >/dev/null 2>&1; then
    printf '%s' 'Selected PHP is custom or cannot be verified against distribution metadata. Run command -v php, php --ini and php -m; use that CLI installation’s extension instructions. Automatic runtime replacement is unavailable.'
    return
  fi
  status="$(install_package_status "${package}")"
  if [[ "${status}" == installed ]]; then
    printf '%s\n' "${package} is installed; reinstalling is not the next action. Inspect php --ini and php -m, PHPRC and PHP_INI_SCAN_DIR."
    printf '%s\n' "For the standard /etc/php/${php_series}/cli configuration, inspect /etc/php/${php_series}/cli/conf.d and /etc/php/${php_series}/mods-available/${module}.ini."
    printf '%s' "If its CLI link is disabled, enable it manually: sudo phpenmod -v ${php_series} -s cli ${module}. Otherwise repair the selected configuration or host restrictions; then verify php -m and ./v3 check-dependencies."
  elif [[ -z "${status}" || "${status}" == not-installed || "${status}" == config-files ]] && install_candidate "${package}" >/dev/null; then
    printf '%s' "Missing ${package}. Run sudo apt-get update, then sudo apt-get install ${package} (administrator privileges; omit sudo as root). Verify ./v3 check-dependencies and retry the original command."
  else
    printf '%s' "No verified installable ${package} candidate. Inspect apt-cache policy ${package}, dpkg --audit and APT sources; refresh metadata manually with sudo apt-get update, then rerun ./v3 check-dependencies."
  fi
}
