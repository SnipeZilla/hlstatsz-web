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
	// Rank Details

	$rank = valid_request($_GET['rank'] ?? '', true) or error(t('error.no.uniqueid'));
	
	$db->query("
		SELECT
			rankName,
			minKills,
			maxKills
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

    $total = 0;

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $col = array("rank_position","skill","kills","playerName");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $page = max(1, (int)($_GET['page'] ?? 1));

    // The page with each player's rank among the players of the rank (includes/players_rank.php)
    require_once INCLUDE_PATH . '/players_rank.php';
    $ranking = isset($actiondata)
        ? rankInfoPlayers($game, (int) $actiondata['minKills'], (int) $actiondata['maxKills'], $sort, $sortorder, $page)
        : array('rows' => array(), 'total' => 0);
    $numitems = $ranking['total'];
    $total    = $numitems;

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

		printSectionTitle(t('title.rank.details'));
?>
<div id="rankinfo">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['sort','sortorder'], 'rankinfo') ?>Rank</a></th>
        <th class="hlstats-main-description left<?= isSorted('playerName',$sort,$sortorder) ?>"><?= headerUrl('playerName', ['sort','sortorder'], 'rankinfo') ?>Player</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['sort','sortorder'], 'rankinfo') ?>Kills</a></th>
        <th class="<?= isSorted('skill',$sort,$sortorder) ?>"><?= headerUrl('skill', ['sort','sortorder'], 'rankinfo') ?>Skill</a></th>
    </tr>
    <?php
        foreach ($ranking['rows'] as $res)
        {
            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="hlstats-main-description left">
                       <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>
                       <a href="'.$g_options['scripturl'].'?mode=playerinfo&amp;player='.$res['playerId'].'"><span class="hlstats-name">'.htmlspecialchars(html_entity_decode($res['playerName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT).'</span></a>
                   </td>
                  <td class="nowrap">'.$res['kills'].'</td>
                  <td class="nowrap">'.$res['skill'].'</td>
                  </tr>';
        }
   ?>
   </table></div>
   <?php
       echo Pagination($total, $page, 30, 'page', true, 'rankinfo');

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
<div class="hlstats-note"><a href="?mode=awards&amp;game=<?=$game?>&tab=ranks#ranks">&larr;&nbsp;<?= t('goto.rank.stats') ?></a></div>
