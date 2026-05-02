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

    ob_flush();
    flush();

    $asterisk = $g_options['DeleteDays'] ? ' *' : '';

if (empty($_GET['ajax']) || $_GET['ajax'] == 'server') {


    $sortorder = $_GET['server_sortorder'] ?? '';
    $sort      = $_GET['server_sort'] ?? '';
    $sort2     = "kills";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("server","kills","kpercent","kdeaths","kpd","headshots","hpercent","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "kills";
        $sortorder = "DESC";
    }

    if ($sort == "server") {
        $sort2 = "kills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";
  
	// leave the join on this one, we do groupings..
	$result = $db->query("
		SELECT
			hlstats_Servers.name AS server,
			SUM(hlstats_Events_Frags.killerId = $player) AS kills,
			SUM(hlstats_Events_Frags.victimId = $player) AS deaths,
			SUM(hlstats_Events_Frags.killerId = $player) / IF(SUM(hlstats_Events_Frags.victimId = $player) = 0, 1, SUM(hlstats_Events_Frags.victimId = $player)) AS kpd,
			ROUND(SUM(hlstats_Events_Frags.killerId = $player) / $realkills * 100, 2) AS kpercent,
			ROUND(SUM(hlstats_Events_Frags.victimId = $player) / $realdeaths * 100, 2) AS dpercent,
			SUM(hlstats_Events_Frags.killerId = $player AND hlstats_Events_Frags.headshot = 1) AS headshots,
			IFNULL(SUM(hlstats_Events_Frags.killerId = $player AND hlstats_Events_Frags.headshot = 1) / SUM(hlstats_Events_Frags.killerId = $player), '-') AS hpk,
			ROUND(SUM(hlstats_Events_Frags.killerId = $player AND hlstats_Events_Frags.headshot = 1) / $realheadshots * 100, 2) AS hpercent
		FROM
			hlstats_Events_Frags
		LEFT JOIN
			hlstats_Servers
		ON
			hlstats_Servers.serverId = hlstats_Events_Frags.serverId
		WHERE
			hlstats_Servers.game = '$game'
			AND hlstats_Events_Frags.killerId = '$player'
			OR hlstats_Events_Frags.victimId = '$player'
		GROUP BY
			hlstats_Servers.name
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
	");

	if ($db->num_rows($result))
	{
       if (empty($_GET['ajax'])) {

		printSectionTitle('Server Activity'.$asterisk);
?>
<div id="server">
<?php
}
?>
<div class="responsive-table">
  <table class="server-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('server',$sort,$sortorder) ?>"><?= headerUrl('server', ['server_sort','server_sortorder'], 'server') ?>Server</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['server_sort','server_sortorder'], 'server') ?>Kills</a></th>
        <th class="hide-2 meter-ratio<?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['server_sort','server_sortorder'], 'server') ?>Ratio</a></th>
       <th class="hide<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['server_sort','server_sortorder'], 'server') ?>Deaths</a></th>
       <th class="hide-1<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['server_sort','server_sortorder'], 'server') ?>K:D</a></th>
       <th class="hide-1<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['server_sort','server_sortorder'], 'server') ?>Headshots</a></th>
       <th class="hide-2 meter-ratio<?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['server_sort','server_sortorder'], 'server') ?>Ratio</a></th>
       <th class="hide-3<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['server_sort','server_sortorder'], 'server') ?>HS:K</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?game='.$game.'"><span class="hlstats-name">'.htmlspecialchars($res['server']).'</span></a></td>
                  <td class="nowrap">'.nf($res['kills']).' times</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter id="progressMeter"min="0" max="100" low="25" high="50" optimum="75" value="'.$res['kpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['kpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide">'.nf($res['deaths']).'</td>
                  <td class="nowrap hide-1">'.nf($res['kpd'],2,'.','').'</td>
                  <td class="nowrap hide-1">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter id="progressMeter"min="0" max="100" low="25" high="50" optimum="75" value="'.$res['hpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['hpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide-3">'.nf($res['hpk'],2,'.','').'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}
