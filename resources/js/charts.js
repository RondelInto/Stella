/**
 * Charts for the Sales Analysis pages (module 8, decision AN8).
 * Chart.js is bundled by Vite (no outside CDN) and loaded only on pages that need it:
 *   @vite('resources/js/charts.js')
 *
 * A page draws a chart with a <canvas> whose data-chart attribute holds a Chart.js config as JSON:
 *   <canvas data-chart="{{ json_encode($config) }}"></canvas>
 * Points may carry a "name" (e.g. the product) that is shown in the tooltip.
 */
import Chart from 'chart.js/auto';

const charts = new Map();

function draw(canvas) {
    const config = JSON.parse(canvas.dataset.chart);

    config.options ??= {};
    config.options.maintainAspectRatio = false;
    config.options.plugins ??= {};
    config.options.plugins.tooltip ??= {};
    config.options.plugins.tooltip.callbacks = {
        label(context) {
            const raw = context.raw ?? {};
            if (raw.name !== undefined) {
                return `${raw.name}: ${context.dataset.xLabel ?? 'x'} ${raw.x}, ${context.dataset.yLabel ?? 'y'} ${raw.y}`;
            }
            return `${context.dataset.label}: ${context.formattedValue}`;
        },
    };

    charts.get(canvas)?.destroy();
    charts.set(canvas, new Chart(canvas, config));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart]').forEach(draw);

    // A <select data-chart-switch="#canvasId"> whose options carry data-chart configs swaps the chart shown.
    document.querySelectorAll('select[data-chart-switch]').forEach((select) => {
        const canvas = document.querySelector(select.dataset.chartSwitch);
        select.addEventListener('change', () => {
            canvas.dataset.chart = select.selectedOptions[0].dataset.chart;
            draw(canvas);
        });
    });
});
