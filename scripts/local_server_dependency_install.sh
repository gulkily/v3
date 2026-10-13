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
  LC_ALL=C apt-cache policy "${package}" 2>/dev/null | awk -v wanted="${version}" -v suite="${ubuntu_suite:-noble}" '
    $1 == "***" { $1=""; sub(/^ +/, "") }
    NF == 2 && $2 ~ /^[0-9]+$/ { active=($1 == wanted); next }
    active && $1 ~ /^[0-9]+$/ {
      if ($2 == "/var/lib/dpkg/status") next
      if ($2 ~ /^https?:\/\/(archive.ubuntu.com\/ubuntu|security.ubuntu.com\/ubuntu|ports.ubuntu.com\/ubuntu-ports)\/?$/ && $3 ~ ("^" suite "(-updates|-security)?/")) good=1
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
  [[ "${selected}" == /usr/bin/php${php_series:-8.3} ]] || return 1
  identity="$(php -r 'echo PHP_VERSION_ID, " ", PHP_BINARY;' 2>/dev/null)" || return 1
  read -r version binary <<<"${identity}"
  [[ "${version}" =~ ${php_version_pattern:-^803[0-9][0-9]$} && "${binary}" == /usr/bin/php${php_series:-8.3} ]] || return 1
  [[ "$(dpkg-query -S /usr/bin/php${php_series:-8.3} 2>/dev/null)" == "php${php_series:-8.3}-cli: /usr/bin/php${php_series:-8.3}" ]] || return 1
  [[ "$(install_package_status php${php_series:-8.3}-cli)" == installed ]] || return 1
  installed="$(dpkg-query -W -f='${Version}' php${php_series:-8.3}-cli 2>/dev/null)" || return 1
  install_official_version php${php_series:-8.3}-cli "${installed}" || return 1
  # Modified CLI executables are custom runtimes even if dpkg still owns the path.
  if dpkg --verify php${php_series:-8.3}-cli 2>/dev/null | grep -q " /usr/bin/php${php_series:-8.3}\$"; then return 1; fi
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
    [[ "$(install_package_status php8.3-intl)" == installed ]] || install_add_missing php8.3-intl optional || true
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
      install_add_missing php8.3-intl optional || true
    fi
  fi
}

install_confirm() {
  local answer
  printf '%s [type yes to continue]: ' "$1"
  IFS= read -r answer || return 1
  [[ "${answer}" == yes ]]
}

install_resolve() {
  local package version simulation line name rest audit
  transaction=(); requested_versions=()
  audit="$(dpkg --audit 2>&1)" || return 1
  [[ -z "${audit}" ]] || { install_manual 'dpkg reports an unfinished transaction; inspect dpkg --audit manually.'; return 1; }
  for package in "${selected_packages[@]}"; do
    version="$(install_candidate "${package}")" || { install_manual "unverified candidate for ${package}."; return 1; }
    requested_versions+=("${package}=${version}")
  done
  simulation="$(LC_ALL=C apt-get --simulate --no-install-recommends --no-remove install "${requested_versions[@]}" 2>&1)" || {
    printf '%s\n' "${simulation}"; install_manual 'APT could not resolve a safe installation.'; return 1;
  }
  while IFS= read -r line; do
    case "${line}" in
      Remv\ *) install_manual 'APT proposed removing packages; no installation will run.'; return 1 ;;
      Inst\ *)
        read -r _ name rest <<<"${line}"
        version="${rest#*(}"; version="${version%% *}"
        [[ "${name}" =~ ^[a-z0-9][a-z0-9.+:-]*$ && "${version}" =~ ^[a-zA-Z0-9.+:~_-]+$ ]] || return 1
        case "${name%%:*}" in
          php-common|php8.3-cli|php8.3-common|php8.3-opcache|php8.3-readline|php8.3-sqlite3|php8.3-mbstring|php8.3-intl) ;;
          php*|libapache2-mod-php*) install_manual "APT proposed an unapproved PHP runtime package: ${name}."; return 1 ;;
        esac
        install_official_version "${name}" "${version}" || { install_manual "unverified dependency source: ${name} ${version}."; return 1; }
        transaction+=("${name}=${version}")
        ;;
    esac
  done <<<"${simulation}"
  (( ${#transaction[@]} )) || { install_manual 'APT proposed no package changes; repair unavailable capabilities manually.'; return 1; }
  printf '%s\n' "${simulation}"
}

install_interrupt() {
  interrupted=1
  trap '' INT TERM
  printf '\nInstallation interrupted; stopping the package process group. Partial changes are possible.\n'
  if [[ -z "${apt_pgid}" && -s "${apt_pid_file}" ]]; then
    IFS= read -r apt_pgid < "${apt_pid_file}" || true
  fi
  if [[ "${apt_pgid}" =~ ^[0-9]+$ ]]; then
    "${privilege[@]}" /bin/kill -TERM -- "-${apt_pgid}" 2>/dev/null || true
  elif [[ -n "${apt_pid}" ]]; then
    kill -TERM "${apt_pid}" 2>/dev/null || true
  fi
}

install_group_active() {
  ps -eo pgid=,stat= | awk -v group="${apt_pgid}" '$1 == group && $2 !~ /^Z/ {live=1} END {exit !live}'
}

install_privileged_apt() {
  local status=0 count=0 pid_dir
  pid_dir="$(mktemp -d)" || return 1
  apt_pid_file="${pid_dir}/group"
  # Authenticate sudo in the caller's terminal session, then isolate APT. Moving
  # sudo itself into a new session would lose the terminal-scoped credential cache.
  "${privilege[@]}" /usr/bin/setsid --wait /bin/bash -c '
    printf "%s\n" "$$" > "$1"; shift
    exec /usr/bin/env DEBIAN_FRONTEND=noninteractive LC_ALL=C /usr/bin/apt-get "$@"
  ' v3-apt "${apt_pid_file}" "$@" &
  apt_pid=$!
  wait "${apt_pid}" || status=$?
  if [[ -s "${apt_pid_file}" ]]; then IFS= read -r apt_pgid < "${apt_pid_file}" || true; fi
  if (( interrupted )); then
    if [[ "${apt_pgid}" =~ ^[0-9]+$ ]]; then
      "${privilege[@]}" /bin/kill -TERM -- "-${apt_pgid}" 2>/dev/null || true
    fi
    while install_group_active && (( count < 100 )); do
      sleep 0.1
      count=$((count + 1))
    done
    if install_group_active; then
      package_active=1
      printf 'Package processes remain active in process group %s; do not retry or repair dpkg until they exit.\n' "${apt_pgid}"
    fi
    if (( ! package_active )); then wait "${apt_pid}" 2>/dev/null || true; fi
    status=1
  fi
  rm -rf -- "${pid_dir}"
  apt_pid=''; apt_pgid=''; apt_pid_file=''
  return "${status}"
}

install_reassess() {
  local package_status="$1" readiness=0 identity=''
  if (( interrupted )); then package_status=interrupted; fi
  if (( package_active )); then
    printf '%s\n' 'Package operation: interrupted; processes may still be active. Readiness was not rechecked.' \
      'System changes may be partial. Inspect running APT/dpkg processes and logs manually; no automatic rollback, repair, or retry.'
    return 1
  fi
  printf '\nFresh dependency assessment\n'
  local_server_dependencies_check report || readiness=1
  identity="$(install_runtime_identity)" || readiness=1
  if [[ "${identity}" != /usr/bin/php8.3 || ( "${original_runtime}" != missing && "${identity}" != "${original_runtime}" ) ]]; then
    printf '%s\n' 'Selected PHP runtime changed or could not be verified; inspect PATH and alternatives manually.'
    readiness=1
  fi
  printf 'Package operation: %s\n' "${package_status}"
  if [[ "${package_status}" != succeeded ]]; then
    printf '%s\n' 'System packages or APT metadata may have changed. No automatic rollback or retry was attempted.' \
      'Inspect APT output and /var/log/apt/history.log; resolve package state manually before retrying.'
  fi
  printf '%s\n' 'After required checks pass, retry your original ./v3 start command with the same port and logging options.'
  [[ "${package_status}" == succeeded && "${readiness}" == 0 ]]
}

local_server_dependencies_install() {
  local -a required_packages=() optional_packages=() selected_packages=() privilege=()
  local -a transaction=() requested_versions=() reviewed=()
  local original_runtime='' initial_ready=0 apt_pid='' apt_pgid='' apt_pid_file='' interrupted=0 package_active=0
  local_server_dependencies_check report || initial_ready=1
  printf '\nDependency installation review\n'
  install_assess_batch || return 1
  printf 'Required packages:'; printf ' %s' "${required_packages[@]:-(none)}"; printf '\n'
  printf 'Optional packages (separate choice required):'; printf ' %s' "${optional_packages[@]:-(none)}"; printf '\n'
  selected_packages=("${required_packages[@]}")
  if (( ${#selected_packages[@]} == 0 && ${#optional_packages[@]} == 0 )); then
    printf '%s\n' 'No installable package changes. Remaining configuration restrictions need manual recovery.'
    return "${initial_ready}"
  fi
  if [[ ! -t 0 || ! -t 1 ]]; then
    printf '%s\n' 'Installation requires terminal input and output; no unattended confirmation bypass. No system changes made.'
    return 1
  fi
  local package
  for package in "${optional_packages[@]}"; do
    if install_confirm "Include optional ${package}?"; then
      selected_packages+=("${package}")
    else
      printf 'Optional %s not selected.\n' "${package}"
    fi
  done
  if (( ${#selected_packages[@]} == 0 )); then
    printf '%s\n' 'No packages selected. No system changes made.'
    return "${initial_ready}"
  fi
  for package in /usr/bin/setsid /bin/kill; do
    [[ -x "${package}" ]] || { install_manual "${package} is unavailable; repair process-control tools externally."; return 1; }
  done
  for package in ps sleep mktemp rm; do
    command -v "${package}" >/dev/null || { install_manual 'process-control tools unavailable.'; return 1; }
  done
  install_resolve || return 1
  reviewed=("${transaction[@]}")
  printf '%s\n' 'This changes system packages and may upgrade the dependencies listed above.' \
    'Administrator privileges are required. After confirmation, APT metadata will be refreshed.' \
    'Configuration files are preserved; package maintainer scripts will run. No automatic rollback.'
  install_confirm 'Refresh metadata and install this reviewed batch?' || {
    printf '%s\n' 'Cancelled before execution. No system changes made.'; return 1;
  }
  if (( EUID != 0 )); then
    command -v sudo >/dev/null 2>&1 || { install_manual 'sudo is unavailable; ask an administrator or use an existing root shell.'; return 1; }
    sudo -v || { install_manual 'administrator privileges were not obtained; no package changes made.'; return 1; }
    privilege=(sudo -n)
  fi
  trap install_interrupt INT TERM
  if ! install_privileged_apt update -o APT::Update::Error-Mode=any; then
    install_reassess 'metadata refresh failed' || true; return 1
  fi
  # Resolve again after refresh, and obtain new approval if the concrete batch changed.
  if ! install_resolve; then
    install_reassess 'resolution failed after metadata refresh' || true; return 1
  fi
  if (( interrupted )); then install_reassess interrupted || true; return 1; fi
  if [[ "${transaction[*]}" != "${reviewed[*]}" ]]; then
    install_confirm 'APT changed the transaction above. Install this revised batch?' || {
      install_reassess 'cancelled after metadata refresh; no install attempted' || true; return 1;
    }
  fi
  if (( interrupted )); then install_reassess interrupted || true; return 1; fi
  if ! install_privileged_apt --yes --no-remove --no-install-recommends \
    -o Dpkg::Options::=--force-confold install "${transaction[@]}"; then
    install_reassess failed || true; return 1
  fi
  install_reassess succeeded
}
