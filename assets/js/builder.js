/**
 * CustomCore — Live PC Builder price calculator (Commit 5.2)
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Updates the builder summary subtotal and running total immediately when
 *   the user changes a component radio selection. Uses prices from trusted
 *   server-rendered data-price attributes (Commit 5.3 will verify totals on
 *   the server). No network requests — pure client-side recalculation.
 *
 * Expected markup on builder.php:
 *   form#builder-form[data-builder-live][data-other-total][data-category-id]
 *   input.builder-option__radio[data-price][data-name]
 *   #builder-live-subtotal, #builder-live-total
 *   [data-live-category-row] with [data-live-part], [data-live-price],
 *   optional [data-live-empty]
 *
 * Loaded deferred from includes/footer.php when $currentPage === 'builder'.
 * ----------------------------------------------------------------------------
 */

(function (window, document) {
  "use strict";

  /**
   * Format a number as a US-style currency string, or "Included" for zero.
   *
   * @param {number} amount
   * @returns {string}
   */
  function formatMoney(amount) {
    var value = Number(amount);
    if (!isFinite(value) || value <= 0) {
      return "Included";
    }
    return "$" + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  /**
   * Parse a data-price attribute into a finite number (defaults to 0).
   *
   * @param {string|null|undefined} raw
   * @returns {number}
   */
  function parsePrice(raw) {
      var value = parseFloat(String(raw || "0"));
      return isFinite(value) ? value : 0;
  }

  /**
   * Apply (or clear) the selected visual state on option labels.
   *
   * @param {HTMLFormElement} form
   * @param {HTMLInputElement|null} selectedRadio
   * @returns {void}
   */
  function syncSelectedStyles(form, selectedRadio) {
    var options = form.querySelectorAll(".builder-option");
    for (var i = 0; i < options.length; i += 1) {
      options[i].classList.remove("builder-option--selected");
    }
    if (selectedRadio && selectedRadio.closest) {
      var label = selectedRadio.closest(".builder-option");
      if (label) {
        label.classList.add("builder-option--selected");
      }
    }
  }

  /**
   * Update the current-step summary row, this-step subtotal, and running total.
   *
   * @param {HTMLFormElement} form
   * @returns {void}
   */
  function recalculate(form) {
    var otherTotal = parsePrice(form.getAttribute("data-other-total"));
    var selected = form.querySelector('input[name="component_id"]:checked');
    var stepPrice = 0;
    var stepName = "";

    if (selected) {
      stepPrice = parsePrice(selected.getAttribute("data-price"));
      stepName = selected.getAttribute("data-name") || "";
    }

    var runningTotal = otherTotal + (selected ? stepPrice : 0);

    var subtotalEl = document.getElementById("builder-live-subtotal");
    var totalEl = document.getElementById("builder-live-total");

    if (subtotalEl) {
      subtotalEl.textContent = selected ? formatMoney(stepPrice) : "—";
    }

    if (totalEl) {
      // Running total always shows a dollar amount (including $0.00).
      totalEl.textContent =
        "$" +
        runningTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    var row = document.querySelector("[data-live-category-row]");
    if (row) {
      var partEl = row.querySelector("[data-live-part]");
      var priceEl = row.querySelector("[data-live-price]");
      var emptyEl = row.querySelector("[data-live-empty]");

      if (selected) {
        if (partEl) {
          partEl.textContent = stepName;
          partEl.hidden = false;
        }
        if (priceEl) {
          priceEl.textContent = formatMoney(stepPrice);
          priceEl.hidden = false;
        }
        if (emptyEl) {
          emptyEl.hidden = true;
        }
      } else {
        if (partEl) {
          partEl.textContent = "";
          partEl.hidden = true;
        }
        if (priceEl) {
          priceEl.textContent = "";
          priceEl.hidden = true;
        }
        if (emptyEl) {
          emptyEl.hidden = false;
        }
      }
    }

    syncSelectedStyles(form, selected);

    // Brief visual pulse so the change is noticeable.
    if (totalEl) {
      totalEl.classList.remove("is-updating");
      void totalEl.offsetWidth;
      totalEl.classList.add("is-updating");
    }
    if (subtotalEl) {
      subtotalEl.classList.remove("is-updating");
      void subtotalEl.offsetWidth;
      subtotalEl.classList.add("is-updating");
    }
  }

  /**
   * Bind change listeners on the builder form.
   *
   * @returns {void}
   */
  function initBuilderLivePrice() {
    var form = document.getElementById("builder-form");
    if (!form || form.getAttribute("data-builder-live") !== "1") {
      return;
    }

    form.addEventListener("change", function (event) {
      var target = event.target;
      if (
        !target ||
        target.name !== "component_id" ||
        target.type !== "radio"
      ) {
        return;
      }
      recalculate(form);
    });

    // Also respond to clicks on already-checked radios (label re-clicks).
    form.addEventListener("click", function (event) {
      var target = event.target;
      if (!target) {
        return;
      }
      var radio =
        target.name === "component_id" && target.type === "radio"
          ? target
          : target.closest
            ? target.closest('input[name="component_id"]')
            : null;
      if (!radio) {
        // Clicking the label content still bubbles; find associated radio.
        var label = target.closest ? target.closest(".builder-option") : null;
        if (label) {
          radio = label.querySelector('input[name="component_id"]');
        }
      }
      if (radio && radio.checked) {
        // Defer so the browser finishes toggling checked state first.
        window.setTimeout(function () {
          recalculate(form);
        }, 0);
      }
    });

    // Ensure the displayed totals match the initial checked state.
    recalculate(form);

    document.body.setAttribute("data-cc-builder-live", "ready");
  }

  function boot() {
    initBuilderLivePrice();
  }

  if (window.CustomCore && typeof window.CustomCore.onReady === "function") {
    window.CustomCore.onReady(boot);
  } else if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }

  // Expose for debugging / future builder features (compatibility, charts).
  window.CustomCore = window.CustomCore || {};
  window.CustomCore.initBuilderLivePrice = initBuilderLivePrice;
})(window, document);
