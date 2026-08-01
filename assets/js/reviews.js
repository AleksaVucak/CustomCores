/**
 * CustomCore — Review form client-side validation (Commit 7.2)
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Progressive enhancement for the product review form (#review-form).
 *   Server validation remains authoritative.
 *
 * Loaded from includes/footer.php when $loadReviewForm is truthy.
 * ----------------------------------------------------------------------------
 */

(function (window, document) {
  "use strict";

  /**
   * @param {HTMLElement} input
   * @param {string|null} message
   * @returns {void}
   */
  function setFieldError(input, message) {
    var errId = "js-err-" + (input.id || input.name);
    var existing = document.getElementById(errId);
    var container = input.closest(".form-row") || input.closest("fieldset") || input.parentNode;

    if (!message) {
      if (existing) {
        existing.remove();
      }
      input.removeAttribute("aria-invalid");
      input.removeAttribute("aria-describedby");
      if (container && container.classList) {
        container.classList.remove("has-error");
      }
      return;
    }

    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", errId);
    if (container && container.classList) {
      container.classList.add("has-error");
    }

    if (existing) {
      existing.textContent = message;
    } else {
      var el = document.createElement("p");
      el.className = "form-error";
      el.id = errId;
      el.textContent = message;
      container.appendChild(el);
    }
  }

  /**
   * @param {HTMLFormElement} form
   * @returns {boolean}
   */
  function validateReviewForm(form) {
    var ok = true;
    var firstInvalid = null;

    var productSelect = form.querySelector("#review-product");
    if (productSelect) {
      if (!productSelect.value) {
        setFieldError(productSelect, "Please choose a product to review.");
        ok = false;
        firstInvalid = firstInvalid || productSelect;
      } else {
        setFieldError(productSelect, null);
      }
    }

    var ratingChecked = form.querySelector('input[name="rating"]:checked');
    var ratingInputs = form.querySelectorAll('input[name="rating"]');
    var ratingFieldset = form.querySelector(".review-form__rating");
    if (!ratingChecked) {
      if (ratingInputs.length > 0) {
        setFieldError(ratingInputs[0], "Please choose a rating from 1 to 5 stars.");
        ok = false;
        firstInvalid = firstInvalid || ratingInputs[0];
      }
      if (ratingFieldset) {
        ratingFieldset.classList.add("has-error");
      }
    } else {
      if (ratingInputs.length > 0) {
        setFieldError(ratingInputs[0], null);
      }
      if (ratingFieldset) {
        ratingFieldset.classList.remove("has-error");
      }
    }

    var title = form.querySelector("#review-title, #product-review-title");
    if (title) {
      var titleVal = (title.value || "").trim();
      if (titleVal === "") {
        setFieldError(title, "Please enter a review title.");
        ok = false;
        firstInvalid = firstInvalid || title;
      } else if (titleVal.length > 200) {
        setFieldError(title, "Title must be 200 characters or fewer.");
        ok = false;
        firstInvalid = firstInvalid || title;
      } else {
        setFieldError(title, null);
      }
    }

    var body = form.querySelector("#review-body, #product-review-body");
    if (body) {
      var bodyVal = (body.value || "").trim();
      if (bodyVal === "") {
        setFieldError(body, "Please write your review.");
        ok = false;
        firstInvalid = firstInvalid || body;
      } else if (bodyVal.length < 20) {
        setFieldError(body, "Please write at least 20 characters.");
        ok = false;
        firstInvalid = firstInvalid || body;
      } else if (bodyVal.length > 5000) {
        setFieldError(body, "Review must be 5,000 characters or fewer.");
        ok = false;
        firstInvalid = firstInvalid || body;
      } else {
        setFieldError(body, null);
      }
    }

    if (!ok && firstInvalid && typeof firstInvalid.focus === "function") {
      firstInvalid.focus();
    }

    return ok;
  }

  /**
   * @returns {void}
   */
  function initReviewForm() {
    var form = document.getElementById("review-form");
    if (!form) {
      return;
    }

    form.addEventListener("submit", function (event) {
      if (!validateReviewForm(form)) {
        event.preventDefault();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initReviewForm);
  } else {
    initReviewForm();
  }
})(window, document);
