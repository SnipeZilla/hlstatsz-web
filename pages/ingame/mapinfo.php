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

	// Map Details
	$player = valid_request(intval($_GET['player'] ?? 0), true);
	$map = valid_request($_GET['map'] ?? '', false) or error('No map specified.');
	
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() != 1) {
		error('Invalid or no game specified.');
	} else {
		list($gamename) = $db->fetch_row();
	}

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "headshots";

    $col = array("killerName","frags","headshots","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "frags";
        $sortorder = "DESC";
    }

    if ($sort == "headshots") {
        $sort2 = "frags";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;


    $result = $db->query("
        SELECT
            hlstats_Events_Frags.killerId,
            hlstats_Players.lastName AS killerName,
            hlstats_Players.flag as flag,
            COUNT(hlstats_Events_Frags.map) AS frags,
            SUM(hlstats_Events_Frags.headshot=1) as headshots,
            IFNULL(SUM(hlstats_Events_Frags.headshot=1) / Count(hlstats_Events_Frags.map), '-') AS hpk
        FROM
            hlstats_Events_Frags,
            hlstats_Players
        WHERE
            hlstats_Players.playerId = hlstats_Events_Frags.killerId
            AND hlstats_Events_Frags.map='$map'
            AND hlstats_Players.game='$game'
            AND hlstats_Players.hideranking<>'1'
        GROUP BY
            hlstats_Events_Frags.killerId
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        LIMIT 30 OFFSET $start
    ");

    $resultCount = $db->query("
        SELECT
            COUNT(DISTINCT hlstats_Events_Frags.killerId),
            SUM(hlstats_Events_Frags.map='$map')
        FROM
            hlstats_Events_Frags,
        hlstats_Servers
        WHERE
            hlstats_Servers.serverId = hlstats_Events_Frags.serverId
            AND hlstats_Events_Frags.map='$map'
            AND hlstats_Servers.game='$game'
    ");
	
	list($numitems, $totalkills) = $db->fetch_row($resultCount);

?>

<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-pname"><?= $map ?></div>
  <div class="hlstats-card-foot">From a total of <strong><?php echo nf(intval($totalkills)); ?></strong> kills</div>
</section>

<section class="hlstats-section hlstats-card">
<div style="margin: 10px auto 0">

<?php // figure out URL and absolute path of image
	if ($mapimg = getImage("/games/$realgame/maps/$map"))
	{
		$mapimg = $mapimg['url'];
	}
	elseif ($mapimg = getImage("/games/$game/maps/$map"))
	{
		$mapimg = $mapimg['url'];
	}
	else
	{
		$mapimg = IMAGE_PATH."/nomap.png";
	}
	
	if ($mapimg)
	{
		echo "<a href=\"" . $mapimg . "\" rel=\"boxed\" title=\"$map\"><img src=\"$mapimg\" alt=\"$map\" width=\"220\"/></a>";
	}

?>
</div>


</section>
</div>

<div id="mapinfo">

<div  class="responsive-table">
  <table class="maps-table">
    <tr>
        <th class="nowarp right" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('killerName',$sort,$sortorder) ?>"><?= headerUrl('killerName', ['sort','sortorder'], 'mapinfo') ?>Player</a></th>
        <th class="<?= isSorted('frags',$sort,$sortorder) ?>"><?= headerUrl('frags', ['sort','sortorder'], 'mapinfo') ?>Kills</a></th>
        <th class="<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['sort','sortorder'], 'mapinfo') ?>Headshots</a></th>
        <th class="<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['sort','sortorder'], 'mapinfo') ?>HS:K</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left">';
                  if ($g_options['countrydata']) {
                  echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                  }
                  echo '<a href="?mode=statsme&amp;player='.$res['killerId'].'"><span class="hlstats-name">'.htmlspecialchars($res['killerName']).'</span></a>
                  </td>
                  <td class="nowrap">'.nf($res['frags']).'</td>
                  <td class="nowrap">'.nf($res['headshots']).'</td>
                  <td class="nowrap">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', false, 'mapinfo');

  ?>
</div>

<div>
<div style="float:left;">
  <a href="?mode=maps&amp;game=<?=$game?>&amp;player=<?=$player?>">&larr;&nbsp;Map Statistics</a>
</div>
</div>
