import process from "node:process";
import { execFileSync } from "node:child_process";

const DEFAULT_ORIGIN = "http://zenmemes.com";
const RELEASE_USERNAME = "release-check";
const DEFAULT_SUBJECT = "New release just dropped, making sure it works";
const DEFAULT_BODY = "New release just dropped, making sure it works.";
const DEFAULT_TIMEOUT_MS = 90_000;

export function parseOptions(argumentsList, environment = process.env, findExecutable = findChromiumExecutable) {
  const options = {
    confirmProductionWrite: false,
    origin: DEFAULT_ORIGIN,
    browserExecutable: environment.OPENPGP_SMOKE_BROWSER_EXECUTABLE || findExecutable(),
    headed: false,
    help: false,
  };

  for (const argument of argumentsList) {
    if (argument === "--help" || argument === "-h") {
      options.help = true;
    } else if (argument === "--confirm-production-write") {
      options.confirmProductionWrite = true;
    } else if (argument === "--headed") {
      options.headed = true;
    } else if (argument.startsWith("--origin=")) {
      options.origin = argument.slice("--origin=".length);
    } else if (argument.startsWith("--browser-executable=")) {
      options.browserExecutable = argument.slice("--browser-executable=".length);
    } else {
      throw new Error(`Unknown option: ${argument}`);
    }
  }

  options.origin = normalizeHttpOrigin(options.origin);
  if (!options.help && !options.confirmProductionWrite) {
    throw new Error("Refusing to create a production identity and post without --confirm-production-write.");
  }
  if (!options.help && options.browserExecutable === "") {
    throw new Error("Chromium was not found with 'which chromium'. Supply --browser-executable=/path/to/chromium or OPENPGP_SMOKE_BROWSER_EXECUTABLE.");
  }

  return options;
}

export function findChromiumExecutable() {
  try {
    return execFileSync("which", ["chromium"], { encoding: "utf8", stdio: ["ignore", "pipe", "ignore"] }).trim();
  } catch {
    return "";
  }
}

export function normalizeHttpOrigin(value) {
  let parsed;
  try {
    parsed = new URL(String(value));
  } catch {
    throw new Error("--origin must be an HTTP origin, for example http://zenmemes.com.");
  }
  if (parsed.protocol !== "http:" || parsed.username || parsed.password || parsed.pathname !== "/" || parsed.search || parsed.hash) {
    throw new Error("--origin must be an HTTP origin without a path, query, or fragment.");
  }

  return parsed.origin;
}

export function releaseUsername() {
  return RELEASE_USERNAME;
}

/**
 * Runs the durable, manually authorized production canary. `launchBrowser`
 * is injected so the workflow can be tested without reaching a live domain.
 */
export async function runCanary(options, launchBrowser, onProgress = () => {}) {
  const username = releaseUsername();
  const report = {
    passed: false,
    phase: "launching browser",
    origin: options.origin,
    username,
    subject: DEFAULT_SUBJECT,
    postUrl: null,
    promptCount: 0,
    dialogs: [],
    consoleErrors: [],
    expectedHttpResponses: [],
    unexpectedHttpResponses: [],
    failure: null,
  };
  let browser;
  let context;
  const setPhase = (phase) => {
    report.phase = phase;
    onProgress(phase);
  };

  try {
    setPhase("launching isolated browser");
    browser = await launchBrowser({
      headless: !options.headed,
      executablePath: options.browserExecutable,
    });
    context = await browser.newContext();
    const page = await context.newPage();
    let unexpectedDialog = null;

    page.on("dialog", async (dialog) => {
      const details = { type: dialog.type(), message: dialog.message() };
      report.dialogs.push(details);
      if (details.type === "prompt" && details.message.includes("What should we call you?")) {
        report.promptCount += 1;
        if (report.promptCount === 1) {
          await dialog.accept(username);
          return;
        }
        await dialog.dismiss();
        return;
      }

      unexpectedDialog = details;
      await dialog.dismiss();
    });
    page.on("console", (message) => {
      if (message.type() === "error") {
        const text = message.text();
        // Chromium prints an unhelpful generic console error for every HTTP
        // 4xx response. The response listener below records its URL and
        // classifies it, so retain only actual JavaScript/browser errors here.
        if (!text.startsWith("Failed to load resource: the server responded with a status of")) {
          report.consoleErrors.push(text);
        }
      }
    });
    page.on("response", (response) => {
      if (response.status() < 400) {
        return;
      }
      const details = {
        status: response.status(),
        method: response.request().method(),
        resourceType: response.request().resourceType(),
        url: response.url(),
      };
      if (isExpectedFirstIdentityProfileLookup(details, options.origin)) {
        report.expectedHttpResponses.push(details);
      } else {
        report.unexpectedHttpResponses.push(details);
      }
    });

    setPhase("opening HTTP compose page");
    await page.goto(`${options.origin}/compose/thread`, { waitUntil: "domcontentloaded", timeout: DEFAULT_TIMEOUT_MS });
    setPhase("filling release post");
    await page.locator('input[name="board_tags"]').fill("general");
    await page.locator('input[name="subject"]').fill(DEFAULT_SUBJECT);
    await page.locator('textarea[name="body"]').fill(DEFAULT_BODY);

    setPhase("creating identity and publishing release post");
    await page.locator('form[data-compose-kind="thread"] button[type="submit"]:not([data-action="submit-anonymous-compose"])').click();
    await page.waitForURL(/\/threads\//, { timeout: DEFAULT_TIMEOUT_MS });
    report.postUrl = page.url();
    if (unexpectedDialog !== null) {
      throw new Error(`Unexpected browser dialog during first post: ${describeDialog(unexpectedDialog)}`);
    }
    if (report.promptCount !== 1) {
      throw new Error(`Expected exactly one username prompt while creating the identity; observed ${report.promptCount}.`);
    }

    setPhase("reloading published post");
    await page.reload({ waitUntil: "domcontentloaded", timeout: DEFAULT_TIMEOUT_MS });
    const postText = await page.locator("body").innerText();
    if (!postText.includes(DEFAULT_SUBJECT)) {
      throw new Error("Reloaded post does not contain the release-canary subject.");
    }

    setPhase("verifying identity reuse without another prompt");
    await page.goto(`${options.origin}/compose/thread`, { waitUntil: "domcontentloaded", timeout: DEFAULT_TIMEOUT_MS });
    await page.locator('textarea[name="body"]').focus();
    await page.waitForFunction(
      () => Boolean(window.ForumBrowserSigning && window.ForumBrowserSigning.ensureActionIdentity),
      { timeout: DEFAULT_TIMEOUT_MS },
    );
    const identity = await page.evaluate(async () => {
      await window.ForumBrowserSigning.ensureActionIdentity(null, null, { verifyPublishedIdentity: true });
      return {
        username: window.localStorage.getItem("forum_pki_username") || "",
        publicKey: window.localStorage.getItem("forum_pki_public_key") || "",
        privateKey: window.localStorage.getItem("forum_pki_private_key") || "",
        fingerprint: window.localStorage.getItem("forum_pki_fingerprint") || "",
      };
    });
    if (unexpectedDialog !== null) {
      throw new Error(`Unexpected browser dialog while reusing the identity: ${describeDialog(unexpectedDialog)}`);
    }
    if (report.promptCount !== 1) {
      throw new Error(`Identity reuse prompted for a username again; observed ${report.promptCount} prompts.`);
    }
    if (identity.username !== username || !identity.publicKey || !identity.privateKey || !identity.fingerprint) {
      throw new Error("Reloaded browser identity is incomplete or does not match the identity that created the post.");
    }

    report.passed = true;
    setPhase("complete");
    return report;
  } catch (error) {
    report.failure = error instanceof Error ? error.message : String(error);
    return report;
  } finally {
    if (context) {
      await context.close();
    }
    if (browser) {
      await browser.close();
    }
  }
}

export function formatReport(report) {
  const lines = [
    "OpenPGP production canary (manual, durable write)",
    `Origin: ${report.origin}`,
    `Release-check username: ${report.username}`,
    `Release post: ${report.postUrl || "not created"}`,
    `Username prompts: ${report.promptCount}`,
    `Phase: ${report.phase}`,
    `Expected first-identity profile lookup 404s: ${report.expectedHttpResponses.length}`,
    `Unexpected HTTP failures: ${report.unexpectedHttpResponses.length === 0 ? "none" : report.unexpectedHttpResponses.map(describeHttpResponse).join(" | ")}`,
    `Browser console errors: ${report.consoleErrors.length === 0 ? "none" : report.consoleErrors.join(" | ")}`,
    `Result: ${report.passed ? "PASS" : "FAIL"}${report.failure ? ` — ${report.failure}` : ""}`,
  ];

  return lines.join("\n");
}

export function isExpectedFirstIdentityProfileLookup(response, origin) {
  let url;
  try {
    url = new URL(response.url);
  } catch {
    return false;
  }

  return response.status === 404
    && response.method === "GET"
    && url.origin === origin
    && url.pathname === "/api/get_profile";
}

function describeHttpResponse(response) {
  return `HTTP ${response.status} ${response.method} ${response.url}`;
}

function describeDialog(dialog) {
  return `${dialog.type}: ${dialog.message}`;
}

export function printUsage(stream = process.stdout) {
  stream.write("Usage: ./v3 openpgp canary --confirm-production-write [--origin=http://zenmemes.com] [--browser-executable=/path/to/chromium] [--headed]\n\n");
  stream.write("Creates one durable browser identity and the visible post ‘New release just dropped, making sure it works’.\n");
  stream.write("When no browser path is supplied, it uses the result of 'which chromium'.\n");
  stream.write("Run manually and only after ./v3 openpgp smoke passes. Do not rerun after an ambiguous result: inspect the reported post URL first.\n");
}

async function main() {
  try {
    const options = parseOptions(process.argv.slice(2));
    if (options.help) {
      printUsage();
      return 0;
    }
    const { chromium } = await import("playwright-core");
    const report = await runCanary(
      options,
      (launchOptions) => chromium.launch(launchOptions),
      (phase) => process.stdout.write(`[OpenPGP canary] ${phase}...\n`),
    );
    process.stdout.write(`${formatReport(report)}\n`);
    return report.passed ? 0 : 2;
  } catch (error) {
    process.stderr.write(`Error: ${error instanceof Error ? error.message : String(error)}\n\n`);
    printUsage(process.stderr);
    return 1;
  }
}

if (process.argv[1] && new URL(`file://${process.argv[1]}`).href === import.meta.url) {
  process.exitCode = await main();
}
