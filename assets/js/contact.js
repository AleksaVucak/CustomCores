/**
 * CustomCore — Contact form progressive enhancement (Commit 7.5)
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Shows the "Custom subject" field when the visitor selects "Other".
 *   Server validation remains authoritative.
 *
 * Loaded from includes/footer.php when $currentPage === 'contact'.
 * ----------------------------------------------------------------------------
 */

(function (document) {
  "use strict";

  function initContactForm() {
    var select = document.getElementById("contact-subject");
    var otherRow = document.getElementById("contact-subject-other-row");
    var otherInput = document.getElementById("contact-subject-other");

    if (!select || !otherRow) {
      return;
    }

    function syncOther() {
      var isOther = select.value === "Other";
      otherRow.hidden = !isOther;
      if (otherInput) {
        if (isOther) {
          otherInput.setAttribute("required", "required");
        } else {
          otherInput.removeAttribute("required");
          otherInput.removeAttribute("aria-invalid");
        }
      }
    }

    select.addEventListener("change", syncOther);
    syncOther();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initContactForm);
  } else {
    initContactForm();
  }
})(document);
