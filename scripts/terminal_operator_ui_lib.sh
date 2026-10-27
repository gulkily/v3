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

# Capture an observation without allowing a slow collector to trap navigation.
bounded_capture() (
  local limit="$1" output diagnostic_pid='' deadline result=0
  shift
  output="$(mktemp "${TMPDIR:-/tmp}/v3-tui-observation.XXXXXX")"
  trap 'stop_diagnostic; rm -f "$output"' EXIT
  trap 'exit 130' INT
  trap 'exit 143' TERM HUP
  set -m
  "$@" >"$output" 2>/dev/null </dev/null &
  diagnostic_pid=$!
  set +m
  deadline=$((SECONDS + limit))
  while kill -0 "$diagnostic_pid" 2>/dev/null; do
    (( SECONDS < deadline )) || exit 124
    sleep 0.05
  done
  wait "$diagnostic_pid" || result=$?
  (( result == 0 )) && cat "$output"
  exit "$result"
)

refresh_home() {
  local observation
  printf '\rRefreshing status…'
  if observation="$(bounded_capture 5 "${root}/v3" status --summary)"; then
    home_status="$observation"
    home_observed="$(date -u '+%H:%M:%S UTC')"
    home_freshness="Observed: $home_observed"
  else
    home_freshness="Status: unavailable; refresh or inspect ./v3 status"
    if [[ -n "${home_observed:-}" ]]; then
      home_freshness+=" (last observation: $home_observed; stale)"
    fi
  fi
  printf '\r\033[K'
}

chooser_filter=''
chooser_selection=status
show_command_launcher() {
  local choice entered key description search_text
  local -a entries
  while true; do
    entries=(search 'Search')
    for key in status task-queue fast-score; do
      case "$key" in
        status) description='./v3 status — operator health' ;;
        task-queue) description='./v3 task-queue status — queue details' ;;
        fast-score) description='./v3 fast-score status — Fastmod details' ;;
      esac
      search_text="${description,,}"
      if [[ "$search_text" == *"${chooser_filter,,}"* ]]; then
        entries+=("$key" "$description")
      fi
    done
    local prompt="${chooser_filter:+Search: $chooser_filter}"
    (( ${#entries[@]} > 2 )) || prompt+=$'\nNo matches. Change or clear Search.'
    choice="$("${whiptail_bin}" --title 'Read-only commands' --default-item "$chooser_selection" \
      --menu "$prompt" 0 0 5 "${entries[@]}" 3>&1 1>&2 2>&3)" || return 0
    chooser_selection="$choice"
    case "$choice" in
      search)
        entered="$("${whiptail_bin}" --inputbox 'Search' 0 0 "$chooser_filter" 3>&1 1>&2 2>&3)" || continue
        chooser_filter="$entered"
        ;;
      status) show_command_output 'Operator status' "${root}/v3" status ;;
      task-queue) show_command_output 'Task queue status' "${root}/v3" task-queue status ;;
      fast-score) show_command_output 'Fastmod status' "${root}/v3" fast-score status ;;
    esac
  done
}
