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
  if [[ "${valid}" == 1 && "${distribution}" == ubuntu && "${version}" == 24.04 ]]; then
    printf '%s\n' ubuntu-24.04
  else
    printf '%s\n' generic
  fi
}
