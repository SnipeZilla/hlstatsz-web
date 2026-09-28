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

// Groups: web panel groups, in-game admin groups and groups of servers, each table with its own pages
$canAdd   = sbCan('ADMIN_ADD_GROUP');
$canEdit  = sbCan('ADMIN_EDIT_GROUPS');
$canDel   = sbCan('ADMIN_DELETE_GROUPS');
$P        = DB_SBPREFIX;
$flagsWeb = sbWebFlags();
$full     = !is_ajax();   // otherwise only the table asked for is sent: #sba-web-groups, #sba-srv-groups or #sba-server-groups

// Edit and delete buttons of a row
$buttons = function ($dialog, array $values, $action, $name, $locked = false) use ($canEdit, $canDel) {
	$html = '';
	if ($canEdit && !$locked) {
		$html .= '<button type="button" class="sba-btn is-small" data-sba-edit="' . $dialog . '" data-sba-title="Edit group"'
			. " data-sba-values='" . htmlspecialchars(json_encode($values), ENT_QUOTES) . "'>Edit</button> ";
	}
	if ($canDel && !$locked) {
		$html .= '<button type="button" class="sba-btn is-small is-danger" data-sba-do="' . $action . '" data-sba-id="' . (int) $values['id'] . '"'
			. ' data-sba-confirm="' . htmlspecialchars("Delete the group $name?") . '">Delete</button>';
	}
	return $html;
};

if ($full) {
	printSectionTitle('Groups');
?>
<div class="sba">
<p class="sba-intro">Admins get their rights from a <b>web panel group</b> (what they can do on this site) and an <b>in-game group</b> (their SourceMod flags). <b>Server groups</b> bundle servers so an admin can be given all of them at once.</p>
<?php
}

/*
 * Web panel groups
 */
if ($full || sbAjaxPart('sba-web-groups')):
	$db->query("SELECT COUNT(*) FROM {$P}_groups WHERE type = 1");
	list($total) = $db->fetch_row();
	$page = sbPage('page_webgroups', (int) $total);
	$db->query("SELECT g.gid, g.name, g.flags, (SELECT COUNT(*) FROM {$P}_admins AS a WHERE a.gid = g.gid AND a.aid > 0) AS members FROM {$P}_groups AS g WHERE g.type = 1 ORDER BY g.name, g.gid " . sbLimit($page));
	$webGroups = $db->fetch_row_set() ?: array();
	if ($full):
?>
<section class="hlstats-section hlstats-card sba-card">
	<div class="sba-card-head">
		<div><div class="hlstats-card-title">Web panel groups</div><div class="sba-muted">What admins can do on this site</div></div>
<?php if ($canAdd): ?>
		<button type="button" class="sba-btn is-primary" data-sba-open="sba-group-web" data-sba-title="New web panel group">+ New group</button>
<?php endif; ?>
	</div>
	<div id="sba-web-groups">
<?php endif; ?>
	<div class="responsive-table">
	<table class="sba-table">
		<thead><tr><th class="left">Group</th><th class="left">Permissions</th><th class="hlstats-numeric">Admins</th><th></th></tr></thead>
		<tbody>
<?php foreach ($webGroups as $group):
	$mask  = (int) $group['flags'] & 0xFFFFFFFF;
	$names = sbWebNames($mask);
	$owner = sbIsOwnerMask($mask);
?>
		<tr>
			<td class="left"><span class="hlstats-name"><?= htmlspecialchars($group['name']) ?></span></td>
			<td class="left"><?= $owner ? '<span class="sba-badge is-owner">Owner</span> every permission'
				: ($names ? '<span data-tooltip="' . htmlspecialchars(implode(', ', array_map(function ($n) use ($flagsWeb) { return $flagsWeb[$n]['display']; }, $names))) . '">' . count($names) . (count($names) === 1 ? ' permission' : ' permissions') . '</span>' : '<span class="sba-muted">none</span>') ?></td>
			<td class="hlstats-numeric"><?= (int) $group['members'] ?></td>
			<td class="right nowrap"><?= $buttons('sba-group-web', array('id' => (int) $group['gid'], 'name' => $group['name'], 'web[]' => $names), 'group.web.delete', $group['name'], $owner && !sbIsOwner()) ?></td>
		</tr>
<?php endforeach; if (!$webGroups): ?>
		<tr><td class="left sba-muted" colspan="4">No web panel group yet.</td></tr>
<?php endif; ?>
		</tbody>
	</table>
	</div>
	<?= Pagination((int) $total, $page, SB_PER_PAGE, 'page_webgroups', true, 'sba-web-groups') ?>
<?php if ($full): ?>
	</div>
</section>
<?php endif; endif;

/*
 * In-game admin groups
 */
if ($full || sbAjaxPart('sba-srv-groups')):
	$db->query("SELECT COUNT(*) FROM {$P}_srvgroups");
	list($total) = $db->fetch_row();
	$page = sbPage('page_srvgroups', (int) $total);
	$db->query("SELECT s.id, s.name, s.flags, s.immunity, (SELECT COUNT(*) FROM {$P}_admins AS a WHERE a.srv_group = s.name) AS members FROM {$P}_srvgroups AS s ORDER BY s.name, s.id " . sbLimit($page));
	$srvGroups = $db->fetch_row_set() ?: array();
	if ($full):
?>
<section class="hlstats-section hlstats-card sba-card">
	<div class="sba-card-head">
		<div><div class="hlstats-card-title">In-game admin groups</div><div class="sba-muted">SourceMod flags and immunity on the servers</div></div>
<?php if ($canAdd): ?>
		<button type="button" class="sba-btn is-primary" data-sba-open="sba-group-srv" data-sba-title="New in-game group">+ New group</button>
<?php endif; ?>
	</div>
	<div id="sba-srv-groups">
<?php endif; ?>
	<div class="responsive-table">
	<table class="sba-table">
		<thead><tr><th class="left">Group</th><th class="left">Flags</th><th class="hlstats-numeric">Immunity</th><th class="hlstats-numeric">Admins</th><th></th></tr></thead>
		<tbody>
<?php foreach ($srvGroups as $group): $flags = sbCleanFlags($group['flags']); ?>
		<tr>
			<td class="left"><span class="hlstats-name"><?= htmlspecialchars($group['name']) ?></span></td>
			<td class="left"><?= $flags !== '' ? '<code class="sba-code" data-tooltip="' . htmlspecialchars(implode(', ', array_intersect_key(sbServerFlags(), array_flip(str_split($flags))))) . '">' . $flags . '</code>' : '<span class="sba-muted">none</span>' ?></td>
			<td class="hlstats-numeric"><?= (int) $group['immunity'] ?></td>
			<td class="hlstats-numeric"><?= (int) $group['members'] ?></td>
			<td class="right nowrap"><?= $buttons('sba-group-srv', array('id' => (int) $group['id'], 'name' => $group['name'], 'srv[]' => str_split($flags), 'immunity' => (int) $group['immunity']), 'group.srv.delete', $group['name']) ?></td>
		</tr>
<?php endforeach; if (!$srvGroups): ?>
		<tr><td class="left sba-muted" colspan="5">No in-game group yet.</td></tr>
<?php endif; ?>
		</tbody>
	</table>
	</div>
	<?= Pagination((int) $total, $page, SB_PER_PAGE, 'page_srvgroups', true, 'sba-srv-groups') ?>
<?php if ($full): ?>
	</div>
</section>
<?php endif; endif;

/*
 * Server groups
 */
if ($full || sbAjaxPart('sba-server-groups')):
	$db->query("SELECT COUNT(*) FROM {$P}_groups WHERE type = 3");
	list($total) = $db->fetch_row();
	$page = sbPage('page_servergroups', (int) $total);
	$db->query("SELECT g.gid, g.name, GROUP_CONCAT(sg.server_id) AS servers FROM {$P}_groups AS g LEFT JOIN {$P}_servers_groups AS sg ON sg.group_id = g.gid WHERE g.type = 3 GROUP BY g.gid, g.name ORDER BY g.name, g.gid " . sbLimit($page));
	$serverGroups = $db->fetch_row_set() ?: array();
	if ($full):
?>
<section class="hlstats-section hlstats-card sba-card">
	<div class="sba-card-head">
		<div><div class="hlstats-card-title">Server groups</div><div class="sba-muted">Servers given to admins together</div></div>
<?php if ($canAdd): ?>
		<button type="button" class="sba-btn is-primary" data-sba-open="sba-group-servers" data-sba-title="New server group">+ New group</button>
<?php endif; ?>
	</div>
	<div id="sba-server-groups">
<?php endif; ?>
	<div class="responsive-table">
	<table class="sba-table">
		<thead><tr><th class="left">Group</th><th class="left">Servers</th><th></th></tr></thead>
		<tbody>
<?php foreach ($serverGroups as $group):
	$members = $group['servers'] !== null ? array_map('intval', explode(',', $group['servers'])) : array();
	$names   = array_map(function ($sid) { return sbServerName($sid) ?: "Server #$sid"; }, $members);
?>
		<tr>
			<td class="left"><span class="hlstats-name"><?= htmlspecialchars($group['name']) ?></span></td>
			<td class="left"><?= $names ? htmlspecialchars(implode(', ', $names)) : '<span class="sba-muted">no server</span>' ?></td>
			<td class="right nowrap"><?= $buttons('sba-group-servers', array('id' => (int) $group['gid'], 'name' => $group['name'], 'servers[]' => $members), 'group.servers.delete', $group['name']) ?></td>
		</tr>
<?php endforeach; if (!$serverGroups): ?>
		<tr><td class="left sba-muted" colspan="3">No server group yet.</td></tr>
<?php endif; ?>
		</tbody>
	</table>
	</div>
	<?= Pagination((int) $total, $page, SB_PER_PAGE, 'page_servergroups', true, 'sba-server-groups') ?>
<?php if ($full): ?>
	</div>
</section>
<?php endif; endif;

if (!$full) {
	exit;
}
?>
</div>

<?php if ($canAdd || $canEdit):
	$db->query("SELECT sid, ip, port FROM {$P}_servers ORDER BY sid");
	$allServers = $db->fetch_row_set() ?: array();
?>
<dialog id="sba-group-web" class="sba-dialog is-wide">
	<form data-sba-action="group.web.save" novalidate>
		<input type="hidden" name="id" value="0">
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Web panel group</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<label class="sba-field"><span class="sba-label">Name</span><input type="text" name="name" maxlength="120" required><span class="sba-error" data-error-for="name"></span></label>
			<fieldset class="sba-field"><legend class="sba-label">Permissions</legend><?= sbWebPermsField(array()) ?><span class="sba-error" data-error-for="web"></span></fieldset>
		</div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Save group</button></div>
	</form>
</dialog>

<dialog id="sba-group-srv" class="sba-dialog is-wide">
	<form data-sba-action="group.srv.save" novalidate>
		<input type="hidden" name="id" value="0">
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">In-game admin group</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<label class="sba-field"><span class="sba-label">Name</span><input type="text" name="name" maxlength="120" required><span class="sba-error" data-error-for="name"></span></label>
			<fieldset class="sba-field"><legend class="sba-label">SourceMod flags <button type="button" class="sba-link" data-sba-all>All</button></legend><?= sbServerFlagsField('') ?></fieldset>
			<label class="sba-field sba-narrow"><span class="sba-label">Immunity <small>0 to 100</small></span><input type="number" name="immunity" min="0" max="100" value="0"></label>
		</div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Save group</button></div>
	</form>
</dialog>

<dialog id="sba-group-servers" class="sba-dialog">
	<form data-sba-action="group.servers.save" novalidate>
		<input type="hidden" name="id" value="0">
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Server group</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<label class="sba-field"><span class="sba-label">Name</span><input type="text" name="name" maxlength="120" required><span class="sba-error" data-error-for="name"></span></label>
			<fieldset class="sba-field"><legend class="sba-label">Servers <button type="button" class="sba-link" data-sba-all>All</button></legend>
				<div class="sba-chips">
<?php foreach ($allServers as $server): ?>
					<label class="sba-chip"><input type="checkbox" name="servers[]" value="<?= (int) $server['sid'] ?>"><span><?= htmlspecialchars(sbServerName($server['sid']) ?: $server['ip'] . ':' . $server['port']) ?></span></label>
<?php endforeach; ?>
				</div>
			</fieldset>
		</div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Save group</button></div>
	</form>
</dialog>
<?php endif;
