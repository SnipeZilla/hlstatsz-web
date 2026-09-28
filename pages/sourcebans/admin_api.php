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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	sbFail('Send the request as a POST.');
}
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
// AMXBans (GoldSrc servers): the visitor's rights on its servers decide, not SourceBans' (amx_api.php)
if (strncmp($action, 'amx.', 4) === 0) {
	require __DIR__ . '/amx_api.php';
}
$admin = sbAdmin();
if (!$admin) {
	sbFail('Sign in with Steam as a SourceBans admin.');
}
if (!is_string($_POST['token'] ?? null) || !hash_equals(sbToken(), $_POST['token'])) {
	sbFail('Your session has expired. Reload the page.');
}
// RCON follow-ups for several servers run in parallel
session_write_close();

$id     = (int) ($_POST['id'] ?? 0);
$sid    = (int) ($_POST['sid'] ?? 0);
$now    = time();
$P      = DB_SBPREFIX;

// Trimmed string field, or $default when missing
$in = function ($key, $default = '') {
	return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : $default;
};
// Array field of strings
$list = function ($key) {
	return array_values(array_filter((array) ($_POST[$key] ?? array()), 'is_string'));
};
$denied = function () {
	sbFail('You do not have permission to do that.');
};
$q = function ($sql) use ($db) {
	$db->query($sql);
};
$e = function ($value) use ($db) {
	return "'" . $db->escape((string) $value) . "'";
};

switch ($action) {

	/*
	 * Steam profile preview
	 */
	case 'steam.lookup':
		$ids = sbResolveSteam($in('steam'));
		if (!$ids) {
			sbFail('Enter a Steam ID or a Steam profile link.');
		}
		$profile = sbSteamProfiles(array($ids['id64']))[$ids['id64']] ?? null;
		$match   = sbSteamMatch($ids);
		$q("SELECT user FROM {$P}_admins WHERE aid > 0 AND authid IN ($match) LIMIT 1");
		$existing = $db->fetch_row();
		$q("SELECT COUNT(*) FROM {$P}_bans WHERE authid IN ($match) AND RemoveType IS NULL AND (length = 0 OR ends > $now)");
		list($banned) = $db->fetch_row();
		sbReply(array(
			'ok'     => true,
			'ids'    => $ids,
			'name'   => $profile['name'] ?? '',
			'avatar' => $profile['avatar'] ?? '',
			'admin'  => $existing ? $existing[0] : '',
			'banned' => (int) $banned,
		));

	/*
	 * Admins
	 */
	case 'admin.add':
	case 'admin.edit':
		$edit = $action === 'admin.edit';
		if (!sbCan($edit ? 'ADMIN_EDIT_ADMINS' : 'ADMIN_ADD_ADMINS')) {
			$denied();
		}
		$target = null;
		if ($edit) {
			$target = sbAdminRow($id);
			if (!$target) {
				sbFail('This admin no longer exists. Reload the page.');
			}
			if (sbIsOwnerMask($target['web']) && !sbIsOwner()) {
				sbFail('Only an owner can edit an owner.');
			}
		}

		$errors = array();
		$ids    = sbResolveSteam($in('steam'));
		if (!$ids) {
			$errors['steam'] = 'Enter a Steam ID or a Steam profile link.';
		} else {
			$q("SELECT aid, user FROM {$P}_admins WHERE aid > 0 AND authid IN (" . sbSteamMatch($ids) . ") AND aid <> " . (int) $id . " LIMIT 1");
			if ($other = $db->fetch_array()) {
				$errors['steam'] = 'Admin ' . $other['user'] . ' already uses this Steam ID.';
			}
		}

		$name = $in('name');
		if ($name === '' && $ids) {
			$name = sbUniqueAdminName(sbSteamProfiles(array($ids['id64']))[$ids['id64']]['name'] ?? '', $id);
		}
		if ($name === '') {
			$errors['name'] = 'Enter a name for this admin.';
		} elseif (mb_strlen($name) > 64) {
			$errors['name'] = 'Use 64 characters at most.';
		} elseif (strpos($name, "'") !== false) {
			$errors['name'] = 'A name cannot contain an apostrophe (\').';
		} else {
			$q("SELECT 1 FROM {$P}_admins WHERE user = " . $e($name) . " AND aid <> " . (int) $id);
			if ($db->num_rows() > 0) {
				$errors['name'] = 'Another admin already has this name.';
			}
		}

		$gid = (int) $in('gid', '-1');
		if ($gid > 0) {
			$q("SELECT flags FROM {$P}_groups WHERE gid = $gid AND type = 1");
			$group = $db->fetch_row();
			if (!$group) {
				$errors['gid'] = 'This web group no longer exists.';
			} elseif (sbIsOwnerMask((int) $group[0]) && !sbIsOwner()) {
				$errors['gid'] = 'Only an owner can give the rights of this group.';
			}
		} else {
			$gid = -1;
		}

		$web = array_values(array_intersect($list('web'), array_keys(sbWebFlags())));
		if (in_array('ADMIN_OWNER', $web) && !sbIsOwner()) {
			$errors['web'] = 'Only an owner can make someone an owner.';
		}

		$srvGroup   = $in('srv_group');
		$srvGroupId = -1;
		if ($srvGroup !== '') {
			$q("SELECT id FROM {$P}_srvgroups WHERE name = " . $e($srvGroup));
			$row = $db->fetch_row();
			if (!$row) {
				$errors['srv_group'] = 'This server group no longer exists.';
			} else {
				$srvGroupId = (int) $row[0];
			}
		}
		$srvFlags = sbCleanFlags(implode('', $list('srv')));
		$immunity = max(0, min(100, (int) $in('immunity', '0')));
		list($accessGroups, $accessServers) = sbParseAccess($list('access'));

		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}

		$before = $edit ? sbAdminServerIds($id) : array();
		$fields = 'user = ' . $e($name) . ', authid = ' . $e($ids['steam2']) . ", gid = $gid, extraflags = " . sbSigned32(sbWebMask($web))
			. ", immunity = $immunity, srv_group = " . ($srvGroup === '' ? 'NULL' : $e($srvGroup)) . ', srv_flags = ' . $e($srvFlags);
		if ($edit) {
			$q("UPDATE {$P}_admins SET $fields WHERE aid = $id");
			$q("DELETE FROM {$P}_admins_servers_groups WHERE admin_id = $id");
		} else {
			// Steam sign-in only: the password is random and never shown
			$q("INSERT INTO {$P}_admins SET $fields, password = " . $e(password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT)) . ", email = ''");
			$id = (int) $db->insert_id();
		}
		// Access rows as SourceBans++ writes them: a server group (server_id -1) or a single server (srv_group_id -1)
		foreach ($accessGroups as $group) {
			$q("INSERT INTO {$P}_admins_servers_groups (admin_id, group_id, srv_group_id, server_id) VALUES ($id, $srvGroupId, $group, -1)");
		}
		foreach ($accessServers as $server) {
			$q("INSERT INTO {$P}_admins_servers_groups (admin_id, group_id, srv_group_id, server_id) VALUES ($id, $srvGroupId, -1, $server)");
		}

		sbLog('m', $edit ? 'Admin Updated' : 'Admin Added', "Admin ($name) has been " . ($edit ? 'updated' : 'added') . '.');
		sbReply(array(
			'ok'      => true,
			'message' => $edit ? "$name has been updated." : "$name is now an admin.",
			'tasks'   => sbRehashTasks(array_merge($before, sbAdminServerIds($id))),
			'reload'  => true,
		));

	case 'admin.delete':
		if (!sbCan('ADMIN_DELETE_ADMINS')) {
			$denied();
		}
		$target = sbAdminRow($id);
		if (!$target) {
			sbFail('This admin no longer exists. Reload the page.');
		}
		if ($target['aid'] === $admin['aid']) {
			sbFail('You cannot delete your own account.');
		}
		if (sbIsOwnerMask($target['web'])) {
			sbFail('An owner cannot be deleted. Remove the owner rights first.');
		}
		$before = sbAdminServerIds($id);
		$q("DELETE FROM {$P}_admins WHERE aid = $id LIMIT 1");
		$q("DELETE FROM {$P}_admins_servers_groups WHERE admin_id = $id");
		sbLog('m', 'Admin Deleted', "Admin ({$target['user']}) has been deleted.");
		sbReply(array('ok' => true, 'message' => "{$target['user']} is no longer an admin.", 'tasks' => sbRehashTasks($before), 'reload' => true));

	/*
	 * Groups: web admin groups (sb_groups type 1), server admin groups (sb_srvgroups), server groups (sb_groups type 3)
	 */
	case 'group.web.save':
	case 'group.srv.save':
	case 'group.servers.save':
		$kind = explode('.', $action)[1];
		$edit = $id > 0;
		if (!sbCan($edit ? 'ADMIN_EDIT_GROUPS' : 'ADMIN_ADD_GROUP')) {
			$denied();
		}
		$errors = array();
		$name   = $in('name');
		if ($name === '') {
			$errors['name'] = 'Enter a name for this group.';
		} elseif (mb_strlen($name) > 120) {
			$errors['name'] = 'Use 120 characters at most.';
		} elseif (strpos($name, ',') !== false) {
			$errors['name'] = 'A group name cannot contain a comma.';
		} else {
			// SourceBans++ keeps names unique across both group tables
			$q("SELECT 1 FROM {$P}_groups WHERE name = " . $e($name) . ($kind !== 'srv' ? " AND gid <> $id" : ''));
			$taken = $db->num_rows() > 0;
			$q("SELECT 1 FROM {$P}_srvgroups WHERE name = " . $e($name) . ($kind === 'srv' ? " AND id <> $id" : ''));
			if ($taken || $db->num_rows() > 0) {
				$errors['name'] = 'Another group already has this name.';
			}
		}

		if ($kind === 'web') {
			$type = 1;
			$web  = array_values(array_intersect($list('web'), array_keys(sbWebFlags())));
			if ($edit) {
				$q("SELECT flags FROM {$P}_groups WHERE gid = $id AND type = 1");
				$row = $db->fetch_row();
				if (!$row) {
					sbFail('This group no longer exists. Reload the page.');
				}
				if (sbIsOwnerMask((int) $row[0]) && !sbIsOwner()) {
					sbFail('Only an owner can edit a group with owner rights.');
				}
			}
			if (in_array('ADMIN_OWNER', $web) && !sbIsOwner()) {
				$errors['web'] = 'Only an owner can give owner rights.';
			}
			if ($errors) {
				sbFail('Check the highlighted fields.', $errors);
			}
			$mask = sbSigned32(sbWebMask($web));
			$q($edit
				? "UPDATE {$P}_groups SET name = " . $e($name) . ", flags = $mask WHERE gid = $id"
				: "INSERT INTO {$P}_groups (type, name, flags) VALUES (1, " . $e($name) . ", $mask)");
			$servers = array();
		} elseif ($kind === 'srv') {
			$flags    = sbCleanFlags(implode('', $list('srv')));
			$immunity = max(0, min(100, (int) $in('immunity', '0')));
			if ($edit) {
				$q("SELECT name FROM {$P}_srvgroups WHERE id = $id");
				$row = $db->fetch_row();
				if (!$row) {
					sbFail('This group no longer exists. Reload the page.');
				}
				$oldName = $row[0];
			}
			if ($errors) {
				sbFail('Check the highlighted fields.', $errors);
			}
			if ($edit) {
				$q("UPDATE {$P}_srvgroups SET name = " . $e($name) . ', flags = ' . $e($flags) . ", immunity = $immunity WHERE id = $id");
				// Admins point at their server group by name
				$q("UPDATE {$P}_admins SET srv_group = " . $e($name) . ' WHERE srv_group = ' . $e($oldName));
			} else {
				$q("INSERT INTO {$P}_srvgroups (flags, immunity, name, groups_immune) VALUES (" . $e($flags) . ", $immunity, " . $e($name) . ", ' ')");
			}
			$servers = sbRconServerIds();
		} else {
			list(, $members) = sbParseAccess(array_map(function ($sid) { return 's' . $sid; }, $list('servers')));
			if ($edit) {
				$q("SELECT 1 FROM {$P}_groups WHERE gid = $id AND type = 3");
				if ($db->num_rows() === 0) {
					sbFail('This group no longer exists. Reload the page.');
				}
			}
			if ($errors) {
				sbFail('Check the highlighted fields.', $errors);
			}
			$q("SELECT server_id FROM {$P}_servers_groups WHERE group_id = $id");
			$previous = array_map('intval', array_column($db->fetch_row_set() ?: array(), 'server_id'));
			if ($edit) {
				$q("UPDATE {$P}_groups SET name = " . $e($name) . " WHERE gid = $id");
				$q("DELETE FROM {$P}_servers_groups WHERE group_id = $id");
			} else {
				$q("INSERT INTO {$P}_groups (type, name, flags) VALUES (3, " . $e($name) . ', 0)');
				$id = (int) $db->insert_id();
			}
			foreach ($members as $server) {
				$q("INSERT INTO {$P}_servers_groups (server_id, group_id) VALUES ($server, $id)");
			}
			$servers = array_merge($previous, $members);
		}

		sbLog('m', $edit ? 'Group Updated' : 'Group Created', "Group ($name) has been " . ($edit ? 'updated' : 'created') . '.');
		sbReply(array('ok' => true, 'message' => "Group $name has been " . ($edit ? 'saved.' : 'created.'), 'tasks' => sbRehashTasks($servers), 'reload' => true));

	case 'group.web.delete':
	case 'group.srv.delete':
	case 'group.servers.delete':
		if (!sbCan('ADMIN_DELETE_GROUPS')) {
			$denied();
		}
		$kind = explode('.', $action)[1];
		if ($kind === 'srv') {
			$q("SELECT name FROM {$P}_srvgroups WHERE id = $id");
			$row = $db->fetch_row();
			if (!$row) {
				sbFail('This group no longer exists. Reload the page.');
			}
			$name = $row[0];
			$q("UPDATE {$P}_admins SET srv_group = NULL WHERE srv_group = " . $e($name));
			$q("DELETE FROM {$P}_srvgroups WHERE id = $id");
			$q("DELETE FROM {$P}_srvgroups_overrides WHERE group_id = $id");
			$servers = sbRconServerIds();
		} else {
			$q("SELECT name, flags FROM {$P}_groups WHERE gid = $id AND type = " . ($kind === 'web' ? 1 : 3));
			$row = $db->fetch_row();
			if (!$row) {
				sbFail('This group no longer exists. Reload the page.');
			}
			$name = $row[0];
			if ($kind === 'web') {
				if (sbIsOwnerMask((int) $row[1]) && !sbIsOwner()) {
					sbFail('Only an owner can delete a group with owner rights.');
				}
				$q("UPDATE {$P}_admins SET gid = -1 WHERE gid = $id");
				$servers = array();
			} else {
				$q("SELECT server_id FROM {$P}_servers_groups WHERE group_id = $id");
				$servers = array_map('intval', array_column($db->fetch_row_set() ?: array(), 'server_id'));
				$q("DELETE FROM {$P}_servers_groups WHERE group_id = $id");
				$q("DELETE FROM {$P}_admins_servers_groups WHERE srv_group_id = $id");
			}
			$q("DELETE FROM {$P}_groups WHERE gid = $id");
		}
		sbLog('m', 'Group Deleted', "Group ($name) has been deleted.");
		sbReply(array('ok' => true, 'message' => "Group $name has been deleted.", 'tasks' => sbRehashTasks($servers), 'reload' => true));

	/*
	 * Servers
	 */
	case 'server.save':
		$edit = $id > 0;
		if (!sbCan($edit ? 'ADMIN_EDIT_SERVERS' : 'ADMIN_ADD_SERVER')) {
			$denied();
		}
		$errors = array();
		$rcon   = is_string($_POST['rcon'] ?? null) ? $_POST['rcon'] : '';
		if (strlen($rcon) > 64) {
			$errors['rcon'] = 'Use 64 characters at most.';
		}

		// One of the HLstatsZ servers, or an address running one of the HLstatsZ games
		if (!$edit && $in('source') === 'hlz') {
			$field = 'hlz';
			$hlz   = sbHlzServers()[(int) $in('hlz')] ?? null;
			if (!$hlz || $hlz['goldsrc']) {
				sbFail('Check the highlighted fields.', array('hlz' => 'Choose one of the HLstatsZ servers.'));
			}
			$ip    = $hlz['address'];
			$port  = (int) $hlz['port'];
			$game  = $hlz['game'];
			$label = $hlz['name'] !== '' ? $hlz['name'] : "$ip:$port";
			if ($rcon === '') {
				// The password HLstatsZ has for the server; it is read here and never sent to the browser
				$q("SELECT rcon_password FROM `" . DB_NAME . "`.hlstats_Servers WHERE serverId = " . (int) $hlz['serverId']);
				$rcon = (string) ($db->fetch_row()[0] ?? '');
				if (strlen($rcon) > 64) {
					$errors['rcon'] = 'The RCON password in HLstatsZ is longer than SourceBans takes (64 characters). Type one here.';
				}
			}
		} else {
			$field = 'ip';
			$ip    = $in('ip');
			$port  = (int) $in('port', '27015');
			$game  = $in('game');
			$label = "$ip:$port";
			if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^(?=.{1,64}$)([a-z0-9-]+\.)+[a-z]{2,}$/i', $ip)) {
				$errors['ip'] = 'Enter an IP address or a host name.';
			}
			if ($port < 1 || $port > 65535) {
				$errors['port'] = 'Enter a port between 1 and 65535.';
			}
		}
		$hlzGame = sbHlzGames()[$game] ?? null;
		if (!$hlzGame || $hlzGame['goldsrc']) {
			$errors[$field === 'hlz' ? 'hlz' : 'game'] = $hlzGame ? 'GoldSrc servers use AMXBans, not SourceBans.' : 'Choose the game this server runs.';
		}
		if (!isset($errors[$field]) && !isset($errors['port'])) {
			$q("SELECT sid FROM {$P}_servers WHERE ip = " . $e($ip) . " AND port = $port AND sid <> $id");
			if ($db->num_rows() > 0) {
				$errors[$field] = 'This server is already in the list.';
			}
		}
		$enabled = $in('enabled') === '1' ? 1 : 0;
		$groups  = sbParseAccess(array_map(function ($gid) { return 'g' . $gid; }, $list('groups')))[0];
		if ($edit) {
			$q("SELECT 1 FROM {$P}_servers WHERE sid = $id");
			if ($db->num_rows() === 0) {
				sbFail('This server no longer exists. Reload the page.');
			}
		}
		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}

		// The SourceBans mod of the game, added the first time a server runs it
		$modid  = sbModForGame($game);
		$fields = 'ip = ' . $e($ip) . ", port = $port, modid = $modid, enabled = $enabled";
		if ($edit) {
			// A blank RCON password keeps the current one
			$q("UPDATE {$P}_servers SET $fields" . ($rcon !== '' ? ', rcon = ' . $e($rcon) : '') . " WHERE sid = $id");
			$q("DELETE FROM {$P}_servers_groups WHERE server_id = $id");
		} else {
			$q("INSERT INTO {$P}_servers SET $fields, rcon = " . $e($rcon));
			$id = (int) $db->insert_id();
		}
		foreach ($groups as $group) {
			$q("INSERT INTO {$P}_servers_groups (server_id, group_id) VALUES ($id, $group)");
		}
		sbLog('m', $edit ? 'Server Updated' : 'Server Added', "Server ($ip:$port) has been " . ($edit ? 'updated' : 'added') . '.');
		sbReply(array('ok' => true, 'message' => "$label has been " . ($edit ? 'saved.' : 'added.'), 'reload' => true));

	case 'server.delete':
		if (!sbCan('ADMIN_DELETE_SERVERS')) {
			$denied();
		}
		$q("SELECT ip, port FROM {$P}_servers WHERE sid = $id");
		$server = $db->fetch_array();
		if (!$server) {
			sbFail('This server no longer exists. Reload the page.');
		}
		$q("DELETE FROM {$P}_servers WHERE sid = $id");
		$q("DELETE FROM {$P}_servers_groups WHERE server_id = $id");
		$q("UPDATE {$P}_admins_servers_groups SET server_id = -1 WHERE server_id = $id");
		sbLog('m', 'Server Deleted', "Server ({$server['ip']}:{$server['port']}) has been deleted.");
		// Named as in the list: the HLstatsZ server at this address, if any
		$label = "{$server['ip']}:{$server['port']}";
		foreach (sbHlzServers() as $row) {
			if ($row['address'] . ':' . $row['port'] === $label && $row['name'] !== '') {
				$label = $row['name'];
				break;
			}
		}
		sbReply(array('ok' => true, 'message' => "$label has been deleted.", 'reload' => true));

	case 'server.test':
		if (!sbCan('ADMIN_LIST_SERVERS', 'ADMIN_ADD_SERVER', 'ADMIN_EDIT_SERVERS')) {
			$denied();
		}
		list($ok, $output) = sbRcon($id, 'status');
		if (!$ok) {
			sbFail($output);
		}
		$info  = sbStatusInfo($output);
		$parts = array_filter(array($info['host'], $info['map'], $info['players'] !== null ? $info['players'] . ' players' : ''));
		sbReply(array('ok' => true, 'message' => 'RCON works' . ($parts ? ': ' . implode(' · ', $parts) : '.')));

	// Who is on a server, for the ban and block buttons of the Servers list
	case 'server.players':
		if (!sbCan('ADMIN_ADD_BAN')) {
			$denied();
		}
		$found = sbServerPlayers($sid);
		if (!$found[0]) {
			sbFail($found[1]);
		}
		sbReply(array('ok' => true, 'html' => sbPlayersPanel($sid, $found[1], $found[2])));

	/*
	 * Settings
	 */
	case 'settings.save':
		if (!sbCan('ADMIN_WEB_SETTINGS')) {
			$denied();
		}
		$errors = array();
		$values = array();
		foreach (SB_SETTINGS as $key => $setting) {
			$field = str_replace('.', '_', $key);
			if ($setting['type'] === 'bool') {
				$values[$key] = $in($field) === '1' ? '1' : '0';
			} elseif ($setting['type'] === 'int') {
				$value = $in($field);
				if (!ctype_digit($value) || (int) $value < $setting['min'] || (int) $value > $setting['max']) {
					$errors[$field] = "Enter a number from {$setting['min']} to {$setting['max']}.";
				}
				$values[$key] = (string) (int) $value;
			} elseif ($setting['type'] === 'lines') {
				$lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $in($field)))));
				$values[$key] = $lines ? serialize($lines) : '';
			} else {
				$values[$key] = mb_substr($in($field), 0, 255);
			}
		}
		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}
		foreach ($values as $key => $value) {
			$q("INSERT INTO {$P}_settings (setting, value) VALUES (" . $e($key) . ', ' . $e($value) . ') ON DUPLICATE KEY UPDATE value = VALUES(value)');
		}
		sbLog('m', 'Settings Updated', 'The web panel settings have been updated.');
		sbReply(array('ok' => true, 'message' => 'Settings saved.', 'reload' => true));

	/*
	 * Bans and comm blocks
	 */
	case 'ban.add':
	case 'comm.add':
		if (!sbCan('ADMIN_ADD_BAN')) {
			$denied();
		}
		$comm   = $action === 'comm.add';
		$errors = array();
		$byIp   = !$comm && $in('target') === 'ip';
		$ids    = null;
		$ip     = '';
		if ($byIp) {
			$ip = $in('ip');
			if (!filter_var($ip, FILTER_VALIDATE_IP)) {
				$errors['ip'] = 'Enter an IP address.';
			}
		} else {
			$ids = sbResolveSteam($in('steam'));
			if (!$ids) {
				$errors['steam'] = 'Enter a Steam ID or a Steam profile link.';
			}
		}
		$length = $in('length');
		if (!ctype_digit($length)) {
			$errors['length'] = 'Choose a length.';
		}
		$length = (int) $length;
		$reason = $in('reason');
		if ($reason === '') {
			$errors['reason'] = 'Give a reason.';
		} elseif (mb_strlen($reason) > 500) {
			$errors['reason'] = 'Use 500 characters at most.';
		}
		$types = array(1);
		if ($comm) {
			$types = array('mute' => array(1), 'gag' => array(2), 'both' => array(1, 2))[$in('type')] ?? null;
			if (!$types) {
				$errors['type'] = 'Choose mute, gag or both.';
			}
		}
		$name = mb_substr($in('name'), 0, 128);
		if ($name === '' && $ids) {
			$name = sbSteamProfiles(array($ids['id64']))[$ids['id64']]['name'] ?? '';
		}
		if ($name === '') {
			$name = 'unnamed';
		}

		if (!$errors) {
			$active = "RemoveType IS NULL AND (length = 0 OR ends > $now)";
			if ($comm) {
				$q("SELECT type FROM {$P}_comms WHERE authid IN (" . sbSteamMatch($ids) . ") AND $active AND type IN (" . implode(',', $types) . ')');
				if ($row = $db->fetch_row()) {
					$errors['steam'] = 'This player is already ' . ((int) $row[0] === 2 ? 'gagged.' : 'muted.');
				}
			} else {
				$q("SELECT bid FROM {$P}_bans WHERE " . ($byIp ? 'type = 1 AND ip = ' . $e($ip) : 'type = 0 AND authid IN (' . sbSteamMatch($ids) . ')') . " AND $active LIMIT 1");
				if ($row = $db->fetch_row()) {
					$errors[$byIp ? 'ip' : 'steam'] = 'This player is already banned (ban #' . $row[0] . ').';
				}
			}
		}
		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}

		$seconds = $length * 60;
		$created = array();
		foreach ($types as $type) {
			$q("INSERT INTO {$P}_" . ($comm ? 'comms' : 'bans') . " SET created = $now, ends = " . ($now + $seconds) . ", length = $seconds, type = " . ($comm ? $type : ($byIp ? 1 : 0))
				. ', authid = ' . $e($ids ? $ids['steam2'] : '') . ($comm ? '' : ', ip = ' . $e($ip)) . ', name = ' . $e($name) . ', reason = ' . $e($reason)
				. ", aid = {$admin['aid']}, adminIp = " . $e($_SERVER['REMOTE_ADDR'] ?? '') . ', sid = 0');
			$created[] = (int) $db->insert_id();
		}
		$what = $comm ? array(1 => 'muted', 2 => 'gagged')[$types[0]] . (count($types) > 1 ? ' and gagged' : '') : 'banned';
		sbLog('m', $comm ? 'Block Added' : 'Ban Added', ($comm ? 'Block' : 'Ban') . ' against (' . ($ids ? $ids['steam2'] : $ip) . ") has been added. Reason: $reason; Length: $length");

		// Right away in game too: kick the banned player, apply the block (SourceBans++ does both)
		$tasks = array();
		if ($comm || sbSetting('config.enablekickit') === '1') {
			foreach (sbRconServerIds() as $server) {
				foreach ($comm ? $created : array($created[0]) as $bid) {
					$tasks[] = array('action' => $comm ? 'comm.apply' : 'ban.kick', 'id' => $bid, 'sid' => $server, 'label' => sbServerName($server) ?: "Server #$server");
				}
			}
		}
		sbReply(array(
			'ok'      => true,
			'message' => "$name has been $what" . ($length ? ' for ' . sbDuration($seconds) : ' permanently') . '.',
			'id'      => $created[0],
			'tasks'   => $tasks,
			'reset'   => true,
		));

	case 'ban.kick':
	case 'comm.apply':
		if (!sbCan('ADMIN_ADD_BAN')) {
			$denied();
		}
		$comm = $action === 'comm.apply';
		$q("SELECT authid, " . ($comm ? "'' AS ip" : 'ip') . ", type, length FROM {$P}_" . ($comm ? 'comms' : 'bans') . " WHERE bid = $id AND RemoveType IS NULL AND (length = 0 OR ends > $now)");
		$row = $db->fetch_array();
		if (!$row) {
			sbReply(array('ok' => true, 'message' => 'No longer active.'));
		}
		$found = sbServerPlayers($sid);
		if (!$found[0]) {
			sbFail($found[1]);
		}
		$ids = sbSteamIds($row['authid']);
		foreach ($found[1] as $player) {
			if (($ids && $player['steam2'] === $ids['steam2']) || (!$comm && $row['ip'] !== '' && $player['ip'] === $row['ip'])) {
				list($ok, $error) = $comm
					? sbRcon($sid, "sc_fw_block {$row['type']} " . intdiv((int) $row['length'], 60) . " {$player['steam2']}")
					: sbRcon($sid, "kickid {$player['id']} \"You have been banned by this server, check " . ($_SERVER['HTTP_HOST'] ?? 'the website') . ' for more info"');
				sbReply($ok ? array('ok' => true, 'message' => $comm ? 'Applied in game.' : 'Kicked.') : array('ok' => false, 'message' => $error));
			}
		}
		sbReply(array('ok' => true, 'message' => 'Not on this server.'));

	case 'ban.edit':
	case 'ban.unban':
	case 'ban.delete':
	case 'comm.edit':
	case 'comm.unban':
	case 'comm.delete':
		list($table, $verb) = explode('.', $action);
		$table = $table === 'comm' ? 'comms' : 'bans';
		$q("SELECT b.*, ad.gid AS admin_gid FROM {$P}_$table AS b LEFT JOIN {$P}_admins AS ad ON ad.aid = b.aid WHERE b.bid = $id");
		$row = $db->fetch_array();
		if (!$row) {
			sbFail('This entry no longer exists. Reload the page.');
		}
		if (!sbCanOnBan($row, $verb)) {
			$denied();
		}
		$label = $table === 'comms' ? 'comm block' : 'ban';

		if ($verb === 'delete') {
			if ($table === 'bans') {
				$q("DELETE FROM {$P}_banlog WHERE bid = $id");
			}
			$q("DELETE FROM {$P}_$table WHERE bid = $id");
			sbLog('m', ucfirst($label) . ' Deleted', ucfirst($label) . " #$id ({$row['name']}) has been deleted.");
			sbReply(array('ok' => true, 'message' => ucfirst($label) . ' deleted.', 'reload' => true));
		}

		$active = $row['RemoveType'] === null && ((int) $row['length'] === 0 || (int) $row['ends'] > $now);
		if ($verb === 'unban') {
			$reason = $in('reason');
			if (!$active) {
				sbFail('This ' . $label . ' is not active any more.');
			}
			if ($reason === '') {
				sbFail('Give a reason.', array('reason' => 'Give a reason.'));
			}
			$q("UPDATE {$P}_$table SET RemovedBy = {$admin['aid']}, RemoveType = 'U', RemovedOn = $now, ureason = " . $e(mb_substr($reason, 0, 500)) . " WHERE bid = $id");
			sbLog('m', $table === 'comms' ? 'Player Unblocked' : 'Player Unbanned', "{$row['name']} (" . ($row['authid'] !== '' ? $row['authid'] : ($row['ip'] ?? '')) . ') has been ' . ($table === 'comms' ? 'unblocked' : 'unbanned') . ". Reason: $reason");
			// Lift a comm block in game at once, as SourceBans++ does
			$tasks = array();
			if ($table === 'comms') {
				foreach (sbRconServerIds() as $server) {
					$tasks[] = array('action' => 'comm.lift', 'id' => $id, 'sid' => $server, 'label' => sbServerName($server) ?: "Server #$server");
				}
			}
			sbReply(array('ok' => true, 'message' => $table === 'comms' ? 'Block lifted.' : 'Player unbanned.', 'tasks' => $tasks, 'reload' => true));
		}

		// edit: name, reason and length; a longer length can make an expired entry active again
		$errors = array();
		$name   = mb_substr($in('name'), 0, 128);
		$reason = $in('reason');
		$length = $in('length');
		if ($name === '') {
			$errors['name'] = 'Enter a name.';
		}
		if ($reason === '' || mb_strlen($reason) > 500) {
			$errors['reason'] = 'Give a reason (500 characters at most).';
		}
		if (!ctype_digit($length)) {
			$errors['length'] = 'Choose a length.';
		}
		if ($errors) {
			sbFail('Check the highlighted fields.', $errors);
		}
		$seconds = (int) $length * 60;
		$ends    = (int) $row['created'] + $seconds;
		$revive  = $row['RemoveType'] === 'E' && ($seconds === 0 || $ends > $now) ? ', RemoveType = NULL, RemovedBy = NULL, RemovedOn = NULL' : '';
		$q("UPDATE {$P}_$table SET name = " . $e($name) . ', reason = ' . $e($reason) . ", length = $seconds, ends = $ends$revive WHERE bid = $id");
		sbLog('m', ucfirst($label) . ' Edited', ucfirst($label) . " #$id ({$name}) has been edited.");
		sbReply(array('ok' => true, 'message' => ucfirst($label) . ' saved.', 'reload' => true));

	case 'comm.lift':
		if (!sbCan('ADMIN_UNBAN', 'ADMIN_UNBAN_OWN_BANS', 'ADMIN_UNBAN_GROUP_BANS')) {
			$denied();
		}
		$q("SELECT authid, type FROM {$P}_comms WHERE bid = $id AND RemoveType = 'U'");
		$row = $db->fetch_array();
		if (!$row) {
			sbReply(array('ok' => true, 'message' => 'Nothing to lift.'));
		}
		list($ok, $error) = sbRcon($sid, ((int) $row['type'] === 2 ? 'sc_fw_ungag ' : 'sc_fw_unmute ') . $row['authid']);
		sbReply($ok ? array('ok' => true, 'message' => 'Lifted in game.') : array('ok' => false, 'message' => $error));

	/*
	 * Reload the admins of a server (SourceMod sm_rehash)
	 */
	case 'rehash':
		if (!sbCan('ADMIN_ADD_ADMINS', 'ADMIN_EDIT_ADMINS', 'ADMIN_DELETE_ADMINS', 'ADMIN_ADD_GROUP', 'ADMIN_EDIT_GROUPS', 'ADMIN_DELETE_GROUPS')) {
			$denied();
		}
		list($ok, $error) = sbRcon($sid, 'sm_rehash');
		sbReply($ok ? array('ok' => true, 'message' => 'Admins reloaded.') : array('ok' => false, 'message' => $error));
}

sbFail('Unknown action.');
