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

require_once INCLUDE_PATH . '/players_rank.php';

// select the available ranks
$db->query("
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
$rankList = $db->fetch_row_set() ?: array();

// Players of each rank (kills from minKills to maxKills, not hidden), in one pass over the game's players on the
// ranking index: INTERVAL() puts each player in his slice of kills between two rank bounds, and each rank adds up its
// slices (so ranks may leave gaps or overlap). Counted once a minute.
$bounds = array();
foreach ($rankList as $r) {
    $bounds[] = (int) $r['minKills'];
    $bounds[] = (int) $r['maxKills'] + 1;
}
$bounds = array_values(array_unique($bounds));
sort($bounds);

$slices = null;
$cacheFile = './cache/hlstatsz_ranks_' . md5($game . '|' . implode(',', $bounds)) . '.json';
if (is_file($cacheFile) && time() - filemtime($cacheFile) < 60) {
    $slices = json_decode((string) @file_get_contents($cacheFile), true);
}
if (!is_array($slices)) {
    $slices = array();
    if ($bounds) {
        $db->query("
            SELECT INTERVAL(kills, " . implode(', ', $bounds) . ") AS slice, COUNT(*)
            FROM hlstats_Players " . rankIndex() . "
            WHERE game = '$game'
            AND hideranking <> 1
            GROUP BY slice
        ");
        while (list($slice, $players) = $db->fetch_row()) {
            $slices[(int) $slice] = (int) $players;
        }
    }
    if (!is_dir('./cache')) @mkdir('./cache', 0755, true);
    @file_put_contents($cacheFile, json_encode($slices), LOCK_EX);
}

// slice i holds the kills from $bounds[i - 1] to $bounds[i] - 1
$ranks = array();
foreach ($rankList as $r) {
    $from = array_search((int) $r['minKills'], $bounds, true) + 1;
    $to   = array_search((int) $r['maxKills'] + 1, $bounds, true);
    $ranks[$r['rankId']] = 0;
    for ($i = $from; $i <= $to; $i++) {
        $ranks[$r['rankId']] += $slices[$i] ?? 0;
    }
}
?>


<?php printSectionTitle(t('title.awards.ranks')); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-center">

<?php
    foreach ($rankList as $r)
    {
  
        $image = getImage('/ranks/'.$r['image']);
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
            $achvd = t('achieved.by', ["{count}" => $ranks[$r['rankId']]]);
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