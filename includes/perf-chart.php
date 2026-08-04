<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Performance chart markup partial.
// Expected before include:
//   $perfChartApi, URL to api/chart-data.php
//   $perfChartIds, int[] component IDs for static pages (optional)
//   $perfChartForm, CSS selector for live builder form (optional)
//   $perfReport, optional precomputed report for SSR fallback
//   $perfChartTitle, optional heading override
// Always renders a text fallback. Chart.js enhances the canvas when available.

declare(strict_types=1);

if (!isset($perfChartApi) || !is_string($perfChartApi) || $perfChartApi === '') {
    return;
}

$perfChartIds = isset($perfChartIds) && is_array($perfChartIds) ? $perfChartIds : [];
$perfChartForm = isset($perfChartForm) && is_string($perfChartForm) ? $perfChartForm : '';
$perfChartTitle = isset($perfChartTitle) && is_string($perfChartTitle)
    ? $perfChartTitle
    : 'Performance visualization';

$fallbackRows = [];
if (isset($perfReport) && is_array($perfReport)) {
    $g = (int) ($perfReport['gaming'] ?? 0);
    $p = (int) ($perfReport['productivity'] ?? 0);
    $ug = (int) ($perfReport['upgrade_gaming'] ?? 0);
    $up = (int) ($perfReport['upgrade_productivity'] ?? 0);
    $h = (int) ($perfReport['upgrade_headroom'] ?? 0);
    $fallbackRows = [
        ['label' => 'Gaming performance', 'value' => $g > 0 ? $g . ' / 100' : 'Not enough scored parts yet'],
        ['label' => 'Productivity performance', 'value' => $p > 0 ? $p . ' / 100' : 'Not enough scored parts yet'],
        ['label' => 'Catalogue gaming ceiling', 'value' => $ug . ' / 100'],
        ['label' => 'Catalogue productivity ceiling', 'value' => $up . ' / 100'],
        ['label' => 'Upgrade headroom', 'value' => $h . ' points remaining on average'],
    ];
}
?>
<div
    class="perf-chart"
    data-perf-chart="1"
    data-chart-api="<?php echo customcore_e($perfChartApi); ?>"
    <?php if ($perfChartIds !== []): ?>
        data-chart-ids="<?php echo customcore_e(json_encode(array_values(array_map('intval', $perfChartIds)))); ?>"
    <?php endif; ?>
    <?php if ($perfChartForm !== ''): ?>
        data-chart-form="<?php echo customcore_e($perfChartForm); ?>"
    <?php endif; ?>
>
    <h3 class="perf-chart__title"><?php echo customcore_e($perfChartTitle); ?></h3>
    <p class="perf-chart__status" data-perf-status aria-live="polite">Loading performance data…</p>

    <div class="perf-chart__canvas-wrap">
        <canvas
            data-perf-canvas
            role="img"
            aria-label="Bar chart comparing this build's gaming, productivity, and upgrade headroom against the catalogue ceiling"
        ></canvas>
    </div>

    <div class="perf-chart__fallback" data-perf-fallback>
        <?php if ($fallbackRows !== []): ?>
            <ul class="perf-chart__fallback-list">
                <?php foreach ($fallbackRows as $row): ?>
                    <li>
                        <strong><?php echo customcore_e($row['label']); ?>:</strong>
                        <?php echo customcore_e($row['value']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Select CPU, GPU, RAM, or storage to see performance scores.</p>
        <?php endif; ?>
    </div>
    <p class="perf-chart__note">
        Scores are weighted from CPU, GPU, RAM, and storage (1 to 100). Upgrade headroom
        compares this build to the best active catalogue parts. Text values above remain
        available if the chart does not load.
    </p>
</div>
