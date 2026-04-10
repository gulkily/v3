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
    const response = await fetch("/api/approve_user", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: new URLSearchParams({ profile_slug: profileSlug }).toString(),
    });

    return response.text();
  }

  function parseResponseValue(text, key) {
    const prefix = `${key}=`;
    const line = String(text)
      .split("\n")
      .find((item) => item.startsWith(prefix));

    return line ? line.slice(prefix.length) : "";
  }

  function bindPendingApprovals(root) {
    const body = root.querySelector('[data-role="pending-approvals-body"]');
    const feedback = root.querySelector('[data-role="pending-approvals-feedback"]');
    const emptyState = root.querySelector('[data-role="pending-approvals-empty"]');

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
      setFeedback(feedback, "Approving user...", "ok");

      try {
        const text = await approveUser(profileSlug);
        if (!text.includes("status=ok")) {
          const errorMessage = parseResponseValue(text, "error") || "Unable to approve user.";
          throw new Error(errorMessage);
        }

        const row = button.closest("tr");
        const username = row ? row.getAttribute("data-username") || profileSlug : profileSlug;
        if (row) {
          row.remove();
        }

        if (body && body.children.length === 0 && emptyState) {
          emptyState.hidden = false;
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

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-pending-approvals-root]");
    if (root) {
      bindPendingApprovals(root);
    }
  });
})();
