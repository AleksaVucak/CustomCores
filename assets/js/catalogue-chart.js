/*
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Public catalogue visualization.
// Renders a Chart.js bar chart of active products per performance tier. The data is produced
// server-side from MySQL and embedded in the data-catalogue-chart attribute, so the graph always
// uses real application data. An accessible data table is rendered server-side beside the canvas
// and remains the source of truth if Chart.js fails to load.
// Expected markup: .catalogue-chart[data-catalogue-chart='{"labels":[...],"datasets":[...]}']
// canvas#tier-product-chart

(function (window, document) {
  'use strict';

  /**
   * Read the server-embedded JSON payload and render the tier bar chart into the
   * canvas. Silently returns when the container, canvas, Chart.js, or a valid
   * payload is missing, the server-rendered data table remains the fallback.
   *
   * @returns {void}
   */
  function initCatalogueChart() {
    var root = document.querySelector('[data-catalogue-chart]');
    if (!root) {
      return;
    }

    var canvas = root.querySelector('#tier-product-chart');
    if (!canvas || typeof window.Chart === 'undefined') {
      // No Chart.js, the server-rendered table already communicates the data.
      return;
    }

    var payload;
    try {
      payload = JSON.parse(root.getAttribute('data-catalogue-chart'));
    } catch (e) {
      return;
    }

    if (!payload || !Array.isArray(payload.labels) || !Array.isArray(payload.datasets)) {
      return;
    }

    var dataset = payload.datasets[0] || {};

    new window.Chart(canvas, {
      type: 'bar',
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: dataset.label || 'Active products',
            data: dataset.data || [],
            backgroundColor: dataset.backgroundColor || '#0f7a6e',
            borderColor: dataset.borderColor || '#0b5f56',
            borderWidth: typeof dataset.borderWidth === 'number' ? dataset.borderWidth : 1,
            borderRadius: 6,
            maxBarThickness: 72
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'Active products by performance tier',
            font: { size: 15, weight: '600' },
            color: '#18212b'
          },
          subtitle: {
            display: true,
            text: 'Counts are read live from the CustomCore catalogue database.',
            color: '#5b6b7a',
            padding: { bottom: 8 }
          },
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var v = ctx.parsed.y;
                return v + (v === 1 ? ' active product' : ' active products');
              }
            }
          }
        },
        scales: {
          x: {
            title: { display: true, text: 'Performance tier' },
            grid: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: { precision: 0, stepSize: 1 },
            title: { display: true, text: 'Number of active products' }
          }
        }
      }
    });
  }

  /**
   * Entry point. Chart.js is deferred just before this file, so if the CDN is
   * still loading, poll briefly (up to ~2s) before drawing the chart.
   *
   * @returns {void}
   */
  function boot() {
    // Chart.js is deferred just before this file; if the CDN is slow, retry briefly.
    if (typeof window.Chart === 'undefined') {
      var tries = 0;
      var wait = window.setInterval(function () {
        tries += 1;
        if (typeof window.Chart !== 'undefined' || tries > 40) {
          window.clearInterval(wait);
          initCatalogueChart();
        }
      }, 50);
      return;
    }
    initCatalogueChart();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})(window, document);
