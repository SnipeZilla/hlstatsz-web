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

// The ban history card only, zoomed in or reset
if (sbAjaxPart('sb-history')) {
	sbHistoryCard(is_string($_GET['history'] ?? null) ? $_GET['history'] : '');
	exit;
}

$now   = time();
$month = 2592000;
$comms = sbComms();

/*
 * Headline numbers
 */
$db->query("
	SELECT
		COUNT(*) AS total,
		SUM(RemoveType IS NULL AND (length = 0 OR ends > $now)) AS active,
		SUM(RemoveType IS NULL AND length = 0) AS permanent,
		SUM(created > $now - $month) AS recent
	FROM
		" . sbAllBans() . "
");
$bans = array_map('intval', $db->fetch_array());

// Blocked joins: SourceBans logs each one; AMXBans only counts them per ban, without dates
$blocked = array('total' => 0, 'recent' => 0);
if (sbOn()) {
	$db->query("
		SELECT
			COUNT(*) AS total,
			SUM(time > $now - $month) AS recent
		FROM
			" . DB_SBPREFIX . "_banlog
	");
	$blocked = array_map('intval', $db->fetch_array());
}
$blocked['total'] += sbAmxKicks();

$tiles = array(
	array(t('sb.kpi.active'), $bans['active'], t('sb.kpi.active.note', array('{n}' => nf($bans['permanent']))), ''),
	array(t('sb.kpi.total'), $bans['total'], t('sb.kpi.recent.note', array('{n}' => nf($bans['recent']))), ''),
);
if ($comms) {
	$db->query("
		SELECT
			COUNT(*) AS total,
			SUM(type = 1) AS mutes,
			SUM(type = 2) AS gags
		FROM
			" . DB_SBPREFIX . "_comms
	");
	$commStats = array_map('intval', $db->fetch_array());
	$tiles[] = array(t('sb.kpi.comms'), $commStats['total'], t('sb.kpi.comms.note', array('{mutes}' => nf($commStats['mutes']), '{gags}' => nf($commStats['gags']))), '');
}
$tiles[] = array(t('sb.kpi.blocked'), $blocked['total'], sbOn() ? t('sb.kpi.recent.note', array('{n}' => nf($blocked['recent']))) : '', t('sb.kpi.blocked.tip'));

/*
 * Latest bans, comm blocks and blocked join attempts
 */
$result = $db->query("SELECT * FROM " . sbAllBans() . " ORDER BY created DESC, bid DESC LIMIT 8");
$latestBans = $db->fetch_row_set($result) ?: array();

$latestComms = array();
if ($comms) {
	$result = $db->query("
		SELECT
			bid,
			name,
			authid,
			created,
			ends,
			length,
			reason,
			type,
			RemoveType,
			RemovedOn
		FROM
			" . DB_SBPREFIX . "_comms
		ORDER BY
			created DESC,
			bid DESC
		LIMIT 8
	");
	$latestComms = $db->fetch_row_set($result) ?: array();
}

// One entry per ban, at its latest attempt: a banned player often retries dozens of times.
// The log outlives deleted bans, so only attempts whose ban still exists are listed. SourceBans' log only.
$latestBlocked = array();
if (sbOn()) {
	$result = $db->query("
		SELECT
			bl.bid,
			bl.name,
			bl.sid,
			t.last AS created,
			t.attempts,
			ba.authid,
			ba.ip,
			ba.country,
			mo.modfolder,
			mo.name AS modname
		FROM
			(
				SELECT lo.bid, MAX(lo.time) AS last, COUNT(*) AS attempts
				FROM " . DB_SBPREFIX . "_banlog AS lo
				JOIN " . DB_SBPREFIX . "_bans AS b ON b.bid = lo.bid
				GROUP BY lo.bid
				ORDER BY last DESC
				LIMIT 8
			) AS t
			JOIN " . DB_SBPREFIX . "_banlog AS bl ON bl.bid = t.bid AND bl.time = t.last
			JOIN " . DB_SBPREFIX . "_bans AS ba ON ba.bid = t.bid
			LEFT JOIN " . DB_SBPREFIX . "_servers AS se ON se.sid = bl.sid
			LEFT JOIN " . DB_SBPREFIX . "_mods AS mo ON mo.mid = se.modid
		ORDER BY
			t.last DESC
	");
	while ($row = $db->fetch_array($result)) {
		$latestBlocked[$row['bid']] ??= $row;   // two servers can log the same second
	}
}
$cards = 1 + ($comms ? 1 : 0) + (sbOn() ? 1 : 0);   // latest bans, comm blocks, blocked joins

printSectionTitle(t('sb.title.stats'));
?>
<div class="hlstats-cards-grid hlstats-kpis">
<?php foreach ($tiles as list($label, $value, $note, $tip)): ?>
	<div class="hlstats-card hlstats-kpi">
		<div class="hlstats-kpi-label"<?= $tip !== '' ? ' data-tooltip="' . htmlspecialchars($tip) . '"' : '' ?>><?= $label ?></div>
		<div class="hlstats-kpi-value"><?= nf($value) ?></div>
		<div class="hlstats-kpi-note"><?= $note ?></div>
	</div>
<?php endforeach; ?>
</div>

<?php sbRecordCard(); ?>

<section class="hlstats-section hlstats-card sb-history" id="sb-history">
<?php sbHistoryCard(''); ?>
</section>
<?php sbHistoryScript(); ?>

<?php printSectionTitle(t('sb.title.latest')); ?>
<div class="hlstats-cards-grid sb-latest<?= array(1 => ' is-single', 2 => ' is-pair', 3 => '')[$cards] ?>">
	<section class="hlstats-section hlstats-card">
		<div class="hlstats-card-head">
			<div class="hlstats-card-title"><?= t('sb.latest.bans') ?></div>
			<a class="sb-more" href="<?= $g_options['scripturl'] ?>?mode=sourcebans&amp;task=sourcebans_bans"><?= t('sb.view.bans') ?> &rsaquo;</a>
		</div>
<?php if (!$latestBans): ?>
		<p class="sb-empty"><?= t('sb.none.bans') ?></p>
<?php else: ?>
		<ul class="sb-list">
<?php foreach ($latestBans as $ban): $game = sbBanGame($ban); ?>
			<li<?= sbExpandAttrs(sbBanType($ban), $ban['bid'], 'sb-item') ?>>
				<img class="sb-item-icon" src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>">
				<div class="sb-item-main">
					<div class="sb-item-name"><?= sbFlag($ban) ?><?= sbNameLink(sbBanType($ban), $ban['bid'], $ban) ?></div>
					<div class="sb-item-sub"><?= sbItemSub($ban, $now) ?></div>
				</div>
				<?= sbPill($ban, $now) ?>
			</li>
<?php endforeach; ?>
		</ul>
<?php endif; ?>
	</section>
<?php if ($comms): ?>
	<section class="hlstats-section hlstats-card">
		<div class="hlstats-card-head">
			<div class="hlstats-card-title"><?= t('sb.latest.comms') ?></div>
			<a class="sb-more" href="<?= $g_options['scripturl'] ?>?mode=sourcebans&amp;task=sourcebans_comms"><?= t('sb.view.comms') ?> &rsaquo;</a>
		</div>
<?php if (!$latestComms): ?>
		<p class="sb-empty"><?= t('sb.none.comms') ?></p>
<?php else: ?>
		<ul class="sb-list">
<?php foreach ($latestComms as $comm): ?>
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
<?php endif; ?>
	</section>
<?php endif; ?>
<?php if (sbOn()): ?>
	<section class="hlstats-section hlstats-card">
		<div class="hlstats-card-head">
			<div class="hlstats-card-title" data-tooltip="<?= htmlspecialchars(t('sb.kpi.blocked.tip')) ?>"><?= t('sb.latest.blocked') ?></div>
			<a class="sb-more" href="<?= $g_options['scripturl'] ?>?mode=sourcebans&amp;task=sourcebans_blocked"><?= t('sb.view.blocked') ?> &rsaquo;</a>
		</div>
<?php if (!$latestBlocked): ?>
		<p class="sb-empty"><?= t('sb.none.blocked') ?></p>
<?php else: ?>
		<ul class="sb-list">
<?php foreach ($latestBlocked as $block): $game = sbGame($block['sid'], $block['modfolder'], $block['modname']); $server = sbServerName($block['sid']) ?: $game['name']; ?>
			<li<?= sbExpandAttrs('ban', $block['bid'], 'sb-item') ?>>
				<img class="sb-item-icon" src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>">
				<div class="sb-item-main">
					<div class="sb-item-name"><?= sbFlag($block) ?><?= sbNameLink('ban', $block['bid'], $block) ?></div>
					<div class="sb-item-sub"><?= sbItemSub($block, $now, $server) ?></div>
				</div>
				<span class="hlstats-sb sb-pill neutral" data-tooltip="<?= htmlspecialchars(t('sb.attempts.tip')) ?>"><?= (int) $block['attempts'] === 1 ? t('sb.attempt') : t('sb.attempts', array('{n}' => nf($block['attempts']))) ?></span>
			</li>
<?php endforeach; ?>
		</ul>
<?php endif; ?>
	</section>
<?php endif; ?>
</div>

<?php
printSectionTitle(t('title.servers'));
sbServerTable();
sbPlayerDialogs();
