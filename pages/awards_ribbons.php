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

// select the available ribbons, with the players who have them (hidden players left out). Only this game's ribbons
// are counted. Ribbons change only when hlstats-awards.pl gives them, and it writes ribbons_date once they are all
// given (awards_d_date with an older script): the list is kept until then (delete cache/hlstatsz_ribbons_*.json to
// see a change made in between).
$ribbons = null;
$awardsDate = (string) (($g_options['ribbons_date'] ?? '') ?: ($g_options['awards_d_date'] ?? ''));
$cacheFile = './cache/hlstatsz_ribbons_' . md5($game) . '.json';
$saved = json_decode((string) @file_get_contents($cacheFile), true);
if (is_array($saved) && ($saved['date'] ?? null) === $awardsDate && is_array($saved['ribbons'] ?? null)) {
    $ribbons = $saved['ribbons'];
}
if (!is_array($ribbons)) {
    $db->query("
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
            FROM hlstats_Ribbons gr
            JOIN hlstats_Players_Ribbons pr ON pr.ribbonId = gr.ribbonId
            JOIN hlstats_Players p ON p.playerId = pr.playerId
            WHERE gr.game = '$game'
              AND p.hideranking <> '1'
            GROUP BY pr.ribbonId
        ) pr
          ON pr.ribbonId = r.ribbonId
        WHERE r.game = '$game'
          AND r.special = 0
        ORDER BY r.awardCount, r.ribbonName, r.awardCode
    ");
    $ribbons = $db->fetch_row_set() ?: array();
    if (!is_dir('./cache')) @mkdir('./cache', 0755, true);
    @file_put_contents($cacheFile, json_encode(array('date' => $awardsDate, 'ribbons' => $ribbons), JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
}
if (!$ribbons) return;
?>

<?php printSectionTitle(t('title.awards.ribbons')); ?>
<div class="hlstats-cards-grid">
<?php
    $c   = 0;
    $cnt = -1;
    foreach ($ribbons as $r)
    {
        if ($cnt != $r['awardCount'])
        {
            $c++;
            if ($cnt != -1) {
                echo '</div>';
                echo '</section>';
            }
            $cnt = $r['awardCount'];
            echo '<section class="hlstats-section hlstats-card hlstats-card-foot">';
            echo '<div class="hlstats-card-title">'.t('ribbon.class',
                ["{number}" => $c,
                 "{count}"  => $cnt]).'</div>';
            echo '<div class="hlstats-center">';
        }
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
            $achvd = '<a href="hlstats.php?mode=ribboninfo&amp;ribbon='.$r['ribbonId'].'&amp;game='.$game.'">'.t('achieved.by', ["{count}" => $r['achievedcount']]).'</a>';
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

