<?php
/*
HLstatsZ - Real-time player and clan rankings and statistics
Originally HLstatsX Community Edition by Nicholas Hastings (2008–20XX)
Based on ELstatsNEO by Malte Bayer, HLstatsX by Tobias Oetzel, and HLstats by Simon Garner

HLstats > HLstatsX > HLstatsX:CE > HLStatsZ
HLstatsZ continues a long lineage of open-source server stats tools for Half-Life and Source games.
This version is released under the GNU General Public License v2 or later.

For current support and updates:
   https://snipezilla.com
   https://github.com/SnipeZilla
   https://forums.alliedmods.net/forumdisplay.php?f=156
*/
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

	if ($auth->userdata["acclevel"] < 100) {
        die ("Access denied!");
	}

require_once INCLUDE_PATH . '/class_rcon.php';

if (empty($_SESSION['rcon_token'])) {
	$_SESSION['rcon_token'] = bin2hex(random_bytes(16));
}

// GameEngine: 1 = GoldSrc (UDP rcon), 2/3 = Source (TCP rcon)
$rconFrom = "
	hlstats_Servers s
	LEFT JOIN hlstats_Games g
		ON g.code = s.game
	LEFT JOIN hlstats_Servers_Config sc
		ON sc.serverId = s.serverId AND sc.parameter = 'GameEngine'
	LEFT JOIN hlstats_Games_Defaults gd
		ON gd.code = COALESCE(g.realgame, s.game) AND gd.parameter = 'GameEngine'
";

// The classic HL1 mods are GoldSrc whatever GameEngine says (the dod default is 2)
function rcon_is_goldsrc($engine, $realgame)
{
	return $engine === '1' || in_array($realgame, array('cstrike', 'tfc', 'dod', 'ns', 'valve'), true);
}

// JSON answer of the endpoints below
$reply = function (array $data) {
	// Drop the task output buffer opened by admin.php
	while (ob_get_level() > 0) {
		if (!@ob_end_clean()) {
			break;
		}
	}
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
};

/*
 * Other servers, not in HLstatsZ: the admin gives their address, RCON password and engine. They are kept in the
 * session only, under negative ids, and their password never goes back to the browser.
 */
if (isset($_POST['rcon_other'])) {
	$token = $_POST['rcon_token'] ?? '';
	if (!is_string($token) || !hash_equals($_SESSION['rcon_token'], $token)) {
		$reply(array('ok' => false, 'error' => 'Security token mismatch. Reload the page and try again.'));
	}
	$others = is_array($_SESSION['rcon_other'] ?? null) ? $_SESSION['rcon_other'] : array();
	if ($_POST['rcon_other'] === 'remove') {
		unset($others[(int) ($_POST['id'] ?? 0)]);
		$_SESSION['rcon_other'] = $others;
		$reply(array('ok' => true));
	}
	$address  = is_string($_POST['address'] ?? null) ? trim($_POST['address']) : '';
	$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
	$goldsrc  = ($_POST['engine'] ?? '') === 'goldsrc';
	if (!preg_match('/^(\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+):(\d{1,5})$/', $address, $m) || (int) $m[2] < 1 || (int) $m[2] > 65535) {
		$reply(array('ok' => false, 'error' => 'Enter the address as IP:port, such as 203.0.113.7:27015.'));
	}
	// GoldSrc sends the password between quotes
	if ($password === '' || strlen($password) > 128 || preg_match('/[\x00-\x1F"]/', $password)) {
		$reply(array('ok' => false, 'error' => 'Enter its RCON password (without a double quote).'));
	}
	if (count($others) >= 20) {
		$reply(array('ok' => false, 'error' => 'Twenty other servers at most: remove one first.'));
	}
	$id          = min(array_merge(array(0), array_keys($others))) - 1;
	$others[$id] = array('host' => trim($m[1], '[]'), 'port' => (int) $m[2], 'name' => $address, 'password' => $password, 'goldsrc' => $goldsrc);
	$_SESSION['rcon_other'] = $others;
	$reply(array('ok' => true, 'id' => $id, 'name' => $address, 'engine' => $goldsrc ? 'GoldSrc' : 'Source'));
}

/*
 * Command endpoint: runs one command on one server and answers with JSON.
 */
if (isset($_POST['rcon_command'])) {
	$token = $_POST['rcon_token'] ?? '';
	if (!is_string($token) || !hash_equals($_SESSION['rcon_token'], $token)) {
		$reply(array('ok' => false, 'error' => 'Security token mismatch. Reload the page and try again.'));
	}
	// Release the session lock so a command sent to several servers runs in parallel
	session_write_close();

	$command = is_string($_POST['rcon_command']) ? trim(str_replace(array("\r", "\n", "\0"), ' ', $_POST['rcon_command'])) : '';
	if ($command === '') {
		$reply(array('ok' => false, 'error' => 'Enter a command.'));
	}
	if (strlen($command) > 500) {
		$reply(array('ok' => false, 'error' => 'The command is too long (500 characters max).'));
	}

	$serverId = (int) ($_POST['rcon_server'] ?? 0);
	if ($serverId < 0) {
		// Another server, kept in the session
		$other = $_SESSION['rcon_other'][$serverId] ?? null;
		if (!$other) {
			$reply(array('ok' => false, 'error' => 'This server is no longer in your list (it lasts for your session). Add it again.'));
		}
		$server = array('address' => $other['host'], 'port' => $other['port'], 'rcon_password' => $other['password'], 'goldsrc' => $other['goldsrc']);
	} else {
		$db->query("
			SELECT
				s.address,
				s.port,
				s.rcon_password,
				COALESCE(g.realgame, s.game) AS realgame,
				COALESCE(sc.value, gd.value, '') AS engine
			FROM
				$rconFrom
			WHERE
				s.serverId = $serverId
			LIMIT 1
		");
		$server = $db->fetch_array();

		if (!$server) {
			$reply(array('ok' => false, 'error' => 'This server no longer exists. Reload the page.'));
		}
		if ($server['rcon_password'] === '') {
			$reply(array('ok' => false, 'error' => 'No RCON password is set for this server (Game Settings › Edit Servers).'));
		}
		$server['goldsrc'] = rcon_is_goldsrc($server['engine'], $server['realgame']);
	}

	$rcon   = new Rcon($server['address'], $server['port'], $server['rcon_password'], $server['goldsrc']);
	$start  = microtime(true);
	$output = $rcon->execute($command);
	$ms     = (int) round((microtime(true) - $start) * 1000);

	if ($output === false) {
		$reply(array('ok' => false, 'error' => $rcon->error, 'ms' => $ms));
	}
	$reply(array('ok' => true, 'output' => $output, 'ms' => $ms));
}

/*
 * Console page
 */
$db->query("
	SELECT
		s.serverId,
		s.name,
		s.address,
		s.port,
		s.publicaddress,
		s.game,
		s.act_players,
		s.max_players,
		s.act_map,
		s.rcon_password <> '' AS has_rcon,
		COALESCE(g.name, s.game) AS gamename,
		COALESCE(g.realgame, s.game) AS realgame,
		COALESCE(sc.value, gd.value, '') AS engine
	FROM
		$rconFrom
	ORDER BY
		gamename,
		s.sortorder,
		s.name,
		s.serverId
");
$servers = $db->fetch_row_set() ?: array();

$groups  = array();
$enabled = 0;
foreach ($servers as $server) {
	$code = $server['game'];
	if (!isset($groups[$code])) {
		$image = getImage("/games/$code/game") ?: getImage('/games/' . $server['realgame'] . '/game');
		$groups[$code] = array(
			'name'    => html_entity_decode($server['gamename'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			'icon'    => $image ? $image['url'] : IMAGE_PATH . '/game.png',
			'enabled' => 0,
			'servers' => array(),
		);
	}
	$groups[$code]['servers'][] = $server;
	if ($server['has_rcon']) {
		$groups[$code]['enabled']++;
		$enabled++;
	}
}

// Commands with data-run are sent right away; the others are only typed into the prompt (a trailing space means "add arguments")
$quickCommands = array(
	'status'           => true,
	'stats'            => true,
	'users'            => true,
	'maps *'           => true,
	'meta list'        => true,
	'sm plugins list'  => true,
	'say '             => false,
	'changelevel '     => false,
	'mp_restartgame 1' => false,
);
?>
<?php
$others = is_array($_SESSION['rcon_other'] ?? null) ? $_SESSION['rcon_other'] : array();
?>
<div class="panel rcon" id="rcon" data-url="<?= htmlspecialchars($g_options['scripturl'] . '?mode=admin&task=tools_rcon') ?>" data-token="<?= htmlspecialchars($_SESSION['rcon_token']) ?>">
<?php
if (!$servers) {
	message('warning', 'No servers in HLstatsZ yet: add one under Game Settings › Add Server, or another server below.');
} elseif (!$enabled) {
	message('warning', 'None of your servers has an RCON password. Set one under Game Settings › Edit Servers.');
}
?>
<div class="hlstats-admin-note">
	Commands are sent from the web server to each selected server's IP address and port.
	RCON passwords are read from <em>Edit Servers</em> and never reach your browser; those of other servers are kept
	for your session only.
</div>

<form class="rcon-form" method="post" data-bound="1" autocomplete="off">

<section class="rcon-servers" aria-label="Servers">
	<div class="rcon-toolbar">
		<span class="rcon-toolbar-title">Servers</span>
		<span class="rcon-count" aria-live="polite"></span>
<?php if (count($servers) > 6): ?>
		<input type="text" class="rcon-filter" placeholder="Filter servers…" aria-label="Filter servers">
<?php endif; ?>
<?php if ($enabled > 1): ?>
		<button type="button" class="rcon-btn" data-select="all">All</button>
		<button type="button" class="rcon-btn" data-select="none">None</button>
<?php endif; ?>
	</div>

<?php foreach ($groups as $code => $group): ?>
	<div class="rcon-group">
		<div class="rcon-group-head">
			<img src="<?= htmlspecialchars($group['icon']) ?>" alt="">
			<span><?= htmlspecialchars($group['name']) ?></span>
<?php if (count($groups) > 1 && $group['enabled'] > 1): ?>
			<button type="button" class="rcon-link" data-select="group">Select all</button>
<?php endif; ?>
		</div>
		<div class="rcon-grid">
<?php
	foreach ($group['servers'] as $server):
		$id      = (int) $server['serverId'];
		$name    = html_entity_decode($server['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$addr    = $server['address'] . ':' . $server['port'];
		$engine  = rcon_is_goldsrc($server['engine'], $server['realgame']) ? 'GoldSrc' : 'Source';
		$live    = $server['act_map'] !== ''
			? $server['act_map'] . ' · ' . (int) $server['act_players'] . '/' . (int) $server['max_players'] . ' players'
			: 'No live data';
		$search  = mb_strtolower(implode(' ', array($name, $addr, $server['publicaddress'], $server['act_map'], $group['name'])));
?>
			<label class="rcon-server<?= $server['has_rcon'] ? '' : ' is-disabled' ?>" data-id="<?= $id ?>" data-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
				<input type="checkbox" value="<?= $id ?>"<?= $server['has_rcon'] ? '' : ' disabled' ?>>
				<span class="rcon-server-name">
					<span class="rcon-dot" data-state="idle" data-tooltip="<?= $server['has_rcon'] ? 'Not contacted yet' : 'No RCON password' ?>"></span>
					<span class="rcon-server-title" title="<?= htmlspecialchars($name, ENT_QUOTES) ?>"><?= htmlspecialchars($name) ?></span>
				</span>
				<span class="rcon-server-meta"><?= htmlspecialchars($addr) ?> · <?= $engine ?></span>
<?php if ($server['has_rcon']): ?>
				<span class="rcon-server-meta"><?= htmlspecialchars($live) ?></span>
<?php else: ?>
				<span class="rcon-server-meta">No RCON password · <a href="<?= htmlspecialchars($g_options['scripturl'] . '?mode=admin&task=servers&game=' . urlencode($code)) ?>">Edit Servers</a></span>
<?php endif; ?>
			</label>
<?php endforeach; ?>
		</div>
	</div>
<?php endforeach; ?>
	<div class="rcon-group rcon-others">
		<div class="rcon-group-head"><span>Other servers</span></div>
		<div class="rcon-grid">
<?php foreach ($others as $oid => $other): ?>
			<label class="rcon-server" data-id="<?= (int) $oid ?>" data-name="<?= htmlspecialchars($other['name'], ENT_QUOTES) ?>" data-search="<?= htmlspecialchars(mb_strtolower($other['name']), ENT_QUOTES) ?>">
				<input type="checkbox" value="<?= (int) $oid ?>">
				<span class="rcon-server-name">
					<span class="rcon-dot" data-state="idle" data-tooltip="Not contacted yet"></span>
					<span class="rcon-server-title"><?= htmlspecialchars($other['name']) ?></span>
					<button type="button" class="rcon-remove" data-remove="<?= (int) $oid ?>" aria-label="Remove this server">&times;</button>
				</span>
				<span class="rcon-server-meta"><?= $other['goldsrc'] ? 'GoldSrc' : 'Source' ?> · for this session</span>
			</label>
<?php endforeach; ?>
		</div>
		<div class="rcon-other">
			<input type="text" class="rcon-other-address" placeholder="IP:port, such as 203.0.113.7:27015" aria-label="Address of the other server" spellcheck="false" autocomplete="off">
			<input type="password" class="rcon-other-password" placeholder="RCON password" aria-label="Its RCON password" autocomplete="new-password">
			<select class="rcon-other-engine" aria-label="Its engine">
				<option value="source">Source (CS2, CS:S, TF2…)</option>
				<option value="goldsrc">GoldSrc (CS 1.6, HL…)</option>
			</select>
			<button type="button" class="rcon-btn" data-action="add-other">Add</button>
			<span class="rcon-other-error" aria-live="polite"></span>
		</div>
	</div>
</section>

<section class="rcon-console" aria-label="Console">
	<div class="rcon-console-bar">
		<span class="rcon-console-title">Console</span>
		<label class="rcon-wrap"><input type="checkbox" checked> Wrap lines</label>
		<button type="button" data-action="copy">Copy</button>
		<button type="button" data-action="clear">Clear</button>
	</div>
	<div class="rcon-output" role="log" tabindex="0">
		<div class="rcon-note rcon-welcome">
			<div>HLstatsZ RCON console. A command is sent to every selected server.</div>
			<div>Enter sends · ↑ ↓ history · Esc clears the line · Ctrl+L clears the console</div>
		</div>
	</div>
	<div class="rcon-quick">
<?php foreach ($quickCommands as $cmd => $run): ?>
		<button type="button" data-cmd="<?= htmlspecialchars($cmd) ?>"<?= $run ? ' data-run' : '' ?>><?= htmlspecialchars(substr($cmd, -1) === ' ' ? rtrim($cmd) . ' …' : $cmd) ?></button>
<?php endforeach; ?>
	</div>
	<div class="rcon-prompt">
		<span class="rcon-caret" aria-hidden="true">]</span>
		<input type="text" class="rcon-input" maxlength="500" spellcheck="false" autocapitalize="off" aria-label="RCON command"<?= $enabled || $others ? '' : ' disabled' ?>>
		<button type="submit" class="rcon-send">Send</button>
	</div>
</section>

</form>
</div>

<script>
(function () {
    const root = document.getElementById('rcon');
    const form = root && root.querySelector('.rcon-form');
    if (!form) return;

    const url    = new URL(root.dataset.url, window.location.href).toString();
    const token  = root.dataset.token;
    const out    = root.querySelector('.rcon-output');
    const input  = root.querySelector('.rcon-input');
    const send   = root.querySelector('.rcon-send');
    const count  = root.querySelector('.rcon-count');
    const filter = root.querySelector('.rcon-filter');
    const wrap   = root.querySelector('.rcon-wrap input');
    let cards, boxes;   // again when another server is added or removed

    function collect() {
        cards = [...root.querySelectorAll('.rcon-server')];
        boxes = cards.map(card => card.querySelector('input')).filter(box => !box.disabled);
    }
    collect();

    const DANGER      = /^\s*(quit|exit|_restart|restart|killserver|shutdown)\b/i;
    const MAX_ENTRIES = 200;
    const MAX_HISTORY = 50;

    // Per-browser conveniences only; storage can be unavailable (private mode)
    const store = {
        get(key, fallback) {
            try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); } catch {}
        }
    };

    let commandHistory = [].concat(store.get('hlz-rcon-history', [])).filter(cmd => typeof cmd === 'string').slice(-MAX_HISTORY);
    let historyPos     = commandHistory.length;
    let draft          = '';

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    /* ── Server selection ── */

    function selectedCards() {
        return boxes.filter(box => box.checked).map(box => box.closest('.rcon-server'));
    }

    function refresh() {
        cards.forEach(card => card.classList.toggle('is-selected', card.querySelector('input').checked));
        root.querySelectorAll('[data-select="group"]').forEach(toggle => {
            const own = [...toggle.closest('.rcon-group').querySelectorAll('.rcon-server input:not(:disabled)')];
            toggle.textContent = own.every(box => box.checked) ? 'Clear' : 'Select all';
        });

        const selected = selectedCards();
        count.textContent = selected.length ? selected.length + ' of ' + boxes.length + ' selected' : 'No server selected';
        input.placeholder = !boxes.length          ? 'No server has an RCON password'
                          : selected.length === 0  ? 'Select a server above…'
                          : selected.length === 1  ? 'Command for ' + selected[0].dataset.name
                          : 'Command for ' + selected.length + ' servers';
        send.disabled = selected.length === 0;
        store.set('hlz-rcon-servers', selected.map(card => card.dataset.id));
    }

    root.querySelector('.rcon-servers').addEventListener('change', event => {
        if (event.target.matches('.rcon-server input')) refresh();
    });

    /* ── Other servers, not in HLstatsZ: kept in the session ── */

    const other = {
        address:  root.querySelector('.rcon-other-address'),
        password: root.querySelector('.rcon-other-password'),
        engine:   root.querySelector('.rcon-other-engine'),
        error:    root.querySelector('.rcon-other-error'),
        grid:     root.querySelector('.rcon-others .rcon-grid')
    };

    async function post(fields) {
        const body = new FormData();
        for (const [key, value] of Object.entries(fields)) body.append(key, value);
        body.append('rcon_token', token);
        try {
            const res = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return JSON.parse(await res.text());
        } catch {
            return { ok: false, error: 'Unexpected reply from the web server. Reload the page and try again.' };
        }
    }

    function otherCard(id, name, engine) {
        const card  = el('label', 'rcon-server');
        const box   = el('input');
        const title = el('span', 'rcon-server-name');
        const dot   = el('span', 'rcon-dot');
        const drop  = el('button', 'rcon-remove', '×');
        box.type = 'checkbox';
        box.value = id;
        dot.dataset.state = 'idle';
        dot.dataset.tooltip = 'Not contacted yet';
        drop.type = 'button';
        drop.dataset.remove = id;
        drop.setAttribute('aria-label', 'Remove this server');
        title.append(dot, el('span', 'rcon-server-title', name), drop);
        card.dataset.id = id;
        card.dataset.name = name;
        card.dataset.search = name.toLowerCase();
        card.append(box, title, el('span', 'rcon-server-meta', engine + ' · for this session'));
        return card;
    }

    async function addOther() {
        other.error.textContent = '';
        const data = await post({ rcon_other: 'add', address: other.address.value.trim(), password: other.password.value, engine: other.engine.value });
        if (!data.ok) {
            other.error.textContent = data.error;
            return;
        }
        const card = otherCard(data.id, data.name, data.engine);
        other.grid.append(card);
        other.address.value = other.password.value = '';
        collect();
        card.querySelector('input').checked = true;
        input.disabled = false;
        refresh();
        input.focus();
    }

    root.querySelector('[data-action="add-other"]').addEventListener('click', addOther);
    [other.address, other.password].forEach(field => field.addEventListener('keydown', event => {
        if (event.key === 'Enter') {   // not the command form around
            event.preventDefault();
            addOther();
        }
    }));

    other.grid.addEventListener('click', async event => {
        const drop = event.target.closest('[data-remove]');
        if (!drop) return;
        event.preventDefault();   // the card is a label: no toggle
        const data = await post({ rcon_other: 'remove', id: drop.dataset.remove });
        if (data.ok) {
            drop.closest('.rcon-server').remove();
            collect();
            refresh();
        }
    });

    root.querySelectorAll('[data-select]').forEach(button => button.addEventListener('click', () => {
        let scope, state;
        if (button.dataset.select === 'group') {
            scope = [...button.closest('.rcon-group').querySelectorAll('.rcon-server input:not(:disabled)')];
            state = !scope.every(box => box.checked);
        } else {
            state = button.dataset.select === 'all';
            scope = state ? boxes.filter(box => !box.closest('.rcon-server').hidden) : boxes;
        }
        scope.forEach(box => { box.checked = state; });
        refresh();
    }));

    filter?.addEventListener('input', () => {
        const query = filter.value.trim().toLowerCase();
        cards.forEach(card => { card.hidden = query !== '' && !card.dataset.search.includes(query); });
        root.querySelectorAll('.rcon-group').forEach(group => {
            group.hidden = !group.querySelector('.rcon-server:not([hidden])');
        });
    });

    function setState(card, state, tooltip) {
        const dot = card.querySelector('.rcon-dot');
        dot.dataset.state   = state;
        dot.dataset.tooltip = tooltip;
    }

    /* ── Console output ── */

    function atBottom() {
        return out.scrollHeight - out.scrollTop - out.clientHeight < 40;
    }

    function append(node) {
        const stick = atBottom();
        out.append(node);
        while (out.childElementCount > MAX_ENTRIES) out.firstElementChild.remove();
        if (stick) out.scrollTop = out.scrollHeight;
    }

    async function copyText(text, button) {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            // Clipboard API needs HTTPS (or localhost)
            const area = el('textarea');
            area.value = text;
            area.style.cssText = 'position:fixed;top:0;opacity:0';
            document.body.append(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
        button.dataset.label ??= button.textContent;
        button.textContent = 'Copied';
        clearTimeout(button.copyTimer);
        button.copyTimer = setTimeout(() => { button.textContent = button.dataset.label; }, 1200);
    }

    function addEntry(card, command) {
        const entry = el('div', 'rcon-entry is-pending');
        const head  = el('div', 'rcon-entry-head');
        const tag   = el('span', 'rcon-tag', card.dataset.name);
        const meta  = el('span', 'rcon-entry-meta');
        const copy  = el('button', 'rcon-copy', 'Copy');
        const time  = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

        tag.style.setProperty('--tag', 'hsl(' + (card.dataset.id * 137.508 % 360) + ', 70%, 70%)');
        copy.type = 'button';
        copy.addEventListener('click', () => copyText(entry.querySelector('.rcon-body').textContent, copy));
        meta.append(el('span', 'rcon-ms'), copy);

        head.append(el('span', 'rcon-time', time), tag, el('span', 'rcon-cmd', '] ' + command), meta);
        entry.append(head, el('pre', 'rcon-body', 'Waiting for reply'));
        append(entry);
        return entry;
    }

    function finishEntry(entry, data) {
        const stick = atBottom();
        const body  = entry.querySelector('.rcon-body');

        entry.classList.remove('is-pending');
        entry.classList.add(data.ok ? 'is-ok' : 'is-error');
        entry.querySelector('.rcon-ms').textContent = Number.isFinite(data.ms) ? data.ms + ' ms' : '';
        body.classList.toggle('is-empty', data.ok && data.output === '');
        body.textContent = !data.ok ? data.error : data.output === '' ? '(no output)' : data.output;

        if (stick) out.scrollTop = out.scrollHeight;
    }

    /* ── Commands ── */

    async function execute(card, command) {
        const entry = addEntry(card, command);
        setState(card, 'busy', 'Waiting for reply…');

        const body = new FormData();
        body.append('rcon_server', card.dataset.id);
        body.append('rcon_command', command);
        body.append('rcon_token', token);

        let data;
        try {
            const res  = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const text = await res.text();
            try { data = JSON.parse(text); } catch { data = null; }
            if (!data || typeof data !== 'object') {
                data = { ok: false, error: text.includes('authusername')
                    ? 'Your admin session has expired. Reload the page and sign in again.'
                    : 'Unexpected reply from the web server (HTTP ' + res.status + '). Reload the page and try again.' };
            }
        } catch (err) {
            data = { ok: false, error: 'Could not reach the web server: ' + err.message };
        }

        finishEntry(entry, data);
        setState(card, data.ok ? 'ok' : 'error', data.ok ? 'Last command OK · ' + data.ms + ' ms' : data.error);
    }

    function run(command) {
        command = command.trim();
        if (!command) return;

        const targets = selectedCards();
        if (!targets.length) {
            append(el('div', 'rcon-note is-warning', 'Select at least one server first.'));
            return;
        }
        const where = targets.length === 1 ? targets[0].dataset.name : targets.length + ' servers';
        if (DANGER.test(command) && !confirm('Run "' + command + '" on ' + where + '?')) return;

        if (commandHistory[commandHistory.length - 1] !== command) commandHistory.push(command);
        commandHistory = commandHistory.slice(-MAX_HISTORY);
        historyPos     = commandHistory.length;
        draft          = '';
        store.set('hlz-rcon-history', commandHistory);

        input.value = '';
        out.querySelector('.rcon-welcome')?.remove();
        targets.forEach(card => execute(card, command));
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        run(input.value);
        input.focus();
    });

    root.querySelectorAll('[data-cmd]').forEach(button => button.addEventListener('click', () => {
        if (button.hasAttribute('data-run')) {
            run(button.dataset.cmd);
            return;
        }
        input.value = button.dataset.cmd;
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }));

    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            if (!commandHistory.length) return;
            event.preventDefault();
            if (historyPos === commandHistory.length) draft = input.value;
            historyPos  = Math.min(commandHistory.length, Math.max(0, historyPos + (event.key === 'ArrowUp' ? -1 : 1)));
            input.value = historyPos === commandHistory.length ? draft : commandHistory[historyPos];
            input.setSelectionRange(input.value.length, input.value.length);
        } else if (event.key === 'Escape') {
            input.value = '';
            historyPos  = commandHistory.length;
        } else if (event.ctrlKey && event.key.toLowerCase() === 'l') {
            event.preventDefault();
            out.replaceChildren();
        }
    });

    root.querySelector('[data-action="clear"]').addEventListener('click', () => {
        out.replaceChildren();
        input.focus();
    });

    root.querySelector('[data-action="copy"]').addEventListener('click', event => {
        const text = [...out.querySelectorAll('.rcon-entry')].map(entry => {
            const head = [...entry.querySelectorAll('.rcon-time, .rcon-tag, .rcon-cmd, .rcon-ms')]
                .map(node => node.textContent).filter(Boolean).join('  ');
            return head + '\n' + entry.querySelector('.rcon-body').textContent;
        }).join('\n\n');
        copyText(text, event.currentTarget);
    });

    wrap.checked = store.get('hlz-rcon-wrap', true) !== false;
    out.classList.toggle('is-nowrap', !wrap.checked);
    wrap.addEventListener('change', () => {
        out.classList.toggle('is-nowrap', !wrap.checked);
        store.set('hlz-rcon-wrap', wrap.checked);
    });

    // Restore the last selection; a lone server is selected by default
    const saved = [].concat(store.get('hlz-rcon-servers', []));
    boxes.forEach(box => { box.checked = saved.includes(box.value) || boxes.length === 1; });
    refresh();

    if (selectedCards().length && window.matchMedia('(pointer: fine)').matches) {
        input.focus({ preventScroll: true });
    }
}());
</script>
