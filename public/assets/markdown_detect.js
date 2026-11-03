/*
 * Ticks a composer's Markdown box when the draft looks like Markdown, until the author
 * sets the box by hand. Deliberately naive: it looks for a few clear line shapes and
 * inline markers, and the author sees the box before posting.
 */
(function () {
  "use strict";

  var lineShapes = [
    /^ {0,3}#{1,6}\s+\S/,            // heading
    /^ {0,3}[-*+]\s+\S/,              // bullet list
    /^ {0,3}\d{1,9}[.)]\s+\S/,        // numbered list
    /^ {0,3}>\s?\S/,                  // quote
    /^ {0,3}(?:```|~~~)/,             // code fence
    /^ {0,3}\|.*\|\s*$/               // table row
  ];
  var inlineShapes = [
    /\*\*\S(?:[^*\n]*\S)?\*\*/,       // **bold**
    /(^|[^\w*])\*\S(?:[^*\n]*\S)?\*(?![\w*])/, // *emphasis*, not 2 * 3
    /\[[^\]\n]+\]\(https?:\/\/[^\s)]+\)/, // [label](https://...)
    /`[^`\n]+`/                       // `code`
  ];

  function looksLikeMarkdown(text) {
    var value = String(text || "");
    if (value.trim() === "") return false;
    var lines = value.split(/\r\n|\r|\n/);
    for (var i = 0; i < lines.length; i++) {
      for (var j = 0; j < lineShapes.length; j++) {
        if (lineShapes[j].test(lines[i])) return true;
      }
    }
    for (var k = 0; k < inlineShapes.length; k++) {
      if (inlineShapes[k].test(value)) return true;
    }
    return false;
  }

  function bind(form) {
    var toggle = form.querySelector('input[type="checkbox"][name="body_format"]');
    var body = form.querySelector('textarea[name="body"]');
    if (!toggle || !body || toggle.dataset.markdownDetectBound === "1") return;
    toggle.dataset.markdownDetectBound = "1";
    function sync() {
      if (toggle.dataset.markdownManual === "1") return;
      toggle.checked = looksLikeMarkdown(body.value);
    }
    toggle.addEventListener("change", function () { toggle.dataset.markdownManual = "1"; });
    body.addEventListener("input", sync);
    form.addEventListener("click", function (event) {
      var target = event.target;
      if (target && target.closest && target.closest('[data-action="clear-compose-fields"]')) {
        toggle.dataset.markdownManual = "";
        setTimeout(sync, 0);
      }
    });
    sync();
  }

  function bindAll(root) {
    var forms = (root || document).querySelectorAll("form[data-compose-form]");
    for (var i = 0; i < forms.length; i++) bind(forms[i]);
  }

  window.ForumMarkdownDetect = { looksLikeMarkdown: looksLikeMarkdown, bind: bind, bindAll: bindAll };
  if (typeof document !== "undefined" && document.addEventListener) {
    document.addEventListener("DOMContentLoaded", function () { bindAll(document); });
    // Composers inserted later (paned dialogs, inline replies) bind on first focus.
    document.addEventListener("focusin", function (event) {
      var form = event.target && event.target.closest && event.target.closest("form[data-compose-form]");
      if (form) bind(form);
    });
  }
})();
