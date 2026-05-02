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

$result = $db->query("
    SELECT
        rankName,
        minKills,
        rankId,
        count(playerId) AS obj_count
    FROM
        hlstats_Ranks
    INNER JOIN
        hlstats_Players
    ON (
       hlstats_Ranks.game=hlstats_Players.game
       )    
    WHERE
        kills>=minKills
        AND kills<=maxKills
        AND hideranking <> '1'
        AND hlstats_Ranks.game='$game'
    GROUP BY
        rankName,
        minKills,
        rankId
");

while ($r = $db->fetch_array())
{
    $ranks[$r['rankId']] = $r['obj_count'];
}
$db->free_result();
// select the available ranks
$result = $db->query("
    SELECT
        rankName,
        minKills,
        maxKills,
        rankId,
        image
    FROM
        hlstats_Ranks
    WHERE
        hlstats_Ranks.game='$game'
    ORDER BY
        minKills
");
?>


<?php printSectionTitle('Ranks'); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-center">

<?php
    while ($r = $db->fetch_array())
    {
  
        $image = getImage('/ranks/'.$r['image'].'_small');
        $link = '<a href="hlstats.php?mode=rankinfo&amp;rank='.$r['rankId']."&amp;game=$game\">";
        if ($image)
        {
            $imagestring = '<img src="'.$image['url'].'" alt="'.$r['image'].'" />';
        }
        else
        {
            $imagestring = 'Player List';
        }
        $wincount=0;
        $achvd = '';
        if ($ranks[$r['rankId']] > 0)
        {
            $imagestring = "$link$imagestring</a>";
            $achvd = 'Achieved by '.$ranks[$r['rankId']].' Players';
            $wincount=1;
        }
    echo '<div class="hlstats-award'.($wincount>0?' has-winner':'').'">
            <div class="hlstats-award-title">'.htmlspecialchars($r['rankName']).'</div>
            <div class="hlstats-award-count">('.$r['minKills'].'-'.$r['maxKills'].' kills)</div>
            <div class="hlstats-award-icon">'.$imagestring.'</div>'
            .($achvd? '<div class="hlstats-award-count">'.$achvd.'</div>':'').
          '</div>';

    }

?>
    </div>
  </section>
</div>