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
include(PAGE_PATH . '/voicecomm_serverlist.php');

$scId = valid_request($_GET['scId'] ?? '', true);
$db->query("SELECT name, addr, descr FROM hlstats_Servers_VoiceComm WHERE serverId=" . intval($scId));
$s = $db->fetch_array();

if (!$s) {
	error("Steam Community group not found", 1);
	return;
}

$group_slug   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $s['addr']);
$sc_group_url = 'https://steamcommunity.com/groups/' . urlencode($group_slug);

if (!is_dir('./cache')) mkdir('./cache', 0755, true);
$sc_cache_ttl  = 300;
$sc_cache_file = './cache/hlstatsz_sc_' . md5($group_slug) . '.xml';

$sc_xml_raw = false;
if (is_file($sc_cache_file) && (time() - filemtime($sc_cache_file)) < $sc_cache_ttl) {
	$sc_xml_raw = file_get_contents($sc_cache_file);
} else {
	$url = 'https://steamcommunity.com/groups/' . urlencode($group_slug) . '/memberslistxml/?xml=1';
	$ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true, 'user_agent' => 'HLstatsZ/1.0'], 'ssl' => hlstatsSsl()]);
	$sc_xml_raw = @file_get_contents($url, false, $ctx);
	if ($sc_xml_raw !== false) {
		@file_put_contents($sc_cache_file, $sc_xml_raw, LOCK_EX);
	} elseif (is_file($sc_cache_file)) {
		// Steam did not answer: what it said last time
		$sc_xml_raw = file_get_contents($sc_cache_file);
	}
}

$sc_data = $sc_xml_raw ? @simplexml_load_string($sc_xml_raw) : null;

$sc_headline = htmlspecialchars($s['name']);
$sc_avatar   = IMAGE_PATH . '/unknown.jpg';
$sc_stats    = ['Members' => '&mdash;', 'Online' => '&mdash;', 'In Game' => '&mdash;', 'In Chat' => '&mdash;'];
$sc_summary  = '';

if ($sc_data && isset($sc_data->groupDetails)) {
	$d  = $sc_data->groupDetails;

	$hl = trim((string)($d->headline ?? ''));
	if ($hl !== '') $sc_headline = htmlspecialchars($hl);

	$av = trim((string)($d->avatarFull ?? ''));
	if ($av !== '') $sc_avatar = htmlspecialchars($av, ENT_QUOTES);

	$sc_stats = [
		'Members' => number_format((int)($d->memberCount   ?? 0)),
		'Online'  => number_format((int)($d->membersOnline ?? 0)),
		'In Game' => number_format((int)($d->membersInGame ?? 0)),
		'In Chat' => number_format((int)($d->membersInChat ?? 0)),
	];

	$raw = (string)($d->summary ?? '');
	if ($raw !== '') {
		$raw = preg_replace_callback(
			'/href="https?:\/\/steamcommunity\.com\/linkfilter\/\?u=([^"]+)"/i',
			function ($m) {
				return 'href="' . htmlspecialchars(urldecode($m[1]), ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer"';
			},
			$raw
		);
		$sc_summary = strip_tags($raw, '<a><br><div><span><u><i><b><strong><em>');
	}
}

if (!$sc_data) {
	error("Could not fetch Steam Community data. Check that the group slug is correct.", 1);
}

printSectionTitle(t('title.comm.steam'));
?>
<div class="hlstats-steam-group">
	<div class="hlstats-profile-head">
		<div class="hlstats-avatar">
			<a href="<?= $sc_group_url ?>" target="_blank" rel="noopener noreferrer">
				<img src="<?= $sc_avatar ?>" class="hlstats-avatar-img" alt="Steam Group Avatar" />
			</a>
		</div>
		<div class="hlstats-identity">
			<div class="hlstats-pname">
				<a href="<?= $sc_group_url ?>" target="_blank" rel="noopener noreferrer"><?= $sc_headline ?></a>
			</div>
			<div class="hlstats-steam-stats">
				<?php foreach ($sc_stats as $label => $val): ?>
				<div class="hlstats-steam-stat">
					<span class="sc-stat-value <?= strcasecmp($label,'online')  === 0 ? ' green' :
												(strcasecmp($label,'in game') === 0 ? ' blue' : '') ?>"><?= $val ?></span>
					<span class="sc-stat-label"><?= htmlspecialchars($label) ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php if (!empty($s['descr'])): ?>
			<p class="hlstats-steam-descr"><?= htmlspecialchars($s['descr']) ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php if (!empty($sc_summary)): ?>
	<div class="hlstats-steam-summary">
		<?= $sc_summary ?>
	</div>
	<?php endif; ?>
</div>
