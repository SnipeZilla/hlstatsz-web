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

$limit     = SB_PER_PAGE;
$now       = time();
$showAdmin = sbShowAdminNames();

$sortorder = valid_request($_GET['sortorder_comms'] ?? '', false);
$sort      = valid_request($_GET['sort_comms'] ?? '', false);

$col = array('game', 'type', 'created', 'name', 'length');
if ($showAdmin) {
	$col[] = 'admin';
}
if (!in_array($sort, $col)) {
	$sort      = 'created';
	$sortorder = 'DESC';
}
$sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';
$sort2     = $sort === 'created' ? 'bid' : 'created';

// A player searched from the header (sourcebans_search.php): only their comm blocks
$search = sbSearch();
$where  = $search !== '' ? 'WHERE ' . sbPlayerMatch($search, 'co.name', 'co.authid') : '';

$db->query("SELECT COUNT(co.bid) FROM " . DB_SBPREFIX . "_comms AS co $where");
list($total) = $db->fetch_row();

$page  = min(max(1, (int) ($_GET['page_comms'] ?? 1)), max(1, (int) ceil($total / $limit)));
$start = ($page - 1) * $limit;

$result = $db->query("
	SELECT
		co.bid,
		co.name,
		co.authid,
		co.created,
		co.ends,
		co.length,
		co.reason,
		co.type,
		co.sid,
		co.RemoveType,
		co.RemovedOn,
		ad.user AS admin,
		mo.modfolder AS game,
		mo.name AS modname
	FROM
		" . DB_SBPREFIX . "_comms AS co
		LEFT JOIN " . DB_SBPREFIX . "_admins AS ad ON ad.aid = co.aid
		LEFT JOIN " . DB_SBPREFIX . "_servers AS se ON se.sid = co.sid
		LEFT JOIN " . DB_SBPREFIX . "_mods AS mo ON mo.mid = se.modid
	$where
	ORDER BY
		$sort $sortorder,
		$sort2 DESC
	LIMIT $limit OFFSET $start
");

if (!is_ajax()) {
	printSectionTitle(t('sb.title.comms'));
	echo '<div id="comms">';
}
?>
<?php if (!$total): ?>
<p class="sb-empty"><?= t($search !== '' ? 'sb.search.none.comms' : 'sb.none.comms') ?></p>
<?php else: ?>

<div class="responsive-table">
  <table class="sb-table comms">
    <tr>
        <th class="nowrap<?= isSorted('game', $sort, $sortorder) ?>" style="width:1%"><?= headerUrl('game', ['sort_comms', 'sortorder_comms'], 'comms') ?><?= t('sb.th.game') ?></a></th>
        <th class="nowrap<?= isSorted('type', $sort, $sortorder) ?>" style="width:1%"><?= headerUrl('type', ['sort_comms', 'sortorder_comms'], 'comms') ?><?= t('th.type') ?></a></th>
        <th class="left<?= isSorted('created', $sort, $sortorder) ?>"><?= headerUrl('created', ['sort_comms', 'sortorder_comms'], 'comms') ?><?= t('th.date') ?></a></th>
        <th class="left<?= isSorted('name', $sort, $sortorder) ?>"><?= headerUrl('name', ['sort_comms', 'sortorder_comms'], 'comms') ?><?= t('name') ?></a></th>
        <th class="left hide-1"><?= t('sb.th.reason') ?></th>
<?php if ($showAdmin) { ?>
        <th class="left hide<?= isSorted('admin', $sort, $sortorder) ?>"><?= headerUrl('admin', ['sort_comms', 'sortorder_comms'], 'comms') ?><?= t('sb.th.admin') ?></a></th>
<?php } ?>
        <th class="hlstats-numeric<?= isSorted('length', $sort, $sortorder) ?>"><?= headerUrl('length', ['sort_comms', 'sortorder_comms'], 'comms') ?><?= t('sb.th.length') ?></a></th>
    </tr>
<?php
    while ($row = $db->fetch_array($result)) {
        $game   = sbGame($row['sid'], $row['game'], $row['modname']);
        $reason = trim((string) $row['reason']);
?>
    <tr<?= sbExpandAttrs('comm', $row['bid']) ?>>
        <td><span class="hlstats-icon"><img src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>"></span></td>
        <td><?= sbCommType($row['type']) ?></td>
        <td class="left nowrap"><?= str_replace(' ', '<br>@', date('Y-m-d H:i:s', $row['created'])) ?></td>
        <td class="left"><span class="hlstats-slider-arrow">&#9660;</span><?= sbNameLink('comm', $row['bid'], $row) ?></td>
        <td class="left hide-1"><?= $reason !== '' ? htmlspecialchars($reason) : '&ndash;' ?></td>
<?php if ($showAdmin) { ?>
        <td class="left hide"><?= $row['admin'] !== null ? htmlspecialchars($row['admin']) : '&ndash;' ?></td>
<?php } ?>
        <td class="nowrap"><?= sbPill($row, $now) ?></td>
    </tr>
<?php } ?>
  </table>
</div>
<?php
	echo Pagination($total, $page, $limit, 'page_comms', true, 'comms');
endif;

if (is_ajax()) exit;
?>
</div>
