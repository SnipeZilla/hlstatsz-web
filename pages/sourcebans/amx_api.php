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

if (!is_string($_POST['token'] ?? null) || !hash_equals(sbToken(), $_POST['token'])) {
	sbFail('Your session has expired. Reload the page.');
}
$rights = sbAmxRights();
if (!$rights['servers']) {
	sbFail('Sign in with Steam as an admin of this AMXBans server.');
}
session_write_close();

$in    = fn($key) => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
$admin = $rights['admin'];

// The server of the request, when the visitor may $right ('kick' or 'ban') on it
$server = function ($id, $right) use ($rights) {
	$id = (int) $id;
	if (!($rights['servers'][$id][$right] ?? false)) {
		sbFail('You are not an admin of this server who may ' . ($right === 'kick' ? 'kick.' : 'ban.'));
	}
	$row = amxServer($id);
	if (!$row) {
		sbFail('This server is no longer in AMXBans. Reload the page.');
	}
	return $row;
};

// The player of the request, still on the server, neither a bot nor HLTV, without immunity
$player = function (array $server, $userid) {
	list($ok, $players) = amxPlayers($server);
	if (!$ok) {
		sbFail('The server did not answer: ' . $players);
	}
	foreach ($players as $p) {
		if ($p['userid'] === (int) $userid) {
			if ($p['kind'] !== 0) {
				sbFail($p['name'] . ' is ' . ($p['kind'] === 1 ? 'a bot.' : 'HLTV.'));
			}
			if ($p['immune']) {
				sbFail($p['name'] . ' has immunity.');
			}
			return $p;
		}
	}
	sbFail('This player is no longer on the server. Refresh the list.');
};

// Length (minutes) and reason of a ban, or the fields in error
$terms = function () use ($in) {
	$errors = array();
	$length = $in('length');
	$reason = amxClean($in('reason'), 1000);
	if (!ctype_digit($length)) {
		$errors['length'] = 'Choose a length.';
	}
	if ($reason === '') {
		$errors['reason'] = 'Give a reason.';
	} elseif (mb_strlen($reason) > 100) {
		$errors['reason'] = 'Use 100 characters at most.';
	}
	return array((int) $length, $reason, $errors);
};

$lasting = fn($minutes) => $minutes ? 'for ' . sbDuration($minutes * 60) : 'permanently';

switch ($action) {

	/*
	 * The players of a server, under its row in the Servers list
	 */
	case 'amx.players':
		$id = (int) $in('sid');
		if (empty($rights['servers'][$id])) {
			sbFail('You are not an admin of this server.');
		}
		$row = amxServer($id);
		if (!$row) {
			sbFail('This server is no longer in AMXBans. Reload the page.');
		}
		$players = (string) $row['rcon'] !== '' ? amxPlayers($row) : array(false, 'no RCON password is set for it (HLstatsZ admin, Bans > AMXBans).');
		// Host name and map as the Servers list shows them: from HLstatsZ when it tracks the server, else from RCON
		$hlz  = sbAmxServers()[$id]['hlz'] ?? null;
		$info = $hlz ? array('host' => html_entity_decode($hlz['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'map' => (string) $hlz['act_map'])
			: ($players[0] ? sbAmxServerStatus($id, true) : null);
		sbReply(array('ok' => true, 'html' => sbAmxPlayersPanel($id, $players, $info ?: array(), $rights['servers'][$id])));

	/*
	 * Kick of a player on a server, with an optional message
	 */
	case 'amx.kick':
		$row     = $server($in('server'), 'kick');
		$p       = $player($row, $in('userid'));
		$message = amxClean($in('reason'), 100);
		list($ok, $why) = amxKick($row, $p['userid'], $message);
		if (!$ok) {
			sbFail($p['name'] . ' was not kicked: ' . $why);
		}
		amxLog('Kick online', 'nick: ' . $p['name'] . ' <' . $p['authid'] . '><' . $p['ip'] . '> kicked', $admin['name']);
		sbReply(array('ok' => true, 'message' => $p['name'] . ' is kicked.'));

	/*
	 * Ban of a player on a server: written, then the player is kicked
	 */
	case 'amx.ban':
		$row = $server($in('server'), 'ban');
		list($minutes, $reason, $errors) = $terms();
		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}
		$p    = $player($row, $in('userid'));
		$type = amxBanType($p['authid'], $p['ip']);
		if ($type === '') {
			sbFail($p['name'] . ' has neither a Steam ID nor an IP address to ban.');
		}
		if ($bid = amxActiveBan($type, $p['authid'], $p['ip'])) {
			sbFail($p['name'] . ' is already banned (ban #' . $bid . ').');
		}
		amxAddBan(array('type' => $type, 'authid' => $p['authid'], 'ip' => $p['ip'], 'name' => $p['name'], 'minutes' => $minutes, 'reason' => $reason), $row, $admin);
		amxLog('Add ban online', 'nick: ' . $p['name'] . ' <' . $p['authid'] . '><' . $p['ip'] . "> banned for $minutes minutes", $admin['name']);
		list($kicked) = amxKick($row, $p['userid'], amxBanMessage($minutes, $reason));
		sbReply(array(
			'ok'      => true,
			'message' => $p['name'] . ' is banned ' . $lasting($minutes) . '.' . ($kicked ? '' : ' The kick failed: the AMXBans plugin keeps them out when they join again.'),
			'reset'   => true,
		));

	/*
	 * Ban of a player who is not on the server, by Steam ID or IP address; kicked if on it after all
	 */
	case 'amx.banoffline':
		$row    = $server($in('server'), 'ban');
		$byIp   = $in('target') === 'ip';
		$steam  = $byIp ? null : amxSteamId($in('steam'));
		$ip     = $byIp ? $in('ip') : '';
		list($minutes, $reason, $errors) = $terms();
		if (!$byIp && !$steam) {
			$errors['steam'] = 'Enter a Steam ID (STEAM_0:1:123, [U:1:246], 7656…) or a profile link.';
		} elseif ($byIp && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$errors['ip'] = 'Enter an IP address, such as 203.0.113.7.';
		}
		$type = $byIp ? 'SI' : 'S';
		if (!$errors && ($bid = amxActiveBan($type, (string) $steam, $ip))) {
			$errors[$byIp ? 'ip' : 'steam'] = 'This player is already banned (ban #' . $bid . ').';
		}
		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}
		$name = mb_substr($in('name'), 0, 100);
		amxAddBan(array('type' => $type, 'authid' => (string) $steam, 'ip' => $ip, 'name' => $name, 'minutes' => $minutes, 'reason' => $reason), $row, $admin);
		amxLog('Add ban', 'nick: ' . ($name !== '' ? $name : 'Unknown') . ' <' . $steam . '><' . $ip . "> banned for $minutes minutes", $admin['name']);
		$kicked = false;
		list($listed, $players) = (string) $row['rcon'] !== '' ? amxPlayers($row) : array(false, array());
		foreach ($listed ? $players : array() as $p) {
			if ($p['kind'] === 0 && !$p['immune'] && ($byIp ? $p['ip'] === $ip : $p['authid'] === $steam)) {
				$kicked = amxKick($row, $p['userid'], amxBanMessage($minutes, $reason))[0] || $kicked;
			}
		}
		sbReply(array(
			'ok'      => true,
			'message' => ($name !== '' ? $name : ($steam ?: $ip)) . ' is banned ' . $lasting($minutes) . '.' . ($kicked ? ' They were on the server: kicked.' : ''),
			'reset'   => true,
		));

	/*
	 * Unban, from the details of a ban
	 */
	case 'amx.unban':
		$bid = (int) $in('id');
		$db->query("SELECT bid, player_nick, player_id, player_ip, server_ip, ban_created, ban_length, expired FROM " . amxTable('bans') . " WHERE bid = $bid");
		$ban = $db->fetch_array();
		if (!$ban) {
			sbFail('This ban no longer exists. Reload the page.');
		}
		if (!sbAmxMay('ban', (string) $ban['server_ip'])) {
			sbFail('You are not an admin of the server of this ban.');
		}
		if ((int) $ban['expired'] !== 0 || ((int) $ban['ban_length'] !== 0 && (int) $ban['ban_created'] + (int) $ban['ban_length'] * 60 <= time())) {
			sbFail('This ban is not active any more.');
		}
		$reason = mb_substr($in('reason'), 0, 240);
		if ($reason === '') {
			sbFail('Give a reason.', array('reason' => 'Give a reason.'));
		}
		amxUnban($bid, $reason, $admin);
		amxLog('Ban edit', "Unban: ID $bid (<" . $ban['player_nick'] . '> <' . $ban['player_id'] . '>)', $admin['name']);
		sbReply(array('ok' => true, 'message' => $ban['player_nick'] . ' is unbanned.', 'reload' => true));
}

sbFail('Unknown action.');
