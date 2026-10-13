#!/usr/bin/env bash
# Bash owns signals; a control pipe lets PHP clean up even without PCNTL.
local_server_start() {
  local server_pid control_fd read_fd write_fd output_fd result=0 stopping=0 control_ready=0
  stop_local_server() {
    stopping=1
    if (( control_ready )); then
      printf 'stop\n' >&"${control_fd}" 2>/dev/null || true
      exec {control_fd}>&-
      control_ready=0
    fi
  }
  trap stop_local_server INT TERM EXIT
  trap '' PIPE
  exec {output_fd}>&1
  coproc V3_SERVER {
    # Keep PHP alive long enough to receive the control message without PCNTL.
    trap '' INT TERM
    export V3_DEV_CONTROL=1
    exec php "${ROOT}/scripts/dev_server.php" "$@" >&"${output_fd}"
  }
  server_pid=$!
  read_fd="${V3_SERVER[0]}" write_fd="${V3_SERVER[1]}"
  exec {control_fd}>&"${write_fd}"
  exec {read_fd}<&-
  exec {write_fd}>&-
  exec {output_fd}>&-
  control_ready=1
  if (( stopping )); then stop_local_server; fi
  while true; do
    if wait "${server_pid}"; then result=0; else result=$?; fi
    # A trapped signal interrupts wait; wait again until cleanup finishes.
    if ! kill -0 "${server_pid}" 2>/dev/null; then break; fi
  done
  stop_local_server
  trap - INT TERM EXIT PIPE
  return "${result}"
}
