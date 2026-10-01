import assert from "node:assert/strict";
import test from "node:test";

import { parseOptions, runCanary } from "../../scripts/openpgp_production_canary.mjs";

test("canary requires an explicit production-write confirmation", () => {
  assert.throws(
    () => parseOptions(["--browser-executable=/tmp/chromium"]),
    /--confirm-production-write/,
  );
});

test("canary uses chromium found on PATH when no browser path is supplied", () => {
  const options = parseOptions(
    ["--confirm-production-write"],
    {},
    () => "/usr/bin/chromium",
  );

  assert.equal(options.browserExecutable, "/usr/bin/chromium");
});

test("canary reports a successful first identity, post, reload, and reuse workflow", async () => {
  const page = new FakePage({ repeatPrompt: false });
  const report = await runCanary(
    parseOptions(["--confirm-production-write", "--browser-executable=/tmp/chromium"]),
    async () => new FakeBrowser(page),
    new Date("2026-10-01T12:34:56Z"),
  );

  assert.equal(report.passed, true);
  assert.equal(report.promptCount, 1);
  assert.equal(report.phase, "complete");
  assert.equal(report.postUrl, "http://zenmemes.com/threads/release-canary");
  assert.equal(page.closed, true);
});

test("canary returns structured failure when reuse prompts for a username again", async () => {
  const page = new FakePage({ repeatPrompt: true });
  const report = await runCanary(
    parseOptions(["--confirm-production-write", "--browser-executable=/tmp/chromium"]),
    async () => new FakeBrowser(page),
    new Date("2026-10-01T12:34:56Z"),
  );

  assert.equal(report.passed, false);
  assert.match(report.failure, /prompted for a username again/);
  assert.equal(report.promptCount, 2);
  assert.equal(report.phase, "verifying identity reuse without another prompt");
});

class FakeBrowser {
  constructor(page) {
    this.page = page;
    this.closed = false;
  }

  async newContext() {
    return {
      newPage: async () => this.page,
      close: async () => {
        this.page.closed = true;
      },
    };
  }

  async close() {
    this.closed = true;
  }
}

class FakePage {
  constructor({ repeatPrompt }) {
    this.repeatPrompt = repeatPrompt;
    this.handlers = new Map();
    this.currentUrl = "http://zenmemes.com/compose/thread";
    this.closed = false;
  }

  on(event, handler) {
    this.handlers.set(event, handler);
  }

  async goto(url) {
    this.currentUrl = url;
  }

  locator(selector) {
    return {
      fill: async () => {},
      focus: async () => {},
      click: async () => {
        if (selector.includes("button")) {
          await this.emitUsernamePrompt();
          this.currentUrl = "http://zenmemes.com/threads/release-canary";
        }
      },
      innerText: async () => "New release just dropped, making sure it works",
    };
  }

  async waitForURL() {}

  url() {
    return this.currentUrl;
  }

  async reload() {}

  async waitForFunction() {}

  async evaluate() {
    if (this.repeatPrompt) {
      await this.emitUsernamePrompt();
    }
    return {
      username: "release-check-20261001123456",
      publicKey: "public",
      privateKey: "private",
      fingerprint: "fingerprint",
    };
  }

  async emitUsernamePrompt() {
    const handler = this.handlers.get("dialog");
    await handler({
      type: () => "prompt",
      message: () => "Choose a username for your first post:",
      accept: async () => {},
      dismiss: async () => {},
    });
  }
}
