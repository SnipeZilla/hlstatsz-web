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

    if (empty($_GET['ajax']) || $_GET['ajax'] == 'aliases') {
    ob_flush();
    flush();
    $sortorder = $_GET['aliases_sortorder'] ?? '';
    $sort      = $_GET['aliases_sort'] ?? '';


    $col = array("name","connection_time","lastuse","kills","deaths","kpd","headshots","hpk","suicides","acc");
    if (!in_array($sort, $col)) {
        $sort      = "lastuse";
        $sortorder = "DESC";
    }


    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['aliases_page']) ? ((int)$_GET['aliases_page'] - 1) * 10 : 0;

	$result = $db->query
	("
		SELECT
			hlstats_PlayerNames.name,
			hlstats_PlayerNames.connection_time,
			hlstats_PlayerNames.lastuse,
			hlstats_PlayerNames.numuses,
			hlstats_PlayerNames.kills,
			hlstats_PlayerNames.deaths,
			IFNULL(ROUND(hlstats_PlayerNames.kills / IF(hlstats_PlayerNames.deaths = 0, 1, hlstats_PlayerNames.deaths), 2), '-') AS kpd,
			hlstats_PlayerNames.headshots,
			IFNULL(ROUND(hlstats_PlayerNames.headshots / hlstats_PlayerNames.kills, 2), '-') AS hpk,
			hlstats_PlayerNames.suicides,
			IFNULL(ROUND(hlstats_PlayerNames.hits / hlstats_PlayerNames.shots * 100, 1), 0.0) AS acc
		FROM
			hlstats_PlayerNames
		WHERE
			hlstats_PlayerNames.playerId = $player
		ORDER BY
			$sort $sortorder
		LIMIT
			10 OFFSET $start
	");
	$resultCount = $db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_PlayerNames
		WHERE
			hlstats_PlayerNames.playerId = $player
	");
	list($numitems) = $db->fetch_row($resultCount);

	if ($numitems > 1)
	{

       if (empty($_GET['ajax'])) {

		printSectionTitle('Aliases');
?>
<div id="aliases">
<?php
}
?>
<div  class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Name</a></th>
        <th class="<?= isSorted('lastuse',$sort,$sortorder) ?>"><?= headerUrl('lastuse', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Last Use</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Kills</a></th>
        <th class="hide<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Deaths</a></th>
        <th class="hide-1<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['aliases_sort','aliases_sortorder'], 'aliases') ?>K:D</a></th>
        <th class="hide<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Headshots</a></th>
        <th class="hide-1<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['aliases_sort','aliases_sortorder'], 'aliases') ?>HS:K</a></th>
        <th class="hide-1<?= isSorted('suicides',$sort,$sortorder) ?>"><?= headerUrl('suicides', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Suicides</a></th>
       <th class="hide-2<?= isSorted('acc',$sort,$sortorder) ?>"><?= headerUrl('acc', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Accuracy</a></th>
       <th class="hide-2<?= isSorted('connection_time',$sort,$sortorder) ?>"><?= headerUrl('connection_time', ['aliases_sort','aliases_sortorder'], 'aliases') ?>Time</a></th>
   </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left">
                       <span class="hlstats-name">'.htmlspecialchars($res['name']).'</span>
                   </td>
                  <td class="nowrap">'.str_replace(" ","<br>@",$res['lastuse']).'</td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap hide">'.nf($res['deaths']).'</td>
                  <td class="nowrap hide-1">'.$res['kpd'].'</td>
                  <td class="nowrap hide">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-1">'.$res['hpk'].'</td>
                  <td class="nowrap hide-1">'.nf($res['suicides']).'</td>
                  <td class="nowrap hide-2">'.$res['acc'].'</td>
                  <td class="nowrap hide-2">'.TimeStamp($res['connection_time']).'</td>
                   </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['aliases_page'] ?? 1, 10, 'aliases_page', true, 'aliases');

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}
?>