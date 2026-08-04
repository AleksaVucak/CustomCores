/*
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator reports charts.
// Renders Chart.js doughnut/bar charts for the admin reports page. Each chart reads its payload
// from a data-admin-report-chart attribute produced server-side from live MySQL aggregates.
// Server-rendered tables beside each canvas remain the accessible source of truth if Chart.js
// fails to load.
// Expected markup: .admin-report-chart[data-admin-report-chart='{...}'][data-chart-
// type='bar|doughnut'] canvas

(function (window, document) {
  'use strict';

  /**
   * Build the Chart.js options object for a report chart, adding numeric axes for
   * bar charts and a bottom legend for doughnut charts.
   *
   * @param {string} type Chart type ("bar" or "doughnut").
   * @param {string} title Chart title text.
   * @returns {Object} Chart.js options.
   */
  function buildOptions(type, title) {
    var isDoughnut = type === 'doughnut';
    var options = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: title || '',
          font: { size: 14, weight: '600' },
          color: '#18212b'
        },
        legend: {
          display: isDoughnut,
          position: 'bottom'
        },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var label = ctx.label || '';
              var value = isDoughnut ? ctx.parsed : ctx.parsed.y;
              if (typeof value !== 'number') {
                value = 0;
              }
              return label + ': ' + value;
            }
          }
        }
      }
    };

    if (!isDoughnut) {
      options.scales = {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0, stepSize: 1 }
        }
      };
    }

    return options;
  }

  /**
   * Render a single report chart from one container's data-* attributes. No-op if
   * the canvas, Chart.js, or a valid JSON payload is missing.
   *
   * @param {Element} root The.admin-report-chart container element.
   * @returns {void}
   */
  function initOne(root) {
    var canvas = root.querySelector('canvas');
    if (!canvas || typeof window.Chart === 'undefined') {
      return;
    }

    var payload;
    try {
      payload = JSON.parse(root.getAttribute('data-admin-report-chart') || '{}');
    } catch (e) {
      return;
    }

    if (!payload || !Array.isArray(payload.labels) || !Array.isArray(payload.datasets)) {
      return;
    }

    var type = root.getAttribute('data-chart-type') || 'bar';
    if (type !== 'doughnut' && type !== 'bar') {
      type = 'bar';
    }

    var title = root.getAttribute('data-chart-title') || '';
    var dataset = payload.datasets[0] || {};

    new window.Chart(canvas, {
      type: type,
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: dataset.label || 'Count',
            data: dataset.data || [],
            backgroundColor: dataset.backgroundColor || '#0f7a6e',
            borderColor: dataset.borderColor || '#0b5f56',
            borderWidth: typeof dataset.borderWidth === 'number' ? dataset.borderWidth : 1,
            borderRadius: type === 'bar' ? 6 : 0,
            maxBarThickness: type === 'bar' ? 64 : undefined
          }
        ]
      },
      options: buildOptions(type, title)
    });
  }

  /**
   * Render every report chart present on the page.
   *
   * @returns {void}
   */
  function initAll() {
    var roots = document.querySelectorAll('[data-admin-report-chart]');
    for (var i = 0; i < roots.length; i += 1) {
      initOne(roots[i]);
    }
  }

  /**
   * Entry point. Chart.js is deferred just before this file, so poll briefly
   * (up to ~2s) for it before drawing when the CDN is slow.
   *
   * @returns {void}
   */
  function boot() {
    if (typeof window.Chart === 'undefined') {
      var tries = 0;
      var wait = window.setInterval(function () {
        tries += 1;
        if (typeof window.Chart !== 'undefined' || tries > 40) {
          window.clearInterval(wait);
          initAll();
        }
      }, 50);
      return;
    }
    initAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})(window, document);
