#!/usr/bin/env bash
# Shared terminal lifecycle helpers. Commands are supplied as argument arrays.
show_command_output() {
  local title="$1"
  shift
  local terminal_state diagnostic_pid='' interrupted=0 result answer
  terminal_state="$(stty -g)"
  while true; do
    interrupted=0
    printf '\n%s\nRunning — Ctrl-C interrupts this diagnostic.\n\n' "$title"
    # Bash job control gives this invocation its own process group. Never signal
    # the TUI's group or an independently running queue worker.
    set -m
    "$@" </dev/null &
    diagnostic_pid=$!
    set +m
    trap 'interrupted=1; stop_diagnostic' INT
    trap 'stop_diagnostic; stty "$terminal_state"; exit 143' TERM HUP
    result=0
    wait "$diagnostic_pid" || result=$?
    stop_diagnostic
    trap - INT TERM HUP
    stty "$terminal_state"
    if (( interrupted )); then
      printf '\nInterrupted.\n'
    elif (( result == 0 )); then
      printf '\nCompleted.\n'
    else
      printf '\nFailed (exit %s).\n' "$result"
    fi
    printf 'Enter: return · r: retry '
    answer=''
    read -r answer || true
    [[ "$answer" == r ]] || break
  done
}

stop_diagnostic() {
  if [[ -n "${diagnostic_pid:-}" ]]; then
    kill -TERM -- "-$diagnostic_pid" 2>/dev/null || true
    # Bound cleanup even when a child ignores TERM.
    sleep 0.15
    kill -KILL -- "-$diagnostic_pid" 2>/dev/null || true
    wait "$diagnostic_pid" 2>/dev/null || true
    diagnostic_pid=''
  fi
}
