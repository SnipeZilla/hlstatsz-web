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

require_once INCLUDE_PATH . '/amxbans.php';   // tables, RCON, players, bans (shared with Bans > AMXBans)

// AMX Mod X access flags; not the SourceMod letters ("a" is immunity here, "z" means no admin rights)
const SB_AMX_FLAGS = array(
	'a' => 'Immunity', 'b' => 'Reserved slot', 'c' => 'Kick', 'd' => 'Ban and unban', 'e' => 'Slay and slap', 'f' => 'Change map',
	'g' => 'Change cvars', 'h' => 'Run configs', 'i' => 'Admin chat', 'j' => 'Votes', 'k' => 'Server password', 'l' => 'RCON',
	'm' => 'Custom level A', 'n' => 'Custom level B', 'o' => 'Custom level C', 'p' => 'Custom level D', 'q' => 'Custom level E',
	'r' => 'Custom level F', 's' => 'Custom level G', 't' => 'Custom level H', 'u' => 'Menus', 'z' => 'No admin rights',
);

// AMXBans game types whose HLstatsZ game has another code
const SB_AMX_GAMES = array('czero' => 'cstrike');

/**
 * Whether AMXBans is set up (DB_AMXNAME) and its tables can be read; a wrong name turns it off, not the page.
 */
function sbAmx()
{
	global $db;
	static $ok = null;

	if ($ok === null) {
		$ok = defined('DB_AMXNAME') && DB_AMXNAME !== ''
			&& $db->query("SELECT 1 FROM " . sbAmxTable('bans') . ", " . sbAmxTable('bans_edit') . ", " . sbAmxTable('serverinfo') . ", "
				. sbAmxTable('amxadmins') . ", " . sbAmxTable('admins_servers') . " LIMIT 0", false) !== false;
	}
	return $ok;
}

/**
 * Full name of an AMXBans table, e.g. `amxbans`.`amx_bans` (amxTable).
 */
function sbAmxTable($name)
{
	return amxTable($name);
}

/**
 * What the visitor signed in with Steam may do on the AMXBans servers: array('admin' => the name and Steam ID their
 * bans are recorded with, 'all' => whether an HLstatsZ admin, 'servers' => server id => array('kick' => bool,
 * 'ban' => bool)). An HLstatsZ admin may do all on every server. An AMXBans admin recognised by Steam ID (account
 * flag c) and still active may, on the servers they are admin of, what their access flags there allow (the server's
 * custom flags when set): c to kick, d to ban and unban.
 */
function sbAmxRights()
{
	global $db;
	static $rights = null;

	if ($rights !== null) {
		return $rights;
	}
	$rights = array('admin' => null, 'all' => false, 'servers' => array());
	$id64   = (string) ($_SESSION['ID64'] ?? '');
	$steam  = preg_match('/^7656119\d{10}$/', $id64) ? amxSteamId($id64) : null;
	if (!$steam || !sbAmx()) {
		return $rights;
	}
	$servers = sbAmxServers();
	if (isSteamAdmin($id64)) {
		$rights['all'] = true;
		foreach (array_keys($servers) as $id) {
			$rights['servers'][$id] = array('kick' => true, 'ban' => true);
		}
	} else {
		$db->query("
			SELECT
				s.server_id,
				s.custom_flags,
				a.access,
				a.flags
			FROM
				" . sbAmxTable('amxadmins') . " AS a
				JOIN " . sbAmxTable('admins_servers') . " AS s ON s.admin_id = a.id
			WHERE
				a.steamid IN ('$steam', '" . str_replace('STEAM_0:', 'STEAM_1:', $steam) . "')
				AND (a.days = 0 OR a.expired > UNIX_TIMESTAMP())
		");
		foreach ($db->fetch_row_set() ?: array() as $row) {
			$id     = (int) $row['server_id'];
			$access = trim((string) $row['custom_flags']) !== '' ? (string) $row['custom_flags'] : (string) $row['access'];
			if (strpos((string) $row['flags'], 'c') === false || !isset($servers[$id])) {
				continue;   // recognised in game by something else than the Steam ID, or a server gone
			}
			$have = $rights['servers'][$id] ?? array('kick' => false, 'ban' => false);
			$can  = array('kick' => $have['kick'] || strpos($access, 'c') !== false, 'ban' => $have['ban'] || strpos($access, 'd') !== false);
			if ($can['kick'] || $can['ban']) {
				$rights['servers'][$id] = $can;
			}
		}
	}
	if ($rights['servers']) {
		$rights['admin'] = amxWebAdmin($id64);
	}
	return $rights;
}

/**
 * Whether the visitor may kick or ban ($right) on the AMXBans server at this address; bans given on the website
 * (no server) are for HLstatsZ admins.
 */
function sbAmxMay($right, $address)
{
	$rights = sbAmxRights();
	if ($rights['all']) {
		return true;
	}
	foreach ($rights['servers'] as $id => $can) {
		if ($can[$right] && (string) $address !== '' && (string) (sbAmxServers()[$id]['address'] ?? '') === (string) $address) {
			return true;
		}
	}
	return false;
}

/**
 * An AMXBans text column in the collation of the lists, so it can be merged with the SourceBans ones.
 */
function sbAmxText($column)
{
	return "CONVERT($column USING utf8mb4) COLLATE utf8mb4_unicode_ci";
}

/**
 * AMXBans bans with the columns of sbAllBans(); with $more, also server_name and kicks (joins blocked).
 */
function sbAmxBansSelect($more = false)
{
	return "
		SELECT
			'amx' AS src,
			b.bid,
			" . sbAmxText('b.player_ip') . " AS ip,
			" . sbAmxText('b.player_id') . " AS authid,
			" . sbAmxText('b.player_nick') . " AS name,
			'' AS country,
			b.ban_created + COALESCE(s.tz, 0) * 3600 AS created,
			b.ban_created + COALESCE(s.tz, 0) * 3600 + GREATEST(b.ban_length, 0) * 60 AS ends,
			IF(b.ban_length < 0, -1, b.ban_length * 60) AS length,
			" . sbAmxText('b.ban_reason') . " AS reason,
			0 AS aid,
			0 AS sid,
			" . sbAmxText('b.admin_nick') . " AS admin,
			" . sbAmxText('b.server_ip') . " AS server,
			" . sbAmxText('s.gametype') . " AS game,
			NULL AS modname,
			NULL AS RemovedOn,
			IF(b.expired = 1, IF(b.ban_length < 0, 'U', 'E'), NULL) AS RemoveType,
			IF(b.ban_type = 'SI', 1, 0) AS type"
		. ($more ? ",
			" . sbAmxText('b.server_name') . " AS server_name,
			b.ban_kicks AS kicks" : '') . "
		FROM
			" . sbAmxTable('bans') . " AS b
			LEFT JOIN (
				SELECT address, MAX(gametype) AS gametype, MAX(timezone_fixx) AS tz FROM " . sbAmxTable('serverinfo') . " GROUP BY address
			) AS s ON s.address = b.server_ip";
}

/**
 * Joins blocked by AMXBans bans (it only keeps a count per ban, not when).
 */
function sbAmxKicks()
{
	global $db;

	if (!sbAmx()) {
		return 0;
	}
	$db->query("SELECT COALESCE(SUM(ban_kicks), 0) FROM " . sbAmxTable('bans'));
	return (int) $db->fetch_row()[0];
}

/**
 * AMXBans servers by id: hostname, address (also as 'ip' and 'port'), gametype, whether AMXBans keeps an RCON
 * password for it ('has_rcon'), the HLstatsZ server at their address in 'hlz' (or null), a display 'label', and
 * their bans and blocked joins.
 */
function sbAmxServers()
{
	global $db;
	static $servers = null;

	if ($servers !== null) {
		return $servers;
	}
	$servers = array();
	if (!sbAmx()) {
		return $servers;
	}
	$hlz    = sbHlzByAddress();
	$result = $db->query("
		SELECT
			s.id,
			s.hostname,
			s.address,
			s.gametype,
			COALESCE(s.rcon, '') <> '' AS has_rcon,
			(SELECT COUNT(*) FROM " . sbAmxTable('bans') . " AS b WHERE b.server_ip = s.address) AS bans,
			(SELECT COALESCE(SUM(b.ban_kicks), 0) FROM " . sbAmxTable('bans') . " AS b WHERE b.server_ip = s.address) AS blocked
		FROM
			" . sbAmxTable('serverinfo') . " AS s
		ORDER BY
			s.id
	");
	while ($row = $db->fetch_array($result)) {
		$row['hlz']   = $hlz[$row['address']] ?? null;
		$row['label'] = $row['hlz'] && $row['hlz']['name'] !== ''
			? html_entity_decode($row['hlz']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
			: (trim((string) $row['hostname']) !== '' ? trim($row['hostname']) : $row['address']);
		// The plugin writes "ip:port"; without a port there is nothing to reach over RCON
		$parts           = preg_match('/^(.+):(\d+)$/', (string) $row['address'], $m) ? $m : null;
		$row['ip']       = $parts ? $parts[1] : (string) $row['address'];
		$row['port']     = $parts ? (int) $parts[2] : 0;
		$row['has_rcon'] = $parts && $row['has_rcon'];
		$servers[(int) $row['id']] = $row;
	}
	return $servers;
}

/**
 * Host name, map and players of an AMXBans server HLstatsZ does not track, over GoldSrc RCON with the password
 * AMXBans keeps for it (sbRconStatus).
 */
function sbAmxServerStatus($id, $query)
{
	global $db;

	$server = sbAmxServers()[(int) $id] ?? null;
	return $server ? sbRconStatus($server, function () use ($db, $id) {
		$db->query("SELECT rcon FROM " . sbAmxTable('serverinfo') . " WHERE id = " . (int) $id);
		return (string) ($db->fetch_row()[0] ?? '');
	}, true, $query) : null;
}

/**
 * Icon and name of the game of an AMXBans server: the HLstatsZ server at its address first, then its game type.
 * Bans added on the AMXBans pages have no server.
 */
function sbAmxGame($address, $gametype)
{
	$type = strtolower(trim((string) $gametype));
	if (trim((string) $address) === '' || $type === 'website') {
		return array('icon' => sbGameIcon(array()), 'name' => t('sb.web'));
	}
	$hlz   = sbHlzByAddress()[$address] ?? null;
	$codes = $hlz ? array($hlz['game'], $hlz['realgame']) : array();
	if ($type !== '') {
		$codes[] = SB_AMX_GAMES[$type] ?? $type;
	}
	$games = sbHlzGames();
	foreach ($codes as $code) {
		if (isset($games[$code])) {
			return array('icon' => sbGameIcon($codes), 'name' => $games[$code]['name']);
		}
	}
	return array('icon' => sbGameIcon($codes), 'name' => $type !== '' ? $type : t('sb.web'));
}

/**
 * Details of an AMXBans ban: the panel under a list row, or the body of the details page.
 */
function sbAmxDetails($id, $page = false)
{
	global $db, $g_options;

	$now       = time();
	$showAdmin = sbShowAdminNames();
	$row       = sbAmx() ? $db->fetch_array($db->query(sbAmxBansSelect(true) . " WHERE b.bid = " . (int) $id)) : null;
	if (!$row) {
		echo '<p class="sb-empty">' . t('sb.details.missing') . '</p>';
		return;
	}

	$length  = (int) $row['length'];
	$removed = $row['RemoveType'] ?? '';
	$authid  = trim((string) $row['authid']);
	$fields  = array();   // label, value (HTML), full width

	// Changes logged by AMXBans, newest first; an unban is logged as "Unban: <reason>"
	$db->query("SELECT edit_time, admin_nick, edit_reason FROM " . sbAmxTable('bans_edit') . " WHERE bid = " . (int) $row['bid'] . " ORDER BY edit_time DESC, id DESC");
	$edits = $db->fetch_row_set() ?: array();
	$unban = null;
	foreach ($edits as $edit) {
		if ($unban === null && stripos((string) $edit['edit_reason'], 'Unban') === 0) {
			$unban = $edit;
		}
	}
	$row['RemovedOn'] = $unban ? $unban['edit_time'] : null;

	// Identity; GoldSrc servers without Steam give ids such as STEAM_ID_LAN
	$ids = sbSteamIds($authid);
	if ($authid !== '') {
		$value = htmlspecialchars($authid);
		if ($ids) {
			$value .= ' <span class="sb-muted">' . $ids['steam3'] . '</span>'
				. ' &middot; <a href="https://steamcommunity.com/profiles/' . $ids['id64'] . '" target="_blank" rel="noopener">' . t('sb.details.steam') . ' &#8599;</a>';
		}
		$fields[] = array(t('sb.details.steamid'), $value);
	}
	$fields[] = array(t('sb.details.bantype'), t((int) $row['type'] === 1 ? 'sb.details.type.ip' : 'sb.details.type.steam'));

	// Timing
	$fields[] = array(t('sb.details.date'), sbDate($row['created']));
	$fields[] = array(t('sb.th.length'), $length === 0 ? t('sb.status.permanent') : ($length < 0 ? '&ndash;' : sbDuration($length)));
	if ($removed === 'U') {
		$by = $showAdmin && $unban && trim((string) $unban['admin_nick']) !== '' ? ' ' . t('sb.details.by', array('{admin}' => htmlspecialchars($unban['admin_nick']))) : '';
		$fields[] = array(t('sb.details.lifted'), ($unban ? sbDate($unban['edit_time']) : '&ndash;') . $by);
	} elseif ($removed === 'E' || ($length > 0 && (int) $row['ends'] <= $now)) {
		$fields[] = array(t('sb.details.expired'), $length > 0 ? sbDate($row['ends']) : '&ndash;');
	} elseif ($length > 0) {
		$fields[] = array(t('sb.details.expires'), sbDate($row['ends']) . ' <span class="sb-muted">&middot; ' . t('sb.details.left', array('{time}' => sbDuration((int) $row['ends'] - $now))) . '</span>');
	} else {
		$fields[] = array(t('sb.details.expires'), t('sb.details.never'));
	}

	// Where and by whom
	$game   = sbAmxGame($row['server'], $row['game']);
	$server = $game['name'];
	if (trim((string) $row['server']) !== '') {
		$hlz    = sbHlzByAddress()[$row['server']] ?? null;
		$server = $hlz && $hlz['name'] !== '' ? html_entity_decode($hlz['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
			: (trim((string) $row['server_name']) !== '' ? trim($row['server_name']) : $row['server']);
	}
	$fields[] = array(t('sb.details.from'), '<img class="sb-field-icon" src="' . htmlspecialchars($game['icon']) . '" alt="">' . htmlspecialchars($server));
	if ($showAdmin) {
		$fields[] = array(t('sb.details.admin'), trim((string) $row['admin']) !== '' ? htmlspecialchars($row['admin']) : '&ndash;');
	}

	// History: joins blocked, and the record of the player in both ban systems
	$fields[] = array(t('sb.kpi.blocked'), nf($row['kicks']));
	if ($ids) {
		$match = sbSteamMatch($ids);
		$db->query("
			SELECT
				(SELECT COUNT(*) FROM " . sbAmxTable('bans') . " WHERE player_id IN ($match))"
				. (sbOn() ? "
				+ (SELECT COUNT(*) FROM " . DB_SBPREFIX . "_bans WHERE authid IN ($match)),
				(SELECT COUNT(*) FROM " . DB_SBPREFIX . "_comms WHERE authid IN ($match))" : ", NULL")
		);
		list($bans, $comms) = $db->fetch_row();
		$fields[] = array(t('sb.details.record'), t('sb.th.bans') . ' ' . nf($bans) . ($comms !== null ? ' &middot; ' . t('sb.kpi.comms') . ' ' . nf($comms) : ''));

		$links = array();
		foreach (sbProfiles($ids['steam2']) as $profile) {
			$links[] = '<a href="' . $g_options['scripturl'] . '?mode=playerinfo&amp;player=' . (int) $profile['playerId'] . '">'
				. htmlspecialchars($profile['gamename']) . ' &rsaquo;</a>';
		}
		if ($links) {
			$fields[] = array(t('sb.details.stats'), implode(' &middot; ', $links));
		}
	}

	$reason = trim((string) $row['reason']);
	$fields[] = array(t('sb.th.reason'), $reason !== '' ? htmlspecialchars($reason) : '<span class="sb-muted">' . t('sb.no.reason') . '</span>', true);
	if ($unban) {
		$ureason = trim(preg_replace('/^Unban:?\s*/i', '', (string) $unban['edit_reason']));
		if ($ureason !== '') {
			$fields[] = array(t('sb.details.ureason'), htmlspecialchars($ureason), true);
		}
	}
	$changes = array();
	foreach (array_slice($edits, 0, 5) as $edit) {
		if ($edit !== $unban) {
			$changes[] = sbDate($edit['edit_time']) . ($showAdmin && trim((string) $edit['admin_nick']) !== '' ? ' &middot; ' . htmlspecialchars($edit['admin_nick']) : '')
				. ' &middot; ' . htmlspecialchars((string) $edit['edit_reason']);
		}
	}
	if ($changes) {
		$fields[] = array(t('sb.details.changes'), implode('<br>', $changes), true);
	}

	if ($page) {
		echo '<div class="sb-details-head">' . sbFlag($row) . '<span class="hlstats-name">' . sbPlayerName($row) . '</span>' . sbPill($row, $now) . '</div>';
	}
	sbDetailsFields($fields);

	// Appeal, and unban for an admin of the server of the ban (sbAmxRights)
	$active = $removed === '' && ($length === 0 || (int) $row['ends'] > $now);
	if ($active) {
		echo sbAppealLink();
	}
	if ($active && sbAmxMay('ban', (string) $row['server'])) {
		$values = array('id' => (int) $row['bid'], '@summary' => 'Unban ' . $row['name'] . '?');
		echo '<div class="sba-actions"><button type="button" class="sba-btn is-small" data-sba-edit="sba-amx-unban" data-sba-title="Unban"'
			. " data-sba-values='" . htmlspecialchars(json_encode($values), ENT_QUOTES) . "'>Unban</button></div>";
	}
	echo '<p class="sb-details-note">' . t('sb.details.amx') . '</p>';
}

/**
 * The players of an AMXBans server under its row in the Servers list, for an admin of it: who is on, their Steam ID
 * and IP address, the kick and ban buttons their rights allow ($can), and the ban of a player who is not on it.
 * $players is array(listed, the players or why not) (amxPlayers); $info the host name and map (sbAmxServerStatus).
 */
function sbAmxPlayersPanel($id, array $players, $info, array $can)
{
	global $db, $g_options;

	list($listed, $list) = $players;
	$list = $listed ? $list : array();
	$head = array_filter(array(
		($info['host'] ?? '') !== '' ? '<b>' . htmlspecialchars($info['host']) . '</b>' : '',
		($info['map'] ?? '') !== '' ? htmlspecialchars($info['map']) : '',
		$listed ? count($list) . (count($list) === 1 ? ' player' : ' players') : '',
	));
	$html = '<div class="sba-players-head"><div>' . implode(' &middot; ', $head) . '</div><div>'
		. ($can['ban'] ? '<button type="button" class="sba-btn is-small" data-sba-edit="sba-amx-offline" data-sba-title="Ban a player who is not on the server"'
			. " data-sba-values='" . htmlspecialchars(json_encode(array('server' => (int) $id)), ENT_QUOTES) . "'>Ban by Steam ID or IP&hellip;</button> " : '')
		. '<button type="button" class="sba-btn is-small" data-sba-players-refresh>Refresh</button></div></div>';
	if (!$listed) {
		return $html . '<p class="sba-players-error">The players cannot be listed: ' . htmlspecialchars($players[1]) . '</p>';
	}
	if (!$list) {
		return $html . '<p class="sba-muted sba-players-none">Nobody is playing on this server right now.</p>';
	}

	// HLstatsZ profiles in the server's game (HLstatsZ keeps STEAM_X:Y:Z as "Y:Z")
	$profiles = array();
	$steam    = array_filter(array_column($list, 'authid'), fn($authid) => preg_match('/^STEAM_[0-5]:[01]:\d+$/', $authid));
	if ($steam && ($hlz = sbAmxServers()[(int) $id]['hlz'] ?? null)) {
		$unique = implode(', ', array_map(fn($authid) => "'" . $db->escape(substr($authid, 8)) . "'", $steam));
		$db->query("SELECT uniqueId, playerId FROM `" . DB_NAME . "`.hlstats_PlayerUniqueIds WHERE game = '" . $db->escape($hlz['game']) . "' AND uniqueId IN ($unique)");
		foreach ($db->fetch_row_set() ?: array() as $row) {
			$profiles[$row['uniqueId']] = (int) $row['playerId'];
		}
	}

	$html .= '<div class="responsive-table"><table class="sba-table sba-players-table"><thead><tr>'
		. '<th class="left">Player</th><th class="left hide-1">Steam ID</th><th class="left hide">IP address</th><th></th>'
		. '</tr></thead><tbody>';
	foreach ($list as $player) {
		$name    = htmlspecialchars($player['name']);
		$profile = $profiles[substr($player['authid'], 8)] ?? null;
		$badge   = array(1 => 'Bot', 2 => 'HLTV')[$player['kind']] ?? ($player['immune'] ? 'Immunity' : '');
		$open    = $player['kind'] === 0 && !$player['immune'];
		$values  = array('server' => (int) $id, 'userid' => $player['userid'],
			'@summary' => $player['name'] . ' · ' . ($player['authid'] !== '' ? $player['authid'] : 'no Steam ID') . ($player['ip'] !== '' ? ' · ' . $player['ip'] : ''));
		$html .= '<tr><td class="left">'
			. ($profile ? '<a class="hlstats-name" href="' . $g_options['scripturl'] . '?mode=playerinfo&amp;player=' . $profile . '">' . $name . '</a>' : '<span class="hlstats-name">' . $name . '</span>')
			. ($badge !== '' ? ' <span class="sba-badge' . ($player['immune'] ? ' is-warn' : '') . '">' . $badge . '</span>' : '') . '</td>'
			. '<td class="left hide-1"><code class="sba-code">' . htmlspecialchars($player['authid']) . '</code></td>'
			. '<td class="left hide">' . htmlspecialchars($player['ip']) . '</td>'
			. '<td class="right nowrap">'
			. ($can['ban'] && $open ? '<button type="button" class="sba-btn is-small is-danger" data-sba-edit="sba-amx-ban" data-sba-title="' . htmlspecialchars('Ban ' . $player['name']) . '"'
				. " data-sba-values='" . htmlspecialchars(json_encode($values), ENT_QUOTES) . "'>Ban</button> " : '')
			. ($can['kick'] && $open ? '<button type="button" class="sba-btn is-small" data-sba-edit="sba-amx-kick" data-sba-title="' . htmlspecialchars('Kick ' . $player['name']) . '"'
				. " data-sba-values='" . htmlspecialchars(json_encode($values), ENT_QUOTES) . "'>Kick</button>" : '')
			. '</td></tr>';
	}
	return $html . '</tbody></table></div>';
}

/**
 * Dialogs of the AMXBans admin tools: kick and ban of a player on a server, ban of a player who is not on it, unban.
 */
function sbAmxDialogs()
{
	global $db;

	// Reasons: the SourceBans++ defaults, and those of AMXBans when it has some
	$db->query("SELECT reason FROM " . sbAmxTable('reasons') . " WHERE reason <> '' ORDER BY id LIMIT 30", false);
	$server  = array_column($db->fetch_row_set() ?: array(), 'reason');
	$reasons = array('Hacking' => SB_REASONS['Hacking'], 'Behaviour' => SB_REASONS['Behaviour'], 'Server' => $server);
	$foot    = fn($label, $danger) => '<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button>'
		. '<button type="submit" class="sba-btn ' . ($danger ? 'is-danger is-solid' : 'is-primary') . '">' . $label . '</button></div>';
	$head    = fn($title) => '<div class="sba-dialog-head"><h2 class="sba-dialog-title">' . $title . '</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>';
?>
<dialog id="sba-amx-kick" class="sba-dialog">
	<form data-sba-action="amx.kick" novalidate>
		<input type="hidden" name="server" value="0"><input type="hidden" name="userid" value="0">
		<?= $head('Kick player') ?>
		<div class="sba-dialog-body hlstats-scrollbar">
			<p class="sba-tight" data-sba-text="summary"></p>
			<label class="sba-field"><span class="sba-label">Message <small>shown to the player, optional</small></span><input type="text" name="reason" maxlength="100"><span class="sba-error" data-error-for="reason"></span></label>
		</div>
		<?= $foot('Kick player', false) ?>
	</form>
</dialog>
<dialog id="sba-amx-ban" class="sba-dialog">
	<form data-sba-action="amx.ban" novalidate>
		<input type="hidden" name="server" value="0"><input type="hidden" name="userid" value="0">
		<?= $head('Ban player') ?>
		<div class="sba-dialog-body hlstats-scrollbar">
			<p class="sba-tight" data-sba-text="summary"></p>
			<?= sbLengthField() ?>
			<?= sbReasonField($reasons, 100) ?>
		</div>
		<?= $foot('Ban player', true) ?>
	</form>
</dialog>
<dialog id="sba-amx-offline" class="sba-dialog">
	<form data-sba-action="amx.banoffline" novalidate>
		<input type="hidden" name="server" value="0">
		<?= $head('Ban a player who is not on the server') ?>
		<div class="sba-dialog-body hlstats-scrollbar">
			<fieldset class="sba-field">
				<legend class="sba-label">Ban by</legend>
				<div class="sba-segment">
					<label><input type="radio" name="target" value="steam" checked><span>Steam ID</span></label>
					<label><input type="radio" name="target" value="ip"><span>IP address</span></label>
				</div>
			</fieldset>
			<label class="sba-field" data-sba-when="target=steam"><span class="sba-label">Steam ID <small>STEAM_0:1:123, [U:1:246], 7656… or a profile link</small></span><input type="text" name="steam" spellcheck="false" autocomplete="off"><span class="sba-error" data-error-for="steam"></span></label>
			<label class="sba-field" data-sba-when="target=ip" hidden><span class="sba-label">IP address</span><input type="text" name="ip" spellcheck="false" autocomplete="off" placeholder="203.0.113.7"><span class="sba-error" data-error-for="ip"></span></label>
			<label class="sba-field"><span class="sba-label">Player name <small>optional</small></span><input type="text" name="name" maxlength="100"></label>
			<?= sbLengthField() ?>
			<?= sbReasonField($reasons, 100) ?>
		</div>
		<?= $foot('Ban player', true) ?>
	</form>
</dialog>
<dialog id="sba-amx-unban" class="sba-dialog">
	<form data-sba-action="amx.unban" novalidate>
		<input type="hidden" name="id" value="0">
		<?= $head('Unban') ?>
		<div class="sba-dialog-body hlstats-scrollbar">
			<p class="sba-tight" data-sba-text="summary"></p>
			<label class="sba-field"><span class="sba-label">Why?</span><input type="text" name="reason" maxlength="240" required placeholder="Kept with the ban"><span class="sba-error" data-error-for="reason"></span></label>
		</div>
		<?= $foot('Unban', false) ?>
	</form>
</dialog>
<?php
}
