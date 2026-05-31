/*
 * HLstatsZ - Chart.js front-end for server load / trend graphs.
 */
(function (global) {
    'use strict';

    if (!global.Chart) {
        global.HLStatsZCharts = { init: function () {} };
        return;
    }

    var COLORS = {
        act:       'rgb(79, 195, 247)',
        actFill:   'rgba(79, 195, 247, 0.22)',
        min:       'rgb(180, 180, 180)',
        max:       'rgb(129, 212, 250)',
        fps:       'rgb(255, 82, 82)',
        uptime:    'rgb(255, 183, 77)',
        uptimeFill: 'rgb(255, 183, 77, 0.22)',
        players:   'rgb(255, 82, 82)',
        kills:     'rgb(255, 138, 101)',
        killsFill: 'rgb(255, 138, 101, 0.22)',
        headshots: 'rgb(240, 98, 146)',
        act_slots: 'rgb(129, 212, 250)',
        max_slots: 'rgb(160, 160, 160)'
    };

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    var _fmt = {
        weekday:  new Intl.DateTimeFormat(undefined, { weekday: 'short' }),
        dayMonth: new Intl.DateTimeFormat(undefined, { day: '2-digit', month: '2-digit' }),
        monYear:  new Intl.DateTimeFormat(undefined, { month: '2-digit', year: 'numeric' })
    };

    function fmtLabel(ts, range) {
        var d = new Date(ts * 1000);
        if (range === '1' || range === 1) {
            return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
        }
        if (range === '2' || range === 2) {
            return _fmt.weekday.format(d) + ' ' + pad2(d.getHours()) + 'h';
        }
        if (range === '3' || range === 3) {
            return _fmt.dayMonth.format(d);
        }
        return _fmt.monYear.format(d);
    }

    function buildUrl(params) {
        var q = [];
        for (var k in params) {
            if (params.hasOwnProperty(k) && params[k] !== undefined && params[k] !== null) {
                q.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
            }
        }
        return 'show_chart.php?' + q.join('&');
    }

    function fetchJson(params) {
        return fetch(buildUrl(params), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            });
    }

    function getChartTextColor(el) {
        return getComputedStyle(el).getPropertyValue('--chart-text').trim() || '#c6d4df';
    }

    function tooltipLabelColor(ctx) {
        var c = (ctx.dataset && ctx.dataset.borderColor) || '#888';
        return { borderColor: c, backgroundColor: c, borderWidth: 0, borderRadius: 2 };
    }

    var splitLegendPlugin = {
        id: 'splitLegend',
        afterDraw: function (chart) {
            var opts = chart.options.plugins.splitLegend;
            if (!opts || !opts.enabled) return;
            var ctx     = chart.ctx;
            var area    = chart.chartArea;
            if (!area) return;
            var color   = opts.textColor || '#c6d4df';
            var caption = opts.caption   || '';
            var yMid    = area.top - 12;
            var BOX = 9, PAD = 4, GAP = 12;
            ctx.save();
            ctx.font         = '11px sans-serif';
            ctx.textBaseline = 'middle';
            // All dataset labels, left-to-right starting at area.left
            ctx.textAlign = 'left';
            var x = area.left;
            chart.data.datasets.forEach(function (ds, i) {
                if (chart.getDatasetMeta(i).hidden) return;
                var label = ds.label || '';
                ctx.fillStyle = ds.borderColor || '#888';
                ctx.fillRect(x, yMid - 4, BOX, BOX);
                ctx.fillStyle = color;
                ctx.fillText(label, x + BOX + PAD, yMid);
                x += BOX + PAD + ctx.measureText(label).width + GAP;
            });

            if (caption) {
                ctx.textAlign = 'right';
                ctx.fillStyle = color;
                ctx.fillText(caption, area.right, yMid);
            }
            ctx.restore();
        }
    };

    var mapBgPlugin = {
        id: 'mapBg',
        beforeDraw: function (chart) {
            var opts = chart.options.plugins.mapBg;
            if (!opts || !opts.segments || !opts.segments.length) return;
            var segs = opts.segments;
            var ctx  = chart.ctx;
            var xs   = chart.scales.x;
            var area = chart.chartArea;
            if (!xs || !area) return;
            ctx.save();
            ctx.beginPath();
            ctx.rect(area.left, area.top, area.width, area.height);
            ctx.clip();
            for (var s = 0; s < segs.length; s++) {
                var x0 = xs.getPixelForValue(segs[s].from);
                var x1 = s + 1 < segs.length
                    ? xs.getPixelForValue(segs[s + 1].from)
                    : area.right;
                if (x1 <= x0) continue;
                ctx.fillStyle = s % 2 === 0 ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.08)';
                ctx.fillRect(x0, area.top, x1 - x0, area.height);
            }
            ctx.restore();
        },
        afterDraw: function (chart) {
            var opts = chart.options.plugins.mapBg;
            if (!opts || !opts.segments || !opts.segments.length) return;
            var segs = opts.segments;
            var ctx  = chart.ctx;
            var xs   = chart.scales.x;
            var area = chart.chartArea;
            if (!xs || !area) return;
            ctx.save();
            ctx.beginPath();
            ctx.rect(area.left, area.top, area.width, area.height);
            ctx.clip();
            ctx.font        = '13px sans-serif';
            ctx.fillStyle   = 'rgba(180,180,180,0.85)';
            ctx.strokeStyle = 'rgba(120,120,120,0.45)';
            ctx.lineWidth   = 1;
            for (var s = 0; s < segs.length; s++) {
                var px    = xs.getPixelForValue(segs[s].from);
                var pxEnd = s + 1 < segs.length ? xs.getPixelForValue(segs[s + 1].from) : area.right;
                var segW  = pxEnd - px;
                if (s > 0) {
                    ctx.beginPath();
                    ctx.moveTo(px, area.top);
                    ctx.lineTo(px, area.bottom);
                    ctx.stroke();
                }
                if (segW > 14) {
                    ctx.save();
                    ctx.translate(px + 13, area.bottom - 4);
                    ctx.rotate(-Math.PI / 2);
                    ctx.fillText(segs[s].name, 0, 0);
                    ctx.restore();
                }
            }
            ctx.restore();
        }
    };

    function destroyChart(canvas) {
        if (canvas && canvas._chart) {
            try { canvas._chart.destroy(); } catch (e) {}
            canvas._chart = null;
        }
    }

    function serverLoadConfig(json, range, textColor) {
        var rows = json.data || [];

        var labels  = rows.map(function (r) { return fmtLabel(r.t, range); });

        var mapSegs = [];
        if (range === '1' || range === 1) {
            var lastMap = null;
            for (var li = 0; li < rows.length; li++) {
                var mapNow = (rows[li].map || '').trim();
                if (mapNow && mapNow !== lastMap) {
                    mapSegs.push({ from: li, name: mapNow });
                    lastMap = mapNow;
                }
            }
        }

        var datasets = [
            {
                label: t('chart.act_players'),
                data: rows.map(function (r) { return r.act; }),
                borderColor: COLORS.act,
                backgroundColor: COLORS.actFill,
                borderWidth: 1, fill: true, tension: 0.25,
                yAxisID: 'y', pointRadius: 1
            },
            {
                label: t('chart.min_players'),
                data: rows.map(function (r) { return r.min; }),
                borderColor: COLORS.min, borderDash: [3, 3],
                borderWidth: 1, tension: 0.25,
                yAxisID: 'y', pointRadius: 0
            },
            {
                label: t('chart.max_players'),
                data: rows.map(function (r) { return r.max; }),
                borderColor: COLORS.max, borderDash: [6, 4],
                borderWidth: 1, tension: 0.25,
                yAxisID: 'y', pointRadius: 0
            },
            {
                label: t('chart.fps'),
                data: rows.map(function (r) { return r.fps; }),
                borderColor: COLORS.fps,
                borderWidth: 1, tension: 0.25,
                yAxisID: 'y2', pointRadius: 0
            },
            {
                label: t('chart.uptime'),
                data: rows.map(function (r) { return r.uptime || 0; }),
                borderColor: COLORS.uptime,
                borderWidth: 1, tension: 0.25,
                yAxisID: 'y3', pointRadius: 0,
                _uptimeSeconds: true
            }
        ];

        var allPlugins = [splitLegendPlugin];
        if (mapSegs.length) allPlugins.push(mapBgPlugin);

        return {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                color: textColor,
                layout: { padding: { top: 24 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    splitLegend: {
                        enabled: true,
                        textColor: textColor,
                        caption: (range === '1' || range === 1) && json.avg_24h !== null
                            ? t('chart.avg_players_24h') + ' ' + json.avg_24h +
                              '  ' + t('chart.last_1h') + ' ' + json.avg_1h
                            : ''
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            labelColor: tooltipLabelColor,
                            title: function (items) {
                                if (!items.length) return '';
                                var i = items[0].dataIndex;
                                var t = rows[i] ? rows[i].t : null;
                                if (!t) return items[0].label;
                                return new Date(t * 1000).toLocaleString()
                                    + (rows[i].map ? '  [' + rows[i].map + ']' : '');
                            },
                            label: function (context) {
                                var label = context.dataset.label || '';
                                var val   = context.parsed.y;
                                if (context.dataset._uptimeSeconds) {
                                    return label + ': ' + (val / 60).toFixed(1) + 'h';
                                }
                                return label + ': ' + val;
                            }
                        }
                    },
                    mapBg: mapSegs.length ? { segments: mapSegs } : false
                },
                scales: {
                    x: {
                        ticks: { color: textColor, maxTicksLimit: 8, autoSkip: true },
                        grid:  { display: true }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, color: textColor, text: t('players') },
                        ticks: { color: textColor, precision: 0 }
                    },
                    y2: {
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        title: { display: true, color: textColor, text: t('chart.fps') },
                        ticks: { color: textColor, precision: 0 }
                    },
                    y3: {
                        position: 'right',
                        beginAtZero: true,
                        display: false,
                        grid: { drawOnChartArea: false }
                    }
                }
            },
            plugins: allPlugins
        };
    }

    function gameTrendConfig(json, textColor) {
        var rows = json.data || [];
        var labels = rows.map(function (r) {
            return _fmt.dayMonth.format(new Date(r.t * 1000));
        });

        return {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: t('chart.players'),
                        data: rows.map(function (r) { return r.players; }),
                        borderWidth: 1, borderColor: COLORS.players,
                        tension: 0.25, yAxisID: 'y', pointRadius: 0
                    },
                    {
                        label: t('chart.kills'),
                        data: rows.map(function (r) { return r.kills; }),
                        borderWidth: 1, borderColor: COLORS.kills,
                        tension: 0.25, yAxisID: 'y', pointRadius: 0
                    },
                    {
                        label: t('chart.headshots'),
                        data: rows.map(function (r) { return r.headshots; }),
                        borderWidth: 1, borderColor: COLORS.headshots,
                        tension: 0.25, yAxisID: 'y', pointRadius: 0
                    },
                    {
                        label: t('chart.act_slots'),
                        data: rows.map(function (r) { return r.act_slots; }),
                        borderWidth: 1, borderColor: COLORS.act_slots,
                        backgroundColor: COLORS.actFill, fill: true,
                        tension: 0.25, yAxisID: 'y2', pointRadius: 0
                    },
                    {
                        label: t('chart.max_slots'),
                        data: rows.map(function (r) { return r.max_slots; }),
                        borderWidth: 1, borderColor: COLORS.max_slots,
                        borderDash: [4, 4],
                        tension: 0.25, yAxisID: 'y2', pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: true,
                color: textColor,
                layout: { padding: { top: 24 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    splitLegend: {
                        enabled: true,
                        textColor: textColor,
                        caption: (json.new_24h !== null && json.new_24h !== undefined)
                            ? t('chart.new_players_24h') + ' ' + json.new_24h +
                              '  ' + t('chart.last_1h') + ': ' + (json.new_1h !== null ? json.new_1h : 0)
                            : ''
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: { labelColor: tooltipLabelColor }
                    }
                },
                scales: {
                    x: { ticks: { color: textColor, maxTicksLimit: 10, autoSkip: true } },
                    y: {
                        beginAtZero: true,
                        title: { display: true, color: textColor, text: t('chart.players_kills_hs') },
                        ticks: { color: textColor, precision: 0, callback: function (v) { return formatNum(v); } }
                    },
                    y2: {
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        title: { display: true, color: textColor, text: t('chart.slots') },
                        ticks: { color: textColor, precision: 0 }
                    }
                }
            },
            plugins: [splitLegendPlugin]
        };
    }

    function setRangeActive(rangeRoot, range) {
        if (!rangeRoot) return;
        var btns = rangeRoot.querySelectorAll('[data-range]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('active', btns[i].getAttribute('data-range') === String(range));
        }
    }

    function renderServerLoad(root) {
        var canvas = root.querySelector('canvas');
        if (!canvas) return;
        var serverId = root.getAttribute('data-server-id');
        if (!serverId) return;

        var rangeRoot    = root.querySelector('.hlstats-chart-range');
        var currentRange = root.getAttribute('data-range') || '1';

        function draw(r) {
            root.classList.add('is-loading');
            var textColor = getChartTextColor(root);
            fetchJson({ type: 0, server_id: serverId, range: r })
                .then(function (json) {
                    destroyChart(canvas);
                    canvas._chart = new global.Chart(canvas, serverLoadConfig(json, r, textColor));
                })
                .catch(function (e) { console.error('chart load failed', e); })
                .then(function () { root.classList.remove('is-loading'); });
        }

        if (rangeRoot) {
            setRangeActive(rangeRoot, currentRange);
            rangeRoot.addEventListener('click', function (ev) {
                var btn = ev.target.closest ? ev.target.closest('[data-range]') : null;
                if (!btn || !rangeRoot.contains(btn)) return;
                ev.preventDefault();
                currentRange = btn.getAttribute('data-range');
                setRangeActive(rangeRoot, currentRange);
                draw(currentRange);
            });
        }

        draw(currentRange);
    }

    function playerTrendConfig(json, textColor) {
        var rows = json.data || [];
        var labels = rows.map(function (r) {
            var d = new Date(r.t * 1000);
            return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()] + ' ' + d.getDate();
        });

        return {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: t('chart.skill'),
                        data: rows.map(function (r) { return r.skill; }),
                        borderColor: 'rgb(129, 199, 132)',
                        backgroundColor: 'rgba(129, 199, 132, 0.18)',
                        fill: true, borderWidth: 1, tension: 0.35,
                        yAxisID: 'y', pointRadius: 0
                    },
                    {
                        label: t('chart.kills'),
                        data: rows.map(function (r) { return r.kills; }),
                        borderColor: COLORS.kills,
                        borderWidth: 1, tension: 0.25, yAxisID: 'y2', pointRadius: 0
                    },
                    {
                        label: t('chart.deaths'),
                        data: rows.map(function (r) { return r.deaths; }),
                        borderColor: 'rgb(200, 200, 200)',
                        borderWidth: 1, tension: 0.25, yAxisID: 'y2', pointRadius: 0
                    },
                    {
                        label: t('chart.headshots'),
                        data: rows.map(function (r) { return r.headshots; }),
                        borderColor: COLORS.headshots,
                        borderWidth: 1, tension: 0.25, yAxisID: 'y2', pointRadius: 0
                    },
                    {
                        label: t('chart.time_min'),
                        data: rows.map(function (r) {  return r.time || 0; }),
                        borderColor: COLORS.uptime,
                        backgroundColor: COLORS.uptimeFill,
                        fill: true, borderWidth: 1, tension: 0.25, yAxisID: 'y2', pointRadius: 0,
                        hidden: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: true,
                color: textColor,
                layout: { padding: { top: 24 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    splitLegend: { enabled: true, textColor: textColor },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            labelColor: tooltipLabelColor,
                            title: function (items) {
                                if (!items.length) return '';
                                var i = items[0].dataIndex;
                                var t = rows[i] ? rows[i].t : null;
                                if (!t) return items[0].label;
                                return new Date(t * 1000).toLocaleDateString();
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: textColor, maxTicksLimit: 10, autoSkip: true } },
                    y: {
                        beginAtZero: false,
                        title: { display: true, color: textColor, text: t('th.skill') },
                        ticks: { color: textColor }
                    },
                    y2: {
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        title: { display: true, color: textColor, text: t('th.kills') +' / ' + t('th.deaths') +' / ' + t('th.hs') },
                        ticks: { color: textColor, precision: 0 }
                    }
                }
            },
            plugins: [splitLegendPlugin]
        };
    }

    function renderPlayerTrend(root) {
        var canvas = root.querySelector('canvas');
        if (!canvas) return;
        var player = root.getAttribute('data-player');
        if (!player) return;

        var textColor = getChartTextColor(root);
        root.classList.add('is-loading');
        fetchJson({ type: 2, player: player })
            .then(function (json) {
                destroyChart(canvas);
                canvas._chart = new global.Chart(canvas, playerTrendConfig(json, textColor));
            })
            .catch(function (e) { console.error('player trend failed', e); })
            .then(function () { root.classList.remove('is-loading'); });
    }

    function renderGameTrend(root) {
        var canvas = root.querySelector('canvas');
        if (!canvas) return;
        var game = root.getAttribute('data-game') || '';

        var textColor = getChartTextColor(root);
        root.classList.add('is-loading');
        fetchJson({ type: 1, game: game })
            .then(function (json) {
                destroyChart(canvas);
                canvas._chart = new global.Chart(canvas, gameTrendConfig(json, textColor));
            })
            .catch(function (e) { console.error('chart trend failed', e); })
            .then(function () { root.classList.remove('is-loading'); });
    }

    function init(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        var nodes = root.querySelectorAll('[data-chart]:not([data-chart-ready])');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            el.setAttribute('data-chart-ready', '1');
            var kind = el.getAttribute('data-chart');
            if (kind === 'server-load')        renderServerLoad(el);
            else if (kind === 'game-trend')    renderGameTrend(el);
            else if (kind === 'player-trend')  renderPlayerTrend(el);
        }
    }

    global.HLStatsZCharts = { init: init };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }

    document.addEventListener('fetch:loaded', function (ev) {
        init(ev.target || document);
    });

})(window);
