import process from "node:process";

const DEFAULT_ORIGIN = "http://zenmemes.com";
const DEFAULT_SUBJECT = "New release just dropped, making sure it works";
const DEFAULT_BODY = "New release just dropped, making sure it works.";
const DEFAULT_TIMEOUT_MS = 90_000;

export function parseOptions(argumentsList) {
  const options = {
    confirmProductionWrite: false,
    origin: DEFAULT_ORIGIN,
    browserExecutable: process.env.OPENPGP_SMOKE_BROWSER_EXECUTABLE || "",
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
    throw new Error("A Chromium executable is required. Supply --browser-executable=/path/to/chromium or OPENPGP_SMOKE_BROWSER_EXECUTABLE.");
  }

  return options;
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

export function releaseUsername(now = new Date()) {
  const compact = now.toISOString().replace(/[-:.TZ]/g, "").slice(0, 14);
  return `release-check-${compact}`;
}

/**
 * Runs the durable, manually authorized production canary. `launchBrowser`
 * is injected so the workflow can be tested without reaching a live domain.
 */
export async function runCanary(options, launchBrowser, now = new Date()) {
  const username = releaseUsername(now);
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
    failure: null,
  };
  let browser;
  let context;

  try {
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
      if (details.type === "prompt" && details.message.includes("Choose a username")) {
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
        report.consoleErrors.push(message.text());
      }
    });

    report.phase = "opening HTTP compose page";
    await page.goto(`${options.origin}/compose/thread`, { waitUntil: "domcontentloaded", timeout: DEFAULT_TIMEOUT_MS });
    await page.locator('input[name="board_tags"]').fill("general");
    await page.locator('input[name="subject"]').fill(DEFAULT_SUBJECT);
    await page.locator('textarea[name="body"]').fill(DEFAULT_BODY);

    report.phase = "creating identity and publishing release post";
    await page.locator('form[data-compose-kind="thread"] button[type="submit"]:not([data-action="submit-anonymous-compose"])').click();
    await page.waitForURL(/\/threads\//, { timeout: DEFAULT_TIMEOUT_MS });
    report.postUrl = page.url();
    if (unexpectedDialog !== null) {
      throw new Error(`Unexpected browser dialog during first post: ${describeDialog(unexpectedDialog)}`);
    }
    if (report.promptCount !== 1) {
      throw new Error(`Expected exactly one username prompt while creating the identity; observed ${report.promptCount}.`);
    }

    report.phase = "reloading published post";
    await page.reload({ waitUntil: "domcontentloaded", timeout: DEFAULT_TIMEOUT_MS });
    const postText = await page.locator("body").innerText();
    if (!postText.includes(DEFAULT_SUBJECT)) {
      throw new Error("Reloaded post does not contain the release-canary subject.");
    }

    report.phase = "verifying identity reuse without another prompt";
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
    report.phase = "complete";
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
    `Browser console errors: ${report.consoleErrors.length === 0 ? "none" : report.consoleErrors.join(" | ")}`,
    `Result: ${report.passed ? "PASS" : "FAIL"}${report.failure ? ` — ${report.failure}` : ""}`,
  ];

  return lines.join("\n");
}

function describeDialog(dialog) {
  return `${dialog.type}: ${dialog.message}`;
}

export function printUsage(stream = process.stdout) {
  stream.write("Usage: ./v3 openpgp canary --confirm-production-write [--origin=http://zenmemes.com] [--browser-executable=/path/to/chromium] [--headed]\n\n");
  stream.write("Creates one durable browser identity and the visible post ‘New release just dropped, making sure it works’.\n");
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
    const report = await runCanary(options, (launchOptions) => chromium.launch(launchOptions));
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
