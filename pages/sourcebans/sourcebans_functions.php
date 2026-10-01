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

// SourceBans mod folders whose HLstatsZ game code differs (SourceBans' "cstrike" is CS:Source)
const SB_MOD_GAMES = array(
	'cstrike'         => 'css',
	'dod'             => 'dods',
	'insurgency'      => 'insmod',
	'FortressForever' => 'ff',
	'left4dead'       => 'l4d',
	'left4dead2'      => 'l4d2',
	'cspromod'        => 'csp',
	'nucleardawn'     => 'nd',
	'ageofchivalry'   => 'aoc',
	'gesource'        => 'ges',
);

const SB_ICON_MUTE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/><path d="M4 4l16 16"/></svg>';
const SB_ICON_GAG  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 15a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/><path d="M4 4l16 16"/></svg>';

// Rows per page of every SourceBans table
const SB_PER_PAGE = 30;

// Seconds an RCON "status" of a server HLstatsZ does not track is kept (sbRconStatus)
const SB_STATUS_TTL = 30;

/**
 * Full name of a SourceBans table, e.g. `sourcebans`.`sb_bans`: it reaches the SourceBans database whichever one the
 * page uses, as the header asks before sourcebans.php makes it the page's database.
 */
function sbTable($name)
{
	return '`' . str_replace('`', '', DB_SBNAME) . '`.`' . str_replace('`', '', defined('DB_SBPREFIX') ? DB_SBPREFIX : 'sb') . '_' . $name . '`';
}

/**
 * Whether SourceBans is there: DB_SBNAME is set and the tables its pages read answer. A wrong name or prefix, or
 * tables not created yet, turns it off, not the page. Without it the pages show what AMXBans has: its bans and
 * GoldSrc servers, and no comm blocks.
 */
function sbOn()
{
	global $db;
	static $on = null;

	if ($on === null) {
		$on = defined('DB_SBNAME') && DB_SBNAME !== ''
			&& $db->query("SELECT 1 FROM " . implode(', ', array_map('sbTable', array('bans', 'banlog', 'admins', 'groups', 'srvgroups', 'servers', 'mods', 'settings')))
				. " LIMIT 0", false) !== false;
	}
	return $on;
}

/**
 * Whether comm blocks are shown: SourceBans is there with its comms table, and its panel has them on.
 */
function sbComms()
{
	global $db;
	static $comms = null;

	if ($comms === null) {
		$comms = sbOn() && $db->query("SELECT 1 FROM " . sbTable('comms') . " LIMIT 0", false) !== false
			&& (sbSettings()['config.enablecomms'] ?? '1') === '1';
	}
	return $comms;
}

/**
 * SourceBans web settings as setting => value (none without SourceBans).
 */
function sbSettings()
{
	global $db;
	static $settings = null;

	if ($settings === null) {
		$settings = array();
		if (sbOn()) {
			$result = $db->query("SELECT setting, value FROM " . sbTable('settings'));
			while ($row = $db->fetch_row($result)) {
				$settings[$row[0]] = $row[1];
			}
		}
	}
	return $settings;
}

/**
 * Whether admin names are shown: always to signed-in SourceBans admins, to visitors unless banlist.hideadminname is on.
 */
function sbShowAdminNames()
{
	return (sbSettings()['banlist.hideadminname'] ?? '0') !== '1' || (function_exists('sbAdmin') && sbAdmin());
}

/**
 * Page of a paginated table asked in $_GET[$param], kept between 1 and the last page.
 */
function sbPage($param, $total)
{
	$asked = $_GET[$param] ?? 1;
	return min(max(1, is_scalar($asked) ? (int) $asked : 1), max(1, (int) ceil($total / SB_PER_PAGE)));
}

/**
 * LIMIT clause of that page.
 */
function sbLimit($page)
{
	return 'LIMIT ' . SB_PER_PAGE . ' OFFSET ' . (($page - 1) * SB_PER_PAGE);
}

/**
 * Whether the request only asks for the content of element $id again: a page, sort or search of its table.
 */
function sbAjaxPart($id)
{
	return is_ajax() && ($_GET['ajax'] ?? '') === $id;
}

/**
 * HLstatsZ servers by address ("ip:port", their public address too), with their game, map and players.
 * The databases can use different collations, so servers are matched in PHP instead of joined.
 */
function sbHlzByAddress()
{
	global $db;
	static $hlz = null;

	if ($hlz !== null) {
		return $hlz;
	}
	$result = $db->query("
		SELECT
			s.serverId,
			s.address,
			s.port,
			s.publicaddress,
			s.name,
			s.game,
			s.act_map,
			s.act_players,
			s.max_players,
			g.realgame
		FROM
			`" . DB_NAME . "`.hlstats_Servers AS s
			LEFT JOIN `" . DB_NAME . "`.hlstats_Games AS g ON g.code = s.game
	");
	$rows = $db->fetch_row_set($result) ?: array();
	$hlz  = array();
	foreach ($rows as $row) {
		if ($row['publicaddress'] !== '') {
			$hlz[$row['publicaddress']] = $row;
		}
	}
	foreach ($rows as $row) {
		$hlz[$row['address'] . ':' . $row['port']] = $row;
	}
	return $hlz;
}

/**
 * All bans as one derived table for FROM: SourceBans' when it is there, then AMXBans' when it is set up
 * (sourcebans_amx.php); sourcebans.php needs one of them. Columns: src ('sb' or 'amx'), bid, ip, authid, name,
 * country, created, ends, length (seconds, 0 = permanent), reason, aid, sid, admin, server (AMXBans server address),
 * game (SourceBans mod folder or AMXBans game type), modname, RemovedOn, RemoveType and type (1 = IP ban).
 */
function sbAllBans()
{
	$parts = array();
	if (sbOn()) {
		$parts[] = "
		SELECT
			'sb' AS src, ba.bid, ba.ip, ba.authid, ba.name, ba.country, ba.created, ba.ends, ba.length, ba.reason, ba.aid, ba.sid,
			ad.user AS admin, NULL AS server, mo.modfolder AS game, mo.name AS modname, ba.RemovedOn, ba.RemoveType, ba.type
		FROM
			" . DB_SBPREFIX . "_bans AS ba
			LEFT JOIN " . DB_SBPREFIX . "_admins AS ad ON ad.aid = ba.aid
			LEFT JOIN " . DB_SBPREFIX . "_servers AS se ON se.sid = ba.sid
			LEFT JOIN " . DB_SBPREFIX . "_mods AS mo ON mo.mid = se.modid";
	}
	if (sbAmx()) {
		$parts[] = sbAmxBansSelect();
	}
	return '(' . implode("\n\t\tUNION ALL", $parts) . "\n\t) AS bans";
}

/**
 * Enabled SourceBans servers by sid, each with the HLstatsZ server on the same address in 'hlz' (or null).
 */
function sbServers()
{
	global $db;
	static $servers = null;

	if ($servers !== null) {
		return $servers;
	}

	$servers = array();
	if (!sbOn()) {
		return $servers;
	}
	$hlz    = sbHlzByAddress();
	$result = $db->query("
		SELECT
			se.sid,
			se.ip,
			se.port,
			mo.name AS modname,
			mo.modfolder,
			se.rcon <> '' AS has_rcon,
			(SELECT COUNT(*) FROM " . DB_SBPREFIX . "_bans AS ba WHERE ba.sid = se.sid) AS bans,
			(SELECT COUNT(*) FROM " . DB_SBPREFIX . "_banlog AS bl WHERE bl.sid = se.sid) AS blocked
		FROM
			" . DB_SBPREFIX . "_servers AS se
			LEFT JOIN " . DB_SBPREFIX . "_mods AS mo ON mo.mid = se.modid
		WHERE
			se.enabled = 1
	");
	while ($row = $db->fetch_array($result)) {
		$row['hlz'] = $hlz[$row['ip'] . ':' . $row['port']] ?? null;
		$servers[(int) $row['sid']] = $row;
	}
	return $servers;
}

/**
 * Display name of a SourceBans server: its HLstatsZ name, else the host name its last RCON "status" gave,
 * else its address ('' when unknown).
 */
function sbServerName($sid)
{
	$server = sbServers()[(int) $sid] ?? null;
	if (!$server) {
		return '';
	}
	if ($server['hlz']) {
		return html_entity_decode($server['hlz']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	$cache = sbStatusCache($server);
	return ($cache['host'] ?? '') !== '' ? $cache['host'] : $server['ip'] . ':' . $server['port'];
}

/**
 * Host name, map, players (humans) and max players in an RCON "status" reply ('' or null when missing).
 * Source 1 and GoldSrc have a "map :" line; CS2 names the map in its first spawn group.
 */
function sbStatusInfo($status)
{
	$info = array('host' => '', 'map' => '', 'players' => null, 'max' => null);
	foreach (preg_split('/\R/', (string) $status) as $line) {
		if (preg_match('/^\s*hostname\s*:\s*(.+?)\s*$/', $line, $m)) {
			$info['host'] = $m[1];
		} elseif ($info['map'] === '' && (preg_match('/^\s*map\s*:\s*(\S+)/', $line, $m)
				|| preg_match('/^\s*loaded spawngroup.*?\[1:\s*([^\s|\]]+)/', $line, $m))) {
			$info['map'] = basename(str_replace('\\', '/', $m[1]));   // workshop/<id>/<map>
		} elseif (preg_match('/^\s*players\s*:\s*(\d+)\s+(?:humans?|active)\b[^(]*\((\d+)(?:\/\d+)?\s+max\)/', $line, $m)) {
			$info['players'] = (int) $m[1];
			$info['max']     = (int) $m[2];
		}
	}
	return $info;
}

/**
 * Cache file of the RCON "status" of a server (sbServerStatus). ./cache is the one of the other pages.
 */
function sbStatusFile(array $server)
{
	return './cache/hlstatsz_sbstatus_' . md5($server['ip'] . ':' . $server['port']) . '.json';
}

/**
 * What that cache holds: array('until' => end of its validity, 'info' => sbStatusInfo() of the last query or null
 * when it failed, 'host' => the last host name the server gave, 'refused' => set when the password was refused),
 * or null.
 */
function sbStatusCache(array $server)
{
	$file = sbStatusFile($server);
	$data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
	return is_array($data) ? $data : null;
}

/**
 * Host name, map and players of a SourceBans server HLstatsZ does not track (sbRconStatus).
 */
function sbServerStatus($sid, $query)
{
	global $db;

	$server = sbServers()[(int) $sid] ?? null;
	return $server ? sbRconStatus($server, function () use ($db, $sid) {
		$db->query("SELECT rcon FROM " . DB_SBPREFIX . "_servers WHERE sid = " . (int) $sid);
		return (string) ($db->fetch_row()[0] ?? '');
	}, false, $query) : null;
}

/**
 * Host name, map and players of a server HLstatsZ does not track ($server: ip, port, hlz, has_rcon), from RCON
 * "status" (sbStatusInfo), or null when there are none: no RCON password, tracked by HLstatsZ, or no answer.
 * $password gives the password, read only when the server is asked; $goldsrc for GoldSrc RCON (AMXBans servers).
 * Every answer, a failure too, is kept SB_STATUS_TTL seconds so visitors cannot flood a server with RCON.
 * A refused password is not tried again for an hour unless it is changed: failed logins get the web
 * server banned from RCON. Without $query only a kept answer is returned, and false means that there is none.
 */
function sbRconStatus(array $server, callable $password, $goldsrc, $query)
{
	if ($server['hlz'] || !$server['has_rcon']) {
		return null;
	}
	$cache = sbStatusCache($server);
	$kept  = $cache && time() < (int) ($cache['until'] ?? 0);
	if ($kept && ($cache['refused'] ?? '') === '') {
		return $cache['info'];
	}
	if (!$query) {
		return $kept ? $cache['info'] : false;
	}

	$password = $password();
	$key      = hash_hmac('sha256', $password, SECRET_KEY);   // tells a changed password apart without keeping it
	if ($kept && $cache['refused'] === $key) {
		return $cache['info'];
	}

	$save = function ($info, $host, $seconds, $refused = '') use ($server) {
		if (!is_dir('./cache')) {
			@mkdir('./cache', 0755, true);
		}
		@file_put_contents(sbStatusFile($server), json_encode(
			array('until' => time() + $seconds, 'info' => $info, 'host' => $host, 'refused' => $refused),
			JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
	};
	// Taken at once: requests that come during the query get the previous answer instead of querying too
	$host = $cache['host'] ?? '';
	$save($cache['info'] ?? null, $host, SB_STATUS_TTL);

	$rcon   = new Rcon($server['ip'], $server['port'], $password, $goldsrc);
	$status = $rcon->execute('status');
	$info   = $status === false ? null : sbStatusInfo($status);
	if ($status === false && strpos($rcon->error, 'wrong RCON password') !== false) {
		$save(null, $host, 3600, $key);
	} else {
		$save($info, $info && $info['host'] !== '' ? $info['host'] : $host, SB_STATUS_TTL);
	}
	return $info;
}

/**
 * Icon and name of the game a ban or comm block was issued on: the HLstatsZ game of the
 * matching server first, then the SourceBans mod. Web bans have no server.
 */
function sbGame($sid, $modfolder, $modname)
{
	$server = sbServers()[(int) $sid] ?? null;
	$codes  = array();
	if ($server && $server['hlz']) {
		$codes[] = $server['hlz']['game'];
		$codes[] = $server['hlz']['realgame'];
	}
	if ($modfolder) {
		$codes[] = SB_MOD_GAMES[$modfolder] ?? $modfolder;
	}
	return array('icon' => sbGameIcon($codes), 'name' => $modname ?: t('sb.web'));
}

/**
 * Icon of the first HLstatsZ game code that has one, else the generic Z icon.
 */
function sbGameIcon(array $codes)
{
	static $icons = array();

	foreach ($codes as $code) {
		$code = preg_replace('/[^A-Za-z0-9_]/', '', (string) $code);
		if ($code === '') {
			continue;
		}
		if (!isset($icons[$code])) {
			$image = getImage("/games/$code/game");
			$icons[$code] = $image ? $image['url'] : false;
		}
		if ($icons[$code]) {
			return $icons[$code];
		}
	}
	return IMAGE_PATH . '/z.svg';
}

/**
 * Localised date and time for tooltips.
 */
function sbDate($timestamp)
{
	return extension_loaded('intl')
		? formatDate((int) $timestamp, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT)
		: date('Y-m-d H:i', (int) $timestamp);
}

/**
 * Localised short month name, e.g. "Mar".
 */
function sbMonth($timestamp)
{
	static $formatter = null;

	if (!extension_loaded('intl')) {
		return date('M', (int) $timestamp);
	}
	$formatter ??= new IntlDateFormatter(t('locale'), IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLL');
	return $formatter->format((int) $timestamp);
}

/**
 * Top of a chart scale: the smallest round number >= $max (540 -> 600), even below 10 so the midline is whole.
 */
function sbNiceMax($max)
{
	if ($max < 10) {
		return max(2, (int) ceil($max / 2) * 2);
	}
	$magnitude = pow(10, floor(log10($max)));
	foreach (array(1, 1.2, 2, 3, 4, 5, 6, 8, 10) as $step) {
		if ($step * $magnitude >= $max) {
			return (int) round($step * $magnitude);
		}
	}
	return (int) $max;
}

/**
 * Localised date without the time, e.g. "Mar 5, 2024".
 */
function sbDay($timestamp)
{
	return extension_loaded('intl')
		? formatDate((int) $timestamp, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE)
		: date('Y-m-d', (int) $timestamp);
}

/**
 * Bans per period for the ban history chart, or null when there is no ban. $scope '' is the whole history,
 * a column per year once bans span two years and per month before that; 'YYYY' is the months of that year
 * and 'YYYY-MM' the days of that month (anything else is the whole history). Periods start at midnight in
 * PHP's time zone and stop at the current one. Returns array('level' => 'year', 'month' or 'day', 'scope',
 * 'note', 'periods' => list of array('start', 'label', 'full', 'zoom' => scope of its bars or null, 'n')).
 */
function sbBanHistory($scope)
{
	global $db;

	$db->query("SELECT MIN(created) FROM " . sbAllBans());
	list($first) = $db->fetch_row();
	if (!$first) {
		return null;
	}
	$now     = time();
	$first   = (int) $first;
	$from    = (int) date('Y', $first);
	$to      = (int) date('Y', $now);
	$periods = array();

	if (preg_match('/^(\d{4})-(\d{2})$/', $scope, $m) && $m[1] >= $from && $m[2] >= 1 && $m[2] <= 12
		&& ($start = mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1])) <= $now) {
		$level = 'day';
		$note  = sbMonth($start) . ' ' . $m[1];
		for ($day = 1; $day <= (int) date('t', $start); $day++) {
			$ts = mktime(0, 0, 0, (int) $m[2], $day, (int) $m[1]);
			if ($ts > $now) {
				break;
			}
			$periods[] = array('start' => $ts, 'label' => (string) $day, 'full' => sbDay($ts), 'zoom' => null);
		}
		$end = mktime(0, 0, 0, (int) $m[2] + 1, 1, (int) $m[1]);
	} elseif (preg_match('/^\d{4}$/', $scope) && $scope >= $from && $scope <= $to) {
		$level = 'month';
		$note  = $scope;
		for ($month = 1; $month <= 12; $month++) {
			$ts = mktime(0, 0, 0, $month, 1, (int) $scope);
			if ($ts > $now) {
				break;
			}
			$periods[] = array('start' => $ts, 'label' => sbMonth($ts), 'full' => sbMonth($ts) . ' ' . $scope, 'zoom' => date('Y-m', $ts));
		}
		$end = mktime(0, 0, 0, 1, 1, (int) $scope + 1);
	} elseif (($to - $from) * 12 + date('n', $now) - date('n', $first) >= 24) {
		$scope = '';
		$level = 'year';
		$note  = t('sb.chart.since', array('{date}' => $from));
		for ($year = $from; $year <= $to; $year++) {
			$periods[] = array('start' => mktime(0, 0, 0, 1, 1, $year), 'label' => (string) $year, 'full' => (string) $year, 'zoom' => (string) $year);
		}
		$end = mktime(0, 0, 0, 1, 1, $to + 1);
	} else {
		$scope = '';
		$level = 'month';
		$note  = t('sb.chart.since', array('{date}' => sbMonth($first) . ' ' . $from));
		for ($ts = mktime(0, 0, 0, date('n', $first), 1, $from); $ts <= $now; $ts = mktime(0, 0, 0, date('n', $ts) + 1, 1, date('Y', $ts))) {
			$periods[] = array('start' => $ts, 'label' => sbMonth($ts), 'full' => sbMonth($ts) . ' ' . date('Y', $ts), 'zoom' => date('Y-m', $ts));
		}
		$end = mktime(0, 0, 0, date('n', $now) + 1, 1, $to);
	}

	// One pass: INTERVAL() gives the number of period starts at or before each ban
	$starts = array_column($periods, 'start');
	$result = $db->query("
		SELECT INTERVAL(created, " . implode(', ', $starts) . ") - 1 AS i, COUNT(*) AS n
		FROM " . sbAllBans() . "
		WHERE created >= {$starts[0]} AND created < $end
		GROUP BY i
	");
	foreach ($periods as &$period) {
		$period['n'] = 0;
	}
	unset($period);
	while ($row = $db->fetch_array($result)) {
		$periods[(int) $row['i']]['n'] = (int) $row['n'];
	}
	return array('level' => $level, 'scope' => $scope, 'note' => $note, 'periods' => $periods);
}

/**
 * Content of the ban history card (#sb-history) at $scope (sbBanHistory). A bar that has smaller periods
 * zooms into them, Reset goes back to the whole history. The bars scroll sideways under a fixed scale when
 * they do not fit; each one is labelled with its period and count for screen readers.
 */
function sbHistoryCard($scope)
{
	$history = sbBanHistory($scope);
?>
	<div class="hlstats-card-head">
		<div class="hlstats-card-title"><?= t('sb.chart.' . ($history['level'] ?? 'month')) ?></div>
<?php if ($history): ?>
		<span class="hlstats-card-note"><?= htmlspecialchars($history['note']) ?></span>
<?php if ($history['scope'] !== ''): ?>
		<button type="button" class="sba-btn is-small sb-chart-reset" data-sb-zoom=""><?= t('sb.chart.reset') ?></button>
<?php endif; ?>
<?php endif; ?>
	</div>
<?php
	if (!$history) {
		echo '<p class="sb-empty">' . t('sb.none.bans') . '</p>';
		return;
	}
	$periods = $history['periods'];
	$peak    = max(array_column($periods, 'n'));
	$scale   = sbNiceMax($peak);
	$marked  = false;
?>
	<div class="sb-chart is-<?= $history['level'] . ($history['scope'] === '' ? ' is-latest' : '') ?>">
		<div class="sb-chart-y" aria-hidden="true">
<?php foreach (array(1, 0.5, 0) as $f): ?>
			<span style="bottom: <?= $f * 100 ?>%"><?= nf($scale * $f) ?></span>
<?php endforeach; ?>
		</div>
		<div class="sb-chart-scroll hlstats-scrollbar">
			<div class="sb-chart-inner" style="--sb-cols: <?= count($periods) ?>">
				<div class="sb-chart-plot">
<?php foreach (array(1, 0.5, 0) as $f): ?>
					<div class="sb-chart-grid" style="bottom: <?= $f * 100 ?>%"></div>
<?php endforeach; ?>
					<div class="sb-chart-cols">
<?php
	foreach ($periods as $period):
		$height = round($period['n'] / $scale * 100, 2);
		$label  = htmlspecialchars(t('sb.chart.tip', array('{period}' => $period['full'], '{n}' => nf($period['n']))));
		$tag    = $period['zoom'] !== null ? 'button' : 'div';
?>
						<<?= $tag ?> class="sb-chart-col"<?= $period['zoom'] !== null ? ' type="button" data-sb-zoom="' . htmlspecialchars($period['zoom']) . '"' : ' role="img"' ?> aria-label="<?= $label ?>" data-tooltip="<?= $label ?>">
							<i style="height: <?= $height ?>%"></i>
<?php if (!$marked && $peak > 0 && $period['n'] === $peak): $marked = true; ?>
							<span class="sb-chart-value" style="bottom: <?= $height ?>%"><?= nf($peak) ?></span>
<?php endif; ?>
						</<?= $tag ?>>
<?php endforeach; ?>
					</div>
				</div>
				<div class="sb-chart-x" aria-hidden="true">
<?php foreach ($periods as $period): ?>
					<span><?= htmlspecialchars($period['label']) ?></span>
<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
<?php
}

/**
 * Script of the ban history card: a bar or Reset loads the card at its scope (the whole history opens on its
 * latest periods through CSS, a zoom on its first ones).
 */
function sbHistoryScript()
{
?>
<script>
(function () {
    const card = document.getElementById('sb-history');
    if (!card) return;
    card.addEventListener('click', event => {
        const target = event.target.closest('[data-sb-zoom]');
        if (!target) return;
        const scope    = target.dataset.sbZoom;
        const keyboard = event.detail === 0;
        const url      = new URL(location.href);
        url.searchParams.set('ajax', 'sb-history');
        if (scope) url.searchParams.set('history', scope);
        else url.searchParams.delete('history');
        Fetch.run(url.href, card, false).then(() => {
            // Keyboard users keep their place: on Reset once zoomed in, on the latest bar once back
            if (keyboard) (card.querySelector('.sb-chart-reset') || [...card.querySelectorAll('.sb-chart-col')].pop())?.focus();
        }).catch(() => {});
    });
})();
</script>
<?php
}

/**
 * Compact duration with at most two units, e.g. "2 wk" or "1 d 4 h".
 */
function sbDuration($seconds)
{
	$units = array('year' => 31536000, 'month' => 2592000, 'week' => 604800, 'day' => 86400, 'hour' => 3600, 'min' => 60);
	$parts = array();
	foreach ($units as $unit => $size) {
		if ($seconds >= $size) {
			$parts[] = t("sb.unit.$unit", array('{n}' => intdiv($seconds, $size)));
			$seconds %= $size;
			if (count($parts) === 2) {
				break;
			}
		}
	}
	return $parts ? implode(' ', $parts) : t('sb.unit.min', array('{n}' => 1));
}

/**
 * Time since $timestamp, e.g. "3d ago".
 */
function sbAgo($timestamp, $now)
{
	$diff = max(0, $now - (int) $timestamp);
	foreach (array('year' => 31536000, 'month' => 2592000, 'day' => 86400, 'hour' => 3600, 'min' => 60) as $unit => $size) {
		if ($diff >= $size) {
			return t("sb.ago.$unit", array('{n}' => intdiv($diff, $size)));
		}
	}
	return t('sb.ago.now');
}

/**
 * Status pill of a ban or comm block. Expiry is checked against the clock, since the
 * SourceBans web panel only marks expired bans when one of its own pages is opened.
 */
function sbPill($row, $now)
{
	$length  = (int) $row['length'];
	$removed = $row['RemoveType'] ?? '';
	$amx     = ($row['src'] ?? '') === 'amx';   // AMXBans forgets the length when it unbans (-1)
	$planned = $length === 0 ? t('sb.status.permanent') : ($length < 0 ? ($amx ? '' : t('sb.status.session')) : sbDuration($length));
	$tip     = '';

	if ($removed === 'U' || $removed === 'D') {
		list($class, $label) = array('green', t($removed === 'U' ? 'sb.status.lifted' : 'sb.status.removed'));
		if (!empty($row['RemovedOn'])) {
			$tip = $planned !== ''
				? t('sb.tip.lifted', array('{length}' => $planned, '{date}' => sbDate($row['RemovedOn'])))
				: t('sb.tip.lifted.on', array('{date}' => sbDate($row['RemovedOn'])));
		}
	} elseif ($removed === 'E' || ($length > 0 && (int) $row['ends'] <= $now)) {
		list($class, $label) = array('green', t('sb.status.expired'));
		$tip = t('sb.tip.ended', array('{length}' => $planned, '{date}' => sbDate($row['ends'])));
	} elseif ($length > 0) {
		list($class, $label) = array('orange', $planned);
		$tip = t('sb.tip.ends', array('{date}' => sbDate($row['ends'])));
	} elseif ($length < 0) {
		// A session block lasts until the player leaves or the map changes; after an hour it is surely over
		list($class, $label) = array($now - (int) $row['created'] < 3600 ? 'orange' : 'green', $planned);
	} else {
		list($class, $label) = array('red', $planned);
	}

	return '<span class="hlstats-sb sb-pill ' . $class . '"' . ($tip !== '' ? ' data-tooltip="' . htmlspecialchars($tip) . '"' : '') . '>'
		. htmlspecialchars($label) . '</span>';
}

/**
 * Escaped player name; SourceBans stores names raw. Nameless rows fall back to the Steam ID.
 */
function sbPlayerName($row)
{
	$name = trim((string) $row['name']);
	if ($name === '') {
		$name = trim((string) $row['authid']) !== '' ? $row['authid'] : t('sb.unknown.player');
	}
	return htmlspecialchars($name);
}

/**
 * SQL condition finding a player searched from the header (sourcebans_search.php) in a list of bans or comm blocks.
 * A Steam ID in any format (STEAM_X:Y:Z, [U:1:N], SteamID64, profile link) finds that account, stored as
 * STEAM_0 or STEAM_1; other text finds part of a name or Steam ID, and for admins the start of an IP address
 * ($ip: its column, '' for comm blocks, which keep none).
 */
function sbPlayerMatch($search, $name, $authid, $ip = '')
{
	global $db;

	if ($ids = sbSteamIds($search)) {
		$account = substr($ids['steam2'], strlen('STEAM_0:'));
		return "$authid IN ('STEAM_0:$account', 'STEAM_1:$account')";
	}
	$like  = sbLike($search);
	$match = "$name LIKE $like OR $authid LIKE $like";
	if ($ip !== '' && sbAdmin()) {
		$match .= " OR $ip LIKE '" . $db->escape(addcslashes($search, '%_\\')) . "%'";
	}
	return "($match)";
}

/**
 * Country flag of a ban. The IP decides whenever GeoLite2 knows it, and a SourceBans ban whose stored
 * country is missing or different gets that one (AMXBans is only read). A ban whose IP tells nothing (none kept,
 * as for a ban by Steam ID of a player who was not there; a LAN address) shows the country HLstatsZ has for the
 * player's Steam ID, without storing it.
 */
function sbFlag($row)
{
	global $db;

	$code = strtolower((string) ($row['country'] ?? ''));
	if (!empty($row['ip'])) {
		$found = getFlagByIP($row['ip']);
		if ($found !== '' && $found !== $code) {
			if (($row['src'] ?? 'sb') === 'sb' && !empty($row['bid']) && sbOn()) {
				$db->query("UPDATE " . DB_SBPREFIX . "_bans SET country = '" . $db->escape(strtoupper($found)) . "' WHERE bid = " . (int) $row['bid'], false);
			}
			$code = $found;
		}
	}
	if ($code === '' || $code === 'zz') {
		$code = sbPlayerCountry($row['authid'] ?? '') ?: $code;
	}
	$known = $code !== '' && $code !== 'zz';
	$label = $known ? htmlspecialchars(strtoupper($code)) : '';

	return '<span class="hlstats-flag"><img src="' . htmlspecialchars(getFlag($code)) . '" alt="' . $label . '"' . ($known ? ' data-tooltip="' . $label . '"' : '') . '></span>';
}

/**
 * Second line of a dashboard list item: time since, then a note (the reason by default).
 */
function sbItemSub($row, $now, $note = null)
{
	$note = trim((string) ($note ?? $row['reason']));
	return '<time datetime="' . date('c', (int) $row['created']) . '" data-tooltip="' . htmlspecialchars(sbDate($row['created'])) . '">'
		. sbAgo($row['created'], $now) . '</time>'
		. ($note !== ''
			? '<span class="sb-item-note" data-tooltip="' . htmlspecialchars($note) . '">' . htmlspecialchars($note) . '</span>'
			: '<span class="sb-item-note is-empty">' . t('sb.no.reason') . '</span>');
}

/**
 * URL of the details of a ban or comm block ($type 'ban' or 'comm').
 */
function sbDetailsUrl($type, $id)
{
	global $g_options;
	return $g_options['scripturl'] . '?mode=sourcebans&task=sourcebans_details&type=' . $type . '&id=' . (int) $id;
}

/**
 * Attributes of a list row that opens the details of a ban or comm block when clicked.
 */
function sbExpandAttrs($type, $id, $class = '')
{
	return ' class="sb-expand' . ($class !== '' ? ' ' . $class : '') . '" data-sb-details="' . htmlspecialchars(sbDetailsUrl($type, $id)) . '"';
}

/**
 * Player name as a link to the details; without JavaScript it opens the details page.
 */
function sbNameLink($type, $id, $row, $name = null)
{
	return '<a class="sb-open hlstats-name" href="' . htmlspecialchars(sbDetailsUrl($type, $id)) . '" aria-expanded="false">'
		. ($name !== null ? htmlspecialchars($name) : sbPlayerName($row)) . '</a>';
}

/**
 * SteamID64 of a STEAM_X:Y:Z id, or '' for anything else.
 */
function sbSteam64($authid)
{
	return preg_match('/^STEAM_[0-5]:([01]):(\d+)$/', (string) $authid, $m)
		? (string) (76561197960265728 + $m[2] * 2 + $m[1])
		: '';
}

/**
 * HLstatsZ players, one per visible game, with the Steam ID of a ban. HLstatsZ stores STEAM_X:Y:Z as "Y:Z".
 */
function sbProfiles($authid)
{
	global $db;

	if (!preg_match('/^STEAM_[0-5]:([01]):(\d+)$/', (string) $authid, $m)) {
		return array();
	}
	$result = $db->query("
		SELECT
			u.playerId,
			g.name AS gamename
		FROM
			`" . DB_NAME . "`.hlstats_PlayerUniqueIds AS u
			JOIN `" . DB_NAME . "`.hlstats_Games AS g ON g.code = u.game AND g.hidden = '0'
		WHERE
			u.uniqueId = '{$m[1]}:{$m[2]}'
		ORDER BY
			g.name
	");
	return $db->fetch_row_set($result) ?: array();
}

/**
 * The country HLstatsZ has for the player of a Steam ID in any form (their latest player with one), as a lowercase
 * code, or ''. Kept for the page: a list can show the same player more than once.
 */
function sbPlayerCountry($authid)
{
	global $db;
	static $countries = array();

	$ids = function_exists('sbSteamIds') ? sbSteamIds((string) $authid) : null;
	if (!$ids) {
		return '';
	}
	$unique = substr($ids['steam2'], strlen('STEAM_0:'));   // HLstatsZ stores STEAM_X:Y:Z as "Y:Z"
	if (!isset($countries[$unique])) {
		$db->query("
			SELECT
				p.flag
			FROM
				`" . DB_NAME . "`.hlstats_PlayerUniqueIds AS u
				JOIN `" . DB_NAME . "`.hlstats_Players AS p ON p.playerId = u.playerId
			WHERE
				u.uniqueId = '$unique'
				AND p.flag <> ''
				AND p.flag <> 'zz'
			ORDER BY
				p.last_event DESC
			LIMIT 1
		", false);
		$row = $db->fetch_row();
		$countries[$unique] = $row ? strtolower((string) $row[0]) : '';
	}
	return $countries[$unique];
}

/**
 * Link to the page where bans are appealed (HLstatsZ admin, Bans settings), under an active ban or comm block;
 * '' when there is none.
 */
function sbAppealLink($comm = false)
{
	global $g_options;

	$url = trim((string) ($g_options['appeal_url'] ?? ''));
	return $url !== '' ? '<p class="sb-details-appeal"><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">'
		. t($comm ? 'sb.details.appeal.comm' : 'sb.details.appeal') . ' &#8599;</a></p>' : '';
}

/**
 * The visitor's own record: their bans and comm blocks by the Steam ID they signed in with, and the IP bans of the
 * address they come from. array('signed' => signed in with Steam, 'bans' => active bans (rows of sbAllBans),
 * 'comms' => active comm blocks, 'past' => bans no longer active).
 */
function sbMyRecord()
{
	global $db;

	$now    = time();
	$ids    = sbSteamIds($_SESSION['ID64'] ?? '');
	$ip     = (string) filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
	$record = array('signed' => (bool) $ids, 'bans' => array(), 'comms' => array(), 'past' => 0);
	$match  = array();
	if ($ids) {
		$match[] = 'authid IN (' . sbSteamMatch($ids) . ')';
	}
	if ($ip !== '') {
		$match[] = "(type = 1 AND ip = '" . $db->escape($ip) . "')";
	}
	if ($match) {
		$db->query("SELECT * FROM " . sbAllBans() . " WHERE " . implode(' OR ', $match) . " ORDER BY created DESC, bid DESC");
		foreach ($db->fetch_row_set() ?: array() as $row) {
			if ($row['RemoveType'] === null && ((int) $row['length'] === 0 || (int) $row['ends'] > $now)) {
				$record['bans'][] = $row;
			} else {
				$record['past']++;
			}
		}
	}
	if ($ids && sbComms()) {
		$db->query("
			SELECT bid, name, authid, created, ends, length, reason, type, RemoveType, RemovedOn
			FROM " . DB_SBPREFIX . "_comms
			WHERE authid IN (" . sbSteamMatch($ids) . ") AND RemoveType IS NULL AND (length = 0 OR ends > $now)
			ORDER BY created DESC, bid DESC
		");
		$record['comms'] = $db->fetch_row_set() ?: array();
	}
	return $record;
}

/**
 * The "Your status" card of the dashboard: whether a ban or comm block is on the visitor (sbMyRecord), and where to
 * report a player or appeal a ban (Bans settings of the HLstatsZ admin). Nothing when none of it applies.
 */
function sbRecordCard()
{
	global $g_options, $signin;

	$now    = time();
	$me     = sbMyRecord();
	$report = trim((string) ($g_options['report_url'] ?? ''));
	$appeal = trim((string) ($g_options['appeal_url'] ?? ''));
	$login  = preg_match('#<a href="(https://steamcommunity\.com/openid/login[^"]*)"#', (string) $signin, $m) ? $m[1] : '';
	$bad    = $me['bans'] || $me['comms'];
	if (!$me['signed'] && !$bad && $login === '' && $report === '' && $appeal === '') {
		return;
	}
	$link    = fn($url, $label, $class) => '<a class="hlstats-btn' . $class . '" href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . $label . ' &#8599;</a>';
	$buttons = array();
	if (!$me['signed'] && !$bad && $login !== '') {
		$buttons[] = '<a class="hlstats-btn is-primary" href="' . htmlspecialchars($login) . '">' . t('sb.me.signin.button') . '</a>';
	}
	if ($appeal !== '' && ($bad || !$me['signed'])) {
		$buttons[] = $link($appeal, t('sb.appeal'), $bad ? ' is-primary' : '');
	}
	if ($report !== '') {
		$buttons[] = $link($report, t('sb.report'), '');
	}
?>
<section class="hlstats-section hlstats-card sb-record">
	<div class="hlstats-card-head">
		<div class="hlstats-card-title"><?= t('sb.me.title') ?></div>
	</div>
<?php if ($bad): ?>
	<p class="sb-record-state is-bad"><?= implode(' &middot; ', array_filter(array(
		$me['bans'] ? t('sb.me.banned', array('{n}' => nf(count($me['bans'])))) : '',
		$me['comms'] ? t('sb.me.blocked', array('{n}' => nf(count($me['comms'])))) : '',
	))) ?></p>
	<ul class="sb-list">
<?php foreach ($me['bans'] as $ban): $game = sbBanGame($ban); ?>
		<li<?= sbExpandAttrs(sbBanType($ban), $ban['bid'], 'sb-item') ?>>
			<img class="sb-item-icon" src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>">
			<div class="sb-item-main">
				<div class="sb-item-name"><?= sbFlag($ban) ?><?= sbNameLink(sbBanType($ban), $ban['bid'], $ban) ?></div>
				<div class="sb-item-sub"><?= sbItemSub($ban, $now) ?></div>
			</div>
			<?= sbPill($ban, $now) ?>
		</li>
<?php endforeach; ?>
<?php foreach ($me['comms'] as $comm): ?>
		<li<?= sbExpandAttrs('comm', $comm['bid'], 'sb-item') ?>>
			<span class="sb-item-icon"><?= sbCommType($comm['type']) ?></span>
			<div class="sb-item-main">
				<div class="sb-item-name"><?= sbNameLink('comm', $comm['bid'], $comm) ?></div>
				<div class="sb-item-sub"><?= sbItemSub($comm, $now) ?></div>
			</div>
			<?= sbPill($comm, $now) ?>
		</li>
<?php endforeach; ?>
	</ul>
<?php elseif ($me['signed']): ?>
	<p class="sb-record-state is-good"><?= t('sb.me.clean') ?><?= $me['past'] ? ' <span class="sb-muted">' . t('sb.me.past', array('{n}' => nf($me['past']))) . '</span>' : '' ?></p>
<?php else: ?>
	<p class="sb-record-state"><?= t('sb.me.signin') ?></p>
<?php endif; ?>
<?php if ($buttons): ?>
	<div class="sb-record-actions"><?= implode('', $buttons) ?></div>
<?php endif; ?>
</section>
<?php
}

/**
 * Details of a ban or comm block: the panel under a list row, or the body of the details page.
 */
function sbDetails($type, $id, $page = false)
{
	global $db, $g_options;

	$now       = time();
	$comm      = $type === 'comm';
	$showAdmin = sbShowAdminNames();

	if (!sbOn() || ($comm && !sbComms())) {
		echo '<p class="sb-empty">' . t('sb.details.missing') . '</p>';
		return;
	}
	$db->query("
		SELECT
			b.bid,
			b.name,
			b.authid,
			" . ($comm ? '' : 'b.ip, b.country,') . "
			b.created,
			b.ends,
			b.length,
			b.reason,
			b.type,
			b.sid,
			b.RemoveType,
			b.RemovedOn,
			b.ureason,
			b.aid,
			ad.gid AS admin_gid,
			ad.user AS admin,
			ra.user AS remover,
			se.ip AS server_ip,
			se.port AS server_port,
			mo.modfolder,
			mo.name AS modname
		FROM
			" . DB_SBPREFIX . ($comm ? '_comms' : '_bans') . " AS b
			LEFT JOIN " . DB_SBPREFIX . "_admins AS ad ON ad.aid = b.aid
			LEFT JOIN " . DB_SBPREFIX . "_admins AS ra ON ra.aid = b.RemovedBy
			LEFT JOIN " . DB_SBPREFIX . "_servers AS se ON se.sid = b.sid
			LEFT JOIN " . DB_SBPREFIX . "_mods AS mo ON mo.mid = se.modid
		WHERE
			b.bid = " . (int) $id
	);
	$row = $db->fetch_array();
	if (!$row) {
		echo '<p class="sb-empty">' . t('sb.details.missing') . '</p>';
		return;
	}

	$length  = (int) $row['length'];
	$removed = $row['RemoveType'] ?? '';
	$authid  = trim((string) $row['authid']);
	$fields  = array();   // label, value (HTML), full width

	// Identity
	if ($authid !== '') {
		$value = htmlspecialchars($authid);
		if ($id64 = sbSteam64($authid)) {
			$value .= ' <span class="sb-muted">[U:1:' . ($id64 - 76561197960265728) . ']</span>'
				. ' &middot; <a href="https://steamcommunity.com/profiles/' . $id64 . '" target="_blank" rel="noopener">' . t('sb.details.steam') . ' &#8599;</a>';
		}
		$fields[] = array(t('sb.details.steamid'), $value);
	}
	$fields[] = $comm
		? array(t('th.type'), sbCommType($row['type']) . ' ' . t((int) $row['type'] === 2 ? 'sb.gag' : 'sb.mute'))
		: array(t('sb.details.bantype'), t((int) $row['type'] === 1 ? 'sb.details.type.ip' : 'sb.details.type.steam'));

	// Timing
	$fields[] = array(t($comm ? 'sb.details.date.comm' : 'sb.details.date'), sbDate($row['created']));
	$fields[] = array(t('sb.th.length'), $length === 0 ? t('sb.status.permanent') : ($length < 0 ? t('sb.status.session') : sbDuration($length)));
	if ($removed === 'U' || $removed === 'D') {
		$by = $showAdmin && $row['remover'] !== null ? ' ' . t('sb.details.by', array('{admin}' => htmlspecialchars($row['remover']))) : '';
		$fields[] = array(t('sb.details.lifted'), ($row['RemovedOn'] ? sbDate($row['RemovedOn']) : '&ndash;') . $by);
	} elseif ($removed === 'E' || ($length > 0 && (int) $row['ends'] <= $now)) {
		$fields[] = array(t('sb.details.expired'), $length > 0 ? sbDate($row['ends']) : t('sb.status.session'));
	} elseif ($length > 0) {
		$fields[] = array(t('sb.details.expires'), sbDate($row['ends']) . ' <span class="sb-muted">&middot; ' . t('sb.details.left', array('{time}' => sbDuration((int) $row['ends'] - $now))) . '</span>');
	} else {
		$fields[] = array(t('sb.details.expires'), t($length === 0 ? 'sb.details.never' : 'sb.details.session'));
	}

	// Where and by whom
	$game   = sbGame($row['sid'], $row['modfolder'], $row['modname']);
	$server = sbServerName($row['sid']) ?: ($row['server_ip'] !== null ? $row['server_ip'] . ':' . $row['server_port'] : $game['name']);
	$fields[] = array(t($comm ? 'sb.details.server' : 'sb.details.from'),
		'<img class="sb-field-icon" src="' . htmlspecialchars($game['icon']) . '" alt="">' . htmlspecialchars($server));
	if ($showAdmin) {
		$fields[] = array(t('sb.details.admin'), $row['admin'] !== null ? htmlspecialchars($row['admin']) : '&ndash;');
	}

	// History
	if (!$comm) {
		$db->query("SELECT COUNT(*), MAX(time) FROM " . DB_SBPREFIX . "_banlog WHERE bid = " . (int) $row['bid']);
		list($attempts, $last) = $db->fetch_row();
		$fields[] = array(t('sb.kpi.blocked'), nf($attempts) . ($attempts ? ' <span class="sb-muted">&middot; ' . t('sb.details.last', array('{date}' => sbDate($last))) . '</span>' : ''));
	}
	if ($authid !== '') {
		$steam = $db->escape($authid);
		$db->query("
			SELECT
				(SELECT COUNT(*) FROM " . DB_SBPREFIX . "_bans WHERE authid = '$steam'),
				(SELECT COUNT(*) FROM " . DB_SBPREFIX . "_comms WHERE authid = '$steam')
		");
		list($bans, $comms) = $db->fetch_row();
		$fields[] = array(t('sb.details.record'), t('sb.th.bans') . ' ' . nf($bans) . ' &middot; ' . t('sb.kpi.comms') . ' ' . nf($comms));

		$links = array();
		foreach (sbProfiles($authid) as $profile) {
			$links[] = '<a href="' . $g_options['scripturl'] . '?mode=playerinfo&amp;player=' . (int) $profile['playerId'] . '">'
				. htmlspecialchars($profile['gamename']) . ' &rsaquo;</a>';
		}
		if ($links) {
			$fields[] = array(t('sb.details.stats'), implode(' &middot; ', $links));
		}
	}

	$reason = trim((string) $row['reason']);
	$fields[] = array(t('sb.th.reason'), $reason !== '' ? htmlspecialchars($reason) : '<span class="sb-muted">' . t('sb.no.reason') . '</span>', true);
	$ureason = trim((string) $row['ureason']);
	if (($removed === 'U' || $removed === 'D') && $ureason !== '') {
		$fields[] = array(t('sb.details.ureason'), htmlspecialchars($ureason), true);
	}

	if ($page) {
		echo '<div class="sb-details-head">' . ($comm ? sbCommType($row['type']) : sbFlag($row))
			. '<span class="hlstats-name">' . sbPlayerName($row) . '</span>' . sbPill($row, $now) . '</div>';
	}
	sbDetailsFields($fields);
	if ($removed === '' && ($length <= 0 || (int) $row['ends'] > $now)) {
		echo sbAppealLink($comm);
	}

	// Edit, unban and delete for admins allowed to
	if (function_exists('sbAdmin') && sbAdmin()) {
		echo sbBanActions($type, $row);
	}
}

/**
 * Grid of the details fields: array(label, value HTML, full width).
 */
function sbDetailsFields(array $fields)
{
	echo '<div class="sb-fields">';
	foreach ($fields as $field) {
		echo '<div class="sb-field' . (!empty($field[2]) ? ' is-wide' : '') . '">'
			. '<div class="sb-field-label">' . $field[0] . '</div>'
			. '<div class="sb-field-value">' . $field[1] . '</div></div>';
	}
	echo '</div>';
}

/**
 * Details type of a row of sbAllBans(): 'amxban' for AMXBans, 'ban' for SourceBans.
 */
function sbBanType(array $row)
{
	return ($row['src'] ?? 'sb') === 'amx' ? 'amxban' : 'ban';
}

/**
 * Game icon and name of a row of sbAllBans().
 */
function sbBanGame(array $row)
{
	return ($row['src'] ?? 'sb') === 'amx' ? sbAmxGame($row['server'], $row['game']) : sbGame($row['sid'], $row['game'], $row['modname']);
}

/**
 * Script opening the details of a ban or comm block under its row, one at a time, each loaded once.
 * Lists are re-rendered by the AJAX sorting and paging, so clicks are handled on the document.
 */
function sbDetailsScript()
{
?>
<script>
(function () {
    let openRow = null;

    function close(row) {
        const panel = row.nextElementSibling;
        if (panel && panel.classList.contains('sb-details-row')) panel.hidden = true;
        row.classList.remove('is-open');
        row.querySelector('.sb-open')?.setAttribute('aria-expanded', 'false');
    }

    function open(row) {
        let panel = row.nextElementSibling;
        if (!panel || !panel.classList.contains('sb-details-row')) {
            const box = document.createElement('div');
            box.className = 'sb-details';
            box.textContent = t('sb.details.loading');
            if (row.tagName === 'TR') {
                panel = document.createElement('tr');
                const cell = panel.insertCell();
                cell.colSpan = row.cells.length;
                cell.append(box);
            } else {
                panel = document.createElement('li');
                panel.append(box);
            }
            panel.className = 'sb-details-row';
            row.after(panel);
            Fetch.run(row.dataset.sbDetails, box, false).catch(() => { box.textContent = t('sb.details.error'); });
        }
        panel.hidden = false;
        row.classList.add('is-open');
        row.querySelector('.sb-open')?.setAttribute('aria-expanded', 'true');
        openRow = row;
    }

    document.addEventListener('click', event => {
        const row = event.target.closest('.sb-expand');
        if (!row) return;
        const link = event.target.closest('a');
        // Other links keep working, and modifier clicks open the details page itself
        if (link && (!link.classList.contains('sb-open') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) return;
        event.preventDefault();
        const wasOpen = row === openRow;
        if (openRow) close(openRow);
        openRow = null;
        if (!wasOpen) open(row);
    });
})();
</script>
<?php
}

/**
 * Mute or gag icon of a comm block (type 1 = voice, 2 = chat).
 */
function sbCommType($type)
{
	$label = t((int) $type === 2 ? 'sb.gag' : 'sb.mute');
	return '<span class="sb-type" role="img" aria-label="' . htmlspecialchars($label) . '" data-tooltip="' . htmlspecialchars($label) . '">'
		. ((int) $type === 2 ? SB_ICON_GAG : SB_ICON_MUTE) . '</span>';
}

/**
 * Table of the enabled SourceBans servers, with name, map and players from HLstatsZ when it tracks the server.
 * All of them on one page: a community runs a handful of servers, not thousands.
 */
function sbServerTable()
{
	global $g_options;
	// SourceBans servers, then the GoldSrc ones of AMXBans when it is set up. Map and players of the servers
	// HLstatsZ does not track: from RCON "status" while one is kept, else the browser asks sourcebans_status.php
	// ('ask') once the page is shown
	$servers = array();
	foreach (sbServers() as $sid => $server) {
		$servers[] = array_merge($server, array(
			'label'  => sbServerName($sid),
			'addr'   => $server['ip'] . ':' . $server['port'],
			'game'   => sbGame($sid, $server['modfolder'], $server['modname']),
			'status' => sbServerStatus($sid, false),
			'ask'    => 'sid=' . (int) $sid,
		));
	}
	foreach (sbAmxServers() as $id => $server) {
		$servers[] = array_merge($server, array(
			'sid'    => 0,
			'addr'   => $server['address'],
			'game'   => sbAmxGame($server['address'], $server['gametype']),
			'status' => sbAmxServerStatus($id, false),
			'ask'    => 'amx=' . (int) $id,
		));
	}
	if (!$servers) {
		echo '<p class="sb-empty">' . t('sb.none.servers') . '</p>';
		return;
	}
	usort($servers, function ($a, $b) {
		return strcasecmp($a['game']['name'], $b['game']['name']) ?: strcasecmp($a['label'], $b['label']);
	});
?>
<div class="responsive-table">
<table class="sb-table sb-servers">
	<tr>
		<th class="left"><?= t('th.server') ?></th>
		<th class="left hide-1"><?= t('th.address') ?></th>
		<th class="left hide"><?= t('th.map') ?></th>
		<th><?= t('players') ?></th>
		<th class="hlstats-numeric"><?= t('sb.th.bans') ?></th>
		<th class="hlstats-numeric hide-2" data-tooltip="<?= htmlspecialchars(t('sb.kpi.blocked.tip')) ?>"><?= t('sb.th.blocked') ?></th>
	</tr>
<?php
	// Admins who can ban open the players of a server under its row (RCON status, see sourcebans-admin.js): SourceBans
	// admins on SourceBans servers, the admins of an AMXBans server on it (sbAmxRights)
	$canPlayers = function_exists('sbCan') && sbCan('ADMIN_ADD_BAN');
	$amxRights  = sbAmxRights()['servers'];
	foreach ($servers as $server):
		$hlz     = $server['hlz'];
		$game    = $server['game'];
		$addr    = $server['addr'];
		$players = $server['sid'] ? $canPlayers && $server['has_rcon'] : isset($amxRights[(int) $server['id']]);

		// Map and players: from HLstatsZ when it tracks the server, else from RCON "status"
		$status = $server['status'];
		if ($hlz) {
			$map   = $hlz['act_map'];
			$count = $map !== '' ? (int) $hlz['act_players'] . '/' . (int) $hlz['max_players'] : '';
		} else {
			$map   = is_array($status) ? $status['map'] : '';
			// A hibernating CS2 server gives "(0 max)": the count alone then
			$count = is_array($status) && $status['players'] !== null ? $status['players'] . ($status['max'] ? '/' . $status['max'] : '') : '';
		}
		$none  = $status === false ? '&hellip;' : '&ndash;';
		$attrs = ($players ? ' class="sba-server" data-sba-players="' . (int) ($server['sid'] ?: $server['id']) . '"' . ($server['sid'] ? '' : ' data-sba-players-action="amx.players"') : '')
			. ($status === false ? ' data-sb-status="' . htmlspecialchars($g_options['scripturl'] . '?mode=sourcebans&task=sourcebans_status&' . $server['ask']) . '"' : '');
?>
	<tr<?= $attrs ?>>
		<td class="left">
<?php if ($players): ?>
			<span class="hlstats-slider-arrow">&#9660;</span>
<?php endif; ?>
			<span class="hlstats-icon"><img src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>"></span>
<?php if ($hlz): ?>
			<a href="<?= $g_options['scripturl'] ?>?mode=servers&amp;server_id=<?= (int) $hlz['serverId'] ?>"><span class="hlstats-name" data-tooltip="<?= t('title.server.live') ?>"><?= htmlspecialchars($server['label']) ?></span></a>
<?php else: ?>
			<span class="hlstats-name sb-status-host"><?= htmlspecialchars($server['label']) ?></span>
<?php endif; ?>
		</td>
		<td class="left hide-1 nowrap"><a href="steam://connect/<?= htmlspecialchars($addr) ?>" data-tooltip="<?= t('map.join') ?>"><?= htmlspecialchars($addr) ?></a></td>
		<td class="left hide sb-status-map"><?= $map !== '' ? htmlspecialchars($map) : $none ?></td>
		<td class="nowrap sb-status-players"><?= $count !== '' ? $count : $none ?></td>
		<td class="hlstats-numeric"><?= nf($server['bans']) ?></td>
		<td class="hlstats-numeric hide-2"><?= nf($server['blocked']) ?></td>
	</tr>
<?php endforeach; ?>
</table>
</div>
<?php
	if (!is_ajax()) {
		sbServerStatusScript();
	}
}

/**
 * Script filling the rows that wait for an RCON "status" (data-sb-status), again in content loaded later (fetch:loaded).
 */
function sbServerStatusScript()
{
?>
<script>
(function () {
    function ask(root) {
        root.querySelectorAll('tr[data-sb-status]').forEach(row => {
            const url = row.dataset.sbStatus;
            row.removeAttribute('data-sb-status');
            const show = info => {
                if (info.host) row.querySelector('.sb-status-host').textContent = info.host;
                row.querySelector('.sb-status-map').textContent = info.map || '–';
                row.querySelector('.sb-status-players').textContent = Number.isInteger(info.players) ? info.players + (info.max ? '/' + info.max : '') : '–';
            };
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.json())
                .then(info => show(info.ok ? info : {}))
                .catch(() => show({}));
        });
    }
    ask(document);
    document.addEventListener('fetch:loaded', event => ask(event.target));
})();
</script>
<?php
}
