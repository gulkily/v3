(function () {
  function setFeedback(node, message, kind) {
    if (!node) {
      return;
    }

    node.textContent = message;
    node.className = `card feedback feedback-${kind}`;
    node.hidden = false;
  }

  async function approveUser(profileSlug) {
    const signing = window.ForumBrowserSigning || null;
    if (!signing || typeof signing.submitSignedApproval !== "function") {
      throw new Error("Browser signing is unavailable. Refresh this page and try again.");
    }

    const result = await signing.submitSignedApproval(profileSlug);
    if (!result || !result.ok) {
      throw new Error(result && result.error ? result.error : "Unable to approve user.");
    }

    return result;
  }

  function bindPendingApprovals(root) {
    const body = root.querySelector('[data-role="pending-approvals-body"]');
    const feedback = root.querySelector('[data-role="pending-approvals-feedback"]');
    const emptyState = root.querySelector('[data-role="pending-approvals-empty"]');
    const tableCard = root.querySelector('[data-role="pending-approvals-table"]');
    const tableElement = root.querySelector('[data-role="pending-approvals-table-element"]');

    root.addEventListener("click", async (event) => {
      const button = event.target instanceof Element
        ? event.target.closest('[data-action="approve-user"]')
        : null;
      if (!button) {
        return;
      }

      event.preventDefault();
      const profileSlug = button.getAttribute("data-profile-slug") || "";
      if (!profileSlug) {
        return;
      }

      button.setAttribute("disabled", "disabled");
      setFeedback(feedback, "Preparing and signing approval...", "ok");

      try {
        await approveUser(profileSlug);

        const row = button.closest("tr");
        const username = row ? row.getAttribute("data-username") || profileSlug : profileSlug;
        if (row) {
          row.remove();
        }

        if (body && body.children.length === 0) {
          if (tableElement) {
            tableElement.hidden = true;
          }
          if (tableCard) {
            tableCard.hidden = true;
          }
          if (emptyState) {
            emptyState.hidden = false;
          }
        }

        setFeedback(feedback, `Approved user ${username}.`, "ok");
      } catch (error) {
        setFeedback(
          feedback,
          error instanceof Error ? error.message : "Unable to approve user.",
          "error"
        );
        button.removeAttribute("disabled");
      }
    });
  }

  function bindProfileApproval(form) {
    const feedback = document.querySelector('[data-role="signed-approval-feedback"]');
    const button = form.querySelector('button[type="submit"]');
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (button && button.disabled) {
        return;
      }

      const profileSlug = form.getAttribute("data-profile-slug") || "";
      if (!profileSlug) {
        return;
      }

      if (button) {
        button.disabled = true;
      }
      if (feedback) {
        feedback.textContent = "Preparing and signing approval...";
        feedback.hidden = false;
      }

      try {
        const result = await approveUser(profileSlug);
        const postId = encodeURIComponent(String(result.postId || ""));
        const commitSha = encodeURIComponent(String(result.commitSha || ""));
        window.location.assign(`/profiles/${encodeURIComponent(profileSlug)}?approval=success&post_id=${postId}&commit=${commitSha}`);
      } catch (error) {
        if (feedback) {
          feedback.textContent = error instanceof Error ? error.message : "Unable to approve user.";
          feedback.hidden = false;
        }
        if (button) {
          button.disabled = false;
        }
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-pending-approvals-root]");
    if (root) {
      bindPendingApprovals(root);
    }

    const profileForm = document.querySelector("[data-signed-approval-form]");
    if (profileForm) {
      bindProfileApproval(profileForm);
    }
  });
})();
