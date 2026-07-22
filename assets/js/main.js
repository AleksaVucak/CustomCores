/**
 * CustomCore — Shared JavaScript utilities
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Provides common browser helpers used across public pages. Loaded deferred
 *   from includes/footer.php so it does not block first paint.
 *
 * Inputs / outputs:
 *   Exposes window.CustomCore with utility functions. Does not throw when
 *   optional page elements are missing.
 *
 * Later commits:
 *   1.7 — responsive navigation toggle (uses these helpers)
 *   Later stages — builder, cart, validation, charts, and map scripts
 * ----------------------------------------------------------------------------
 */

(function (window, document) {
  "use strict";

  /**
   * Run a callback when the DOM is ready.
   *
   * @param {Function} callback Function to run after DOMContentLoaded (or immediately).
   * @returns {void}
   */
  function onReady(callback) {
    if (typeof callback !== "function") {
      return;
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback, { once: true });
      return;
    }

    callback();
  }

  /**
   * Find the first matching element.
   *
   * @param {string} selector CSS selector.
   * @param {ParentNode} [root=document] Optional root node.
   * @returns {Element|null}
   */
  function qs(selector, root) {
    var scope = root || document;
    return scope.querySelector(selector);
  }

  /**
   * Find all matching elements as a real array.
   *
   * @param {string} selector CSS selector.
   * @param {ParentNode} [root=document] Optional root node.
   * @returns {Element[]}
   */
  function qsa(selector, root) {
    var scope = root || document;
    return Array.prototype.slice.call(scope.querySelectorAll(selector));
  }

  /**
   * Debounce a function so it runs after quiet time.
   *
   * @param {Function} fn Function to debounce.
   * @param {number} waitMs Delay in milliseconds.
   * @returns {Function} Debounced wrapper.
   */
  function debounce(fn, waitMs) {
    var timerId = null;
    var wait = typeof waitMs === "number" ? waitMs : 200;

    return function debounced() {
      var context = this;
      var args = arguments;
      window.clearTimeout(timerId);
      timerId = window.setTimeout(function () {
        fn.apply(context, args);
      }, wait);
    };
  }

  /**
   * Toggle a class on an element safely.
   *
   * @param {Element|null} element Target element.
   * @param {string} className Class to toggle.
   * @param {boolean} [force] Optional force add/remove.
   * @returns {void}
   */
  function toggleClass(element, className, force) {
    if (!element || !className) {
      return;
    }

    if (typeof force === "boolean") {
      element.classList.toggle(className, force);
      return;
    }

    element.classList.toggle(className);
  }

  /**
   * Set or remove an ARIA attribute with a string value.
   *
   * @param {Element|null} element Target element.
   * @param {string} attribute Attribute name (e.g. "aria-expanded").
   * @param {string|boolean|null} value Value to set, or null to remove.
   * @returns {void}
   */
  function setAria(element, attribute, value) {
    if (!element || !attribute) {
      return;
    }

    if (value === null || typeof value === "undefined") {
      element.removeAttribute(attribute);
      return;
    }

    element.setAttribute(attribute, String(value));
  }

  /**
   * Trap focus inside a container while a mobile menu (or dialog) is open.
   * Returns a cleanup function that removes the keydown listener.
   *
   * @param {HTMLElement|null} container Element that contains focusable controls.
   * @returns {Function} Cleanup function.
   */
  function createFocusTrap(container) {
    if (!container) {
      return function noopCleanup() {};
    }

    /**
     * @returns {HTMLElement[]}
     */
    function getFocusable() {
      return qsa(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        container
      ).filter(function (el) {
        return !el.hasAttribute("disabled") && el.getAttribute("aria-hidden") !== "true";
      });
    }

    function onKeyDown(event) {
      if (event.key !== "Tab") {
        return;
      }

      var focusable = getFocusable();
      if (focusable.length === 0) {
        return;
      }

      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      var active = document.activeElement;

      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    }

    container.addEventListener("keydown", onKeyDown);

    return function cleanup() {
      container.removeEventListener("keydown", onKeyDown);
    };
  }

  /**
   * Shared application bootstrap for Commit 1.6.
   * Navigation toggle behaviour is added in Commit 1.7.
   *
   * @returns {void}
   */
  function init() {
    document.documentElement.classList.add("js");

    // Confirm script identity for debugging without noisy console output.
    document.body.setAttribute("data-cc-js", "ready");
  }

  var CustomCore = window.CustomCore || {};

  CustomCore.onReady = onReady;
  CustomCore.qs = qs;
  CustomCore.qsa = qsa;
  CustomCore.debounce = debounce;
  CustomCore.toggleClass = toggleClass;
  CustomCore.setAria = setAria;
  CustomCore.createFocusTrap = createFocusTrap;
  CustomCore.init = init;

  window.CustomCore = CustomCore;

  onReady(init);
})(window, document);
