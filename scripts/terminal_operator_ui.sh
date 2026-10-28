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
  "${root}/v3" private-config view >"${output_file}" 2>&1 || true
  tui_dialog --title 'Private configuration' --scrolltext --textbox "${output_file}" 0 0 || true
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
  terminal_ready || exit 0
  menu_height=$((tui_rows - 15))
  (( menu_height <= 8 )) || menu_height=8
  home_prompt="$(compact_home)"
  next_choice="$(tui_dialog --title 'v3 terminal operator' --default-item "$choice" --menu \
    "$home_prompt" 0 0 "$menu_height" \
    'refresh' 'Refresh' \
    'status' 'Health details' \
    'task-queue' 'Queue details' \
    'fast-score' 'Fastmod details' \
    'config' 'Private configuration' \
    'edit-llm' 'LLM connection' \
    'flags' 'Feature flags (web)' \
    'commands' 'Search diagnostics' \
    'exit' 'Exit' \
    3>&1 1>&2 2>&3)" || {
    result=$?
    (( result == 75 )) && continue
    exit 0
  }
  choice="$next_choice"

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
      tui_dialog --title 'Site feature flags' --msgbox \
        '/tools/feature-flags/' 0 0 || true
      ;;
    commands)
      show_command_launcher
      ;;
    exit)
      exit 0
      ;;
  esac
done
