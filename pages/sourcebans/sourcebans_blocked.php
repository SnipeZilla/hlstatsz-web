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

// Players stopped from joining by a ban: one row per ban, at its latest attempt, since a banned player
// often retries dozens of times. The log outlives deleted bans, so only bans that still exist are listed.
// AMXBans only counts the attempts of a ban, without dates: its details show that count.
$now = time();

$sortorder = valid_request($_GET['sortorder_blocked'] ?? '', false);
$sort      = valid_request($_GET['sort_blocked'] ?? '', false);

$col = array('game', 'created', 'name', 'attempts', 'length');
if (!in_array($sort, $col)) {
	$sort      = 'created';
	$sortorder = 'DESC';
}
$sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';
$sort2     = $sort === 'created' ? 'bid' : 'created';

$db->query("
	SELECT COUNT(DISTINCT lo.bid)
	FROM " . DB_SBPREFIX . "_banlog AS lo
	JOIN " . DB_SBPREFIX . "_bans AS b ON b.bid = lo.bid
");
list($total) = $db->fetch_row();

$page = sbPage('page_blocked', $total);

// Two servers can log the same ban in the same second: the latest attempt is then the one of the higher sid
$result = $db->query("
	SELECT
		bl.bid,
		bl.time AS created,
		bl.name,
		bl.sid,
		t.attempts,
		ba.authid,
		ba.ip,
		ba.country,
		ba.reason,
		ba.created AS ban_created,
		ba.ends,
		ba.length,
		ba.RemoveType,
		ba.RemovedOn,
		mo.modfolder AS game,
		mo.name AS modname
	FROM
		(
			SELECT lo.bid, MAX(lo.time) AS last, COUNT(*) AS attempts
			FROM " . DB_SBPREFIX . "_banlog AS lo
			JOIN " . DB_SBPREFIX . "_bans AS b ON b.bid = lo.bid
			GROUP BY lo.bid
		) AS t
		JOIN " . DB_SBPREFIX . "_banlog AS bl ON bl.bid = t.bid AND bl.time = t.last
			AND bl.sid = (SELECT MAX(l2.sid) FROM " . DB_SBPREFIX . "_banlog AS l2 WHERE l2.bid = t.bid AND l2.time = t.last)
		JOIN " . DB_SBPREFIX . "_bans AS ba ON ba.bid = t.bid
		LEFT JOIN " . DB_SBPREFIX . "_servers AS se ON se.sid = bl.sid
		LEFT JOIN " . DB_SBPREFIX . "_mods AS mo ON mo.mid = se.modid
	ORDER BY
		$sort $sortorder,
		$sort2 DESC
	" . sbLimit($page)
);

if (!is_ajax()) {
	printSectionTitle(t('sb.title.blocked'));
	echo '<div id="blocked">';
}

if (!$total) {
	echo '<p class="sb-empty">' . t('sb.none.blocked') . '</p>';
} else {
?>

<div class="responsive-table">
  <table class="sb-table blocked">
    <tr>
        <th class="nowrap<?= isSorted('game', $sort, $sortorder) ?>" style="width:1%"><?= headerUrl('game', ['sort_blocked', 'sortorder_blocked'], 'blocked') ?><?= t('sb.th.game') ?></a></th>
        <th class="left<?= isSorted('created', $sort, $sortorder) ?>"><?= headerUrl('created', ['sort_blocked', 'sortorder_blocked'], 'blocked') ?><?= t('th.date') ?></a></th>
        <th class="left<?= isSorted('name', $sort, $sortorder) ?>"><?= headerUrl('name', ['sort_blocked', 'sortorder_blocked'], 'blocked') ?><?= t('name') ?></a></th>
        <th class="left hide-2"><?= t('th.server') ?></th>
        <th class="left hide-1"><?= t('sb.th.reason') ?></th>
        <th class="hlstats-numeric<?= isSorted('attempts', $sort, $sortorder) ?>" data-tooltip="<?= htmlspecialchars(t('sb.attempts.tip')) ?>"><?= headerUrl('attempts', ['sort_blocked', 'sortorder_blocked'], 'blocked') ?><?= t('sb.th.blocked') ?></a></th>
        <th class="hlstats-numeric hide<?= isSorted('length', $sort, $sortorder) ?>"><?= headerUrl('length', ['sort_blocked', 'sortorder_blocked'], 'blocked') ?><?= t('sb.th.length') ?></a></th>
    </tr>
<?php
    while ($row = $db->fetch_array($result)) {
        $game   = sbGame($row['sid'], $row['game'], $row['modname']);
        $server = sbServerName($row['sid']);
        $reason = trim((string) $row['reason']);
?>
    <tr<?= sbExpandAttrs('ban', $row['bid']) ?>>
        <td><span class="hlstats-icon"><img src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>"></span></td>
        <td class="left nowrap"><?= str_replace(' ', '<br>@', date('Y-m-d H:i:s', $row['created'])) ?></td>
        <td class="left"><span class="hlstats-slider-arrow">&#9660;</span><?= sbFlag($row) ?><?= sbNameLink('ban', $row['bid'], $row) ?></td>
        <td class="left hide-2"><?= $server !== '' ? htmlspecialchars($server) : '&ndash;' ?></td>
        <td class="left hide-1"><?= $reason !== '' ? htmlspecialchars($reason) : '&ndash;' ?></td>
        <td class="hlstats-numeric"><?= nf($row['attempts']) ?></td>
        <td class="nowrap hide"><?= sbPill(array('created' => $row['ban_created']) + $row, $now) ?></td>
    </tr>
<?php } ?>
  </table>
</div>
<?php
	echo Pagination($total, $page, SB_PER_PAGE, 'page_blocked', true, 'blocked');
}

if (is_ajax()) exit;
?>
</div>
