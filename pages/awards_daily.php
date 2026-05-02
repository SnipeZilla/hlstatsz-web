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

// Daily Awards - WeaponImages und auch als Icondarstellung

$resultAwards = $db->query("
    SELECT
        hlstats_Awards.awardId,
        hlstats_Awards.awardType,
        hlstats_Awards.code,
        hlstats_Awards.name,
        hlstats_Awards.verb,
        hlstats_Awards.d_winner_id,
        hlstats_Awards.d_winner_count,
        hlstats_Players.lastName AS d_winner_name,
        hlstats_Players.flag AS flag,
        hlstats_Players.country AS country
    FROM
        hlstats_Awards
    LEFT JOIN hlstats_Players ON
        hlstats_Players.playerId = hlstats_Awards.d_winner_id
    WHERE
        hlstats_Awards.game='$game'
    ORDER BY
        hlstats_Awards.name
");

$result = $db->query("
    SELECT
        IFNULL(value, 1)
    FROM
        hlstats_Options
    WHERE
        keyname='awards_numdays'
");

if ($db->num_rows($result) == 1)
    list($awards_numdays) = $db->fetch_row($result);
else
    $awards_numdays = 1;

$result = $db->query("
    SELECT
        DATE_FORMAT(value, '%W %e %b'),
        DATE_FORMAT( DATE_SUB( value, INTERVAL $awards_numdays DAY ) , '%W %e %b' )
    FROM
        hlstats_Options
    WHERE
        keyname='awards_d_date'
");
list($awards_d_date, $awards_s_date) = $db->fetch_row($result);

?>

<?php printSectionTitle((($awards_numdays == 1) ? 'Daily' : $awards_numdays.'Day')." Awards ($awards_d_date)"); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-center">

<?php
while ($r = $db->fetch_array($resultAwards))
{
    if ($image = getImage("/games/$game/dawards/".strtolower($r['awardType'].'_'.$r['code'])))
    {
        $img = $image['url'];
    }
    elseif ($image = getImage("/games/$realgame/dawards/".strtolower($r['awardType'].'_'.$r['code'])))
    {
        $img = $image['url'];
    }
    else
    {
        $img = IMAGE_PATH.'/award.png';
    }
    $weapon = '<a href="hlstats.php?mode=dailyawardinfo&amp;award='.$r['awardId']."&amp;game=$game\"><img src=\"$img\" alt=\"".$r['code'].'" /></a>';
    if ($r['d_winner_id'] > 0) {
        if ($g_options['countrydata'])    {
            $imagestring = '<span class="hlstats-flag"><img src="'.getFlag($r['flag']).'" alt="'.$r['flag'].'" /></span>';
        } else {
            $imagestring = '';
        }
        $winnerstring = '<span class="hlstats-name">'.htmlspecialchars($r['d_winner_name'], ENT_COMPAT).'</span>';
        $achvd = "{$imagestring} <a href=\"hlstats.php?mode=playerinfo&amp;player={$r['d_winner_id']}&amp;game={$game}\">{$winnerstring}</a>";
        $wincount = $r['d_winner_count'];
        $class = "";
    } else {
        $achvd = "No Award Winner";
        $wincount= "0";
    }
        
    echo '<div class="hlstats-award'.($wincount>0?' has-winner':'').'">
            <div class="hlstats-award-title">'.$r['name'].'</div>
            <div class="hlstats-award-icon">'.$weapon.'</div>
            <div class="hlstats-award-winner">'.$achvd.'</div>
            <div class="hlstats-award-count">'.$wincount.' '.htmlspecialchars($r['verb']).'</div>
          </div>';
}
?>
    </div>
  </section>
</div>

