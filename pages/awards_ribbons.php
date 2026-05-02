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

// select the available ribbons
$result = $db->query("
    SELECT
        r.ribbonId,
        r.ribbonName,
        r.image,
        a.name AS awardName,
        r.awardCount,
        COALESCE(pr.achievedcount, 0) AS achievedcount
    FROM hlstats_Ribbons r
    JOIN hlstats_Awards a
      ON a.code = r.awardCode
     AND a.game = r.game
    LEFT JOIN (
        SELECT pr.ribbonId, COUNT(DISTINCT pr.playerId) AS achievedcount
        FROM hlstats_Players_Ribbons pr
        JOIN hlstats_Players p ON p.playerId = pr.playerId
        WHERE p.hideranking <> '1'
        GROUP BY pr.ribbonId
    ) pr
      ON pr.ribbonId = r.ribbonId
    WHERE r.game = '$game'
      AND r.special = 0
    ORDER BY r.awardCount, r.ribbonName, r.awardCode;
");
if (!$db->num_rows()) return;
?>

<?php printSectionTitle('Ribbons'); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-center">
<?php
    while ($r = $db->fetch_array())
    {
        if (file_exists(IMAGE_PATH."/games/$game/ribbons/".$r['image']))
        {
            $image = IMAGE_PATH."/games/$game/ribbons/".$r['image'];
        }
        elseif (file_exists(IMAGE_PATH."/games/$realgame/ribbons/".$r['image']))
        {
            $image = IMAGE_PATH."/games/$realgame/ribbons/".$r['image'];
        }
        else
        {
            $image = IMAGE_PATH."/award.png";
        }
        $image = '<img src="'.$image.'" alt="'.$r['ribbonName'].'" />';
        $achvd = '';
        $wincount = 0;
        if ($r['achievedcount'] > 0)
        {
            $achvd = '<a href="hlstats.php?mode=ribboninfo&amp;ribbon='.$r['ribbonId'].'&amp;game='.$game.'">Achieved by '.$r['achievedcount'].' players</a>';
            $wincount=1;
        }
        echo '<div class="hlstats-award'.($wincount>0?' has-winner':'').'">
               <div class="hlstats-award-title">'.htmlspecialchars($r['ribbonName']).'</div>
               <div class="hlstats-award-icon">'.$image.'</div>'
               .($achvd? '<div class="hlstats-award-count">'.$achvd.'</div>':'').
              '</div>';
    }
?>
    </div>
  </section>
</div>

