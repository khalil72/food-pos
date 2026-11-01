/* Dashboard charts */
(function () {
    'use strict';
    const D = window.DASH;
    if (!D || !window.Chart) return;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#71685F';
    Chart.defaults.plugins.tooltip.backgroundColor = '#1F1B17';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    const grid = { color: '#F1ECE5', drawBorder: false };
    const moneyTick = (v) => (Math.abs(v) >= 1000 ? (v / 1000).toFixed(v % 1000 === 0 ? 0 : 1) + 'k' : v);

    const trend = document.getElementById('trendChart');
    if (trend) {
        const ctx = trend.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 300);
        grad.addColorStop(0, 'rgba(232,87,42,.28)');
        grad.addColorStop(1, 'rgba(232,87,42,0)');
        new Chart(trend, {
            data: {
                labels: D.trend.labels,
                datasets: [
                    { type: 'line', label: 'Net sales', data: D.trend.sales, borderColor: '#E8572A', backgroundColor: grad, fill: true, tension: .35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderWidth: 2, order: 1 },
                    { type: 'line', label: 'Net profit', data: D.trend.profit, borderColor: '#15803D', borderDash: [5, 4], tension: .35, borderWidth: 2, pointRadius: 0, fill: false, order: 0 },
                    { type: 'bar', label: 'Expenses', data: D.trend.expenses, backgroundColor: 'rgba(245,165,36,.75)', borderRadius: 6, maxBarThickness: 22, order: 2 },
                ],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${APP.money(c.parsed.y)}` } } },
                scales: { x: { grid: { display: false } }, y: { grid, ticks: { callback: moneyTick }, beginAtZero: true } },
            },
        });
    }

    const cat = document.getElementById('categoryChart');
    if (cat) {
        new Chart(cat, {
            type: 'doughnut',
            data: { labels: D.categories.labels, datasets: [{ data: D.categories.values, backgroundColor: D.categories.colors, borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }] },
            options: {
                maintainAspectRatio: false, cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 12 } },
                    tooltip: { callbacks: { label: (c) => ` ${c.label}: ${APP.money(c.parsed)}` } },
                },
            },
        });
    }

    const hourly = document.getElementById('hourlyChart');
    if (hourly) {
        const labels = D.hourly.map((_, h) => (h % 12 || 12) + (h < 12 ? 'a' : 'p'));
        const max = Math.max(...D.hourly);
        new Chart(hourly, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Orders', data: D.hourly, backgroundColor: D.hourly.map((v) => (v === max && v > 0 ? '#E8572A' : 'rgba(232,87,42,.25)')), borderRadius: 6, maxBarThickness: 18 }] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { grid, beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }
})();
