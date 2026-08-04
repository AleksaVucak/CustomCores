/*
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Live PC Builder price calculator + compatibility.
// 1. Updates the builder summary subtotal and running total immediately when the user changes a
// component radio selection. 2. After each change, calls api/builder-price.php to verify the total
// from the database, ensuring tampered data-price attributes cannot trick the displayed total. The
// server response overwrites the client total and shows a verification badge. 3. After each
// change, calls api/compatibility-check.php to evaluate the build against all active compatibility
// rules and displays the results (compatible/warning/incompatible badge + per-rule messages).
// Expected markup on builder.php: form#builder-form[data-builder-live][data-other-total][data-
// category-id] [data-price-api][data-compat-api][data-build-ids] input.builder-option__radio[data-
// price][data-name] #builder-live-subtotal, #builder-live-total [data-live-category-row] with
// [data-live-part], [data-live-price], optional [data-live-empty] #builder-price-hint (server
// verification badge) #builder-compat-status (compatibility results container)
// Loaded deferred from includes/footer.php when $currentPage === 'builder'.

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
      subtotalEl.textContent = selected ? formatMoney(stepPrice) : "Not selected";
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
      verifyPriceServer(form);
      checkCompatibility(form);
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
        var label = target.closest ? target.closest(".builder-option") : null;
        if (label) {
          radio = label.querySelector('input[name="component_id"]');
        }
      }
      if (radio && radio.checked) {
        window.setTimeout(function () {
          recalculate(form);
          verifyPriceServer(form);
          checkCompatibility(form);
        }, 0);
      }
    });

    // Ensure the displayed totals match the initial checked state.
    recalculate(form);

    // Run initial compatibility check if there are existing selections.
    checkCompatibility(form);

    document.body.setAttribute("data-cc-builder-live", "ready");
  }

  // Server-side price verification

  var verifyTimer = null;

  /**
   * Call api/builder-price.php to confirm the running total from the server.
   * Uses a short debounce so rapid clicks do not flood the endpoint.
   *
   * @param {HTMLFormElement} form
   * @returns {void}
   */
  function verifyPriceServer(form) {
    if (verifyTimer) {
      window.clearTimeout(verifyTimer);
    }

    verifyTimer = window.setTimeout(function () {
      verifyTimer = null;
      doVerify(form);
    }, 350);
  }

  /**
   * Actually perform the server verification request.
   *
   * @param {HTMLFormElement} form
   * @returns {void}
   */
  function doVerify(form) {
    var apiUrl = form.getAttribute("data-price-api");
    if (!apiUrl) {
      return;
    }

    // Build the full component ID list: other steps + current selection.
    var otherIds = [];
    try {
      otherIds = JSON.parse(form.getAttribute("data-build-ids") || "[]");
    } catch (e) {
      otherIds = [];
    }

    var selected = form.querySelector('input[name="component_id"]:checked');
    var allIds = otherIds.slice();
    if (selected && parseInt(selected.value, 10) > 0) {
      allIds.push(parseInt(selected.value, 10));
    }

    if (allIds.length === 0) {
      return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", apiUrl, true);
    xhr.setRequestHeader("Content-Type", "application/json");

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) {
        return;
      }

      if (xhr.status !== 200) {
        showServerBadge(false);
        return;
      }

      var resp;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch (e) {
        showServerBadge(false);
        return;
      }

      if (!resp || !resp.success) {
        showServerBadge(false);
        return;
      }

      var totalEl = document.getElementById("builder-live-total");
      if (totalEl) {
        var serverTotal = Number(resp.total);
        if (isFinite(serverTotal)) {
          totalEl.textContent =
            "$" +
            serverTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
      }

      showServerBadge(true);
    };

    xhr.send(JSON.stringify({ components: allIds }));
  }

  /**
   * Show/hide the server-verified indicator.
   *
   * @param {boolean} verified
   * @returns {void}
   */
  function showServerBadge(verified) {
    var hint = document.getElementById("builder-price-hint");
    if (!hint) {
      return;
    }
    if (verified) {
      hint.textContent = "✓ Total verified by server.";
      hint.classList.add("builder-summary__hint--ok");
      hint.classList.remove("builder-summary__hint--err");
    } else {
      hint.textContent = "Server verification unavailable, total is estimated.";
      hint.classList.add("builder-summary__hint--err");
      hint.classList.remove("builder-summary__hint--ok");
    }
  }

  /**
   * Entry point: start the live-pricing/compatibility enhancement.
   *
   * @returns {void}
   */
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

  // Compatibility checking

  var compatTimer = null;

  /**
   * Call api/compatibility-check.php to evaluate the build against all rules.
   * Debounced 400ms so rapid clicks don't flood the endpoint.
   *
   * @param {HTMLFormElement} form
   * @returns {void}
   */
  function checkCompatibility(form) {
    if (compatTimer) {
      window.clearTimeout(compatTimer);
    }
    compatTimer = window.setTimeout(function () {
      compatTimer = null;
      doCompatCheck(form);
    }, 400);
  }

  /**
   * Perform the actual compatibility check request.
   *
   * @param {HTMLFormElement} form
   * @returns {void}
   */
  function doCompatCheck(form) {
    var apiUrl = form.getAttribute("data-compat-api");
    if (!apiUrl) {
      return;
    }

    var otherIds = [];
    try {
      otherIds = JSON.parse(form.getAttribute("data-build-ids") || "[]");
    } catch (e) {
      otherIds = [];
    }

    var selected = form.querySelector('input[name="component_id"]:checked');
    var allIds = otherIds.slice();
    if (selected && parseInt(selected.value, 10) > 0) {
      allIds.push(parseInt(selected.value, 10));
    }

    if (allIds.length < 2) {
      renderCompatStatus(null);
      return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", apiUrl, true);
    xhr.setRequestHeader("Content-Type", "application/json");

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) {
        return;
      }

      if (xhr.status !== 200) {
        renderCompatStatus(null);
        return;
      }

      var resp;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch (e) {
        renderCompatStatus(null);
        return;
      }

      if (!resp || !resp.success) {
        renderCompatStatus(null);
        return;
      }

      renderCompatStatus(resp);
    };

    xhr.send(JSON.stringify({ components: allIds }));
  }

  /**
   * Render the compatibility status in the summary panel.
   *
   * @param {object|null} resp The server response or null if unavailable.
   * @returns {void}
   */
  function renderCompatStatus(resp) {
    var container = document.getElementById("builder-compat-status");
    if (!container) {
      return;
    }

    if (!resp) {
      container.innerHTML =
        '<span class="compat-badge compat-badge--neutral">Select more components to check compatibility</span>';
      return;
    }

    var statusClass = "compat-badge--compatible";
    var statusLabel = "Compatible";

    if (resp.status === "warning") {
      statusClass = "compat-badge--warning";
      statusLabel = "Warning";
    } else if (resp.status === "incompatible") {
      statusClass = "compat-badge--incompatible";
      statusLabel = "Incompatible";
    }

    var html =
      '<span class="compat-badge ' +
      statusClass +
      '">' +
      statusLabel +
      "</span>";

    if (resp.results && resp.results.length > 0) {
      html += '<ul class="compat-results">';
      for (var i = 0; i < resp.results.length; i += 1) {
        var r = resp.results[i];
        if (r.status === "skip") {
          continue;
        }
        var itemClass = "compat-results__item";
        if (r.status === "pass") {
          itemClass += " compat-results__item--pass";
        } else if (r.status === "warning") {
          itemClass += " compat-results__item--warning";
        } else if (r.status === "fail") {
          itemClass += " compat-results__item--fail";
        }
        html +=
          '<li class="' +
          itemClass +
          '"><strong>' +
          escapeHtml(r.name) +
          ":</strong> " +
          escapeHtml(r.message) +
          "</li>";
      }
      html += "</ul>";
    }

    container.innerHTML = html;
  }

  /**
   * Escape HTML special characters for safe innerHTML insertion.
   *
   * @param {string} str
   * @returns {string}
   */
  function escapeHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str || ""));
    return div.innerHTML;
  }

  // Expose for debugging / future builder features.
  window.CustomCore = window.CustomCore || {};
  window.CustomCore.initBuilderLivePrice = initBuilderLivePrice;
  window.CustomCore.checkCompatibility = checkCompatibility;
})(window, document);
