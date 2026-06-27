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
    form.querySelectorAll("button, select").forEach(function (control) {
      control.disabled = pending;
    });
  }

  function updateRow(form, result) {
    var row = form.closest("[data-feature-flag-row]");
    if (!row) {
      return;
    }

    var effective = row.querySelector("[data-role='feature-flag-effective']");
    var source = row.querySelector("[data-role='feature-flag-source']");
    var status = form.querySelector("[data-role='feature-flag-status']");

    if (effective && result.effective_value) {
      effective.textContent = result.effective_value === "true" ? "enabled" : "disabled";
    }
    if (source && result.source) {
      source.textContent = result.source;
    }
    if (status) {
      status.textContent = result.commit_sha ? "Commit: " + result.commit_sha : "Saved.";
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-feature-flag-form]").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();

        var status = form.querySelector("[data-role='feature-flag-status']");
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
        }).catch(function (error) {
          if (status) {
            status.textContent = error.message;
          }
        }).finally(function () {
          setPending(form, false);
        });
      });
    });
  });
})();
