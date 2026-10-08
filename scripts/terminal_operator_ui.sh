#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

usage() {
  cat <<'EOF'
Usage: ./v3 tui

Opens the interactive terminal operator UI. It requires an interactive terminal
and whiptail. When either is unavailable, run:
  ./v3 status
  ./v3 private-config view
EOF
}

fallback() {
  printf '%s\n' 'Run ./v3 status and ./v3 private-config view instead.' >&2
}

show_command_output() {
  local title="$1"
  shift
  local output_file
  output_file="$(mktemp "${TMPDIR:-/tmp}/v3-tui.XXXXXX")"

  if ! "$@" >"${output_file}" 2>&1; then
    printf '\nCommand exited unsuccessfully; inspect the output above before retrying.\n' >>"${output_file}"
  fi

  "${whiptail_bin}" --title "${title}" --scrolltext --textbox "${output_file}" 22 100 || true
  rm -f "${output_file}"
}

show_private_config() {
  local output_file
  output_file="$(mktemp "${TMPDIR:-/tmp}/v3-tui.XXXXXX")"
  {
    printf '%s\n\n' 'Private configuration (redacted)'
    "${root}/v3" private-config view
    printf '%s\n' ''
    printf '%s\n' 'Environment-origin values are read-only here; change deployment configuration and restart as needed.'
    printf '%s\n' 'Site feature flags are managed on the web at /tools/feature-flags/.'
  } >"${output_file}" 2>&1 || true

  "${whiptail_bin}" --title 'Private configuration' --scrolltext --textbox "${output_file}" 22 100 || true
  rm -f "${output_file}"
}

case "${1:-}" in
  -h|--help)
    usage
    exit 0
    ;;
  '')
    ;;
  *)
    printf 'Unknown tui option: %s\n\n' "$1" >&2
    usage >&2
    exit 1
    ;;
esac

whiptail_bin="${V3_TUI_WHIPTAIL:-whiptail}"
if ! command -v "${whiptail_bin}" >/dev/null 2>&1; then
  printf 'The terminal operator UI requires whiptail.\n' >&2
  fallback
  exit 1
fi

if [[ ! -t 0 || ! -t 1 || ! -t 2 ]]; then
  printf 'The terminal operator UI requires interactive stdin, stdout, and stderr.\n' >&2
  fallback
  exit 1
fi

while true; do
  choice="$("${whiptail_bin}" --title 'v3 terminal operator' --menu \
    'Read-only operator dashboard' 18 78 4 \
    'status' 'View current operator status' \
    'config' 'View redacted effective private configuration' \
    'flags' 'Open the feature-flags management route in a browser' \
    'exit' 'Exit without running a command' \
    3>&1 1>&2 2>&3)" || exit 0

  case "${choice}" in
    status)
      show_command_output 'Operator status' "${root}/v3" status
      ;;
    config)
      show_private_config
      ;;
    flags)
      "${whiptail_bin}" --title 'Site feature flags' --msgbox \
        'Feature flags are managed by the web UI at /tools/feature-flags/. This terminal UI does not edit them.' 9 76 || true
      ;;
    exit)
      exit 0
      ;;
  esac
done
