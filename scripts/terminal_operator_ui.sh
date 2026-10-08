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

json_escape() { local value="$1"; value=${value//\\/\\\\}; value=${value//\"/\\\"}; value=${value//$'\n'/\\n}; printf '%s' "${value}"; }

edit_llm_connection() {
  local provider base_url model timeout api_key preset request
  local key
  for key in LLM_PROVIDER LLM_API_KEY LLM_API_BASE_URL LLM_MODEL LLM_TIMEOUT_SECONDS; do
    if [[ -v "${key}" ]]; then
      "${whiptail_bin}" --title 'LLM connection locked' --msgbox \
        "${key} is set by the environment. Change deployment configuration and restart; this editor will not save partial LLM updates." 10 78 || true
      return 0
    fi
  done
  preset="$("${whiptail_bin}" --title 'LLM provider' --menu 'Select a provider preset.' 16 78 5 openai OpenAI openrouter OpenRouter anthropic Anthropic stub 'Offline stub' custom 'Custom compatible provider' 3>&1 1>&2 2>&3)" || return 0
  case "${preset}" in
    openai) provider=openai; base_url=https://api.openai.com; model=gpt-5-nano ;;
    openrouter) provider=openrouter; base_url=https://openrouter.ai/api; model=openai/gpt-5-nano ;;
    anthropic) provider=anthropic; base_url=https://api.anthropic.com; model=claude-haiku-4-5-20251001 ;;
    stub) provider=stub; base_url=''; model='' ;;
    custom) provider=''; base_url=''; model='' ;;
  esac
  provider="$("${whiptail_bin}" --inputbox 'Provider' 10 78 "${provider}" 3>&1 1>&2 2>&3)" || return 0
  base_url="$("${whiptail_bin}" --inputbox 'Base URL' 10 78 "${base_url}" 3>&1 1>&2 2>&3)" || return 0
  model="$("${whiptail_bin}" --inputbox 'Model' 10 78 "${model}" 3>&1 1>&2 2>&3)" || return 0
  timeout="$("${whiptail_bin}" --inputbox 'Timeout seconds' 10 78 60 3>&1 1>&2 2>&3)" || return 0
  api_key="$("${whiptail_bin}" --passwordbox 'API key (leave blank to keep the current key)' 10 78 3>&1 1>&2 2>&3)" || return 0
  "${whiptail_bin}" --title 'Review LLM change' --yesno "Save this redacted change?\n\nProvider: ${provider}\nBase URL: ${base_url}\nModel: ${model}\nTimeout: ${timeout}\nAPI key: $([[ -n "${api_key}" ]] && printf '<replacement set>' || printf '<unchanged>')" 16 78 || return 0
  request='{"values":{"LLM_PROVIDER":"'"$(json_escape "${provider}")"'","LLM_API_KEY":"'"$(json_escape "${api_key}")"'","LLM_API_BASE_URL":"'"$(json_escape "${base_url}")"'","LLM_MODEL":"'"$(json_escape "${model}")"'","LLM_TIMEOUT_SECONDS":"'"$(json_escape "${timeout}")"'"}}'
  local request_file output_file
  request_file="$(mktemp "${TMPDIR:-/tmp}/v3-tui-request.XXXXXX")"
  output_file="$(mktemp "${TMPDIR:-/tmp}/v3-tui.XXXXXX")"
  chmod 600 "${request_file}"
  printf '%s' "${request}" >"${request_file}"
  if ! "${root}/v3" private-config update-llm <"${request_file}" >"${output_file}" 2>&1; then
    printf '\nUpdate was not saved; correct the reported fields or use ./v3 private-config edit.\n' >>"${output_file}"
  fi
  rm -f "${request_file}"
  "${whiptail_bin}" --title 'LLM connection update' --scrolltext --textbox "${output_file}" 22 100 || true
  rm -f "${output_file}"
}

launch_command() {
  local title="$1"
  local display="$2"
  shift 2

  "${whiptail_bin}" --title 'Run read-only command' --yesno \
    "Run this existing read-only command?\n\n${display}" 10 76 || return 0
  show_command_output "${title}" "$@"
}

show_command_launcher() {
  local choice
  choice="$("${whiptail_bin}" --title 'Read-only commands' --menu \
    'The existing commands perform validation and execution.' 17 78 3 \
    'status' './v3 status — operator health' \
    'task-queue' './v3 task-queue status — queue details' \
    'fast-score' './v3 fast-score status — Fastmod details' \
    3>&1 1>&2 2>&3)" || return 0

  case "${choice}" in
    status)
      launch_command 'Operator status' './v3 status' "${root}/v3" status
      ;;
    task-queue)
      launch_command 'Task queue status' './v3 task-queue status' "${root}/v3" task-queue status
      ;;
    fast-score)
      launch_command 'Fastmod status' './v3 fast-score status' "${root}/v3" fast-score status
      ;;
  esac
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
    'Read-only operator dashboard' 18 78 6 \
    'status' 'View current operator status' \
    'config' 'View redacted effective private configuration' \
    'edit-llm' 'Edit LLM connection settings' \
    'flags' 'Open the feature-flags management route in a browser' \
    'commands' 'Launch an existing read-only command' \
    'exit' 'Exit without running a command' \
    3>&1 1>&2 2>&3)" || exit 0

  case "${choice}" in
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
