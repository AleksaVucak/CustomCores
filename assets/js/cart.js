/**
 * CustomCore — Cart quantity and removal controls (Commit 6.2)
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Enhances cart.php with live line-total / subtotal previews when quantity
 *   changes, +/- steppers, and confirm prompts before remove / clear.
 *   Server still owns authoritative totals after POST.
 *
 * Loaded from includes/footer.php when $currentPage === 'cart'.
 * ----------------------------------------------------------------------------
 */

(function (window, document) {
  "use strict";

  /**
   * Format a number as a USD-style currency string with two decimals.
   *
   * @param {number} amount
   * @returns {string}
   */
  function formatMoney(amount) {
    var safe = Math.round((Number(amount) || 0) * 100) / 100;
    return "$" + safe.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  /**
   * Recalculate every visible line total and the cart subtotal from data attrs.
   *
   * @param {HTMLElement} root
   * @returns {void}
   */
  function refreshTotals(root) {
    var subtotal = 0;
    var rows = root.querySelectorAll("[data-cart-item]");

    Array.prototype.forEach.call(rows, function (row) {
      var unit = parseFloat(row.getAttribute("data-unit-price") || "0");
      var qtyInput = row.querySelector("[data-cart-qty]");
      var lineEl = row.querySelector("[data-cart-line-total]");
      var qty = 1;

      if (qtyInput) {
        qty = parseInt(qtyInput.value, 10);
        if (isNaN(qty) || qty < 0) {
          qty = 0;
        }
      } else if (row.getAttribute("data-item-type") === "saved_build") {
        qty = 1;
      }

      var line = Math.round(unit * qty * 100) / 100;
      subtotal += line;

      if (lineEl) {
        lineEl.textContent = formatMoney(line);
      }
    });

    var subtotalEl = root.querySelector("[data-cart-subtotal]");
    if (subtotalEl) {
      subtotalEl.textContent = formatMoney(subtotal);
    }
  }

  /**
   * Clamp a quantity input to its min/max attributes.
   *
   * @param {HTMLInputElement} input
   * @returns {void}
   */
  function clampInput(input) {
    var min = parseInt(input.getAttribute("min") || "0", 10);
    var max = parseInt(input.getAttribute("max") || "99", 10);
    var value = parseInt(input.value, 10);

    if (isNaN(value)) {
      value = min;
    }

    if (value < min) {
      value = min;
    }
    if (value > max) {
      value = max;
    }

    input.value = String(value);
  }

  /**
   * Wire +/- buttons for one quantity control group.
   *
   * @param {HTMLElement} row
   * @param {HTMLElement} root
   * @returns {void}
   */
  function bindQtyControls(row, root) {
    var input = row.querySelector("[data-cart-qty]");
    var dec = row.querySelector("[data-cart-qty-dec]");
    var inc = row.querySelector("[data-cart-qty-inc]");

    if (!input || input.disabled) {
      return;
    }

    /**
     * Nudge the quantity by delta (+1 / -1), clamp it to the valid range, and
     * refresh the displayed totals.
     *
     * @param {number} delta Amount to add to the current quantity.
     * @returns {void}
     */
    function step(delta) {
      var current = parseInt(input.value, 10);
      if (isNaN(current)) {
        current = 1;
      }
      input.value = String(current + delta);
      clampInput(input);
      refreshTotals(root);
    }

    if (dec) {
      dec.addEventListener("click", function () {
        step(-1);
      });
    }

    if (inc) {
      inc.addEventListener("click", function () {
        step(1);
      });
    }

    input.addEventListener("change", function () {
      clampInput(input);
      refreshTotals(root);
    });

    input.addEventListener("input", function () {
      refreshTotals(root);
    });
  }

  /**
   * Confirm before destructive remove / clear submits.
   *
   * @param {HTMLElement} root
   * @returns {void}
   */
  function bindConfirms(root) {
    var removeButtons = root.querySelectorAll("[data-cart-remove]");
    Array.prototype.forEach.call(removeButtons, function (btn) {
      btn.addEventListener("click", function (event) {
        var name = btn.getAttribute("data-item-name") || "this item";
        var ok = window.confirm('Remove "' + name + '" from your cart?');
        if (!ok) {
          event.preventDefault();
        }
      });
    });

    var clearBtn = root.querySelector("[data-cart-clear]");
    if (clearBtn) {
      clearBtn.addEventListener("click", function (event) {
        var ok = window.confirm(
          "Clear your entire cart? This cannot be undone."
        );
        if (!ok) {
          event.preventDefault();
        }
      });
    }
  }

  /**
   * Initialise cart page enhancements.
   *
   * @returns {void}
   */
  function initCartPage() {
    var root = document.querySelector("[data-cart-page]");
    if (!root) {
      return;
    }

    var rows = root.querySelectorAll("[data-cart-item]");
    Array.prototype.forEach.call(rows, function (row) {
      bindQtyControls(row, root);
    });

    bindConfirms(root);
    refreshTotals(root);
  }

  if (window.CustomCore && typeof window.CustomCore.onReady === "function") {
    window.CustomCore.onReady(initCartPage);
  } else if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCartPage);
  } else {
    initCartPage();
  }
})(window, document);
