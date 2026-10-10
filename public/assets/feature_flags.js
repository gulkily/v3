(function () {
  function parseTextResponse(text) {
    var result = {};
    text.split(/\n/).forEach(function (line) {
      var index = line.indexOf("=");
      if (index <= 0) {
        return;
      }
      result[line.slice(0, index)] = line.slice(index + 1);
    });
    return result;
  }

  function setPending(form, pending) {
    form.querySelectorAll("button").forEach(function (control) {
      control.disabled = pending;
    });
  }

  function statusElementFor(form) {
    var row = form.closest("[data-feature-flag-row]");
    return form.querySelector("[data-role='feature-flag-status']")
      || (row && row.querySelector("[data-role='feature-flag-status']"));
  }

  function requestJson(path, fields) {
    return window.fetch(path, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(fields).toString()
    }).then(function (response) {
      return response.text().then(function (text) {
        var result;
        try {
          result = JSON.parse(text);
        } catch (error) {
          result = { status: "error", error: "Unable to complete signed feature flag change." };
        }
        if (!response.ok || result.status !== "ok") {
          throw new Error(result.error || "Unable to complete signed feature flag change.");
        }
        return result;
      });
    });
  }

  function updateRow(form, result) {
    var row = form.closest("[data-feature-flag-row]");
    if (!row || !result.effective_value) {
      return;
    }

    var effectiveValue = result.effective_value === "true";
    var isDefault = row.getAttribute("data-flag-default") === String(effectiveValue);
    var stateLabel = row.querySelector("[data-role='feature-flag-effective']");
    var source = row.querySelector("[data-role='feature-flag-source']");
    var switchButton = row.querySelector(".switch");
    var toggleForm = row.querySelector("[data-feature-flag-toggle]");
    var resetForm = row.querySelector(".feature-flag-reset-form");
    var badge = row.querySelector(".badge-overridden");

    if (stateLabel) {
      stateLabel.textContent = effectiveValue ? "enabled" : "disabled";
      stateLabel.classList.toggle("is-on", effectiveValue);
    }
    if (source && result.source) {
      source.textContent = result.source;
    }
    if (switchButton) {
      switchButton.setAttribute("aria-checked", String(effectiveValue));
    }
    if (toggleForm) {
      var valueInput = toggleForm.querySelector("input[name='value']");
      if (valueInput) {
        valueInput.value = String(!effectiveValue);
      }
    }
    if (resetForm) {
      resetForm.hidden = isDefault;
    }
    if (badge) {
      badge.hidden = isDefault;
    } else if (!isDefault) {
      var nameEl = row.querySelector(".feature-flag-name");
      if (nameEl) {
        var newBadge = document.createElement("span");
        newBadge.className = "badge badge-overridden";
        newBadge.textContent = "overridden";
        nameEl.appendChild(newBadge);
      }
    }
  }

  function bindForm(form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();

      var status = statusElementFor(form);
      if (status) {
        status.textContent = "Saving...";
      }
      var fields = Object.fromEntries(new FormData(form).entries());
      setPending(form, true);

      Promise.resolve().then(function () {
        if (!window.ForumBrowserSigning || !window.ForumBrowserSigning.ensureActionIdentity || !window.ForumBrowserSigning.signCanonicalRecord) {
          throw new Error("Browser signing is unavailable. Reload the page and try again.");
        }
        return window.ForumBrowserSigning.ensureActionIdentity(null, null);
      }).then(function () {
        return requestJson("/api/prepare_feature_flag_change", fields);
      }).then(function (prepared) {
        if (prepared.wrote_record === "no") {
          return prepared;
        }
        return window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record).then(function (signature) {
          return requestJson("/api/finalize_feature_flag_change", {
            prepare_token: prepared.prepare_token,
            record_id: prepared.record_id,
            record_path: prepared.record_path,
            canonical_record: prepared.canonical_record,
            detached_signature: signature
          });
        });
      }).then(function (result) {
        updateRow(form, result);
        if (status) {
          status.textContent = "Saved.";
        }
      }).catch(function (error) {
        if (status) {
          status.textContent = error.message;
        }
      }).finally(function () {
        setPending(form, false);
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-feature-flag-form]").forEach(bindForm);
  });
})();
