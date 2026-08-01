/**
 * CustomCore — Checkout form client-side validation (Commit 6.4)
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Enhances the checkout form with immediate field validation feedback.
 *   Server validation remains authoritative; this is progressive enhancement.
 *
 * Loaded from includes/footer.php when $currentPage === 'checkout'.
 * ----------------------------------------------------------------------------
 */

(function (window, document) {
  "use strict";

  var REQUIRED_FIELDS = [
    { id: "shipping_name", label: "Full name", maxLen: 200 },
    { id: "shipping_phone", label: "Phone number", maxLen: 30, pattern: /^[0-9+()\-.\s]+$/ },
    { id: "shipping_addr1", label: "Address line 1", maxLen: 255 },
    { id: "shipping_city", label: "City", maxLen: 100 },
    { id: "shipping_prov", label: "Province / State", maxLen: 100 },
    { id: "shipping_postal", label: "Postal / ZIP code", maxLen: 20 },
  ];

  /**
   * Show or clear an inline error for a field.
   *
   * @param {HTMLElement} input
   * @param {string|null} message
   * @returns {void}
   */
  function setFieldError(input, message) {
    var errId = "js-err-" + input.id;
    var existing = document.getElementById(errId);

    if (!message) {
      if (existing) {
        existing.remove();
      }
      input.removeAttribute("aria-invalid");
      input.removeAttribute("aria-describedby");
      return;
    }

    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", errId);

    if (existing) {
      existing.textContent = message;
    } else {
      var el = document.createElement("p");
      el.className = "form-error";
      el.id = errId;
      el.textContent = message;
      input.parentNode.appendChild(el);
    }
  }

  /**
   * Validate a single required text field.
   *
   * @param {HTMLInputElement} input
   * @param {object} rule
   * @returns {boolean}
   */
  function validateField(input, rule) {
    var value = (input.value || "").trim();

    if (value === "") {
      setFieldError(input, rule.label + " is required.");
      return false;
    }

    if (rule.maxLen && value.length > rule.maxLen) {
      setFieldError(input, rule.label + " must be " + rule.maxLen + " characters or fewer.");
      return false;
    }

    if (rule.pattern && !rule.pattern.test(value)) {
      setFieldError(input, rule.label + " contains invalid characters.");
      return false;
    }

    setFieldError(input, null);
    return true;
  }

  /**
   * Validate payment method selection.
   *
   * @param {HTMLFormElement} form
   * @returns {boolean}
   */
  function validatePayment(form) {
    var radios = form.querySelectorAll('input[name="payment_method"]');
    var checked = false;

    Array.prototype.forEach.call(radios, function (radio) {
      if (radio.checked) {
        checked = true;
      }
    });

    return checked;
  }

  /**
   * Initialise checkout form validation.
   *
   * @returns {void}
   */
  function initCheckout() {
    var form = document.querySelector("[data-checkout-form]");
    if (!form) {
      return;
    }

    // Validate on blur for each required field.
    REQUIRED_FIELDS.forEach(function (rule) {
      var input = document.getElementById(rule.id);
      if (!input) {
        return;
      }
      input.addEventListener("blur", function () {
        validateField(input, rule);
      });
    });

    // Validate on submit.
    form.addEventListener("submit", function (event) {
      var allValid = true;

      REQUIRED_FIELDS.forEach(function (rule) {
        var input = document.getElementById(rule.id);
        if (!input) {
          return;
        }
        if (!validateField(input, rule)) {
          allValid = false;
        }
      });

      if (!validatePayment(form)) {
        allValid = false;
      }

      if (!allValid) {
        event.preventDefault();
        var firstInvalid = form.querySelector("[aria-invalid='true']");
        if (firstInvalid && typeof firstInvalid.focus === "function") {
          firstInvalid.focus();
        }
      }
    });
  }

  if (window.CustomCore && typeof window.CustomCore.onReady === "function") {
    window.CustomCore.onReady(initCheckout);
  } else if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCheckout);
  } else {
    initCheckout();
  }
})(window, document);
