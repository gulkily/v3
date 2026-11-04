#!/usr/bin/env python3
"""./v3 work: search, claim and update ZenMemes work items.

Work items are #work threads; their status comes from `status:` reply lines
(see src/ForumRewrite/Work). Reads use /work.txt; writes post signed replies
through public/frm.py (set V3_WORK_FRM_STATE_DIR to choose its key/recovery
directory; GPG needs a short path for its sockets). When the site is unreachable, claims and status changes
are queued in state/work/pending.jsonl and posted later by `./v3 work flush`.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FRM = ROOT / "public" / "frm.py"
DEFAULT_SITE = "https://zenmemes.com"
STATUSES = ("open", "active", "done", "dropped")


def site_url(args: argparse.Namespace) -> str:
    return (args.site or os.environ.get("V3_WORK_SITE") or DEFAULT_SITE).rstrip("/")


def pending_path() -> Path:
    return Path(os.environ.get("V3_WORK_STATE_DIR", str(ROOT / "state" / "work"))) / "pending.jsonl"


class Unreachable(Exception):
    pass


def fetch_work(site: str, words: list[str], status: str | None, timeout: float = 15) -> str:
    query = {}
    if words:
        query["q"] = " ".join(words)
    if status:
        query["status"] = status
    url = site + "/work.txt" + ("?" + urllib.parse.urlencode(query) if query else "")
    try:
        with urllib.request.urlopen(url, timeout=timeout) as response:
            return response.read().decode("utf-8")
    except urllib.error.HTTPError as error:
        if error.code >= 500:
            raise Unreachable(f"{url} returned HTTP {error.code}") from error
        raise SystemExit(f"{url} returned HTTP {error.code}; is /work.txt deployed on this site?")
    except (urllib.error.URLError, socket.timeout, ConnectionError, OSError) as error:
        raise Unreachable(f"{url}: {error}") from error


def parse_items(text: str) -> list[dict[str, str]]:
    items = []
    for line in text.splitlines():
        if not line or line.startswith("#"):
            continue
        cells = line.split("\t")
        if len(cells) >= 6:
            items.append(dict(zip(("status", "thread", "title", "by", "note", "path", "source"), cells)))
    return items


def default_note() -> str:
    try:
        branch = subprocess.run(["git", "-C", str(ROOT), "rev-parse", "--abbrev-ref", "HEAD"],
                                capture_output=True, text=True, timeout=10).stdout.strip()
    except (OSError, subprocess.SubprocessError):
        branch = ""
    host = socket.gethostname().split(".")[0]
    return " on ".join(part for part in (host, branch) if part)


def run_frm(site: str, frm_command: list[str], body: str, frm_args: list[str]) -> int:
    with tempfile.NamedTemporaryFile("w", suffix=".txt", delete=False, encoding="utf-8") as handle:
        handle.write(body)
        body_file = handle.name
    try:
        state_dir = os.environ.get("V3_WORK_FRM_STATE_DIR")
        global_args = ["--state-dir", state_dir] if state_dir else []
        command = [sys.executable, str(FRM), "--site", site, *global_args, *frm_command, "--body-file", body_file, *frm_args]
        return subprocess.run(command).returncode
    finally:
        os.unlink(body_file)


def post_status(site: str, thread: str, status: str, note: str, frm_args: list[str]) -> int:
    body = f"status: {status}" + (f" - {note}" if note else "") + "\n"
    return run_frm(site, ["reply", "--thread", thread], body, frm_args)


def queue(entry: dict[str, str]) -> None:
    path = pending_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(entry) + "\n")


def write_status(args: argparse.Namespace, status: str) -> int:
    site = site_url(args)
    note = args.note if args.note is not None else (default_note() if status == "active" else "")
    try:
        fetch_work(site, [], None)
    except Unreachable as error:
        queue({"site": site, "thread": args.thread, "status": status, "note": note,
               "frm_args": args.frm_arg, "queued_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())})
        print(f"Site unreachable ({error}).")
        print(f"Saved {status} for {args.thread} locally in {pending_path()}; run ./v3 work flush when the site is back.")
        return 3
    code = post_status(site, args.thread, status, note, args.frm_arg)
    if code != 0:
        print("The reply was not confirmed. Follow frm.py's recovery instructions above before retrying.", file=sys.stderr)
        return code
    return confirm(site, args.thread, status)


def confirm(site: str, thread: str, status: str) -> int:
    try:
        items = {item["thread"]: item for item in parse_items(fetch_work(site, [], None))}
    except Unreachable:
        print("Posted; could not re-read /work.txt to confirm the status.")
        return 0
    item = items.get(thread)
    if item is None:
        print(f"Posted, but {thread} is not a #work thread, so it has no status.")
        return 4
    current = item["status"].split(" ")[0]
    if current != status:
        print(f"Posted, but {thread} still shows '{item['status']}'. Status lines only count from verified,")
        print("approved members; ask an operator to approve this signing identity, then the line takes effect.")
        return 4
    print(f"{thread}: {item['status']}" + (f" · {item['by']}" if item["by"] else "") + f" · {site}{item['path']}")
    return 0


def cmd_search(args: argparse.Namespace) -> int:
    site = site_url(args)
    try:
        text = fetch_work(site, args.words, args.status)
    except Unreachable as error:
        print(f"Site unreachable ({error}). Check again before starting work; queued local claims:", file=sys.stderr)
        path = pending_path()
        print(path.read_text(encoding="utf-8") if path.exists() else "(none)", end="")
        return 3
    sys.stdout.write(text)
    return 0


def cmd_claim(args: argparse.Namespace) -> int:
    site = site_url(args)
    try:
        items = {item["thread"]: item for item in parse_items(fetch_work(site, [], None))}
        item = items.get(args.thread)
        if item is not None and item["status"] == "active" and not args.force:
            print(f"{args.thread} is already active" + (f" ({item['by']}: {item['note']})" if item["by"] else "") + ".")
            print("Pick other work, or pass --force if that claim is yours or abandoned.")
            return 5
    except Unreachable:
        pass
    return write_status(args, "active")


def cmd_status(args: argparse.Namespace) -> int:
    return write_status(args, args.status)


def cmd_flush(args: argparse.Namespace) -> int:
    path = pending_path()
    if not path.exists() or not path.read_text(encoding="utf-8").strip():
        print("Nothing queued.")
        return 0
    entries = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
    remaining = []
    for index, entry in enumerate(entries):
        try:
            fetch_work(entry["site"], [], None)
        except Unreachable as error:
            print(f"Still unreachable ({error}); keeping {len(entries) - index} queued.")
            remaining = entries[index:]
            break
        code = post_status(entry["site"], entry["thread"], entry["status"], entry.get("note", ""), entry.get("frm_args", []))
        if code != 0:
            print(f"Posting {entry['status']} for {entry['thread']} was not confirmed; keeping it and the rest queued.")
            remaining = entries[index:]
            break
        confirm(entry["site"], entry["thread"], entry["status"])
    path.write_text("".join(json.dumps(entry) + "\n" for entry in remaining), encoding="utf-8")
    return 0 if not remaining else 3


TODO_FILE = "todo.txt"
TODO_BASELINE_FILE = "docs/reference/work_tracking.md"


def indent_of(line: str) -> int:
    expanded = line.replace("    ", "\t")
    return len(expanded) - len(expanded.lstrip("\t"))


def todo_fingerprint(text: str) -> str:
    normalized = " ".join(text.split()).lower()
    return "todo.txt#" + hashlib.sha256(normalized.encode("utf-8")).hexdigest()[:12]


def todo_candidates(since: str) -> list[dict[str, str]]:
    """Group lines added to todo.txt since a revision (working tree included).

    An added line starts an item unless it is indented deeper than the line that
    starts the current item and directly follows it, in which case it is a detail.
    Underlines (---), the headings they underline, and `x`-marked done lines (with
    their details) are skipped; the `x` stands in for one leading tab, as in todo.txt.
    """
    diff = subprocess.run(["git", "-C", str(ROOT), "diff", "--unified=0", "--no-color", since, "--", TODO_FILE],
                          capture_output=True, text=True)
    if diff.returncode != 0:
        raise SystemExit(f"git diff {since} -- {TODO_FILE} failed: {diff.stderr.strip()}")
    runs: list[list[str]] = []
    for line in diff.stdout.splitlines():
        if line.startswith("@@"):
            runs.append([])
        elif line.startswith("+") and not line.startswith("+++") and runs:
            runs[-1].append(line[1:])
    items: list[dict[str, str]] = []
    for run in runs:
        current: dict | None = None
        skipping_level: int | None = None
        for index, line in enumerate(run):
            stripped = line.strip()
            if not stripped or set(stripped) <= {"-", "="}:
                current = None
                continue
            following = run[index + 1].strip() if index + 1 < len(run) else ""
            if following and set(following) <= {"-", "="}:
                current = None
                continue
            done = line == "x" or line.startswith(("x\t", "x "))
            level = indent_of(line[1:]) if done else indent_of(line)
            if skipping_level is not None:
                if level > skipping_level:
                    continue
                skipping_level = None
            if done:
                skipping_level = level
                current = None
                continue
            if current is not None and level > current["level"]:
                current["details"].append(stripped)
                continue
            current = {"level": level, "title": stripped, "details": []}
            items.append(current)
    return [{"source": todo_fingerprint(item["title"]), "title": item["title"],
             "body": "\n".join(f"- {detail}" for detail in item["details"])} for item in items]


def todo_baseline() -> str:
    result = subprocess.run(["git", "-C", str(ROOT), "log", "--diff-filter=A", "--format=%H", "-1", "--", TODO_BASELINE_FILE],
                            capture_output=True, text=True)
    revision = result.stdout.strip()
    if not revision:
        raise SystemExit(f"Could not find when {TODO_BASELINE_FILE} was added (shallow clone?); pass --since REV.")
    return revision


def request_candidates(site: str) -> list[dict[str, str]]:
    url = site + "/work.txt?kind=requests"
    try:
        with urllib.request.urlopen(url, timeout=30) as response:
            text = response.read().decode("utf-8")
    except (urllib.error.URLError, socket.timeout, OSError) as error:
        raise Unreachable(f"{url}: {error}") from error
    candidates = []
    for line in text.splitlines():
        cells = line.split("\t")
        if line.startswith("#") or len(cells) < 4:
            continue
        candidates.append({"source": "thread:" + cells[1], "title": cells[2],
                           "body": f"Requested in {site}/threads/{cells[1]}"})
    return candidates


def cmd_sweep(args: argparse.Namespace) -> int:
    site = site_url(args)
    try:
        items = parse_items(fetch_work(site, [], None))
        candidates = request_candidates(site)
    except Unreachable as error:
        print(f"Site unreachable ({error}); nothing swept.", file=sys.stderr)
        return 3
    candidates = todo_candidates(args.since or todo_baseline()) + candidates
    known = {item.get("source", "") for item in items} - {""}
    new = []
    for candidate in candidates:
        if candidate["source"] not in known:
            known.add(candidate["source"])
            new.append(candidate)
    if not new:
        print("No new inbox entries.")
        return 0
    for candidate in new:
        print(("Creating" if args.apply else "Would create") + f": {candidate['title'][:120]}  [{candidate['source']}]")
    if not args.apply:
        print(f"{len(new)} new item(s). Review, then rerun with --apply to create them.")
        return 0
    created = 0
    for candidate in new:
        body = f"Source: {candidate['source']}\n\n{candidate['title']}\n" + (f"\n{candidate['body']}\n" if candidate["body"] else "")
        code = run_frm(site, ["thread", "--tags", "work", "--subject", candidate["title"][:120]], body, args.frm_arg)
        if code != 0:
            print(f"Stopped: creating '{candidate['title'][:60]}' was not confirmed. Follow frm.py's recovery steps; "
                  "rerunning the sweep afterwards skips items that were created.", file=sys.stderr)
            return code
        created += 1
    print(f"Created {created} item(s).")
    return 0


EXPORT_BRANCH = "work-export"
EXPORT_FILE = "work.md"


def git(*args: str, input_text: str | None = None, check: bool = True) -> subprocess.CompletedProcess:
    env = dict(os.environ)
    for setting, name_key, fallback in (("user.name", "NAME", "v3 work export"), ("user.email", "EMAIL", "work-export@localhost")):
        configured = subprocess.run(["git", "-C", str(ROOT), "config", setting], capture_output=True, text=True).stdout.strip()
        if not configured:
            env.setdefault(f"GIT_AUTHOR_{name_key}", fallback)
            env.setdefault(f"GIT_COMMITTER_{name_key}", fallback)
    return subprocess.run(["git", "-C", str(ROOT), *args], input=input_text, capture_output=True, text=True, env=env, check=check)


def cmd_export(args: argparse.Namespace) -> int:
    site = site_url(args)
    try:
        with urllib.request.urlopen(site + "/work.md", timeout=30) as response:
            content = response.read().decode("utf-8")
    except (urllib.error.URLError, socket.timeout, OSError) as error:
        print(f"Could not fetch {site}/work.md ({error}); nothing exported.", file=sys.stderr)
        return 3
    if not content.startswith("# Work items"):
        print(f"{site}/work.md did not return a work items export; nothing exported.", file=sys.stderr)
        return 3
    if not args.no_push:
        git("fetch", "--quiet", "origin", f"+refs/heads/{EXPORT_BRANCH}:refs/remotes/origin/{EXPORT_BRANCH}", check=False)
    parent = ""
    for ref in (f"refs/remotes/origin/{EXPORT_BRANCH}", f"refs/heads/{EXPORT_BRANCH}"):
        result = git("rev-parse", "--verify", "--quiet", ref + "^{commit}", check=False)
        if result.returncode == 0:
            parent = result.stdout.strip()
            break
    if parent:
        previous = git("show", f"{parent}:{EXPORT_FILE}", check=False)
        if previous.returncode == 0 and previous.stdout == content:
            print(f"{EXPORT_BRANCH} is already up to date.")
            return 0
    blob = git("hash-object", "-w", "--stdin", input_text=content).stdout.strip()
    tree = git("mktree", input_text=f"100644 blob {blob}\t{EXPORT_FILE}\n").stdout.strip()
    commit_args = ["commit-tree", tree, "-m", f"Update work items from {site}"]
    if parent:
        commit_args[2:2] = ["-p", parent]
    commit = git(*commit_args).stdout.strip()
    git("update-ref", f"refs/heads/{EXPORT_BRANCH}", commit)
    if args.no_push:
        print(f"Committed {commit[:10]} to local {EXPORT_BRANCH} (not pushed).")
        return 0
    push = git("push", "--quiet", "origin", f"{commit}:refs/heads/{EXPORT_BRANCH}", check=False)
    if push.returncode != 0:
        print(f"Committed {commit[:10]} to local {EXPORT_BRANCH}, but the push failed:\n{push.stderr.strip()}", file=sys.stderr)
        return 6
    print(f"Pushed {commit[:10]} to origin/{EXPORT_BRANCH}.")
    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(prog="./v3 work", description="Search, claim and update ZenMemes work items.")
    parser.add_argument("--site", help=f"site origin (default $V3_WORK_SITE or {DEFAULT_SITE})")
    sub = parser.add_subparsers(dest="command", required=True)

    search = sub.add_parser("search", help="list work items matching all words")
    search.add_argument("words", nargs="*")
    search.add_argument("--status", choices=(*STATUSES, "stale"))
    search.set_defaults(func=cmd_search)

    for name, func, help_text in (("claim", cmd_claim, "mark an item active for this session"),
                                  ("status", cmd_status, "set an item's status")):
        command = sub.add_parser(name, help=help_text)
        command.add_argument("thread")
        if name == "status":
            command.add_argument("status", choices=STATUSES)
        else:
            command.add_argument("--force", action="store_true", help="claim even if already active")
        command.add_argument("--note", help="short note shown with the status (claim default: host on branch)")
        command.add_argument("--frm-arg", action="append", default=[], help="extra argument passed to frm.py, repeatable")
        command.set_defaults(func=func)

    flush = sub.add_parser("flush", help="post claims and status changes queued while offline")
    flush.set_defaults(func=cmd_flush)

    sweep = sub.add_parser("sweep", help="turn new todo.txt lines and #request threads into work items")
    sweep.add_argument("--since", help=f"revision to diff {TODO_FILE} against (default: when work tracking was added)")
    sweep.add_argument("--apply", action="store_true", help="create the items (default: only list them)")
    sweep.add_argument("--frm-arg", action="append", default=[], help="extra argument passed to frm.py, repeatable")
    sweep.set_defaults(func=cmd_sweep)

    export = sub.add_parser("export", help=f"commit the site's /work.md to the {EXPORT_BRANCH} branch")
    export.add_argument("--no-push", action="store_true", help="commit locally only")
    export.set_defaults(func=cmd_export)

    args = parser.parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
