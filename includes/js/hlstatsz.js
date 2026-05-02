const Fetch = {
    defaultId: null,

    ini(id) {
        this.defaultId = id;
    },

    async run(url, el = null, his = true) {

        if (!el && this.defaultId) {
            el = document.getElementById(this.defaultId);
        } else if (el && document.getElementById(el)) {
            el = document.getElementById(el);
        }

        if (his) {
            if (!history.state?.fetchUrl) {
                history.replaceState({ fetchUrl: location.href, fetchTarget: el?.id ?? null }, "");
            }
            history.pushState({ fetchUrl: url, fetchTarget: el?.id ?? null }, "", url + location.hash);
        }

        if (el) {
            el.classList.add("is-loading");
        }

        const response = await fetch(url, {
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        if (!response.ok) throw new Error(await response.text());

        const reader  = response.body.getReader();
        const decoder = new TextDecoder("utf-8");

        let buffer = "";
        let lastUpdate = 0;

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            if (el) {
                const now = performance.now();
                if (now - lastUpdate > 50) {
                    el.innerHTML = buffer;
                    lastUpdate = now;
                }
            }
        }

        if (el) {
            el.classList.remove("is-loading");
            el.innerHTML = buffer;
            el.querySelectorAll('[data-fetch-url]').forEach(function(sub) {
                Fetch.run(sub.dataset.fetchUrl, sub, false);
            });
            el.dispatchEvent(new CustomEvent("fetch:loaded", {
            detail: {
              url,
              html: buffer
            }
          }));
        }

        return buffer;
    }

};

window.addEventListener('popstate', (e) => {
    if (!e.state?.fetchUrl) return;
    if (window.location.href === e.state.fetchUrl) {
        window.location.reload();
    } else {
        window.location.href = e.state.fetchUrl;
    }
});

function waitForElement(selector, callback, { timeout = 0 } = {}) {
  const runIfReady = () => {
    const el = document.querySelector(selector);
    if (!el || el.offsetWidth <= 0) {
      return false;
    }

    const run = () => {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => callback(el));
      });
    };

    if (timeout > 0) {
      setTimeout(run, timeout);
    } else {
      run();
    }

    return true;
  };

  if (runIfReady()) {
    return;
  }

    const observer = new MutationObserver(() => {
    if (runIfReady()) {
            observer.disconnect();
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });
}

/* Drop Menu */
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".hlstats-dropbtn");

  if (btn) {
    e.stopPropagation();
    const dropdown = btn.closest(".hlstats-dropdown");
    if (!dropdown) return;

    // close any other open dropdowns first
    document.querySelectorAll(".hlstats-dropdown.open").forEach(d => {
      if (d !== dropdown) {
        d.classList.remove("open");
        d.querySelector(".hlstats-dropbtn")
         .setAttribute("aria-expanded", "false");
      }
    });

    const isOpen = dropdown.classList.toggle("open");
    btn.setAttribute("aria-expanded", isOpen);
    return;
  }

  // click outside — close all
  document.querySelectorAll(".hlstats-dropdown.open").forEach(d => {
    d.classList.remove("open");
    d.querySelector(".hlstats-dropbtn")
     .setAttribute("aria-expanded", "false");
  });
});

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    document.querySelectorAll(".hlstats-dropdown.open").forEach(d => {
      d.classList.remove("open");
      d.querySelector(".hlstats-dropbtn")
       .setAttribute("aria-expanded", "false");
    });
  }
});

/* Tabs */
const Tabs = {
    state: {},
    baseParams: {},
    endpoint: "hlstats.php?",

    init(config = {}) {
        this.baseParams = { ...(config.baseParams || {}) };
        this.endpoint = config.endpoint || this.endpoint;

        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                this.activateTab(tab, true);
            });
        });

        if (config.loadFromHash !== false) {
            this.loadFromHash();
        }

        window.addEventListener('popstate', () => this.loadFromHash());
    },

    setParams(params) {
        Object.assign(this.state, params);
    },

    refreshTab(extraParams = {}) {
        Object.assign(this.state, extraParams);

        const activeTab = document.querySelector('.hlstats-tabs li.active .tab');
        if (!activeTab) return;

        this.activateTab(activeTab, false);
    },

    activateTab(tab, updateHash) {
        if (!tab) return;
        const url = tab.dataset.url;
        const target = tab.dataset.target;

       if (updateHash) {
           const hash = tab.getAttribute('href');
           if (location.hash !== hash) {
               history.pushState(null, '', location.pathname + location.search + hash);
           }
       }

        this.loadTabContent(url, target, tab);
    },

    loadTabContent(pages, targetId, clickedTab) {
        const targetDiv = document.getElementById(targetId);

        if (targetDiv.dataset.loaded === "true" && !this.state.forceReload) {
            this.showTab(targetId, clickedTab);
            return;
        }

        targetDiv.innerHTML = "<div style='width:100%;text-align:center;margin-top:30px;opacity:0.8;'>Loading...</div>";
        this.showTab(targetId, clickedTab);

        const params = new URLSearchParams({
            ...this.baseParams,
            tab: pages,
            ...this.state
        });

        const url = this.endpoint + "&" + params.toString();

        targetDiv.addEventListener('fetch:loaded', function(e) {
            const tmp = document.createElement('div');
            tmp.innerHTML = e.detail.html;
            if (!tmp.textContent.trim()) {
                targetDiv.innerHTML = '<p class="hlstats-no-data center"><em>Not enough data</em></p>';
            }
        }, { once: true });

        Fetch.run(url, targetId, false);

        targetDiv.dataset.loaded = "true";
    },

    showTab(targetId, clickedTab) {
        document.querySelectorAll('.hlstats-tab-content')
            .forEach(div => div.classList.remove('active'));

        document.querySelectorAll('.hlstats-tabs li')
            .forEach(li => li.classList.remove('active'));

        document.getElementById(targetId).classList.add('active');

        if (clickedTab) {
            clickedTab.closest('li').classList.add('active');
        }
    },

    loadFromHash() {
        const hash = location.hash.replace('#', '');

        const tab =
            document.querySelector(`.tab[href="#${hash}"]`) ||
            document.querySelector('.tab'); // fallback to first tab

        this.activateTab(tab, false);
    }
};

/* Map */
function iniMap() {
    OpenMap = L.map('map',{zoomControl:false}).setView([35.45, -12.00], 2);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        minZoom: 2,
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(OpenMap);

    var bounds = L.latLngBounds(
        L.latLng(-89.98155760646617, -180),
        L.latLng(89.99346179538875, 180)
    );

    OpenMap.setMaxBounds(bounds);
    OpenMap.on('drag', () => {
        OpenMap.panInsideBounds(bounds, { animate: false });
    });

    markers = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: true,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 20
    });
}

function escHtml(s) {
  return String(s ?? "").replace(/[&<>"']/g, m => ({
    "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
  }[m]));
}

function stripBackslashes(s) {
  return String(s ?? "").replace(/\\/g, "");
}

function Location(country='', state='', city='')
{
    let loc = new Array();

    if (city && (countrydata == 1 || countrydata == 3) ) {
        loc.push(city);
    }

    if (state && (countrydata == 2 || countrydata == 3)) {
        loc.push(state);
    }

    if (country && countrydata > 0) {
        loc.push(country);
    }

    return loc.length ? loc.join(', ') : '(Unknown)';
}

function popupOpts() {
  return {
    closeButton: true,
    autoPan: true,
    maxWidth: 320,
    minWidth: 240,
    keepInView: true,
    className: "hlz-popup",
    offset: L.point(0, -8)
  };
}

function serverCard(server) {
  const name = escHtml(server.name);
  const city = escHtml(server.city);
  const country = escHtml(server.country);
  const addrRaw = stripBackslashes(server.addr);
  const addrText = escHtml(addrRaw);
  const geocity = Location(country, '', city);

  return `
    <div class="hlstats-map-card">
      <div class="hlstats-map-top">
        <div class="hlstats-map-title">${name}</div>
        <div class="hlstats-map-sub">${geocity}</div>
      </div>

      <div class="hlstats-map-row">
        <span class="hlstats-map-label">Server</span>
        <a class="hlstats-map-value" href="steam://connect/${encodeURI(addrRaw)}">${addrText}</a>
      </div>

      <div class="hlstats-map-row">
        <span class="hlstats-map-label">Players</span><span class="hlstats-map-value">${server.act_players}/${server.max_players}</span>
      </div>

      <div class="hlstats-map-row">
        <span class="hlstats-map-label">Map</span><span class="hlstats-map-value">${server.act_map}</span>
      </div>

      <div class="hlstats-map-actions">
        <a class="hlstats-map-ghost" href="steam://connect/${encodeURI(addrRaw)}"><button>Join</button></a>
        ${DetailPage ? `
        <a class="hlstats-map-ghost" href="hlstats.php?game=${encodeURIComponent(server.game)}"><button>Details</button></a>` : ``}
      </div>
    </div>
  `;
}

function playerCard(player) {
  const server = escHtml(player.server_name);
  const name = escHtml(stripBackslashes(player.name));
  const city = escHtml(player.cli_city);
  const state = escHtml(player.cli_state);
  const country = escHtml(player.cli_country);
  const connected = escHtml(player.connected);
  const team = escHtml(player.team_name);
  const geocity = Location(country, state, city);
  const flag = imagePath+'/flags/'+escHtml(player.cli_flag)+'.png';
 
  return `
    <div class="hlstats-map-card">
      <div class="hlstats-map-top">
        <div class="hlstats-map-title">
          </span><a href="hlstats.php?mode=playerinfo&player=${encodeURIComponent(player.playerId)}"><span class="hlstats-name">${name}</span></a>
        </div>
        <div class="hhlstats-map-sub"><span class="hlstats-flag"><img src="${flag}" onerror="this.style.display='none'"></span><span class="hlstats-name">${geocity}</span></div>
        <div class="hlstats-map-sub">${server}</div>
      </div>

      <div class="hlstats-map-row">
        <span class="hlstats-map-label">Connected:</span> <span class="hlstats-map-value"">${connected}</span>
      </div>

      <div class="hlstats-map-row">
        <span class="hlstats-map-label">Team:</span> <span class="hlstats-map-value"">${team}</span>
      </div>

      <div class="hlstats-map-row">
        <span class="hlstats-map-label">K:D</span> <span class="hlstats-map-value"">${player.kills}:${player.deaths}</span>
      </div>

      <div class="hlstats-map-actions">
        <a class="hlstats-map-ghost" href="hlstats.php?mode=playerinfo&player=${encodeURIComponent(player.playerId)}"><button>Profile</button></a>
      </div>
    </div>
  `;
}

function getTeamIcon(bg) {

const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48">
  <g fill="none" fill-rule="evenodd">
    <!-- Pin shape -->
    <path
      d="M24 4
         C16.8203 4 11 9.8203 11 17
         C11 24.1797 16.8203 32.5 24 44
         C31.1797 32.5 37 24.1797 37 17
         C37 9.8203 31.1797 4 24 4 Z"
      fill="${bg}"/>
    <!-- White inner circle -->
    <circle cx="24" cy="17" r="4" fill="#FFFFFF"/>
  </g>
</svg>
`;
  const url = "data:image/svg+xml;base64," + btoa(svg);

  return new LeafIcon({ iconUrl: url });
}


function createServers(servers) {
  servers.forEach(server => {
    const s_icon = getTeamIcon(server.bg);//new LeafIcon({ iconUrl: imagePath + "/server-marker.png" });

    const marker = L.marker([server.lat, server.lng], { icon: s_icon })
      .bindPopup(serverCard(server), popupOpts());

    markers.addLayer(marker);
  });
}

function createPlayer(players) {
  players.forEach(player => {
    const s_icon = getTeamIcon(player.bg);//new LeafIcon({ iconUrl: imagePath + "/player-marker.png" });

    const marker = L.marker([player.cli_lat, player.cli_lng], { icon: s_icon })
      .bindPopup(playerCard(player), popupOpts());

    markers.addLayer(marker);
  });

  OpenMap.addLayer(markers);
}

document.addEventListener('mousedown', function(event) {
    if ( event.target.tagName === 'BODY' ) {
        event.preventDefault();
        return false;
    }
})
