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
      var body = new URLSearchParams(new FormData(form)).toString();
      setPending(form, true);

      window.fetch("/api/set_feature_flag", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body
      }).then(function (response) {
        return response.text().then(function (text) {
          var result = parseTextResponse(text);
          if (!response.ok || result.error) {
            throw new Error(result.error || "Unable to update feature flag.");
          }
          return result;
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
