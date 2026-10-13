#!/usr/bin/env bash
# Opt-in coordinator. Keep ordinary diagnostics independent of package tooling.
install_manual() {
  printf 'Manual recovery required: %s\n' "$*"
  return 1
}

install_package_status() {
  dpkg-query -W -f='${db:Status-Status}' "$1" 2>/dev/null || true
}

# Accept a version only when all its indexed sources are official Noble archives.
# A local dpkg status entry alone does not establish provenance.
install_official_version() {
  local package="$1" version="$2"
  LC_ALL=C apt-cache policy "${package}" 2>/dev/null | awk -v wanted="${version}" '
    $1 == "***" { $1=""; sub(/^ +/, "") }
    NF == 2 && $2 ~ /^[0-9]+$/ { active=($1 == wanted); next }
    active && $1 ~ /^[0-9]+$/ {
      if ($2 == "/var/lib/dpkg/status") next
      if ($2 ~ /^https?:\/\/(archive.ubuntu.com\/ubuntu|security.ubuntu.com\/ubuntu|ports.ubuntu.com\/ubuntu-ports)\/?$/ && $3 ~ /^noble(-updates|-security)?\//) good=1
      else bad=1
    }
    END { exit !(good && !bad) }'
}

install_candidate() {
  local package="$1" candidate
  candidate="$(LC_ALL=C apt-cache policy "${package}" 2>/dev/null | awk '$1 == "Candidate:" {print $2}')" || return 1
  [[ -n "${candidate}" && "${candidate}" != '(none)' ]] || return 1
  install_official_version "${package}" "${candidate}" || return 1
  printf '%s' "${candidate}"
}

install_add_missing() {
  local package="$1" kind="${2:-required}" status candidate
  status="$(install_package_status "${package}")"
  if [[ "${status}" == installed ]]; then
    install_manual "${package} is installed but its capability is unavailable; inspect PATH, php --ini, php -m, and host configuration. No reinstall proposed."
    return 1
  fi
  if [[ -n "${status}" && "${status}" != not-installed && "${status}" != config-files ]]; then
    install_manual "${package} has dpkg state '${status}'; resolve the existing package transaction manually."
    return 1
  fi
  candidate="$(install_candidate "${package}")" || {
    install_manual "no verified Ubuntu 24.04 candidate for ${package}; inspect APT sources and metadata manually."
    return 1
  }
  if [[ "${kind}" == optional ]]; then
    optional_packages+=("${package}")
  else
    required_packages+=("${package}")
  fi
}

install_runtime_identity() {
  local selected identity version binary installed
  selected="$(type -P php)" || return 1
  selected="$(readlink -f "${selected}")" || return 1
  [[ "${selected}" == /usr/bin/php8.3 ]] || return 1
  identity="$(php -r 'echo PHP_VERSION_ID, " ", PHP_BINARY;' 2>/dev/null)" || return 1
  read -r version binary <<<"${identity}"
  [[ "${version}" =~ ^803[0-9][0-9]$ && "${binary}" == /usr/bin/php8.3 ]] || return 1
  [[ "$(dpkg-query -S /usr/bin/php8.3 2>/dev/null)" == 'php8.3-cli: /usr/bin/php8.3' ]] || return 1
  [[ "$(install_package_status php8.3-cli)" == installed ]] || return 1
  installed="$(dpkg-query -W -f='${Version}' php8.3-cli 2>/dev/null)" || return 1
  install_official_version php8.3-cli "${installed}" || return 1
  # Modified CLI executables are custom runtimes even if dpkg still owns the path.
  if dpkg --verify php8.3-cli 2>/dev/null | grep -q ' /usr/bin/php8.3$'; then return 1; fi
  printf '%s' "${selected}"
}

install_assess_batch() {
  local fact key value php_path git_path
  required_packages=(); optional_packages=()
  [[ "$(php_setup_platform /etc/os-release)" == ubuntu-24.04 ]] || {
    install_manual 'automatic installation supports only Ubuntu 24.04/APT with distribution PHP 8.3.'; return 1;
  }
  local tool
  for tool in apt-cache dpkg dpkg-query readlink awk grep; do
    command -v "${tool}" >/dev/null 2>&1 || { install_manual "${tool} is unavailable; repair launcher/package tooling externally."; return 1; }
  done
  git_path="$(command -v git || true)"
  if [[ -z "${git_path}" ]]; then
    [[ ! -e /usr/bin/git ]] || { install_manual 'Git exists outside PATH; repair PATH first.'; return 1; }
    install_add_missing git || return 1
  elif ! git --version >/dev/null 2>&1; then
    install_manual 'selected Git cannot execute; repair PATH or configuration.'; return 1
  fi
  php_path="$(command -v php || true)"
  if [[ -z "${php_path}" ]]; then
    [[ ! -e /usr/bin/php && ! -e /usr/bin/php8.3 ]] || { install_manual 'PHP exists outside PATH; repair PATH first.'; return 1; }
    # Never add a new runtime alongside any already registered PHP CLI package.
    if dpkg-query -W -f='${binary:Package} ${db:Status-Status}\n' 'php*-cli' 2>/dev/null | grep -q ' installed$'; then
      install_manual 'a PHP CLI package is already installed; repair PATH/alternatives manually.'; return 1
    fi
    original_runtime=missing
    install_add_missing php8.3-cli || return 1
    install_add_missing php8.3-sqlite3 || return 1
    install_add_missing php8.3-mbstring || return 1
    [[ "$(install_package_status php8.3-intl)" == installed ]] || install_add_missing php8.3-intl optional || return 1
    return 0
  fi
  original_runtime="$(install_runtime_identity)" || {
    install_manual 'selected PHP is not a verified, unmodified Ubuntu PHP 8.3 CLI; keep this runtime and use its own setup instructions.'; return 1;
  }
  fact="$(php "${ROOT}/scripts/check_local_server_dependencies.php" facts "$(command -v git >/dev/null 2>&1 && echo 1 || echo 0)" ubuntu-24.04)" || true
  local -A facts=()
  while IFS=$'\t' read -r key value; do
    case "${key}" in version|binary|pdo|sqlite|mbstring|intl|ready) facts["${key}"]="${value}" ;; *) install_manual 'unreadable PHP capability facts.'; return 1 ;; esac
  done <<<"${fact}"
  [[ "${facts[binary]:-}" == "${original_runtime}" && "${facts[version]:-}" =~ ^803[0-9][0-9]$ ]] || return 1
  for key in pdo sqlite mbstring intl ready; do
    [[ "${facts[${key}]:-}" =~ ^[01]$ ]] || { install_manual 'incomplete PHP capability facts.'; return 1; }
  done
  [[ "${facts[pdo]}" == 1 ]] || { install_add_missing php8.3-common || return 1; }
  [[ "${facts[sqlite]}" == 1 ]] || { install_add_missing php8.3-sqlite3 || return 1; }
  [[ "${facts[mbstring]}" == 1 ]] || { install_add_missing php8.3-mbstring || return 1; }
  if [[ "${facts[intl]}" == 0 ]]; then
    if [[ "$(install_package_status php8.3-intl)" == installed ]]; then
      printf '%s\n' 'Optional intl is installed but disabled; enable it manually if wanted.'
    else
      install_add_missing php8.3-intl optional || return 1
    fi
  fi
}

local_server_dependencies_install() {
  local -a required_packages=() optional_packages=()
  local original_runtime=''
  local_server_dependencies_check report || true
  printf '\nDependency installation review\n'
  install_assess_batch || return 1
  printf 'Required packages:'; printf ' %s' "${required_packages[@]:-(none)}"; printf '\n'
  printf 'Optional packages (separate choice required):'; printf ' %s' "${optional_packages[@]:-(none)}"; printf '\n'
  printf '%s\n' 'Review only: installation is not yet available. No system changes made.'
  return 1
}
