// Drives public/assets/post_retraction.js with a minimal DOM stub and prints JSON results.
const fs = require("fs");
const vm = require("vm");

const source = fs.readFileSync(process.argv[2], "utf8");

class Element {}

function makeButton() {
  const attributes = { "data-action": "retract-post", "data-post-id": "thread-1", "data-confirm": "Sure?", "data-done": "Done text." };
  const feedback = { hidden: true, textContent: "", className: "" };
  const row = { querySelector: (selector) => selector === '[data-role="retraction-feedback"]' ? feedback : null };
  const button = Object.assign(new Element(), {
    textContent: "Delete",
    parentElement: row,
    closest: (selector) => selector === '[data-role="retraction-control"]' ? row : selector === '[data-action="retract-post"]' ? button : null,
    hasAttribute: (name) => name in attributes,
    getAttribute: (name) => (name in attributes ? attributes[name] : null),
    setAttribute: (name, value) => { attributes[name] = value; },
    removeAttribute: (name) => { delete attributes[name]; },
  });
  return { button, feedback, attributes };
}

async function scenario(confirmAnswer, submit) {
  const handlers = [];
  const timers = [];
  const calls = { submit: 0, confirm: 0, reload: 0 };
  const context = {
    Element,
    Error,
    document: { addEventListener: (type, handler) => handlers.push({ type, handler }) },
    window: {
      ForumBrowserSigning: submit ? { submitSignedRetraction: async (id) => { calls.submit += 1; return submit(id); } } : {},
      confirm: () => { calls.confirm += 1; return confirmAnswer; },
      setTimeout: (fn) => { timers.push(fn); },
      location: { reload: () => { calls.reload += 1; } },
    },
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  const { button, feedback, attributes } = makeButton();
  handlers.find((h) => h.type === "click").handler({ target: button, preventDefault: () => {} });
  await new Promise((resolve) => setImmediate(resolve));
  timers.forEach((fn) => fn());
  return {
    calls,
    feedback: feedback.textContent,
    feedbackHidden: feedback.hidden,
    feedbackClass: feedback.className,
    disabled: "disabled" in attributes,
    label: button.textContent,
  };
}

(async () => {
  const out = {
    declined: await scenario(false, async () => ({ ok: true, retractionStatus: "complete" })),
    complete: await scenario(true, async () => ({ ok: true, retractionStatus: "complete" })),
    partial: await scenario(true, async () => ({ ok: true, retractionStatus: "partial", failedStep: "read_model_refresh" })),
    failure: await scenario(true, async () => ({ ok: false, error: "Only the post author can retract this post." })),
    unavailable: await scenario(true, null),
  };
  process.stdout.write(JSON.stringify(out));
})();
