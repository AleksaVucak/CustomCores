/**
 * CustomCore — Help centre hub filter (Commit 11.1).
 *
 * Progressive enhancement for help/index.html:
 *   Filters guide cards by title, description, and data-help-keywords as the
 *   visitor types. Without JavaScript every card remains visible.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-help-hub-search]');
  if (!root) {
    return;
  }

  var input = root.querySelector('#help-hub-query');
  var list = document.querySelector('[data-help-hub-list]');
  var empty = root.querySelector('[data-help-hub-empty]');
  var clearBtn = root.querySelector('[data-help-hub-clear]');

  if (!input || !list) {
    return;
  }

  var cards = Array.prototype.slice.call(list.querySelectorAll('.help-hub-card'));

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function cardText(card) {
    var keywords = card.getAttribute('data-help-keywords') || '';
    return normalize(card.textContent + ' ' + keywords);
  }

  function applyFilter() {
    var query = normalize(input.value);
    var visible = 0;

    cards.forEach(function (card) {
      var match = query === '' || cardText(card).indexOf(query) !== -1;
      card.hidden = !match;
      if (match) {
        visible += 1;
      }
    });

    if (empty) {
      empty.hidden = visible !== 0;
    }
  }

  input.addEventListener('input', applyFilter);
  input.addEventListener('change', applyFilter);
  input.addEventListener('search', applyFilter);
  input.addEventListener('keyup', applyFilter);

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      input.value = '';
      applyFilter();
      input.focus();
    });
  }
})();
