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
	// Action Details

	$rank = valid_request($_GET['rank'] ?? '', true) or error('No rank ID specified.');
	
	$db->query("
		SELECT
			rankName
		FROM
			hlstats_Ranks
		WHERE
			rankId=$rank
	");
	
	if ($db->num_rows() != 1) {
		$act_name = ucfirst($action);
	} else {
		$actiondata = $db->fetch_array();
		$db->free_result();
		$act_name = $actiondata['rankName'];
	}

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "kills";

    $col = array("skill","kills","playerName");
    if (!in_array($sort, $col)) {
        $sort      = "skill";
        $sortorder = "DESC";
    }

    if ($sort == "kills") {
        $sort2 = "skill";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
  
	$result = $db->query("
		SELECT
			p.skill,
			p.kills,
			p.flag,
			p.lastName AS playerName,
			p.playerId
		FROM hlstats_Players p
		JOIN hlstats_Ranks r
			ON r.rankId = $rank
			AND r.game = p.game
		WHERE
			p.game = '$game' AND
			p.kills >= r.minKills AND
			p.kills <= r.maxKills AND
			p.hideranking <> '1'
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
		LIMIT 30 OFFSET $start
	");

	$db->query("
		SELECT COUNT(p.playerId)
		FROM hlstats_Players p
		JOIN hlstats_Ranks r
			ON r.rankId = $rank
			AND r.game = p.game
		WHERE
			p.game = '$game' AND
			p.kills >= r.minKills AND
			p.kills <= r.maxKills AND
			p.hideranking <> '1'
	");

	list($numitems) = $db->fetch_row();

	$resultRank = $db->query("
		SELECT
			image,
			rankName
		FROM
			hlstats_Ranks
		WHERE
      rankId=$rank;");

	$rankrow = $db->fetch_array();
    if (!is_ajax()) {
?>

<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
   

<?php

        $image = getImage('/ranks/'.$rankrow['image']);

        if ($image) {
            $imagestring = '<img src="'.$image['url'].'" alt="" />';
        } else {
            $imagestring = '';
        }
    echo '<div class="hlstats-award'.($numitems>0?' has-winner':'').'">
            <div class="hlstats-award-title">'.htmlspecialchars($rankrow['rankName']).'</div>
            <div class="hlstats-award-icon">'.$imagestring.'</div>
            <div class="hlstats-award-count">Achieved by '.$numitems.' Players</div>
          </div>';

    

?>

  </section>
</div>

<?php
}
	if ($numitems)
	{
       if (!is_ajax()) {

		printSectionTitle('Rank Details');
?>
<div id="rankinfo">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('playerName',$sort,$sortorder) ?>"><?= headerUrl('playerName', ['sort','sortorder'], 'rank') ?>Player</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['sort','sortorder'], 'rank') ?>Kills</a></th>
        <th class="<?= isSorted('skill',$sort,$sortorder) ?>"><?= headerUrl('skill', ['sort','sortorder'], 'rank') ?>Skill</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left">
                       <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>
                       <a href="'.$g_options['scripturl'].'?mode=playerinfo&amp;player='.$res['playerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['playerName']).' </span></a>
                   </td>
                  </td>
                  <td class="nowrap">'.$res['kills'].'</td>
                  <td class="nowrap">'.$res['skill'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table></div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'rankinfo');

  if (is_ajax()) exit;
  ?>
</div>
<?php
 } else {
?>
    <table>
      <tr>
        <td class="left"><em>No Ranking Player</em></td>
      </tr>
    </table>
<?php
 }
?>
<div><a href="?mode=awards&amp;game=<?=$game?>&tab=ranks#ranks">&larr;&nbsp;Ranks</a></div>
