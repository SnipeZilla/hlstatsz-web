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

$main = isset($_GET['task']);

$limit     = $main ? SB_PER_PAGE : 10;
$now       = time();
$showAdmin = $main && sbShowAdminNames();

$sortorder    = valid_request($_GET['sortorder_bans'] ?? '', false);
$sort         = valid_request($_GET['sort_bans'] ?? '', false);
$page_bans    = valid_request($_GET['page_bans'] ?? '', false);

//$sortorder_comms   = valid_request($_GET['sortorder_comms'] ?? '', false);
//$sort_comms        = valid_request($_GET['sort_comms'] ?? '', false);
//$page_comms        = valid_request($_GET['page_comms'] ?? '', false);

//$sortorder_blocked = valid_request($_GET['sortorder_blocked'] ?? '', false);
//$sort_blocked      = valid_request($_GET['sort_blocked'] ?? '', false);
//$page_blocked      = valid_request($_GET['page_blocked'] ?? '', false);

$col = $showAdmin ? array("game","created","name","admin","length") : array("game","created","name","length");
    if (!in_array($sort, $col)) {
        $sort      = 'created';
        $sortorder = 'DESC';
    }
    
    if ($sort == 'game') {
        $sort2 = 'created';
    } else { $sort2 = 'game'; }
    $sortorder2 = 'DESC';

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

// A player searched from the header (sourcebans_search.php): only their bans
$search = sbSearch();
$where  = $search !== '' ? ' WHERE ' . sbPlayerMatch($search, 'name', 'authid', 'ip') : '';

$db->query("SELECT COUNT(*) FROM " . sbAllBans() . $where);
list($total) = $db->fetch_row();

    $page  = min(max(1, (int) ($_GET['page_bans'] ?? 1)), max(1, (int) ceil($total / $limit)));
    $start = ($page - 1) * $limit;

	// The SourceBans bans, and the AMXBans ones of the GoldSrc servers when it is set up
	$result = $db->query("SELECT * FROM " . sbAllBans() . "$where ORDER BY $sort $sortorder, $sort2 $sortorder2 LIMIT $limit OFFSET $start");
	

//INSERT INTO `sb__bans` (`bid`, `ip`, `authid`, `name`, `created`, `ends`, `length`, `reason`, `aid`,
//                        `adminIp`, `sid`, `country`, `RemovedBy`, `RemoveType`, `RemovedOn`, `type`, `ureason`) VALUES

if (!is_ajax()) {
    printSectionTitle(t('sb.title.bans'));
   echo '<div id="bans">';
 }
?>
<?php if (!$total) { ?>
<p class="sb-empty"><?= t($search !== '' ? 'sb.search.none.bans' : 'sb.none.bans') ?></p>
<?php } else { ?>

<div  class="responsive-table">
  <table class="sb-table bans">
    <tr>
        <th class="nowarp<?= isSorted('game',$sort,$sortorder) ?>" style="width:1%"><?= headerUrl('game', ['sort_bans','sortorder_bans'], 'bans') ?><?= t('sb.th.game') ?></a></th>
        <th class="left first<?= isSorted('created',$sort,$sortorder) ?>"><?= headerUrl('created', ['sort_bans','sortorder_bans'], 'bans') ?><?= t('th.date') ?></a></th>
        <th class="left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['sort_bans','sortorder_bans'], 'bans') ?><?= t('name') ?></a></th>
        <th class="left hide-1"><?= t('sb.th.reason') ?></th>
<?php if ($showAdmin) { ?>
        <th class="left hide<?= isSorted('admin',$sort,$sortorder) ?>"><?= headerUrl('admin', ['sort_bans','sortorder_bans'], 'bans') ?><?= t('sb.th.admin') ?></a></th>
<?php } ?>
        <th class="hlstats-numeric<?= isSorted('length',$sort,$sortorder) ?>"><?= headerUrl('length', ['sort_bans','sortorder_bans'], 'bans') ?><?= t('sb.th.length') ?></a></th>
    </tr>
    <?php
    while ($res = $db->fetch_array($result)) {
        $game   = sbBanGame($res);
        $reason = trim((string) $res['reason']);
        echo '<tr' . sbExpandAttrs(sbBanType($res), $res['bid']) . '>';
        ?>
        <td><span class="hlstats-icon"><img src="<?= htmlspecialchars($game['icon']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" data-tooltip="<?= htmlspecialchars($game['name']) ?>"></span></td>
        <td class="left nowrap"><?= str_replace(" ","<br>@",date("Y-m-d H:i:s", $res['created']))  ?></td>
        <td class="left">
        <span class="hlstats-slider-arrow">&#9660;</span>
        <?= sbFlag($res) ?>
        <?= sbNameLink(sbBanType($res), $res['bid'], $res) ?>
        </td>
        <td class="left hide-1"><?= $reason !== '' ? htmlspecialchars($reason) : '&ndash;' ?></td>
<?php if ($showAdmin) { ?>
        <td class="left hide"><?= htmlspecialchars($res['admin'] ?? '') ?></td>
<?php } ?>
        <td class="nowrap"><?= sbPill($res, $now) ?></td>
    </tr>
<?php } ?> 
    </table>
</div>
   <?php
       echo Pagination($total, $page, $limit, 'page_bans', true, 'bans');
   }

  if (is_ajax()) exit;
  ?>
</div>
