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

source "${root}/scripts/terminal_operator_ui_lib.sh"

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

edit_llm_connection() {
  php "${root}/scripts/terminal_llm_editor.php" || true
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

home_status="Repository: ${FORUM_REPOSITORY_ROOT:-${root}/state/local_repository}"
home_observed=''
refresh_home
choice=refresh
while true; do
  choice="$("${whiptail_bin}" --title 'v3 terminal operator' --default-item "$choice" --menu \
    "$home_status
$home_freshness" 0 0 8 \
    'refresh' 'Refresh' \
    'status' 'Health details' \
    'task-queue' 'Queue details' \
    'fast-score' 'Fastmod details' \
    'config' 'View redacted effective private configuration' \
    'edit-llm' 'Edit LLM connection settings' \
    'flags' 'Open the feature-flags management route in a browser' \
    'commands' 'Launch an existing read-only command' \
    'exit' 'Exit without running a command' \
    3>&1 1>&2 2>&3)" || exit 0

  case "${choice}" in
    refresh) refresh_home ;;
    task-queue) show_command_output "Task queue status" "${root}/v3" task-queue status ;;
    fast-score) show_command_output "Fastmod status" "${root}/v3" fast-score status ;;
    status)
      show_command_output 'Operator status' "${root}/v3" status
      ;;
    config)
      show_private_config
      ;;
    edit-llm)
      edit_llm_connection
      ;;
    flags)
      "${whiptail_bin}" --title 'Site feature flags' --msgbox \
        'Feature flags are managed by the web UI at /tools/feature-flags/. This terminal UI does not edit them.' 9 76 || true
      ;;
    commands)
      show_command_launcher
      ;;
    exit)
      exit 0
      ;;
  esac
done
