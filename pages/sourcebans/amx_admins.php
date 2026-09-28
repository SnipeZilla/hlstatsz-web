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

// Admins of the GoldSrc servers, from AMXBans (read-only; the HLstatsZ admin manages them, Bans > AMXBans):
// who they are, how the servers recognise them, their AMX Mod X flags and their servers. Searched and paginated here.
$now    = time();
$search = sbSearch();
$where  = '1';
if ($search !== '') {
	$like  = sbLike($search);
	$match = array("a.nickname LIKE $like", "a.username LIKE $like", "a.steamid LIKE $like", "a.access LIKE $like");
	if ($ids = sbSteamIds($search)) {
		$match[] = 'a.steamid IN (' . sbSteamMatch($ids) . ')';
	}
	$where = '(' . implode(' OR ', $match) . ')';
}
$db->query("SELECT COUNT(*) FROM " . sbAmxTable('amxadmins') . " AS a WHERE $where");
list($total) = $db->fetch_row();
$total = (int) $total;
$page  = sbPage('page_amxadmins', $total);
$count = $total . ($total === 1 ? ' admin' : ' admins');

$db->query("
	SELECT
		a.id, a.username, a.nickname, a.steamid, a.access, a.flags, a.created, a.expired, a.days
	FROM
		" . sbAmxTable('amxadmins') . " AS a
	WHERE
		$where
	ORDER BY
		a.nickname,
		a.username,
		a.id
	" . sbLimit($page)
);
$admins = $db->fetch_row_set() ?: array();

// Their servers (with the flags a server gives instead, if any), and who is a SourceBans admin too
$servers = $sourcebans = array();
if ($admins) {
	$amxServers = sbAmxServers();
	$db->query("SELECT admin_id, server_id, custom_flags FROM " . sbAmxTable('admins_servers') . " WHERE admin_id IN (" . implode(',', array_map('intval', array_column($admins, 'id'))) . ")");
	foreach ($db->fetch_row_set() ?: array() as $row) {
		$server = $amxServers[(int) $row['server_id']] ?? null;
		$custom = trim((string) $row['custom_flags']);
		$servers[(int) $row['admin_id']][] = ($server ? $server['label'] : 'Server #' . (int) $row['server_id']) . ($custom !== '' ? " (flags $custom)" : '');
	}
	$steam = array();
	foreach ($admins as $row) {
		if ($ids = sbSteamIds($row['steamid'])) {
			$steam[] = sbSteamMatch($ids);
		}
	}
	if ($steam) {
		$db->query("SELECT authid, user FROM " . DB_SBPREFIX . "_admins WHERE aid > 0 AND authid IN (" . implode(', ', $steam) . ")");
		foreach ($db->fetch_row_set() ?: array() as $row) {
			$sourcebans[sbSteamIds($row['authid'])['steam2'] ?? ''] = $row['user'];
		}
	}
}

// How the servers recognise an admin, from the AMX Mod X account flags
$loginBy = function ($flags) {
	$flags = (string) $flags;
	$by    = strpos($flags, 'c') !== false ? 'Steam ID' : (strpos($flags, 'd') !== false ? 'IP address' : (strpos($flags, 'b') !== false ? 'Clan tag' : 'Name'));
	return $by . (strpos($flags, 'e') === false ? ' + password' : '');
};

// A search or another page reloads only #sba-amx-admins
if (!is_ajax()) {
	printSectionTitle('AMXBans Admins');
?>
<div class="sba">
	<p class="sba-intro">Admins of the GoldSrc servers, as AMXBans has them; they are managed in the HLstatsZ admin, under <a href="?mode=admin&amp;task=tools_amxadmins">Bans &rsaquo; AMXBans</a>. Their flags are the AMX Mod X ones: there <b>a</b> is immunity and <b>z</b> means no admin rights.</p>
	<div class="sba-toolbar">
		<input type="search" class="sba-search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, Steam ID or flags" aria-label="Search AMXBans admins" data-sba-search="sba-amx-admins" data-sba-page="page_amxadmins">
		<span class="sba-count" data-sba-count-of="sba-amx-admins"><?= $count ?></span>
	</div>
	<div id="sba-amx-admins">
<?php } ?>
	<div class="responsive-table">
	<table class="sba-table" data-sba-count="<?= $count ?>">
		<thead>
		<tr>
			<th class="left">Admin</th>
			<th class="left hide-1">Recognised by</th>
			<th class="left">Flags</th>
			<th class="left hide-2">Servers</th>
			<th class="hide">Expires</th>
		</tr>
		</thead>
		<tbody>
<?php
foreach ($admins as $row):
	$ids     = sbSteamIds($row['steamid']);
	$name    = trim((string) $row['nickname']) !== '' ? trim($row['nickname']) : trim((string) $row['username']);
	$active  = (int) $row['days'] === 0 || (int) $row['expired'] > $now;   // as the AMXBans plugin loads them
	$places  = $servers[(int) $row['id']] ?? array();
	$flags   = preg_replace('/[^a-z]/', '', strtolower((string) $row['access']));
	$meaning = array_map(function ($flag) { return SB_AMX_FLAGS[$flag] ?? $flag; }, str_split($flags));
	$sbAdmin = $ids ? ($sourcebans[$ids['steam2']] ?? null) : null;
?>
		<tr class="<?= $active ? '' : 'sba-off' ?>">
			<td class="left">
				<div class="hlstats-name"><?= htmlspecialchars($name) ?><?= $sbAdmin !== null ? ' <span class="sba-badge is-ok" data-tooltip="' . htmlspecialchars('SourceBans admin ' . $sbAdmin) . '">SourceBans</span>' : '' ?></div>
<?php if (trim((string) $row['username']) !== '' && trim($row['username']) !== $name): ?>
				<div class="sba-muted"><?= htmlspecialchars($row['username']) ?></div>
<?php endif; ?>
			</td>
			<td class="left hide-1"><?= htmlspecialchars((string) $row['steamid']) ?> <span class="sba-muted">&middot; <?= $loginBy($row['flags']) ?></span></td>
			<td class="left"><?= $flags !== '' ? '<code class="sba-code" data-tooltip="' . htmlspecialchars(implode(', ', $meaning)) . '">' . $flags . '</code>' : '<span class="sba-muted">&ndash;</span>' ?></td>
			<td class="left hide-2"><?= $places ? '<span data-tooltip="' . htmlspecialchars(implode(', ', $places)) . '">' . (count($places) === 1 ? htmlspecialchars($places[0]) : count($places) . ' servers') . '</span>' : '<span class="sba-muted">none</span>' ?></td>
			<td class="hide nowrap"><?= (int) $row['days'] === 0 ? '<span class="sba-muted">never</span>'
				: ($active ? '<span data-tooltip="' . htmlspecialchars(sbDate($row['expired'])) . '">' . sbDuration((int) $row['expired'] - $now) . ' left</span>'
				: '<span class="sba-badge is-danger" data-tooltip="' . htmlspecialchars(sbDate($row['expired'])) . '">expired</span>') ?></td>
		</tr>
<?php endforeach; if (!$admins): ?>
		<tr><td class="left sba-muted" colspan="5"><?= $search !== '' ? 'No admin matches this search.' : 'AMXBans has no admin.' ?></td></tr>
<?php endif; ?>
		</tbody>
	</table>
	</div>
	<?= Pagination($total, $page, SB_PER_PAGE, 'page_amxadmins', true, 'sba-amx-admins') ?>
<?php
if (is_ajax()) {
	exit;
}
?>
	</div>
</div>
