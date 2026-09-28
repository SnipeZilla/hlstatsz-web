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

if (empty($_GET['ajax'])) {
$days = (int) $g_options['DeleteDays'];
// "... in the last N days": counted from the events HLstatsZ still keeps (they are deleted after DeleteDays); none when no event is left
$recent  = fn($value) => $days && $value !== '-' ? t('player.recent', ['{value}' => $value, '{days}' => $days]) : '';
$percent = fn($ratio) => is_numeric($ratio) ? round($ratio * 100) . '%' : '-';
$own     = isset($_SESSION['ID64']) && $_SESSION['ID64'] == $coid;

// Favorite server, map and weapon (from the events kept)
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
        hlstats_Servers.serverId = hlstats_Events_Entries.serverId
    WHERE
        hlstats_Events_Entries.playerId = '$player'
    GROUP BY
        hlstats_Events_Entries.serverId
    ORDER BY
        cnt DESC
    LIMIT 1
");
list($favServerId, $favServerName) = $db->fetch_row();

$db->query("
    SELECT
        hlstats_Events_Entries.map,
        COUNT(map) AS cnt
    FROM
        hlstats_Events_Entries
    WHERE
        hlstats_Events_Entries.playerId = '$player'
    GROUP BY
        hlstats_Events_Entries.map
    ORDER BY
        cnt DESC
    LIMIT 1
");
list($favMap) = $db->fetch_row();

$db->query("
    SELECT
        hlstats_Events_Frags.weapon,
        hlstats_Weapons.name,
        COUNT(hlstats_Events_Frags.weapon) AS kills,
        SUM(hlstats_Events_Frags.headshot=1) as headshots
    FROM
        hlstats_Events_Frags
    LEFT JOIN
        hlstats_Weapons
    ON
        hlstats_Weapons.code = hlstats_Events_Frags.weapon
    WHERE
        hlstats_Events_Frags.killerId=$player
    GROUP BY
        hlstats_Events_Frags.weapon,
        hlstats_Weapons.name
    ORDER BY
        kills desc, headshots desc
    LIMIT 1
");
list($favWeapon, $favWeaponName) = $db->fetch_row() ?: array('', '');

// Recent kills per death, headshots per kill and accuracy (from the events kept)
$db->query("
    SELECT
        IFNULL(ROUND(SUM(hlstats_Events_Frags.killerId = '$player') /
        IF(SUM(hlstats_Events_Frags.victimId = '$player') = 0, 1, SUM(hlstats_Events_Frags.victimId = '$player')), 2), '-')
    FROM
        hlstats_Events_Frags
    WHERE
        (hlstats_Events_Frags.killerId = '$player' OR hlstats_Events_Frags.victimId = '$player')
");
list($realkpd) = $db->fetch_row();

$db->query("
    SELECT
        IFNULL(SUM(hlstats_Events_Frags.headshot=1) / COUNT(*), '-')
    FROM
        hlstats_Events_Frags
    WHERE
        hlstats_Events_Frags.killerId = '$player'
");
list($realhpk) = $db->fetch_row();

$db->query("
    SELECT
        IFNULL(ROUND((SUM(hlstats_Events_Statsme.hits) / SUM(hlstats_Events_Statsme.shots) * 100), 2), 0.0) AS accuracy,
        SUM(hlstats_Events_Statsme.shots) AS shots,
        SUM(hlstats_Events_Statsme.kills) AS kills
    FROM
        hlstats_Events_Statsme
    WHERE
        hlstats_Events_Statsme.playerId='$player'
");
list($smAccuracy, $smShots, $smKills) = $db->fetch_row();

$db->query("SELECT COUNT(*) FROM hlstats_Players_Awards WHERE hlstats_Players_Awards.playerId = $player");
list($numawards) = $db->fetch_row();

// Rank, as the headline shows it
if ($playerdata['hideranking'] == 2) {
    $rank = '<span class="red">' . t('karma.banned') . '</span>';
} elseif ($playerdata['hideranking'] == 1) {
    $rank = 'Hidden';
} else {
    $rank = '#' . nf($playerdata['rank_position']);
}

// Headline tiles: label, value, note
$tiles = array(
    array(tLabel('kills'), nf($playerdata['kills']), $recent('+' . nf($realkills))),
    array(tLabel('deaths'), nf($playerdata['deaths']), $recent('+' . nf($realdeaths))),
    array(tLabel('kills.death'), $playerdata['kpd'], $recent($realkpd)),
    array(tLabel('headshots.kill'), $percent($playerdata['hpk']), $recent($percent($realhpk))),
    array(tLabel('kills.minute'), $playerdata['connection_time'] > 0 ? sprintf('%.2f', $playerdata['kills'] / ($playerdata['connection_time'] / 60)) : '-', ''),
    array(tLabel('longest.ks'), nf($playerdata['kill_streak']), tLabel('longest.ds') . ' ' . nf($playerdata['death_streak'])),
);

// The other statistics: label, value, tooltip (rows without data are left out)
$stats   = array();
$stats[] = array(tLabel('headshots'), nf($playerdata['headshots'] ?: $realheadshots), $recent(nf($realheadshots)));
if ($smKills > 0) {
    $stats[] = array(tLabel('shots.kill'), sprintf('%.2f', $smShots / $smKills), '');
}
if ($playerdata['shots'] > 0) {
    // acc is hits / shots
    $stats[] = array(tLabel('weapon.accuracy'), sprintf('%.1f%%', $playerdata['acc'] * 100), $smShots > 0 ? $recent(round($smAccuracy) . '%') : '');
} elseif ($smShots > 0) {
    $stats[] = array(tLabel('weapon.accuracy'), round($smAccuracy) . '%', $recent(round($smAccuracy) . '%'));
}
$stats[] = array(tLabel('suicides'), nf($playerdata['suicides']), '');
$stats[] = array(tLabel('teammate.kills'), nf($playerdata['teamkills']), $recent(nf($realteamkills)));
if ($playerdata['createdate']) {
    $stats[] = array(tLabel('first.connect'), formatDate($playerdata['createdate'], IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE), '');
}
if ($ping = (int) ($playerdata['lastPing'] ?? 0)) {
    $stats[] = array(tLabel('last.ping'), t('last.ping.value', ['{ping}' => $ping, '{latency}' => (int) round($ping / 2)]), '');
}
if ($playerdata['homepage']) {
    $stats[] = array(tLabel('homepage'), '<a href="' . htmlspecialchars($playerdata['homepage'], ENT_QUOTES) . '" target="_blank" rel="noopener nofollow">'
        . htmlspecialchars(preg_replace('#^https?://(www\.)?#i', '', rtrim($playerdata['homepage'], '/')), ENT_COMPAT) . '</a>', '');
}

$scripturl = $g_options['scripturl'];
?>

<?php printSectionTitle(t('title.player.info')); ?>
<section class="hlstats-section hlstats-card hlstats-profile">
    <div class="hlstats-profile-main">
        <div id="steam-profile-<?= (int)$player ?>" class="hlstats-profile-identity">
<?php playerIdentity($playerdata, (string) $uqid, (string) $coid, array('avatar' => IMAGE_PATH . '/unknown.jpg', 'status' => '', 'member_since' => t('loading'), 'vac' => false)); ?>
        </div>

        <div class="hlstats-profile-headline">
            <div>
                <span class="hlstats-profile-label"><?= tLabel('rank') ?></span>
                <span class="hlstats-profile-big"><?= $rank ?></span>
            </div>
<?php if ($g_options['rankingtype'] != 'kills') { ?>
            <div>
                <span class="hlstats-profile-label"><?= tLabel('points') ?></span>
                <span class="hlstats-profile-big"><?= nf($playerdata['skill']) ?></span>
            </div>
<?php } ?>
            <div class="hlstats-profile-activity">
                <span class="hlstats-profile-label"><?= tLabel('activity') ?></span>
                <span class="hlstats-profile-big"><?= max(0, (int) $playerdata['activity']) ?>%</span>
                <meter min="0" max="100" low="25" high="50" optimum="75" value="<?= max(0, (int) $playerdata['activity']) ?>"></meter>
            </div>
        </div>
    </div>

    <div class="hlstats-profile-actions">
        <a class="hlstats-btn" href="<?= $scripturl ?>?mode=playerhistory&amp;player=<?= $player ?>"><?= svgIcon('list') . t('events') ?></a>
        <a class="hlstats-btn" href="<?= $scripturl ?>?mode=playersessions&amp;player=<?= $player ?>"><?= svgIcon('history') . t('sessions') ?></a>
        <a class="hlstats-btn" href="<?= $scripturl ?>?mode=playerawards&amp;player=<?= $player ?>"><?= svgIcon('medal') . t('awards') ?><span class="hlstats-count"><?= nf($numawards) ?></span></a>
<?php if ($g_options['nav_globalchat'] == 1) { ?>
        <a class="hlstats-btn" href="<?= $scripturl ?>?mode=chathistory&amp;player=<?= $player ?>"><?= svgIcon('chat') . t('chat') ?></a>
<?php } ?>
<?php if ($own) { ?>
        <details class="hlstats-profile-edit"<?= $error ? ' open' : '' ?>>
            <summary class="hlstats-btn"><?= svgIcon('pencil') . t('edit.profile') ?></summary>
            <form method="post" class="hlstats-profile-editform">
                <label><?= tLabel('member.clan') ?><input type="text" name="clan_name" value="<?= htmlspecialchars($playerdata['clan_name'] ?? '', ENT_QUOTES) ?>" placeholder="[TAG] Name"></label>
                <label><?= tLabel('homepage') ?><input type="text" name="homepage" value="<?= htmlspecialchars($playerdata['homepage'] ?? '', ENT_QUOTES) ?>" placeholder="https://"></label>
                <div><input type="submit" value="<?= t('update') ?>"><?php if ($error) echo ' <span class="red">' . $error . '</span>'; ?></div>
            </form>
        </details>
<?php } ?>
        <a class="hlstats-profile-find" href="<?= $scripturl ?>?mode=search&amp;st=player&amp;q=<?= $pl_urlname ?>"><?= svgIcon('search') . t('find.same.name') ?></a>
    </div>
</section>

<?php
kpiTiles($tiles, 'hlstats-profile-kpis');
?>

<div class="hlstats-cards-grid">
<?php profileFavorites($game, $realgame, array('server_id' => $favServerId, 'server_name' => $favServerName, 'map' => $favMap, 'weapon' => $favWeapon, 'weapon_name' => $favWeaponName), $days); ?>

<section class="hlstats-section hlstats-card">
    <div class="hlstats-card-head">
        <div class="hlstats-card-title"><?= t('statistics.summary') ?></div>
    </div>
    <dl class="hlstats-profile-list">
<?php foreach ($stats as list($label, $value, $tip)) { ?>
        <div><dt><?= $label ?></dt><dd<?= $tip !== '' ? ' data-tooltip="' . htmlspecialchars($tip, ENT_QUOTES) . '"' : '' ?>><?= $value ?></dd></div>
<?php } ?>
    </dl>
</section>
</div>
<?php
ob_flush();
flush();

// Current rank & rank history
$db->query("
    SELECT hlstats_Ranks.rankName, hlstats_Ranks.image, hlstats_Ranks.minKills
    FROM hlstats_Ranks
    WHERE hlstats_Ranks.minKills <= ".$playerdata['kills']."
      AND hlstats_Ranks.game = '$game'
    ORDER BY hlstats_Ranks.minKills DESC
    LIMIT 1
");
$result = $db->fetch_array();
$rankimage = getImage('/ranks/'.$result['image']);
$rankName = $result['rankName'];
$rankCurMinKills = $result['minKills'];

$db->query("
    SELECT hlstats_Ranks.rankName, hlstats_Ranks.minKills
    FROM hlstats_Ranks
    WHERE hlstats_Ranks.minKills > ".$playerdata['kills']."
      AND hlstats_Ranks.game = '$game'
    ORDER BY hlstats_Ranks.minKills
    LIMIT 1
");

// No rank above this one: the bar full, and "Highest rank" in place of the kills needed
$rankTop = $db->num_rows() == 0;
if ($rankTop) {
    $rankKillsNeeded = 0;
    $rankPercent = 100;
} else {
    $result = $db->fetch_array();
    $rankKillsNeeded = $result['minKills'] - $playerdata['kills'];
    $rankPercent = ($playerdata['kills'] - $rankCurMinKills) * 100 / ($result['minKills'] - $rankCurMinKills);
}

$db->query("
    SELECT hlstats_Ranks.rankName, hlstats_Ranks.image
    FROM hlstats_Ranks
    WHERE hlstats_Ranks.minKills <= ".$playerdata['kills']."
      AND hlstats_Ranks.game = '$game'
    ORDER BY hlstats_Ranks.minKills
");

$rankHistory = "";
$db_num_rows = $db->num_rows();

for ($i = 1; $i < $db_num_rows; $i++) {
    $result = $db->fetch_array();
    $histimage = getImage('/ranks/' . $result['image']);
        $rankHistory .= '<div class=" hlstats-rank hlstats-award has-winner">
               <div class="hlstats-award-title">'.$result['rankName'].'</div>
               <div class="hlstats-award-icon"><img src="' . $histimage['url'] . '" alt="' . $result['rankName'] . '" /></div>
              </div>';
}

// The game's banner (or its real game's), behind the current rank's picture
$banner = IMAGE_PATH."/games/$game/banner.jpg";
if (!file_exists($banner)) {
    $banner = IMAGE_PATH."/games/$realgame/banner.jpg";
}
$banner = file_exists($banner) ? $banner : '';
$rankPercentText = nf($rankPercent, 0, '.', '');

 printSectionTitle(t('title.ranks')); ?>
<div class="hlstats-cards-grid hlstats-ranks">
<section class="hlstats-section hlstats-card hlstats-rank-current">
            <div class="hlstats-card-title"><?= t('current.rank') ?></div>
            <div class="hlstats-rank-hero<?= $banner ? ' has-banner' : '' ?>">
<?php if ($banner) { ?>
                <img class="hlstats-rank-banner" src="<?= htmlspecialchars($banner) ?>" alt="" />
<?php } ?>
<?php if ($rankimage) { ?>
                <img class="hlstats-rank-image" src="<?= htmlspecialchars($rankimage['url']) ?>" alt="<?= htmlspecialchars($rankName) ?>" />
<?php } ?>
            </div>
            <div class="hlstats-rank-name"><?= htmlspecialchars($rankName) ?></div>
            <div class="hlstats-card-foot">
                 <div class="hlstats-rankmeter meter-container">
                   <meter min="0" max="100" low="25" high="50" optimum="75" value="<?= $rankPercent ?>"></meter>
                   <div class="meter-value"><?= $rankPercentText ?>%</div>
                 </div>
<?php if ($rankTop) { ?>
                 <div><b><?= t('rank.highest') ?></b></div>
<?php } else { ?>
                 <div><?= t('kills.needed') ?> <b><?= $rankKillsNeeded ?> (<?= $rankPercentText ?>%)</b></div>
<?php } ?>
            </div>
</section>
<section class="hlstats-section hlstats-card">
            <div class="hlstats-card-title"><?= t('rank.history') ?></div>
            <div class="hlstats-card-body hlstats-center hlstats-scrollbar"><?php echo $rankHistory; ?></div>
</section>
</div>
<?php
ob_flush();
flush();

printSectionTitle(t('title.misc.stats')); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-card-title"><?= t('player.trend') ?></div> 
    <?php if (!isset($g_options['chart']) || $g_options['chart'] == 0) {//pChart ?>
    <img src="trend_graph.php?time=<?=time()?>&amp;player=<?= $player ?>" alt="Player Trend Graph" />
    <?php } else { //Chart.js ?>
    <div class="hlstats-chart hlstats-chart-full"
         data-chart="player-trend"
         data-player="<?= (int)$player ?>">
        <div class="hlstats-chart-canvas trend-player"><canvas></canvas></div>
    </div>
    <?php } ?>
  </section>

  <section class="hlstats-section hlstats-card">
    <div class="hlstats-card-title"><?= t('forum.signature') ?></div> 
    <?php
        // The signature and the page it links to (plain URLs, escaped where printed)
        if ($g_options['modrewrite'] == 0) {
            $sigUrl = $script_path . '/sig.php?player_id=' . $player . '&background=' . $g_options['sigbackground'];
        } else {
            $sigUrl = $script_path . '/sig-' . $player . '-' . $g_options['sigbackground'] . '.png';
        }
        $pageUrl = $script_path . '/hlstats.php?mode=playerinfo&player=' . $player;

        // Discord has no signatures: a message where the image line shows as a preview, then the page as a link
        // (<...> keeps that link from a preview of its own, backslashes keep the name's markdown characters as text)
        $discordName = preg_replace('/([\\\\`*_~|\[\]()<>])/', '\\\\$1', html_entity_decode($playerdata['lastName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // name => [code, hint]
        $sigCodes = array(
            'phpBB, SMF'    => array('[url=' . $pageUrl . '][img]' . $sigUrl . '[/img][/url]', ''),
            'XenForo'       => array("[URL='" . $pageUrl . "'][IMG]" . $sigUrl . '[/IMG][/URL]', ''),
            'Invision'      => array('[url="' . $pageUrl . '"][img]' . $sigUrl . '[/img][/url]', ''),
            'Discord'       => array($sigUrl . "\n[" . $discordName . '](<' . $pageUrl . '>)', t('sig.discord.hint')),
            t('image.link') => array($sigUrl, ''),
        );
        $sigFirst = reset($sigCodes)[0];
    ?>
    <div class="hlstats-forum-signature">
        <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES) ?>"><img src="<?= htmlspecialchars($sigUrl, ENT_QUOTES) ?>" alt="<?= htmlspecialchars(t('forum.signature'), ENT_QUOTES) ?>" /></a>
    </div>

    <div class="hlstats-sigcode" data-sigcode>
        <div class="hlstats-sigcode-formats" role="group" aria-label="<?= htmlspecialchars(t('forum.signature'), ENT_QUOTES) ?>">
<?php foreach ($sigCodes as $name => list($code, $hint)) { ?>
            <button type="button" class="hlstats-btn is-small<?= $code === $sigFirst ? ' is-active' : '' ?>" aria-pressed="<?= $code === $sigFirst ? 'true' : 'false' ?>" data-code="<?= htmlspecialchars($code, ENT_QUOTES) ?>" data-hint="<?= htmlspecialchars($hint, ENT_QUOTES) ?>"><?= htmlspecialchars($name, ENT_COMPAT) ?></button>
<?php } ?>
        </div>
        <textarea class="hlstats-sigcode-text" rows="3" readonly aria-label="<?= htmlspecialchars(t('forum.signature'), ENT_QUOTES) ?>" onclick="this.select()"><?= htmlspecialchars($sigFirst, ENT_COMPAT) ?></textarea>
        <div class="hlstats-sigcode-foot">
            <span class="hlstats-sigcode-hint"></span>
            <button type="button" class="hlstats-btn is-small" data-copy="<?= htmlspecialchars($sigFirst, ENT_QUOTES) ?>"><?= svgIcon('copy', 14) . svgIcon('check', 14) . t('copy') ?></button>
        </div>
    </div>
  </section>
</div>

<?php
ob_flush();
flush();

// Awards
$numawards = $db->query("
    SELECT hlstats_Ribbons.awardCode, hlstats_Ribbons.image
    FROM hlstats_Ribbons
    WHERE hlstats_Ribbons.game = '$game'
      AND (hlstats_Ribbons.special = 0 OR hlstats_Ribbons.special = 2)
    GROUP BY hlstats_Ribbons.awardCode, hlstats_Ribbons.image
");

$res=$db->query("
    SELECT a.awardCode, a.ribbonName, a.special, a.image, a.awardCount
    FROM hlstats_Ribbons a
    LEFT JOIN hlstats_Players_Ribbons b
      ON a.ribbonId = b.ribbonId AND a.game = b.game
    WHERE b.playerId = '".$playerdata['playerId']."'
      AND b.game = '".$game."'
");

$ribbonList = '';
$awards_done = array();
while ($result = $db->fetch_array($res)) {
    $ribbonCode = $result['awardCode'];
    if (!isset($awards_done[$ribbonCode])) {
        if (file_exists(IMAGE_PATH."/games/$game/ribbons/".$result['image'])) {
            $image = IMAGE_PATH."/games/$game/ribbons/".$result['image'];
        } elseif (file_exists(IMAGE_PATH."/games/$realgame/ribbons/".$result['image'])) {
            $image = IMAGE_PATH."/games/$realgame/ribbons/".$result['image'];
        } else {
            $image = IMAGE_PATH."/award.png";
        }
$ribbonList .= '
<div class="hlstats-rank hlstats-award has-winner">
    <div class="hlstats-award-icon"><img src="'.$image.'" alt="'.$result['ribbonName'].'" /></div>
    <div class="hlstats-award-title">'.$result['ribbonName'].'</div>
</div>';
   }
}

$awards = array();
$res = $db->query("
    SELECT hlstats_Awards.awardType, hlstats_Awards.code, hlstats_Awards.name
    FROM hlstats_Awards
    WHERE hlstats_Awards.game = '$game'
      AND hlstats_Awards.g_winner_id = $player
    ORDER BY hlstats_Awards.name;
");

while ($r1 = $db->fetch_array()) {
    $tmp_arr = new StdClass;
    $tmp_arr->aType = $r1['awardType'];
    $tmp_arr->code = $r1['code'];
    $tmp_arr->ribbonName = $r1['name'];
    array_push($awards, $tmp_arr);
}

$GlobalAwardsList = '';
foreach ($awards as $a) {
    if ($image = getImage("/games/$game/gawards/".strtolower($a->aType."_$a->code"))) {
        $image = $image['url'];
    } elseif ($image = getImage("/games/$realgame/gawards/".strtolower($a->aType."_$a->code"))) {
        $image = $image['url'];
    } else {
        $image = IMAGE_PATH."/award.png";
    }
$GlobalAwardsList .= '
<div class="hlstats-rank hlstats-award has-winner">
    <div class="hlstats-award-icon"><img src="'.$image.'" alt="'.$a->ribbonName.'" /></div>
    <div class="hlstats-award-title">'.$a->ribbonName.'</div>
</div>';

}

if ($ribbonList != '' || $GlobalAwardsList != '') {

 ob_flush();
 flush();

 printSectionTitle(t('title.awards'));
 ?>
<div class="hlstats-cards-grid">

<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title"><?= t('ribbons') ?></div>
     <div class="hlstats-card-body hlstats-center hlstats-scrollbar"><?php echo $ribbonList; ?></div>
</section>

<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title"><?= t('global.awards') ?></div>
    <div class="hlstats-card-body hlstats-center hlstats-scrollbar"><?php echo $GlobalAwardsList; ?></div>
</section>

</div>


<?php }
}
 ?>
