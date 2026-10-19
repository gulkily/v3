(function () {
  "use strict";
  var button = document.querySelector("[data-test-self-approval]");
  if (!button) return;
  button.addEventListener("click", async function () {
    button.disabled = true;
    var status = document.getElementById("simple-status");
    try {
      var response = await fetch("/api/test_self_approval", {
        method: "POST", credentials: "same-origin",
        body: new URLSearchParams({ token: button.dataset.testSelfApproval })
      });
      var result = await response.json();
      if (!response.ok) throw new Error(result.error || "Approval failed. Try again.");
      window.location.reload();
    } catch (error) {
      status.hidden = false;
      status.textContent = error.message;
      button.disabled = false;
    }
  });
}());
