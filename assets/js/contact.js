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

  /**
   * Wire up the subject <select> so the "Custom subject" field appears only when
   * "Other" is chosen. No-op if the expected fields are absent.
   *
   * @returns {void}
   */
  function initContactForm() {
    var select = document.getElementById("contact-subject");
    var otherRow = document.getElementById("contact-subject-other-row");
    var otherInput = document.getElementById("contact-subject-other");

    if (!select || !otherRow) {
      return;
    }

    /**
     * Show/hide the custom-subject row and toggle its required state to match
     * the current selection.
     *
     * @returns {void}
     */
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
