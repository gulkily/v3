// The pledge amount on a campaign's reply form. The amount lives in the reply body as a
// first line "pledge: 300", so signed, unsigned, Outbox and offline sending all carry it
// with no change. This script keeps that line and the amount field in step, both ways.
// Without JavaScript the server writes the line from the field instead.
// A field with data-pledge-presets="1,3,5" gets a button per amount; pressing one fills the field.
(function () {
  "use strict";

  var LINE = /^\s*pledge:\s*(.*?)\s*$/i;

  function parts(form) {
    return {
      amount: form.querySelector('[data-role="pledge-amount"]'),
      body: form.querySelector('textarea[name="body"]'),
    };
  }

  function lineIndex(lines) {
    for (var i = 0; i < lines.length; i += 1) {
      if (!/^\s*>/.test(lines[i]) && LINE.test(lines[i])) return i;
    }
    return -1;
  }

  // Amount field -> body: write, change or remove the line.
  function writeLine(form) {
    var p = parts(form);
    if (!p.amount || !p.body) return;
    var amount = p.amount.value.replace(/\s+/g, " ").trim();
    var lines = p.body.value.split("\n");
    var at = lineIndex(lines);
    if (amount === "") {
      if (at < 0) return;
      lines.splice(at, 1);
      if (at === 0 && lines.length > 0 && lines[0].trim() === "") lines.splice(0, 1);
    } else if (at >= 0) {
      lines[at] = "pledge: " + amount;
    } else {
      var note = p.body.value.replace(/^\s+/, "");
      lines = ["pledge: " + amount].concat(note === "" ? [] : ["", note]);
    }
    var next = lines.join("\n");
    if (next === p.body.value) return;
    p.body.value = next;
    // Drafts and the composer's own checks listen for input on the body.
    p.body.dispatchEvent(new Event("input", { bubbles: true }));
  }

  // Body -> amount field: a typed, restored or cleared line shows in the field.
  function readLine(form) {
    var p = parts(form);
    if (!p.amount || !p.body || document.activeElement === p.amount) return;
    var lines = p.body.value.split("\n");
    var at = lineIndex(lines);
    var amount = at < 0 ? "" : LINE.exec(lines[at])[1];
    if (p.amount.value.trim() !== amount) p.amount.value = amount;
  }

  // Preset buttons after the field's label: each fills the field as typing would.
  function drawPresets(form, amount) {
    var list = (amount.getAttribute("data-pledge-presets") || "").split(",").filter(function (value) {
      return /^[1-9][0-9]*$/.test(value);
    });
    var label = amount.closest("label");
    if (list.length === 0 || !label || form.querySelector('[data-role="pledge-presets"]')) return;
    var unit = form.querySelector(".pledge-field__unit");
    var group = document.createElement("div");
    group.className = "pledge-presets";
    group.setAttribute("data-role", "pledge-presets");
    group.setAttribute("role", "group");
    group.setAttribute("aria-label", "Quick amounts");
    list.forEach(function (value) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "pledge-preset";
      button.textContent = value;
      button.setAttribute("aria-label", "Pledge " + value + (unit ? " " + unit.textContent : ""));
      button.addEventListener("click", function () {
        amount.value = value;
        amount.dispatchEvent(new Event("input", { bubbles: true }));
        group.querySelectorAll(".pledge-preset").forEach(function (other) {
          other.setAttribute("aria-pressed", other === button ? "true" : "false");
        });
      });
      button.setAttribute("aria-pressed", "false");
      group.appendChild(button);
    });
    amount.addEventListener("input", function () {
      group.querySelectorAll(".pledge-preset").forEach(function (button) {
        button.setAttribute("aria-pressed", button.textContent === amount.value.trim() ? "true" : "false");
      });
    });
    label.insertAdjacentElement("afterend", group);
  }

  function bind(form) {
    var p = parts(form);
    if (!p.amount || !p.body || form.dataset.pledgeFieldBound === "1") return;
    form.dataset.pledgeFieldBound = "1";
    drawPresets(form, p.amount);
    var writing = false;
    p.amount.addEventListener("input", function () {
      writing = true;
      writeLine(form);
      writing = false;
    });
    p.body.addEventListener("input", function () {
      if (!writing) readLine(form);
    });
    form.addEventListener("focusin", function () { readLine(form); });
    readLine(form);
    // A draft restored after this script ran shows up here.
    window.setTimeout(function () { readLine(form); }, 600);
  }

  function bindAll() {
    document.querySelectorAll("form[data-compose-form]").forEach(bind);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindAll);
  } else {
    bindAll();
  }
})();
