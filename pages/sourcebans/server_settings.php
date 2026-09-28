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

// Servers of SourceBans: address, RCON password, game and server groups. Games are the HLstatsZ games,
// and a server can be picked among the HLstatsZ servers.
$canAdd  = sbCan('ADMIN_ADD_SERVER');
$canEdit = sbCan('ADMIN_EDIT_SERVERS');
$canDel  = sbCan('ADMIN_DELETE_SERVERS');
$P       = DB_SBPREFIX;

$db->query("
	SELECT
		s.sid, s.ip, s.port, s.rcon <> '' AS has_rcon, s.modid, s.enabled,
		m.name AS modname, m.modfolder,
		GROUP_CONCAT(sg.group_id) AS groups
	FROM
		{$P}_servers AS s
		LEFT JOIN {$P}_mods AS m ON m.mid = s.modid
		LEFT JOIN {$P}_servers_groups AS sg ON sg.server_id = s.sid
	GROUP BY
		s.sid, s.ip, s.port, s.rcon, s.modid, s.enabled, m.name, m.modfolder
	ORDER BY
		m.name, s.ip, s.port
");
$servers = $db->fetch_row_set() ?: array();
$db->query("SELECT gid, name FROM {$P}_groups WHERE type = 3 ORDER BY name");
$groups = array_column($db->fetch_row_set() ?: array(), 'name', 'gid');

// The HLstatsZ server at each address (its public address counts too), for names and games
$games  = sbHlzGames();
$hlz    = sbHlzServers();
$byAddr = array();
foreach ($hlz as $row) {
	if ($row['publicaddress'] !== '') {
		$byAddr[$row['publicaddress']] = $row;
	}
}
foreach ($hlz as $row) {
	$byAddr[$row['address'] . ':' . $row['port']] = $row;
}
foreach ($servers as &$server) {
	$linked            = $byAddr[$server['ip'] . ':' . $server['port']] ?? null;
	$server['label']   = $linked && $linked['name'] !== '' ? $linked['name'] : $server['ip'] . ':' . $server['port'];
	$server['game']    = $linked ? $linked['game'] : sbGameForFolder($server['modfolder']);
	$server['hlzgame'] = $games[$server['game']]['name'] ?? '';
}
unset($server);
$taken = array_flip(array_map(function ($server) { return $server['ip'] . ':' . $server['port']; }, $servers));

// Search what the table shows (names come from HLstatsZ, so not in SQL), then a page of it
$search = sbSearch();
if ($search !== '') {
	$servers = array_values(array_filter($servers, function ($server) use ($search, $groups) {
		$member = $server['groups'] !== null ? array_intersect_key($groups, array_flip(explode(',', $server['groups']))) : array();
		$text   = implode(' ', array_merge(array($server['label'], $server['ip'] . ':' . $server['port'], (string) $server['modname'], $server['hlzgame']), $member));
		return mb_stripos($text, $search) !== false;
	}));
}
$total = count($servers);
$page  = sbPage('page_servers', $total);
$count = $total . ($total === 1 ? ' server' : ' servers');

// A search or another page reloads only #sba-servers
if (!is_ajax()) {
	printSectionTitle('Servers');
?>
<div class="sba">
	<div class="sba-toolbar">
		<input type="search" class="sba-search" value="<?= htmlspecialchars($search) ?>" placeholder="Search servers" aria-label="Search servers" data-sba-search="sba-servers" data-sba-page="page_servers">
		<span class="sba-count" data-sba-count-of="sba-servers"><?= $count ?></span>
<?php if ($canAdd): ?>
		<button type="button" class="sba-btn is-primary" data-sba-open="sba-server" data-sba-title="Add server">+ Add server</button>
<?php endif; ?>
	</div>
	<div id="sba-servers">
<?php } ?>
	<div class="responsive-table">
	<table class="sba-table" data-sba-count="<?= $count ?>">
		<thead><tr><th class="left">Server</th><th class="left hide-1">Address</th><th class="left hide">Game</th><th class="left hide-2">Groups</th><th>RCON</th><th>Status</th><th></th></tr></thead>
		<tbody>
<?php foreach (array_slice($servers, ($page - 1) * SB_PER_PAGE, SB_PER_PAGE) as $server):
	$game   = sbGame($server['sid'], $server['modfolder'], $server['modname']);
	$member = $server['groups'] !== null ? array_map('intval', explode(',', $server['groups'])) : array();
	$values = array('id' => (int) $server['sid'], 'mode' => 'edit', 'source' => 'new', 'ip' => $server['ip'], 'port' => (int) $server['port'], 'game' => $server['game'], 'enabled' => (string) $server['enabled'], 'groups[]' => $member);
?>
		<tr class="<?= $server['enabled'] ? '' : 'sba-off' ?>">
			<td class="left">
				<span class="hlstats-icon"><img src="<?= htmlspecialchars($game['icon']) ?>" alt="" data-tooltip="<?= htmlspecialchars($game['name']) ?>"></span>
				<span class="hlstats-name"><?= htmlspecialchars($server['label']) ?></span>
			</td>
			<td class="left hide-1 nowrap"><?= htmlspecialchars($server['ip'] . ':' . $server['port']) ?></td>
			<td class="left hide"><?= $server['hlzgame'] !== '' ? htmlspecialchars($server['hlzgame']) : '<span class="sba-muted" data-tooltip="' . htmlspecialchars('No HLstatsZ game for ' . $server['modname'] . '. Edit the server to pick one.') . '">' . htmlspecialchars((string) $server['modname']) . '</span>' ?></td>
			<td class="left hide-2"><?= $member ? htmlspecialchars(implode(', ', array_intersect_key($groups, array_flip($member)))) : '<span class="sba-muted">&ndash;</span>' ?></td>
			<td><?= $server['has_rcon'] ? '<span class="sba-badge is-ok">set</span>' : '<span class="sba-badge">not set</span>' ?></td>
			<td><?= $server['enabled'] ? '<span class="sba-badge is-ok">enabled</span>' : '<span class="sba-badge">disabled</span>' ?></td>
			<td class="right nowrap">
<?php if ($server['has_rcon']): ?>
				<button type="button" class="sba-btn is-small" data-sba-do="server.test" data-sba-id="<?= (int) $server['sid'] ?>" data-tooltip="Connect with RCON and read the server status">Test</button>
<?php endif; ?>
<?php if ($canEdit): ?>
				<button type="button" class="sba-btn is-small" data-sba-edit="sba-server" data-sba-title="Edit server" data-sba-values='<?= htmlspecialchars(json_encode($values), ENT_QUOTES) ?>'>Edit</button>
<?php endif; ?>
<?php if ($canDel): ?>
				<button type="button" class="sba-btn is-small is-danger" data-sba-do="server.delete" data-sba-id="<?= (int) $server['sid'] ?>"
					data-sba-confirm="<?= htmlspecialchars('Delete ' . $server['label'] . '? Its bans stay; admins lose their access to it.') ?>">Delete</button>
<?php endif; ?>
			</td>
		</tr>
<?php endforeach; if (!$servers): ?>
		<tr><td class="left sba-muted" colspan="7"><?= $search !== '' ? 'No server matches this search.' : 'No server yet.' ?></td></tr>
<?php endif; ?>
		</tbody>
	</table>
	</div>
	<?= Pagination($total, $page, SB_PER_PAGE, 'page_servers', true, 'sba-servers') ?>
<?php
if (is_ajax()) {
	exit;
}
?>
	</div>
</div>

<?php if ($canAdd || $canEdit):
	// HLstatsZ servers not in SourceBans yet (GoldSrc ones use AMXBans)
	$free = array_filter($hlz, function ($row) use ($taken) {
		return !$row['goldsrc'] && !isset($taken[$row['address'] . ':' . $row['port']]) && !isset($taken[$row['publicaddress']]);
	});
	$gameOptions = function ($hidden) use ($games) {
		$html = '';
		foreach ($games as $code => $game) {
			if (!$game['goldsrc'] && (bool) $game['hidden'] === $hidden) {
				$html .= '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($game['name']) . '</option>';
			}
		}
		return $html;
	};
?>
<dialog id="sba-server" class="sba-dialog">
	<form data-sba-action="server.save" novalidate>
		<input type="hidden" name="id" value="0">
		<input type="hidden" name="mode" value="add">
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Server</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<fieldset class="sba-field" data-sba-when="mode=add">
				<legend class="sba-label">Server</legend>
				<div class="sba-segment">
					<label><input type="radio" name="source" value="hlz"<?= $free ? ' checked' : ' disabled' ?>><span>From HLstatsZ</span></label>
					<label><input type="radio" name="source" value="new"<?= $free ? '' : ' checked' ?>><span>New server</span></label>
				</div>
<?php if (!$free): ?>
				<small class="sba-help">Every HLstatsZ server is in SourceBans already.</small>
<?php endif; ?>
			</fieldset>
			<div data-sba-when="source=hlz"<?= $free ? '' : ' hidden' ?>>
				<label class="sba-field"><span class="sba-label">HLstatsZ server</span>
					<select name="hlz">
						<option value="">Choose a server</option>
<?php foreach ($free as $serverId => $row): ?>
						<option value="<?= (int) $serverId ?>"><?= htmlspecialchars(($row['name'] !== '' ? $row['name'] : $row['address'] . ':' . $row['port']) . ' · ' . $row['address'] . ':' . $row['port'] . ' · ' . $row['gamename']) ?></option>
<?php endforeach; ?>
					</select><span class="sba-error" data-error-for="hlz"></span></label>
			</div>
			<div data-sba-when="source=new"<?= $free ? ' hidden' : '' ?>>
				<div class="sba-row">
					<label class="sba-field"><span class="sba-label">IP address or host name</span><input type="text" name="ip" required spellcheck="false"><span class="sba-error" data-error-for="ip"></span></label>
					<label class="sba-field sba-narrow"><span class="sba-label">Port</span><input type="number" name="port" min="1" max="65535" value="27015"><span class="sba-error" data-error-for="port"></span></label>
				</div>
				<label class="sba-field"><span class="sba-label">Game <small>from HLstatsZ</small></span>
					<select name="game">
						<option value="">Choose the game</option>
						<?= $gameOptions(false) ?>
<?php if ($hiddenGames = $gameOptions(true)): ?>
						<optgroup label="Hidden in HLstatsZ"><?= $hiddenGames ?></optgroup>
<?php endif; ?>
					</select><span class="sba-error" data-error-for="game"></span></label>
			</div>
			<label class="sba-field"><span class="sba-label">RCON password</span>
				<input type="password" name="rcon" maxlength="64" autocomplete="new-password">
				<small class="sba-help" data-sba-when="source=hlz"<?= $free ? '' : ' hidden' ?>>Leave it empty to use the password HLstatsZ has for this server.</small>
				<small class="sba-help" data-sba-when="mode=edit" hidden>Leave it empty to keep the current one.</small>
				<span class="sba-error" data-error-for="rcon"></span></label>
<?php if ($groups): ?>
			<fieldset class="sba-field"><legend class="sba-label">Server groups</legend>
				<div class="sba-chips">
<?php foreach ($groups as $gid => $name): ?>
					<label class="sba-chip"><input type="checkbox" name="groups[]" value="<?= (int) $gid ?>"><span><?= htmlspecialchars($name) ?></span></label>
<?php endforeach; ?>
				</div>
			</fieldset>
<?php endif; ?>
			<label class="sba-switch"><input type="checkbox" name="enabled" value="1" checked><span class="sba-switch-ui"></span><span>Enabled <small>disabled servers are ignored by the plugins and hidden here</small></span></label>
		</div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Save server</button></div>
	</form>
</dialog>
<?php endif;
