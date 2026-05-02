/* 
 * :) SnipeZilla.com
 * HLstatsZ - HTML5 hitbox renderer
 *
 * Public API:
 *   window.switch_weapon(weaponKey)   // 'total' shows the aggregate
 */
(function () {
    "use strict";

    const PARTS_DEFAULT = [
        { key: "head",     name: "Head",      marker: [50,  5], label: [85,  6] },
        { key: "chest",    name: "Chest",     marker: [50, 26], label: [92, 28] },
        { key: "leftarm",  name: "Left Arm",  marker: [65, 38], label: [95, 50] },
        { key: "rightarm", name: "Right Arm", marker: [35, 38], label: [ 5, 50] },
        { key: "stomach",  name: "Stomach",   marker: [50, 40], label: [10, 60] },
        { key: "leftleg",  name: "Left Leg",  marker: [60, 70], label: [90, 90] },
        { key: "rightleg", name: "Right Leg", marker: [40, 70], label: [10, 90] }
    ];

    // Per-game overrides
    const GAME_PARTS = {
        cs2: [
            { key: "head",     name: "Head",      marker: [50,  5], label: [85,  6] },
            { key: "chest",    name: "Chest",     marker: [50, 26], label: [92, 28] },
            { key: "leftarm",  name: "Left Arm",  marker: [65, 38], label: [95, 50] },
            { key: "rightarm", name: "Right Arm", marker: [35, 38], label: [ 5, 50] },
            { key: "stomach",  name: "Stomach",   marker: [50, 40], label: [10, 60] },
            { key: "leftleg",  name: "Left Leg",  marker: [55, 70], label: [90, 90] },
            { key: "rightleg", name: "Right Leg", marker: [42, 70], label: [10, 90] }
        ]
    };

    function partsFor(game) {
        return GAME_PARTS[game] || PARTS_DEFAULT;
    }
    const SVG_NS = "http://www.w3.org/2000/svg";
    const FALLBACK_MODEL = "ct";

    let CURRENT_WEAPON = null;
    let RESIZE_HANDLER = null;

    function loadData() {
        const el = document.getElementById("hitbox-data");
        if (!el) return null;
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    function colorFor(percent) {
        if (percent >= 25) return "red";
        if (percent >= 10) return "orange";
        return "green";
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c];
        });
    }

    function fmtInt(n) {
        return Number(n || 0).toLocaleString();
    }

    function pretty(weapon) {
        if (weapon === "total") return "All Weapons";
        return String(weapon || "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function buildStatsPanel(weapon, data, total, parts) {
        const wrap = document.createElement("div");
        wrap.className = "hitbox-stats";

        const title = document.createElement("div");
        title.className = "hitbox-weapon-name";
        title.textContent = pretty(weapon);
        wrap.appendChild(title);

        const totalEl = document.createElement("div");
        totalEl.className = "hitbox-total";
        totalEl.innerHTML = "Total Hits <strong>" + fmtInt(total) + "</strong>";
        wrap.appendChild(totalEl);

        const list = document.createElement("ul");
        list.className = "hitbox-bars";
        parts.forEach(function (p) {
            const v = Number(data[p.key]) || 0;
            const pct = total > 0 ? (v / total) * 100 : 0;
            const cls = colorFor(pct);

            const li = document.createElement("li");
            li.innerHTML =
                '<span class="bar-label">' + escapeHtml(p.name) + "</span>" +
                '<div class="bar-track">' +
                    '<div class="bar-fill ' + cls + '" style="width:' + pct.toFixed(1) + '%"></div>' +
                "</div>";
            list.appendChild(li);
        });
        wrap.appendChild(list);

        return wrap;
    }

    function fitStageToImage(stage, img, container) {
        if (!img.naturalWidth || !img.naturalHeight) return;
        const cw = container.clientWidth;
        const ch = container.clientHeight;
        if (!cw || !ch) return;

        const imgRatio = img.naturalWidth / img.naturalHeight;
        const ctRatio  = cw / ch;

        let w, h, left, top;
        if (imgRatio > ctRatio) {
            w = cw;
            h = cw / imgRatio;
            left = 0;
            top  = (ch - h) / 2;
        } else {
            h = ch;
            w = ch * imgRatio;
            left = (cw - w) / 2;
            top  = 0;
        }
        stage.style.left   = left + "px";
        stage.style.top    = top  + "px";
        stage.style.width  = w    + "px";
        stage.style.height = h    + "px";
    }

    function buildModelPanel(data, total, parts) {
        const wrap = document.createElement("div");
        wrap.className = "hitbox-model";

        const stage = document.createElement("div");
        stage.className = "hitbox-stage";
        wrap.appendChild(stage);

        const img = document.createElement("img");
        img.alt = "Player model";
        img.draggable = false;
        img.src = "./hlstatsimg/hitbox/" + (data.model || FALLBACK_MODEL) + ".png";
        img.addEventListener("error", function () {
            if (!img.dataset.fallback) {
                img.dataset.fallback = "1";
                img.src = "./hlstatsimg/hitbox/" + FALLBACK_MODEL + ".png";
            }
        });
        stage.appendChild(img);

        const svg = document.createElementNS(SVG_NS, "svg");
        svg.setAttribute("class", "hitbox-lines");
        svg.setAttribute("viewBox", "0 0 100 100");
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("aria-hidden", "true");

        parts.forEach(function (p) {
            const v = Number(data[p.key]) || 0;
            const pct = total > 0 ? (v / total) * 100 : 0;
            const line = document.createElementNS(SVG_NS, "line");
            line.setAttribute("x1", p.marker[0]);
            line.setAttribute("y1", p.marker[1]);
            line.setAttribute("x2", p.label[0]);
            line.setAttribute("y2", p.label[1]);
            line.setAttribute("class", "hitbox-line " + colorFor(pct));
            svg.appendChild(line);
        });
        stage.appendChild(svg);

        // Markers + floating labels
        parts.forEach(function (p) {
            const v = Number(data[p.key]) || 0;
            const pct = total > 0 ? (v / total) * 100 : 0;
            const cls = colorFor(pct);

            const marker = document.createElement("span");
            marker.className = "hitbox-marker " + cls;
            marker.style.left = p.marker[0] + "%";
            marker.style.top  = p.marker[1] + "%";
            marker.title = p.name + ": " + fmtInt(v) + " hits (" + pct.toFixed(1) + "%)";
            stage.appendChild(marker);

            const label = document.createElement("span");
            label.className = "hitbox-label " + cls;
            label.style.left = p.label[0] + "%";
            label.style.top  = p.label[1] + "%";
            label.innerHTML =
                "<strong>" + fmtInt(v) + "</strong>" +
                '<span class="pct">' + pct.toFixed(pct < 10 ? 1 : 0) + "%</span>";
            stage.appendChild(label);
        });

        const fit = function () { fitStageToImage(stage, img, wrap); };

        if (img.complete && img.naturalWidth) {
            requestAnimationFrame(fit);
        } else {
            img.addEventListener("load", fit, { once: true });
        }

        // Re-run on resize.
        if (typeof ResizeObserver !== "undefined") {
            const ro = new ResizeObserver(fit);
            ro.observe(wrap);
            wrap._hitboxResizeObserver = ro;
        } else {
            if (RESIZE_HANDLER) window.removeEventListener("resize", RESIZE_HANDLER);
            RESIZE_HANDLER = fit;
            window.addEventListener("resize", RESIZE_HANDLER);
        }

        return wrap;
    }

    function render(weapon) {
        const frame = document.getElementById("hitbox-frame");
        if (!frame) return;

        const all = loadData();
        if (!all) return;

        const data = all[weapon];
        if (!data) return;

        const parts = partsFor(all.game);
        const total = parts.reduce(function (sum, p) {
            return sum + (Number(data[p.key]) || 0);
        }, 0);

        const display = document.createElement("div");
        display.className = "hitbox-display";
        display.dataset.weapon = weapon;
        display.appendChild(buildStatsPanel(weapon, data, total, parts));
        display.appendChild(buildModelPanel(data, total, parts));

        frame.replaceChildren(display);
        CURRENT_WEAPON = weapon;
    }

    window.switch_weapon = function (weapon) {
        const frame = document.getElementById("hitbox-frame");
        if (!frame) return;
        const current = frame.firstElementChild;
        if (weapon === CURRENT_WEAPON && current && current.dataset.weapon === weapon) {
            return;
        }
        render(weapon);
    };

    function init() {
        if (typeof waitForElement === "function") {
            waitForElement("#hitbox-frame", function () { render("total"); }, { timeout: 0 });
        } else {
            render("total");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
