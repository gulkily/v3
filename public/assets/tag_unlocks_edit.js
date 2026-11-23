// /tools/tag-unlocks/: an operator previews tagsets text, then signs and saves it, or resets
// to the shipped rules. Save stays off until the current text has previewed without errors.
(function () {
  var form = document.querySelector("[data-tagsets-form]");
  if (!form) return;
  var text = form.querySelector("[data-tagsets-text]");
  var previewButton = form.querySelector("[data-tagsets-preview]");
  var saveButton = form.querySelector("[data-tagsets-save]");
  var resetButton = form.querySelector("[data-tagsets-reset]");
  var shipped = form.querySelector("[data-tagsets-shipped]");
  var status = form.querySelector("[data-tagsets-status]");
  var result = form.querySelector("[data-tagsets-result]");
  var previewed = null; // the text whose preview passed
  var operation = "set";
  var busy = false;

  function setStatus(message, isError) {
    status.hidden = message === "";
    status.textContent = message;
    status.classList.toggle("tag-unlocks-error", !!isError);
  }

  function requestJson(path, fields) {
    return window.fetch(path, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(fields).toString()
    }).then(function (response) {
      return response.text().then(function (body) {
        var data;
        try {
          data = JSON.parse(body);
        } catch (error) {
          data = { status: "error", error: "Unexpected response. Try again." };
        }
        if (!response.ok || data.status !== "ok") throw new Error(data.error || "Unable to save tagsets.");
        return data;
      });
    });
  }

  function element(tag, className, content) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (content !== undefined) node.textContent = content;
    return node;
  }

  function showPreview(data) {
    result.replaceChildren();
    result.hidden = false;
    if (data.errors.length) {
      var errors = element("ul", "tag-unlocks-errors");
      data.errors.forEach(function (line) { errors.appendChild(element("li", "tag-unlocks-error", line)); });
      result.appendChild(errors);
      return;
    }
    var rules = element("ul", "tag-unlocks-sets");
    if (!data.rules.length) rules.appendChild(element("li", "meta", "No tagsets: nothing is unlocked."));
    data.rules.forEach(function (set) {
      var item = element("li", "tag-unlocks-set");
      var rule = element("p", "tag-unlocks-rule");
      rule.appendChild(element("span", "tag-unlocks-parent", "#" + set.parent));
      rule.appendChild(element("span", "tag-unlocks-arrow", "unlocks"));
      set.unlocks.forEach(function (tag) { rule.appendChild(element("span", "tag-unlocks-tag", tag)); });
      item.appendChild(rule);
      item.appendChild(element("p", "meta", "On " + set.targets.map(function (target) {
        return target === "root" ? "the opening post" : "replies";
      }).join(" and ") + (set.exclusive ? ". One state at a time." : ".")));
      rules.appendChild(item);
    });
    result.appendChild(rules);
    if (data.affected_threads.length) {
      result.appendChild(element("p", "tag-unlocks-error", "These threads have marks that would lose their buttons:"));
      var threads = element("ul", "tag-unlocks-posts");
      data.affected_threads.forEach(function (thread) {
        var item = element("li", "tag-unlocks-post");
        var link = element("a", "", thread.thread_subject || thread.thread_id);
        link.href = "/threads/" + encodeURIComponent(thread.thread_id);
        item.appendChild(link);
        item.appendChild(element("p", "meta", thread.tags.join(", ") + " · " + thread.marks + (thread.marks === 1 ? " mark" : " marks")));
        threads.appendChild(item);
      });
      result.appendChild(threads);
    }
  }

  function preview() {
    if (busy) return Promise.resolve();
    busy = true;
    var current = text.value;
    saveButton.disabled = true;
    setStatus("Checking…");
    return requestJson("/api/preview_tagsets", { text: current }).then(function (data) {
      showPreview(data);
      previewed = data.errors.length ? null : current;
      saveButton.disabled = previewed === null;
      setStatus("");
    }).catch(function (error) {
      setStatus(error.message, true);
    }).finally(function () {
      busy = false;
    });
  }

  function save() {
    if (busy || previewed === null || previewed !== text.value) return;
    busy = true;
    saveButton.disabled = true;
    previewButton.disabled = true;
    setStatus("Saving…");
    var fields = operation === "reset" ? { operation: "reset" } : { operation: "set", text: text.value };
    Promise.resolve().then(function () {
      if (!window.ForumBrowserSigning || !window.ForumBrowserSigning.ensureActionIdentity || !window.ForumBrowserSigning.signCanonicalRecord) {
        throw new Error("Browser signing is unavailable. Reload the page and try again.");
      }
      return window.ForumBrowserSigning.ensureActionIdentity(null, null, { verifyPublishedIdentity: true });
    }).then(function () {
      return requestJson("/api/prepare_tagsets_change", fields);
    }).then(function (prepared) {
      if (prepared.wrote_record === "no") return prepared;
      return window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record).then(function (signature) {
        return requestJson("/api/finalize_tagsets_change", {
          prepare_token: prepared.prepare_token,
          record_id: prepared.record_id,
          record_path: prepared.record_path,
          canonical_record: prepared.canonical_record,
          detached_signature: signature
        });
      });
    }).then(function (saved) {
      setStatus(saved.wrote_record === "no" ? "No change." : "Saved.");
      window.location.reload();
    }).catch(function (error) {
      setStatus(error.message, true);
      saveButton.disabled = false;
    }).finally(function () {
      busy = false;
      previewButton.disabled = false;
    });
  }

  text.addEventListener("input", function () {
    operation = "set";
    previewed = null;
    saveButton.disabled = true;
  });
  previewButton.addEventListener("click", preview);
  form.addEventListener("submit", function (event) {
    event.preventDefault();
    save();
  });
  if (resetButton) {
    resetButton.addEventListener("click", function () {
      text.value = shipped.content ? shipped.content.textContent : shipped.textContent;
      operation = "reset";
      preview();
    });
  }
})();
