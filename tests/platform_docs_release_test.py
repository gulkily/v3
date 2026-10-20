#!/usr/bin/env python3
"""Copied-package PHP 8.1, stale release, failed publication and retry acceptance."""
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

project = Path(__file__).resolve().parents[1]
root = Path(tempfile.mkdtemp(prefix="markdown-release-"))
package = root / "application"
package.mkdir()
for name in ["src", "templates", "public", "docs", "scripts", "third_party"]:
    shutil.copytree(project / name, package / name)
for name in ["autoload.php", "v3"]:
    shutil.copy2(project / name, package / name)
shutil.copytree(project / "tests/fixtures/parity_minimal_v1", root / "repository")
database = root / "read.sqlite3"
static = root / "static"
env = dict(os.environ, FORUM_SITE_ID="zenmemes", FORUM_SECRETS_PATH=str(root / "absent.php"),
           FORUM_TASK_QUEUE_LAUNCH_ENABLED="false", VISITOR_STATISTICS_DATABASE_PATH=str(root / "visitors.sqlite3"))
logs = []
def build(shared=False, expect=0):
    cmd = [str(package / "v3"), "build-static"]
    if shared:
        cmd.append("--shared-only")
    cmd += [str(root / "repository"), str(database), str(static)]
    result = subprocess.run(cmd, env=env, capture_output=True, text=True, timeout=120)
    logs.append({"command": cmd, "exit": result.returncode, "output": result.stdout + result.stderr})
    assert (result.returncode == 0) == (expect == 0), result.stdout + result.stderr
    return result

build()
router = root / "router.php"
router.write_text("""<?php
require __DIR__ . '/application/autoload.php';
$root = __DIR__;
if (isset($_GET['dynamic'])) {
    (new ForumRewrite\\Application($root . '/application', $root . '/repository', $root . '/read.sqlite3'))->handle('GET', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
} else {
    (new ForumRewrite\\Host\\FrontController($root . '/application', $root . '/repository', $root . '/read.sqlite3', $root . '/static'))->handle('GET', $_SERVER['REQUEST_URI'], $_COOKIE);
}
""")
with socket.socket() as sock:
    sock.bind(("127.0.0.1", 0))
    port = sock.getsockname()[1]
base = f"http://127.0.0.1:{port}"
server_log = (root / "server.log").open("w")
server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", str(router)], env=env, stdout=server_log, stderr=server_log)
def get(path):
    try:
        with urllib.request.urlopen(base + path, timeout=15) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()

path = "/docs/reference/author_post_retraction.md"
artifact = "docs/reference/author_post_retraction.md.html"
try:
    for _ in range(60):
        try:
            get("/docs/")
            break
        except OSError:
            time.sleep(.1)
    status, dynamic = get(path + "?dynamic=1")
    assert status == 200 and dynamic.count("<table>") == 4
    status, generated = get(path)
    assert status == 200 and "route-source: static-html" in generated
    content = lambda html: re.search(r'<article class="card docs-content">(.*?)</article>', html, re.S)[1]
    assert content(dynamic) == content(generated), "Dynamic/generated content differs"

    prior = (static / "current").resolve()
    (prior / artifact).write_text("<h1>STALE DOCS FIXTURE</h1>")
    assert "STALE DOCS FIXTURE" in get(path)[1]
    build(shared=True)
    recovered = (static / "current").resolve()
    assert recovered != prior
    assert get(path)[1].count("<table>") == 4 and "STALE DOCS FIXTURE" not in get(path)[1]

    vendor = package / "third_party/markdown/vendor"
    # Preserve a complete package for the demonstrated rollback.
    shutil.copytree(vendor, root / "previous-complete-vendor")
    (vendor / "autoload.php").rename(vendor / "autoload.php.missing")
    status, broken = get(path + "?dynamic=1")
    assert status == 503 and "Documentation unavailable" in broken
    assert "<table>" not in broken and "third_party" not in broken
    assert get("/docs/?dynamic=1")[0] == 200
    build(shared=True, expect=1)
    assert (static / "current").resolve() == recovered, "Failed build replaced active release"
    assert get(path)[1].count("<table>") == 4, "Failed build damaged active page"

    # Partial transitive dependency loss has the same controlled failure.
    (vendor / "autoload.php.missing").rename(vendor / "autoload.php")
    missing = vendor / "league/commonmark/src/Environment/Environment.php"
    missing.rename(missing.with_suffix(".missing"))
    assert get(path + "?dynamic=1")[0] == 503

    shutil.rmtree(vendor)
    shutil.copytree(root / "previous-complete-vendor", vendor)
    build(shared=True)
    assert get(path + "?dynamic=1")[0] == 200
    assert get(path)[1].count("<table>") == 4

    # Actual generated delivery through FrontController, not a file-only probe.
    browser_env = dict(env, DOCS_BASE_URL=base, DOCS_EVIDENCE_ROOT=str(root / "browser"))
    browser = subprocess.run(["node", str(project / "tests/browser/platform_docs.mjs")],
                             cwd=project, env=browser_env, capture_output=True, text=True, timeout=120)
    assert browser.returncode == 0, browser.stdout + browser.stderr
    php = subprocess.check_output(["php", "-r", "echo PHP_VERSION;"], text=True)
    report = {"php": php, "composerAtRuntime": False, "dynamicStaticParity": "passed",
              "staleSharedRefreshRetry": "passed", "missingBundle503": "passed",
              "partialBundle503": "passed", "failedBuildRetainsActiveRelease": "passed",
              "completePackageRollbackAndRegeneration": "passed", "staticBrowserScenarios": 8,
              "root": str(root)}
    (root / "report.json").write_text(json.dumps(report, indent=2) + "\n")
    print(json.dumps(report, indent=2))
finally:
    server.terminate()
    server.wait(timeout=10)
    server_log.close()
    (root / "build-logs.json").write_text(json.dumps(logs, indent=2) + "\n")
