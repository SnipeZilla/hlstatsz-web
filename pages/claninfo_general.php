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

$is_Ajax = (isset($_GET['ajax']) && $_GET['ajax'] == 'members');

if (!$is_Ajax) {
$days    = (int) $g_options['DeleteDays'];
$members = (int) $clandata['nummembers'];
$kills   = (int) $clandata['kills'];

// Rank among the clans of the game, counted as the clan rankings count it (clans of two ranked players or more)
list($rank1, $rank2, $order2) = $g_options['rankingtype'] !== 'kills' ? array('skill', 'kills', 'DESC') : array('kills', 'deaths', 'ASC');
$db->query("
    SELECT
        rank_position
    FROM (
        SELECT
            clan,
            RANK() OVER (ORDER BY AVG($rank1) DESC, SUM($rank2) $order2) AS rank_position
        FROM
            hlstats_Players
        WHERE
            hideranking = 0
            AND lastAddress <> ''
            AND game = '$game'
            AND clan > 0
        GROUP BY
            clan
        HAVING
            COUNT(playerId) > 1
    ) AS ranked
    WHERE
        clan = $clan
");
list($clanRank) = $db->fetch_row() ?: array(null);

$db->query("SELECT name FROM hlstats_Games WHERE code = '$game'");
list($gameName) = $db->fetch_row() ?: array('');

// Favorite server, map and weapon of its members (from the events kept)
$db->query("
    SELECT
        hlstats_Events_Entries.serverId,
        hlstats_Servers.name,
        COUNT(hlstats_Events_Entries.serverId) AS cnt
    FROM
        hlstats_Events_Entries
    INNER JOIN
        hlstats_Servers
    ON
        hlstats_Servers.serverId=hlstats_Events_Entries.serverId
    INNER JOIN
        hlstats_Players
    ON
        (hlstats_Events_Entries.playerId=hlstats_Players.playerId)
    WHERE
        clan=$clan
    GROUP BY
        hlstats_Events_Entries.serverId
    ORDER BY
        cnt DESC
    LIMIT 1
");
list($favServerId, $favServerName) = $db->fetch_row() ?: array(0, '');

$db->query("
    SELECT
        hlstats_Events_Entries.map,
        COUNT(map) AS cnt
    FROM
        hlstats_Events_Entries
    INNER JOIN
        hlstats_Players
    ON
        (hlstats_Events_Entries.playerId=hlstats_Players.playerId)
    WHERE
        clan=$clan
    GROUP BY
        hlstats_Events_Entries.map
    ORDER BY
        cnt DESC
    LIMIT 1
");
list($favMap) = $db->fetch_row() ?: array('');

$db->query("
    SELECT
        hlstats_Events_Frags.weapon,
        hlstats_Weapons.name,
        COUNT(hlstats_Events_Frags.weapon) AS kills,
        SUM(hlstats_Events_Frags.headshot=1) as headshots
    FROM
        hlstats_Events_Frags
    INNER JOIN
        hlstats_Weapons
    ON
        hlstats_Weapons.code = hlstats_Events_Frags.weapon
    INNER JOIN
        hlstats_Players
    ON
        hlstats_Events_Frags.killerId=hlstats_Players.playerId
    WHERE
        clan=$clan
    AND
        hlstats_Weapons.game='$game'
    GROUP BY
        hlstats_Events_Frags.weapon
    ORDER BY
        kills desc, headshots desc
    LIMIT 1
");
list($favWeapon, $favWeaponName) = $db->fetch_row() ?: array('', '');

// The clan's badge in place of an avatar: its tag, else the initials of its name
$badge = trim((string) $clandata['tag']);
if ($badge === '') {
    foreach (array_slice(preg_split('/\s+/', trim($clandata['name'])), 0, 3) as $word) {
        $badge .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
    }
}
// its letters sized to fit the badge on one line (a bold letter is about .62 of the font size wide)
$badgeSize = max(11, min(30, (int) floor(88 / (max(1, mb_strlen($badge, 'UTF-8')) * .62))));
$activity  = max(0, (int) $clandata['activity']);   // -1: no activity recorded

$homepage = trim((string) $clandata['homepage']);
if ($homepage !== '' && !preg_match('#^https?://#i', $homepage)) {
    $homepage = 'https://' . $homepage;
}
$homepage = filter_var($homepage, FILTER_VALIDATE_URL) ? $homepage : '';

// Headline tiles: label, value, note
$hours = fn($seconds) => nf(floor($seconds / 3600)) . ' h';
$tiles = array(
    array(tLabel('total.kills'), nf($kills), $members ? tLabel('avg.kills') . ' ' . nf($kills / $members) : ''),
    array(tLabel('total.deaths'), nf($clandata['deaths']), ''),
    array(tLabel('kills.death'), $clandata['deaths'] ? sprintf('%.2f', $kills / $clandata['deaths']) : '-', ''),
    array(tLabel('headshots.kill'), $kills ? round($clandata['headshots'] / $kills * 100) . '%' : '-', tLabel('headshots') . ' ' . nf($clandata['headshots'])),
    array(tLabel('kills.minute'), $clandata['connection_time'] > 0 ? sprintf('%.2f', $kills / ($clandata['connection_time'] / 60)) : '-', ''),
    array(tLabel('total.time'), $hours($clandata['connection_time']), $members ? tLabel('avg.time') . ' ' . $hours($clandata['connection_time'] / $members) : ''),
);
$tip = fn($key) => ' data-tooltip="' . htmlspecialchars(tLabel($key), ENT_QUOTES) . '"';

printSectionTitle(t('title.clan.info'));
?>
<section class="hlstats-section hlstats-card hlstats-profile">
    <div class="hlstats-profile-main">
        <div class="hlstats-profile-identity">
            <span class="hlstats-profile-avatar hlstats-profile-badge" style="--badge-size: <?= $badgeSize ?>px"<?= $clandata['tag'] ? $tip('tag') : ' aria-hidden="true"' ?>><?= htmlspecialchars($badge, ENT_COMPAT) ?></span>
            <div class="hlstats-profile-info">
                <div class="hlstats-profile-name">
                    <span><?= htmlspecialchars($clandata['name'], ENT_COMPAT) ?></span>
<?php if ($gameName) { ?>
                    <span class="hlstats-pill neutral"><?= htmlspecialchars($gameName, ENT_COMPAT) ?></span>
<?php } ?>
                </div>
                <div class="hlstats-profile-meta">
                    <span><?= svgIcon('users', 15) . t('members') . ' ' . nf($members) ?></span>
<?php if ($clandata['last_event']) { ?>
                    <span<?= $tip('last.connect') ?>><?= svgIcon('clock', 15) . formatDate($clandata['last_event'], IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT) ?></span>
<?php } ?>
                </div>
<?php if ($homepage) { ?>
                <div class="hlstats-profile-chips">
                    <a class="hlstats-chip" href="<?= htmlspecialchars($homepage, ENT_QUOTES) ?>" target="_blank" rel="noopener nofollow"<?= $tip('homepage') ?>><?= svgIcon('globe', 14) . htmlspecialchars(preg_replace('#^https?://(www\.)?#i', '', rtrim($homepage, '/')), ENT_COMPAT) ?></a>
                </div>
<?php } ?>
            </div>
        </div>

        <div class="hlstats-profile-headline">
            <div>
                <span class="hlstats-profile-label"><?= tLabel('rank') ?></span>
                <span class="hlstats-profile-big"><?= $clanRank ? '#' . nf($clanRank) : '-' ?></span>
            </div>
<?php if ($g_options['rankingtype'] != 'kills') { ?>
            <div>
                <span class="hlstats-profile-label"><?= tLabel('avg.points') ?></span>
                <span class="hlstats-profile-big"><?= nf($clandata['avgskill']) ?></span>
            </div>
<?php } ?>
            <div class="hlstats-profile-activity">
                <span class="hlstats-profile-label"><?= tLabel('activity') ?></span>
                <span class="hlstats-profile-big"><?= $activity ?>%</span>
                <meter min="0" max="100" low="25" high="50" optimum="75" value="<?= $activity ?>"></meter>
            </div>
        </div>
    </div>
</section>

<?php kpiTiles($tiles, 'hlstats-profile-kpis'); ?>

<div class="hlstats-cards-grid">
<?php profileFavorites($game, $realgame ?? '', array('server_id' => $favServerId, 'server_name' => $favServerName, 'map' => $favMap, 'weapon' => $favWeapon, 'weapon_name' => $favWeaponName), $days); ?>
<?php
ob_flush();
flush();

if ($g_options['show_google_map'] == 1) { ?>
<section class="hlstats-section hlstats-card">
    <div class="hlstats-card-head">
        <div class="hlstats-card-title"><?= t('player.location') ?></div>
    </div>
    <div id="map" style="margin:0 auto;width:100%;height:100%;min-height:380px;"></div>
</section>
<?php } ?>
</div>
<?php
}
    $sortorder = valid_request($_GET['sortorder'] ?? '', false);
    $sort      = valid_request($_GET['sort'] ?? '', false);

    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
    }
    $col = array("lastName","rank_position","skill","kills","percent","deaths","activity","kpd","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = 'rank_position';
        $sortorder = "ASC";
    }
    
    $sort2 = ($sort == $rank_type2? $rank_type1: $rank_type2);

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 15 : 0;

    $result = $db->query("
        WITH RankedPlayers AS (
            SELECT
                RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 DESC) AS rank_position,
                playerId,
                lastName,
                country,
                flag,
                skill,
                connection_time,
                kills,
                deaths,
                clan,
                last_event,
                ROUND(IF(deaths=0, 0, kills/deaths), 2) AS kpd,
                ROUND(
                    kills / IF(" . (int)$clandata['kills'] . " = 0, 1, " . (int)$clandata['kills'] . ") * 100,
                    2
                ) AS percent,
                activity
            FROM hlstats_Players
            WHERE hideranking = 0
              AND lastAddress <> ''
              AND game = '".$game."'
        ),
        ClanPlayers AS (
            SELECT *
            FROM RankedPlayers
            WHERE clan = $clan
        )
        SELECT
            *,
            COUNT(*) OVER() AS total_rows
        FROM ClanPlayers
        ORDER BY $sort $sortorder, $sort2 $sortorder
        LIMIT 15 OFFSET $start
    ");



    if (!$is_Ajax) {
 
printSectionTitle(t('title.members'));

?>

    <div id="members">
<?php 

}

echo '<div class="responsive-table">
<table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'members') .t('th.rank').'</a></th>
        <th class="hlstats-main-column left'. isSorted('lastName', $sort, $sortorder) .'">'. headerUrl('lastName',['sort','sortorder'],'members') .t('player').'</a></th>';
        if ($g_options['rankingtype']!='kills') {
            echo '<th class="'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'members') .t('th.points').'</a></th>
                  <th class="hide'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'members') .t('th.kills').'</a></th>';
        } else {
            echo '<th class="'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'members') .t('th.kills').'</a></th>';
        }
        echo '
        <th class="hide-2'. isSorted('percent', $sort, $sortorder) .'">'. headerUrl('percent',['sort','sortorder'],'members') .t('th.clan.kills').'</a></th>
        <th class="hide-1'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'members') .'Deaths</a></th>
        <th class="hide'. isSorted('kpd', $sort, $sortorder) .'">'. headerUrl('kpd',['sort','sortorder'],'members') .t('th.kd').'</a></th>
        <th class="hide-2'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'members') .t('th.activity').'</a></th>
        <th class="hide-3'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'members') .t('th.connection.time').'</a></th>
    </tr>';



     while ($res = $db->fetch_array($result))
     {
         $total   = $res['total_rows'];
         $time    =  TimeStamp($res['connection_time']);
         $sign    = '';
         $class   = ' skill';
         if ($res['last_skill_change'] > 0) {
             $sign = '+';
             $class .= ' up green';
         }
         if ($res['last_skill_change'] < 0) {
             $class .= ' down red';
         }
         echo '
         <tr>
            <td class="nowrap right">'.$res['rank_position'].'</td>
            <td class="left'.$class.'" >';
            if ($g_options['countrydata']) {
              echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
            }
            echo '<a href="?mode=playerinfo&amp;player='.$res['playerId'].'" ><span class="hlstats-name">'.htmlspecialchars(html_entity_decode($res['lastName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT).'&nbsp;</span></a>
             </td>'
            .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap">'.nf($res['skill']).'<td class="nowrap hide">'.$res['kills'].'</td></td>'):
                                                      '<td class="nowrap">'.$res['kills'].'</td>').
             '<td class="meter-ratio nowrap hide-2">
                 <div class="meter-container">
                   <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['percent'].'"></meter>
                   <div class="meter-value">'.$res['percent'].'%</div>
                 </div>
             </td>
             <td class="nowrap hide-1">'.nf($res['deaths']).'</td>
             <td class="nowrap hide">'.$res['kpd'].'</td>
             <td class="meter-ratio nowrap hide-2">
                 <div class="meter-container">
                   <meter min="0" max="100" low="25" high="50" optimum="75" value="'.max(0, (int) $res['activity']).'" data-tooltip="'.htmlspecialchars(formatDate($res['last_event']), ENT_QUOTES).'"></meter>
                   <div class="meter-value">'.max(0, (int) $res['activity']).'%</div>
                 </div>
             </td>
             <td class="nowrap hide-3">'.$time.'</td>
         </tr>';
     }

    echo  '</table></div>'.
          Pagination($total, $_GET['page'] ?? 1, 15, 'page', true, 'members');



if ($is_Ajax) exit();
?>
</div>