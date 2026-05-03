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

	// Ribbon Statistics

	$ribbon =  valid_request($_GET['ribbon'] ?? '', true) or error('No ribbon ID specified.');

	$db->query("
		SELECT r.ribbonName, r.image, r.awardCode, r.awardCount,
		       a.awardId, a.name AS awardName
		FROM hlstats_Ribbons r
		JOIN hlstats_Awards a ON a.code = r.awardCode AND a.game = r.game
		WHERE r.ribbonId = $ribbon
	");

	$actiondata = $db->fetch_array();
	$db->free_result();
	$act_name  = $actiondata['ribbonName'];
	$awardmin  = $actiondata['awardCount'];
	$awardcode = $actiondata['awardCode'];
	$awardId   = (int)$actiondata['awardId'];
	$awardName = $actiondata['awardName'];
	$image     = $actiondata['image'];

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "playerName";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("playerName","numawards","awardName");
    if (!in_array($sort, $col)) {
        $sort      = "numawards";
        $sortorder = "DESC";
    }

    if ($sort == "playerName") {
        $sort2 = "numawards";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
    
	$result = $db->query("
		SELECT
			p.flag,
			p.lastName AS playerName,
			p.playerId,
			COUNT(pa.awardId) AS numawards
		FROM hlstats_Players_Ribbons prb
		JOIN hlstats_Players p
			ON p.playerId = prb.playerId AND p.game = '$game'
		LEFT JOIN hlstats_Players_Awards pa
			ON pa.playerId = prb.playerId AND pa.game = '$game' AND pa.awardId = $awardId
		WHERE prb.ribbonId = $ribbon
		  AND p.hideranking <> '1'
		GROUP BY p.flag, p.lastName, p.playerId
		ORDER BY $sort $sortorder, $sort2 $sortorder
		LIMIT 30 OFFSET $start
	");

	$db->query("
		SELECT COUNT(DISTINCT prb.playerId)
		FROM hlstats_Players_Ribbons prb
		JOIN hlstats_Players p
			ON p.playerId = prb.playerId AND p.game = '$game'
		WHERE prb.ribbonId = $ribbon
		  AND p.hideranking <> '1'
	");
	list($numitems) = $db->fetch_row();


if (!is_ajax()) {
printSectionTitle('Ribbon Details');
?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">


<div class="hlstats-award has-winner">
  <div class="hlstats-award-title"><?= htmlspecialchars($act_name)?></div>
  <div class="hlstats-award-icon">
	<?php
	$img = IMAGE_PATH."/games/$game/ribbons/$image";
	if (!is_file($img))
	{
		$img = IMAGE_PATH.'/award.png';
	}
	echo "<img src=\"$img\" alt=\"$act_name\" />";
    ?></div>
</div>
  </section>
</div>



<div id="ribboninfo">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('playerName',$sort,$sortorder) ?>"><?= headerUrl('playerName', ['sort','sortorder'], 'ribboninfo') ?>Player</a></th>
        <th class="nowrap<?= isSorted('numawards',$sort,$sortorder) ?>"><?= headerUrl('numawards', ['sort','sortorder'], 'ribboninfo') ?>Daily Awards</a></th>
        <th class="nowrap<?= isSorted('awardName',$sort,$sortorder) ?>"><?= headerUrl('awardName', ['sort','sortorder'], 'ribboninfo') ?>Name</a></th>
    </tr>
    <?php
        $i= 1 + $start;

        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left">
                      <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>
                      <a href="?mode=playerinfo&amp;player='.$res['playerId'].'" title=""></span><span class="hlstats-name">'.htmlspecialchars($res['playerName']).' &nbsp;</span></a>
                  </td>
                  <td class="nowrap">'.$res['numawards'].' times</td>
                  <td class="nowrap"><span class="hlstats-name">'.htmlspecialchars($awardName).'</span></td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'ribboninfo');

  if (is_ajax()) exit;
  ?>
</div>

<div>
<a href="?mode=awards&game=<?=$game?>&tab=ribbons#Ribbons">&larr;&nbsp;Ribbons</a>
</div>

