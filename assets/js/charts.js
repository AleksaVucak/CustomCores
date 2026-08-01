/**
 * CustomCore — Build performance chart (Commit 5.8)
 * ----------------------------------------------------------------------------
 * File responsibility:
 *   Renders a Chart.js grouped bar chart for gaming, productivity, and upgrade
 *   headroom. Fetches trusted scores from api/chart-data.php. Always keeps a
 *   text fallback in the DOM so the page stays usable if Chart.js fails.
 *
 * Expected markup:
 *   [data-perf-chart]
 *     [data-chart-api] URL
 *     [data-chart-ids] JSON array of component IDs (optional; form may supply)
 *     canvas[data-perf-canvas]
 *     [data-perf-fallback] text summary list
 *
 * Chart.js:
 *   Loaded from CDN when $loadCharts is set. If window.Chart is missing, only
 *   the text fallback is shown.
 * ----------------------------------------------------------------------------
 */

(function (window, document) {
  "use strict";

  var chartInstances = [];

  /**
   * @param {number} n
   * @returns {string}
   */
  function escapeHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str || ""));
    return div.innerHTML;
  }

  /**
   * Collect component IDs for a chart root.
   *
   * @param {HTMLElement} root
   * @returns {number[]}
   */
  function collectIds(root) {
    var ids = [];
    var raw = root.getAttribute("data-chart-ids");
    if (raw) {
      try {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
          for (var i = 0; i < parsed.length; i += 1) {
            var n = parseInt(parsed[i], 10);
            if (n > 0) {
              ids.push(n);
            }
          }
        }
      } catch (e) {
        ids = [];
      }
    }

    // Live builder form may provide current + other selections.
    var formSelector = root.getAttribute("data-chart-form");
    if (formSelector) {
      var form = document.querySelector(formSelector);
      if (form) {
        ids = [];
        try {
          var other = JSON.parse(form.getAttribute("data-build-ids") || "[]");
          if (Array.isArray(other)) {
            for (var j = 0; j < other.length; j += 1) {
              var oid = parseInt(other[j], 10);
              if (oid > 0) {
                ids.push(oid);
              }
            }
          }
        } catch (e2) {
          /* ignore */
        }
        var selected = form.querySelector('input[name="component_id"]:checked');
        if (selected) {
          var sid = parseInt(selected.value, 10);
          if (sid > 0) {
            ids.push(sid);
          }
        }
      }
    }

    return ids;
  }

  /**
   * Update the accessible text fallback list.
   *
   * @param {HTMLElement} fallbackEl
   * @param {Array<{label:string,value:string}>} rows
   * @returns {void}
   */
  function renderFallback(fallbackEl, rows) {
    if (!fallbackEl || !rows) {
      return;
    }
    var html = "<ul class=\"perf-chart__fallback-list\">";
    for (var i = 0; i < rows.length; i += 1) {
      html +=
        "<li><strong>" +
        escapeHtml(rows[i].label) +
        ":</strong> " +
        escapeHtml(rows[i].value) +
        "</li>";
    }
    html += "</ul>";
    fallbackEl.innerHTML = html;
  }

  /**
   * Draw or update a Chart.js instance.
   *
   * @param {HTMLCanvasElement} canvas
   * @param {object} chartPayload
   * @returns {void}
   */
  function drawChart(canvas, chartPayload) {
    if (!window.Chart || !canvas || !chartPayload) {
      return;
    }

    var existing = canvas._ccChart;
    if (existing) {
      existing.data.labels = chartPayload.labels;
      existing.data.datasets[0].data = chartPayload.datasets[0].data;
      existing.data.datasets[1].data = chartPayload.datasets[1].data;
      existing.update();
      return;
    }

    var accent = "#0f7a6e";
    var muted = "#5b6b7a";

    var chart = new window.Chart(canvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: chartPayload.labels,
        datasets: [
          {
            label: chartPayload.datasets[0].label || "This build",
            data: chartPayload.datasets[0].data,
            backgroundColor: accent,
            borderRadius: 4,
            maxBarThickness: 36,
          },
          {
            label: chartPayload.datasets[1].label || "Catalogue ceiling",
            data: chartPayload.datasets[1].data,
            backgroundColor: muted,
            borderRadius: 4,
            maxBarThickness: 36,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: { boxWidth: 12, font: { size: 12 } },
          },
          title: {
            display: true,
            text: "Build performance vs catalogue ceiling",
            font: { size: 14, weight: "600" },
            color: "#18212b",
          },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var v = ctx.parsed.y;
                return ctx.dataset.label + ": " + v + " / 100";
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { stepSize: 20 },
            title: { display: true, text: "Score" },
          },
          x: {
            ticks: { font: { size: 11 } },
          },
        },
      },
    });

    canvas._ccChart = chart;
    chartInstances.push(chart);
  }

  /**
   * Fetch chart data and update UI for one root.
   *
   * @param {HTMLElement} root
   * @returns {void}
   */
  function refreshChart(root) {
    var apiUrl = root.getAttribute("data-chart-api");
    if (!apiUrl) {
      return;
    }

    var ids = collectIds(root);
    var canvas = root.querySelector("[data-perf-canvas]");
    var fallbackEl = root.querySelector("[data-perf-fallback]");
    var statusEl = root.querySelector("[data-perf-status]");

    if (ids.length === 0) {
      if (fallbackEl) {
        fallbackEl.innerHTML =
          "<p>Select CPU, GPU, RAM, or storage to see performance scores.</p>";
      }
      if (statusEl) {
        statusEl.textContent = "Waiting for scored components";
      }
      if (canvas && canvas._ccChart) {
        canvas._ccChart.data.datasets[0].data = [0, 0, 0];
        canvas._ccChart.data.datasets[1].data = [0, 0, 0];
        canvas._ccChart.update();
      }
      return;
    }

    if (statusEl) {
      statusEl.textContent = "Updating performance…";
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", apiUrl, true);
    xhr.setRequestHeader("Content-Type", "application/json");

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) {
        return;
      }

      if (xhr.status !== 200) {
        if (statusEl) {
          statusEl.textContent = "Chart data unavailable — see text summary.";
        }
        return;
      }

      var resp;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch (e) {
        if (statusEl) {
          statusEl.textContent = "Chart data unavailable — see text summary.";
        }
        return;
      }

      if (!resp || !resp.success) {
        if (statusEl) {
          statusEl.textContent = "Chart data unavailable — see text summary.";
        }
        return;
      }

      renderFallback(fallbackEl, resp.fallback);
      if (canvas) {
        drawChart(canvas, resp.chart);
      }
      if (statusEl) {
        statusEl.textContent =
          "Gaming " +
          resp.scores.gaming +
          " · Productivity " +
          resp.scores.productivity +
          " · Headroom " +
          resp.scores.upgrade_headroom;
      }
    };

    xhr.send(JSON.stringify({ components: ids }));
  }

  /**
   * Initialise all performance charts on the page.
   *
   * @returns {void}
   */
  function initPerformanceCharts() {
    var roots = document.querySelectorAll("[data-perf-chart]");
    if (!roots.length) {
      return;
    }

    for (var i = 0; i < roots.length; i += 1) {
      refreshChart(roots[i]);
    }

    // Live builder: refresh when component radios change.
    var liveForm = document.getElementById("builder-form");
    if (liveForm) {
      var timer = null;
      var schedule = function () {
        if (timer) {
          window.clearTimeout(timer);
        }
        timer = window.setTimeout(function () {
          timer = null;
          for (var j = 0; j < roots.length; j += 1) {
            if (roots[j].getAttribute("data-chart-form")) {
              refreshChart(roots[j]);
            }
          }
        }, 350);
      };
      liveForm.addEventListener("change", schedule);
      liveForm.addEventListener("click", schedule);
    }

    document.body.setAttribute("data-cc-charts", "ready");
  }

  function boot() {
    // Chart.js is deferred ahead of this file; if the CDN is slow, retry briefly.
    if (!window.Chart) {
      var tries = 0;
      var wait = window.setInterval(function () {
        tries += 1;
        if (window.Chart || tries > 40) {
          window.clearInterval(wait);
          initPerformanceCharts();
        }
      }, 50);
      return;
    }
    initPerformanceCharts();
  }

  if (window.CustomCore && typeof window.CustomCore.onReady === "function") {
    window.CustomCore.onReady(boot);
  } else if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }

  window.CustomCore = window.CustomCore || {};
  window.CustomCore.initPerformanceCharts = initPerformanceCharts;
  window.CustomCore.refreshPerformanceChart = refreshChart;
})(window, document);
