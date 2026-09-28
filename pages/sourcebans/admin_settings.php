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

// Admins: list with the add dialog, or the full editor of one admin (?aid=)
$now     = time();
$canAdd  = sbCan('ADMIN_ADD_ADMINS');
$canEdit = sbCan('ADMIN_EDIT_ADMINS');
$canDel  = sbCan('ADMIN_DELETE_ADMINS');
$aid     = (int) ($_GET['aid'] ?? 0);

/**
 * Fields shared by the add dialog and the edit page.
 */
function sbAdminFields(array $admin, array $access, $advancedOpen)
{
?>
	<?= sbSteamField($admin['authid'] ?? '') ?>
	<label class="sba-field">
		<span class="sba-label">Name <small>used in game logs and on the ban lists</small></span>
		<input type="text" name="name" value="<?= htmlspecialchars($admin['user'] ?? '') ?>" maxlength="64" placeholder="Taken from the Steam profile">
		<span class="sba-error" data-error-for="name"></span>
	</label>
	<div class="sba-row">
		<label class="sba-field">
			<span class="sba-label">Web panel access</span>
			<select name="gid"><?= sbWebGroupOptions($admin['gid'] ?? -1) ?></select>
			<span class="sba-error" data-error-for="gid"></span>
		</label>
		<label class="sba-field">
			<span class="sba-label">In-game admin group</span>
			<select name="srv_group"><?= sbServerGroupOptions($admin['srv_group'] ?? '') ?></select>
			<span class="sba-error" data-error-for="srv_group"></span>
		</label>
	</div>
	<fieldset class="sba-field">
		<legend class="sba-label">Admin on these servers <button type="button" class="sba-link" data-sba-all>All</button></legend>
		<?= sbServerAccessField($access) ?>
	</fieldset>
	<details class="sba-more"<?= $advancedOpen ? ' open' : '' ?>>
		<summary>More permissions <small>on top of the groups</small></summary>
		<fieldset class="sba-field">
			<legend class="sba-label">Web panel</legend>
			<?= sbWebPermsField(sbWebNames((int) ($admin['extraflags'] ?? 0) & 0xFFFFFFFF)) ?>
			<span class="sba-error" data-error-for="web"></span>
		</fieldset>
		<fieldset class="sba-field">
			<legend class="sba-label">In game (SourceMod flags)</legend>
			<?= sbServerFlagsField($admin['srv_flags'] ?? '') ?>
		</fieldset>
		<label class="sba-field">
			<span class="sba-label">Immunity</span>
			<input type="number" class="sba-narrow" name="immunity" min="0" max="100" value="<?= (int) ($admin['immunity'] ?? 0) ?>">
			<small class="sba-help">0 to 100; the in-game group's immunity is used when higher.</small>
		</label>
	</details>
<?php
}

/*
 * Editor of one admin
 */
if ($aid > 0) {
	$admin = sbAdminRow($aid);
	printSectionTitle('Edit Admin');
	if (!$admin) {
		echo '<p class="sb-empty">This admin does not exist.</p>';
		return;
	}
	if (!$canEdit || (sbIsOwnerMask($admin['web']) && !sbIsOwner())) {
		echo '<p class="sb-empty">You do not have permission to edit this admin.</p>';
		return;
	}
	$db->query("SELECT srv_group_id, server_id FROM " . DB_SBPREFIX . "_admins_servers_groups WHERE admin_id = $aid");
	$access = array();
	foreach ($db->fetch_row_set() ?: array() as $row) {
		$access[] = (int) $row['server_id'] > 0 ? 's' . (int) $row['server_id'] : 'g' . (int) $row['srv_group_id'];
	}
	$ids     = sbSteamIds($admin['authid']);
	$profile = $ids ? (sbSteamProfiles(array($ids['id64']))[$ids['id64']] ?? null) : null;
?>
<div class="sba">
	<div class="sba-person">
		<?= $profile && $profile['avatar'] ? '<img class="sba-avatar is-large" src="' . htmlspecialchars($profile['avatar']) . '" alt="">' : sbAvatarInitial($admin['user'], ' is-large') ?>
		<div>
			<div class="sba-person-name"><?= htmlspecialchars($admin['user']) ?><?= sbIsOwnerMask($admin['web']) ? ' <span class="sba-badge is-owner">Owner</span>' : '' ?></div>
			<div class="sba-muted"><?= htmlspecialchars($admin['authid']) ?><?php if ($ids): ?> &middot; <a href="https://steamcommunity.com/profiles/<?= $ids['id64'] ?>" target="_blank" rel="noopener">Steam profile &#8599;</a><?php endif; ?>
				&middot; last visit <?= $admin['lastvisit'] ? sbAgo($admin['lastvisit'], $now) : 'never' ?></div>
		</div>
		<a class="sba-btn" href="?mode=sourcebans&amp;task=admin_settings">&larr; All admins</a>
	</div>
	<form class="hlstats-section hlstats-card sba-card" data-sba-action="admin.edit" novalidate>
		<input type="hidden" name="id" value="<?= $aid ?>">
		<?php sbAdminFields($admin, $access, $admin['extraflags'] || $admin['srv_flags'] !== '' || $admin['immunity']); ?>
		<div class="sba-form-foot">
			<span class="sba-form-error" role="alert"></span>
<?php if ($canDel && $aid !== sbAdmin()['aid'] && !sbIsOwnerMask($admin['web'])): ?>
			<button type="button" class="sba-btn is-danger" data-sba-do="admin.delete" data-sba-id="<?= $aid ?>" data-sba-then="?mode=sourcebans&amp;task=admin_settings"
				data-sba-confirm="<?= htmlspecialchars('Remove ' . $admin['user'] . ' from the admins?') ?>">Delete admin</button>
<?php endif; ?>
			<button type="submit" class="sba-btn is-primary">Save changes</button>
		</div>
	</form>
</div>
<?php
	return;
}

/*
 * List (without STEAM_ID_SERVER, the console account the plugins ban with), searched and paginated here
 */
$search = sbSearch();
$from   = DB_SBPREFIX . "_admins AS a LEFT JOIN " . DB_SBPREFIX . "_groups AS wg ON wg.gid = a.gid AND wg.type = 1";
$where  = "a.aid > 0 AND a.authid <> 'STEAM_ID_SERVER'";
if ($search !== '') {
	$like  = sbLike($search);
	$match = array("a.user LIKE $like", "a.authid LIKE $like", "wg.name LIKE $like", "a.srv_group LIKE $like");
	if ($ids = sbSteamIds($search)) {
		$match[] = 'a.authid IN (' . sbSteamMatch($ids) . ')';
	}
	$where .= ' AND (' . implode(' OR ', $match) . ')';
}
$db->query("SELECT COUNT(*) FROM $from WHERE $where");
list($total) = $db->fetch_row();
$total = (int) $total;
$page  = sbPage('page_admins', $total);
$count = $total . ($total === 1 ? ' admin' : ' admins');

$db->query("
	SELECT
		a.aid, a.user, a.authid, a.gid, a.extraflags, a.immunity, a.srv_group, a.srv_flags, a.lastvisit,
		wg.name AS wgname, wg.flags AS wgflags,
		sg.flags AS sgflags, sg.immunity AS sgimmunity
	FROM
		$from
		LEFT JOIN " . DB_SBPREFIX . "_srvgroups AS sg ON sg.name = a.srv_group
	WHERE
		$where
	ORDER BY
		a.user,
		a.aid
	" . sbLimit($page)
);
$admins = $db->fetch_row_set() ?: array();

// Where the admins of this page are admins: server group and server names
$access = array();
if ($admins) {
	$db->query("
		SELECT asg.admin_id, g.name AS group_name, asg.server_id
		FROM " . DB_SBPREFIX . "_admins_servers_groups AS asg
		LEFT JOIN " . DB_SBPREFIX . "_groups AS g ON g.gid = asg.srv_group_id AND g.type = 3
		WHERE asg.admin_id IN (" . implode(',', array_map('intval', array_column($admins, 'aid'))) . ")
	");
	foreach ($db->fetch_row_set() ?: array() as $row) {
		$name = $row['group_name'] !== null ? $row['group_name'] . ' (group)' : ((int) $row['server_id'] > 0 ? (sbServerName($row['server_id']) ?: 'Server #' . (int) $row['server_id']) : null);
		if ($name !== null) {
			$access[(int) $row['admin_id']][] = $name;
		}
	}
}

$ids64 = array();
foreach ($admins as $row) {
	if ($ids = sbSteamIds($row['authid'])) {
		$ids64[$row['aid']] = $ids['id64'];
	}
}
$profiles = sbSteamProfiles(array_values($ids64));

// A search or another page reloads only #sba-admins
if (!is_ajax()) {
	printSectionTitle('Admins');
?>
<div class="sba">
	<div class="sba-toolbar">
		<input type="search" class="sba-search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, Steam ID or group" aria-label="Search admins" data-sba-search="sba-admins" data-sba-page="page_admins">
		<span class="sba-count" data-sba-count-of="sba-admins"><?= $count ?></span>
<?php if ($canAdd): ?>
		<button type="button" class="sba-btn is-primary" data-sba-open="sba-admin-add">+ Add admin</button>
<?php endif; ?>
	</div>
	<div id="sba-admins">
<?php } ?>
	<div class="responsive-table">
	<table class="sba-table" data-sba-count="<?= $count ?>">
		<thead>
		<tr>
			<th class="left">Admin</th>
			<th class="left">Web panel</th>
			<th class="left hide-1">In game</th>
			<th class="left hide-2">Servers</th>
			<th class="hide">Last visit</th>
			<th></th>
		</tr>
		</thead>
		<tbody>
<?php
foreach ($admins as $row):
	$web     = ((int) $row['extraflags'] | (int) $row['wgflags']) & 0xFFFFFFFF;
	$extra   = count(sbWebNames((int) $row['extraflags'] & 0xFFFFFFFF));
	$flags   = sbCleanFlags($row['srv_flags'] . $row['sgflags']);
	$profile = $profiles[$ids64[$row['aid']] ?? ''] ?? null;
	$owner   = sbIsOwnerMask($web);
	$places  = $access[(int) $row['aid']] ?? array();
	$perms   = $extra . ' permission' . ($extra > 1 ? 's' : '');
?>
		<tr>
			<td class="left">
				<div class="sba-who">
					<?= $profile && $profile['avatar'] ? '<img class="sba-avatar" src="' . htmlspecialchars($profile['avatar']) . '" alt="" loading="lazy">' : sbAvatarInitial($row['user']) ?>
					<div>
						<div class="hlstats-name"><?= htmlspecialchars($row['user']) ?><?= $owner ? ' <span class="sba-badge is-owner">Owner</span>' : '' ?></div>
						<div class="sba-muted"><?= htmlspecialchars($row['authid']) ?></div>
					</div>
				</div>
			</td>
			<td class="left"><?= $row['wgname'] !== null
				? htmlspecialchars($row['wgname']) . ($extra ? ' <span class="sba-badge" data-tooltip="' . $perms . ' of their own">+' . $extra . '</span>' : '')
				: ($extra ? $perms : '<span class="sba-muted">&ndash;</span>') ?></td>
			<td class="left hide-1">
				<?= $row['srv_group'] ? htmlspecialchars($row['srv_group']) : '' ?>
				<?= $flags !== '' ? '<code class="sba-code">' . $flags . '</code>' : ($row['srv_group'] ? '' : '<span class="sba-muted">&ndash;</span>') ?>
				<?= max((int) $row['immunity'], (int) $row['sgimmunity']) ? '<span class="sba-muted">&middot; immunity ' . max((int) $row['immunity'], (int) $row['sgimmunity']) . '</span>' : '' ?>
			</td>
			<td class="left hide-2"><?= $places ? '<span data-tooltip="' . htmlspecialchars(implode(', ', $places)) . '">' . (count($places) === 1 ? htmlspecialchars($places[0]) : count($places) . ' places') . '</span>' : '<span class="sba-muted">none</span>' ?></td>
			<td class="hide nowrap"><?= $row['lastvisit'] ? '<span data-tooltip="' . htmlspecialchars(sbDate($row['lastvisit'])) . '">' . sbAgo($row['lastvisit'], $now) . '</span>' : '<span class="sba-muted">never</span>' ?></td>
			<td class="right nowrap">
<?php if ($canEdit && (!$owner || sbIsOwner())): ?>
				<a class="sba-btn is-small" href="?mode=sourcebans&amp;task=admin_settings&amp;aid=<?= (int) $row['aid'] ?>">Edit</a>
<?php endif; ?>
<?php if ($canDel && !$owner && (int) $row['aid'] !== sbAdmin()['aid']): ?>
				<button type="button" class="sba-btn is-small is-danger" data-sba-do="admin.delete" data-sba-id="<?= (int) $row['aid'] ?>"
					data-sba-confirm="<?= htmlspecialchars('Remove ' . $row['user'] . ' from the admins?') ?>">Delete</button>
<?php endif; ?>
			</td>
		</tr>
<?php endforeach; if (!$admins): ?>
		<tr><td class="left sba-muted" colspan="6"><?= $search !== '' ? 'No admin matches this search.' : 'No admin yet.' ?></td></tr>
<?php endif; ?>
		</tbody>
	</table>
	</div>
	<?= Pagination($total, $page, SB_PER_PAGE, 'page_admins', true, 'sba-admins') ?>
<?php
if (is_ajax()) {
	exit;
}
?>
	</div>
</div>

<?php if ($canAdd): ?>
<dialog id="sba-admin-add" class="sba-dialog">
	<form data-sba-action="admin.add" novalidate>
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Add admin</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<?php sbAdminFields(array(), array(), false); ?>
		</div>
		<div class="sba-form-foot">
			<span class="sba-form-error" role="alert"></span>
			<button type="button" class="sba-btn" data-sba-close>Cancel</button>
			<button type="submit" class="sba-btn is-primary">Add admin</button>
		</div>
	</form>
</dialog>
<?php endif;
