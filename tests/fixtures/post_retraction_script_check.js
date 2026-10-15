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

async function scenario(confirmAnswer, submit, slow = false) {
  const handlers = [];
  const timers = new Map();
  let nextTimer = 0;
  let finish;
  const calls = { submit: 0, confirm: 0, reload: 0 };
  const context = {
    Element,
    Error,
    document: { addEventListener: (type, handler) => handlers.push({ type, handler }) },
    window: {
      ForumBrowserSigning: submit ? { submitSignedRetraction: async (id) => { calls.submit += 1; if (slow) await new Promise(resolve => { finish = resolve; }); return submit(id); } } : {},
      confirm: () => { calls.confirm += 1; return confirmAnswer; },
      setTimeout: (fn, delay) => { const id = ++nextTimer; timers.set(id, { fn, delay }); return id; },
      clearTimeout: (id) => timers.delete(id),
      location: { reload: () => { calls.reload += 1; } },
    },
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  const { button, feedback, attributes } = makeButton();
  handlers.find((h) => h.type === "click").handler({ target: button, preventDefault: () => {} });
  const initialHidden = feedback.hidden;
  let progress = null;
  if (slow) {
    for (const timer of timers.values()) { if (timer.delay !== 300) throw new Error('Unexpected progress delay'); timer.fn(); }
    progress = feedback.textContent;
    finish();
  }
  await new Promise((resolve) => setImmediate(resolve));
  if (timers.size && !slow) throw new Error('Progress timer was not cancelled');
  return {
    calls,
    feedback: feedback.textContent,
    feedbackHidden: feedback.hidden,
    feedbackClass: feedback.className,
    disabled: "disabled" in attributes,
    label: button.textContent,
    initialHidden,
    progress,
  };
}

(async () => {
  const out = {
    declined: await scenario(false, async () => ({ ok: true, retractionStatus: "complete" })),
    complete: await scenario(true, async () => ({ ok: true, retractionStatus: "complete" })),
    slow: await scenario(true, async () => ({ ok: true, retractionStatus: "complete" }), true),
    partial: await scenario(true, async () => ({ ok: true, retractionStatus: "partial", failedStep: "read_model_refresh" })),
    failure: await scenario(true, async () => ({ ok: false, error: "Only the post author can retract this post." })),
    unavailable: await scenario(true, null),
    thrown: await scenario(true, async () => { throw new Error('Transport failure'); }),
  };
  process.stdout.write(JSON.stringify(out));
})();
