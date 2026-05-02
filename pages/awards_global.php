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

	$resultAwards = $db->query("
		SELECT
			hlstats_Awards.awardType,
			hlstats_Awards.code,
			hlstats_Awards.name,
			hlstats_Awards.verb,
			hlstats_Awards.g_winner_id,
			hlstats_Awards.g_winner_count,
			hlstats_Players.lastName AS g_winner_name,
			hlstats_Players.flag AS flag,
			hlstats_Players.country AS country
		FROM
			hlstats_Awards
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId = hlstats_Awards.g_winner_id
		WHERE
			hlstats_Awards.game='$game'
		ORDER BY
			hlstats_Awards.name
	");
?>

<?php printSectionTitle('Global Awards'); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-center">

<?php
	while ($r = $db->fetch_array($resultAwards))
	{
		if ($image = getImage("/games/$game/gawards/".strtolower($r['awardType'].'_'.$r['code'])))
		{
			$img = $image['url'];
		}
		elseif ($image = getImage("/games/$realgame/gawards/".strtolower($r['awardType'].'_'.$r['code'])))
		{
			$img = $image['url'];
		}
		else
		{
			$img = IMAGE_PATH.'/award.png';
		}
		$weapon = "<img src=\"$img\" alt=\"".$r['code'].'" />';
		if ($r['g_winner_id'] > 0)
		{
			if ($g_options['countrydata']) {
				$imagestring = '<span class="hlstats-flag"><img src="'.getFlag($r['flag']).'" alt="'.$r['country'].'" /></span>';
			} else {
				$imagestring = '';
			}
			$winnerstring = '<span class="hlstats-name">'.htmlspecialchars($r['g_winner_name'], ENT_COMPAT).'</span>';
			$achvd = "{$imagestring}<a href=\"hlstats.php?mode=playerinfo&amp;player={$r['g_winner_id']}&amp;game={$game}\">{$winnerstring}</a>";
			$wincount = $r['g_winner_count'];			
		} else {
			$achvd = "No Award Winner";
			$wincount= "0";
			$class = " hlstats-award-none";
   
	    } 

    echo '<div class="hlstats-award'.($wincount>0?' has-winner':'').'">
            <div class="hlstats-award-title">'.htmlspecialchars($r['name']).'</div>
            <div class="hlstats-award-icon">'.$weapon.'</div>
            <div class="hlstats-award-winner">'.$achvd.'</div>
            <div class="hlstats-award-count">'.$wincount.' '.htmlspecialchars($r['verb']).'</div>
          </div>';

}
?>
    </div>
  </section>
</div>