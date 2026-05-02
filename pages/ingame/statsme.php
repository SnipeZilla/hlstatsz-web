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

    // Player Details
    
    $player = valid_request(intval($_GET['player'] ?? 0), true);
    $uniqueid  = valid_request(strval($_GET['uniqueid'] ?? ''), false);
    $game = valid_request(strval($_GET['game'] ?? ''), false);
    
    if (!$player && $uniqueid) {
        if (!$game) {
            header('Location: ' . $g_options['scripturl'] . "&mode=search&st=uniqueid&q=$uniqueid");
            exit;
        }
    }

    if (!$player && $game) {
        $db->query("
            SELECT
                playerId
            FROM
                hlstats_PlayerUniqueIds
            WHERE
                uniqueId='$uniqueid'
                AND game='$game'
        ");        
        if ($db->num_rows() < 1) {
            error("No players found matching uniqueId '$uniqueid'");
        }
        list($player) = $db->fetch_row();
        $player = intval($player);
    }

    if (!$game && $player) {
        $db->query("SELECT game
                    FROM hlstats_players
                    WHERE playerId = '$player'
                    LIMIT 1
                ");
        list($game) = $db->fetch_row();
    }



    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'p.skill';
        $rank_type2 = 'p.kills';
        $sort_type2 = 'DESC';
    } else {
        $rank_type1 = 'p.kills';
        $rank_type2 = 'p.deaths';
        $sort_type2 = 'ASC';
    }

    $db->query(" WITH RankedPlayers AS (
                    SELECT
                        RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sort_type2) AS rank_position,
                        p.playerId,
                        p.last_event,
                        p.connection_time,
                        p.game,
                        p.createdate,
                        p.lastName,
                        p.lastPing,
                        p.homepage,
                        p.flag,
                        p.country,
                        p.city,
                        p.blockavatar,
                        p.clan,
                        p.kills,
                        p.deaths,
                        p.skill,
                        p.shots,
                        p.hits,
                        p.headshots,
                        p.suicides,
                        p.last_skill_change,
                        p.kill_streak,
                        p.death_streak,
                        p.teamkills,
                        p.activity,
                        ROUND(IF(p.deaths=0, 0, p.kills/p.deaths), 2) AS kpd,
                        ROUND(IF(p.kills=0, 0, p.headshots/p.kills), 2) AS hpk,
                        ROUND(IF(p.shots=0, 0, p.hits/p.shots), 3) AS acc,
                        CONCAT(c.name) AS clan_name,
                        p.hideranking
                    FROM hlstats_players AS p
                    LEFT JOIN
                        hlstats_Clans AS c
                    ON
                        c.clanId = p.clan
                    WHERE p.lastAddress <> ''
                          AND p.hideranking = 0
                          AND p.game = '$game'
                )
                SELECT *
                FROM RankedPlayers
                WHERE playerId = '$player'
                LIMIT 1
               ");

	if ($db->num_rows() != 1) {
   $db->query(" WITH RankedPlayers AS (
                    SELECT
                        RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sort_type2) AS rank_position,
                        p.playerId,
                        p.last_event,
                        p.connection_time,
                        p.game,
                        p.createdate,
                        p.lastName,
                        p.lastPing,
                        p.homepage,
                        p.flag,
                        p.country,
                        p.city,
                        p.blockavatar,
                        p.clan,
                        p.kills,
                        p.deaths,
                        p.skill,
                        p.shots,
                        p.hits,
                        p.headshots,
                        p.suicides,
                        p.last_skill_change,
                        p.kill_streak,
                        p.death_streak,
                        p.teamkills,
                        p.activity,
                        ROUND(IF(p.deaths=0, 0, p.kills/p.deaths), 2) AS kpd,
                        ROUND(IF(p.kills=0, 0, p.headshots/p.kills), 2) AS hpk,
                        ROUND(IF(p.shots=0, 0, p.hits/p.shots), 3) AS acc,
                        CONCAT(c.name) AS clan_name,
                        p.hideranking
                    FROM hlstats_players AS p
                    LEFT JOIN
                        hlstats_Clans AS c
                    ON
                        c.clanId = p.clan
                    WHERE p.lastAddress <> ''
                          AND p.hideranking <> 1
                          AND p.game = '$game'
                )
                SELECT *
                FROM RankedPlayers
                WHERE playerId = '$player'
                LIMIT 1
               ");
    }
	if ($db->num_rows() != 1) {
		error("No such player '$player'.");
	}
	$playerdata = $db->fetch_array();
	$db->free_result();
	$pl_name = $playerdata['lastName'];
    
    
$db->query("
    SELECT
        hlstats_PlayerUniqueIds.uniqueId,
        CAST(LEFT(hlstats_PlayerUniqueIds.uniqueId,1) AS unsigned)
            + CAST('76561197960265728' AS unsigned)
            + CAST(MID(hlstats_PlayerUniqueIds.uniqueId, 3,10)*2 AS unsigned) AS communityId
    FROM
        hlstats_PlayerUniqueIds
    WHERE
        hlstats_PlayerUniqueIds.playerId = '$player'
");
list($uqid, $coid) = $db->fetch_row();

$prefix = ($g_options['Mode'] == 'Normal') ? 'STEAM_0:' : '';
$steamProfileUrl = "https://steamcommunity.com/profiles/$coid";
$steam  = "<a href=\"$steamProfileUrl\" target=\"_blank\">$prefix$uqid</a>";
$location='(Unknown)';

if (preg_match('/^BOT/i', (string)$uqid)) {
    $playerdata['flag'] = 'bot';
    $prefix = '';
    $steam = $uqid;
    $location='(Server)';
}

if ($playerdata['country']) {

    if ($playerdata['city']) {
        $city=htmlspecialchars($playerdata['city'], ENT_COMPAT);
    }
    if ($playerdata['country']) {
        $country = htmlspecialchars($playerdata['country'], ENT_COMPAT);
    }
    $comma = (isset($city) && isset($country))?', ':'';
    $location=$city.$comma.$country;
}

if ($playerdata['clan']) {
    $clan = '<a href="' . $g_options['scripturl'] . '?mode=claninfo&amp;clan=' . $playerdata['clan'] . '">' . htmlspecialchars($playerdata['clan_name'], ENT_COMPAT) . '</a>';
} else {
    $clan= '(none)';
}

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

$result = $db->query("
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

$fav_weapon = '';
$weap_name  = '';

while ($rowdata = $db->fetch_row($result)) {
    $fav_weapon = $rowdata[0];
    $weap_name  = htmlspecialchars($rowdata[1] ?? '', ENT_COMPAT);
}

if ($fav_weapon === '') {
    $fav_weapon = 'Unknown';
}

$image = getImage("/games/$game/weapons/$fav_weapon");
$weaponlink_open  = "<a href=\"?mode=weaponinfo&amp;weapon=$fav_weapon&amp;game=$game\">";
$weaponlink_close = "</a>";
if ($image) {
    $favweapon =  $weaponlink_open . "<img src=\"" . $image['url'] . "\" alt=\"".$weap_name."\" title=\"".$weap_name."\" />" . $weaponlink_close;
} else {
    $favweapon =  "<strong>".$weaponlink_open.$weap_name.$weaponlink_close."</strong>";
}

	$hideranking = $playerdata['hideranking'];

    if ($hideranking == 2) {
		$statusmsg = '<span class="hlstats-status banned;">banned</span>';
	} else {
		$statusmsg = '<span class="hlstats-status good;">In good standing</span>';
	}
?>

<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title">Player Profile</div>

    <?php include __DIR__ . '/steamprofile.php'; ?>

  <div class="hlstats-card-body hlstats-card-grid">
      <div><div class="label">Karma:</div><div class="value<?php echo $hideranking != 2 ? ' green':' red';?>"><strong><?= $statusmsg ?></strong></div></div>
      <div><div class="label">Member of Clan:</div><div class="value"><?= $clan ?></div></div>

      <div><div class="label">First Connect:</div>
      <div class="value"><?= $playerdata['createdate'] ? date('D. M. jS, Y @ H:i:s', $playerdata['createdate']) : '(Unknown)' ?></div></div>

      <div><div class="label">Last Connect:</div>
      <div class="value"><?= $playerdata['last_event'] ? date('D. M. jS, Y @ H:i:s', $playerdata['last_event']) : '(Unknown)' ?></div></div>

      <div><div class="label">Last Ping:</div>
      <div class="value">
        <?php
          $ping = (int)($playerdata['lastPing'] ?? 0);
          $latency = $ping ? (int)round($ping / 2) : 0;
          echo $ping ? ($ping . " ms (Latency: $latency ms)") : '-';
        ?>
      </div></div>

      <div><div class="label">Favorite Server:</div>
      <div class="value">
        <a href="?game=<?= urlencode($game) ?>&amp;mode=servers&amp;server_id=<?= (int)$favServerId ?>">
          <?= htmlspecialchars($favServerName, ENT_COMPAT) ?>
        </a>
      </div></div>

      <div><div class="label">Favorite Map:</div>
      <div class="value">
        <a href="?game=<?= urlencode($game) ?>&amp;mode=mapinfo&amp;map=<?= urlencode($favMap) ?>">
          <?= htmlspecialchars($favMap, ENT_COMPAT) ?>
        </a>
      </div></div>

      <div><div class="label">Favorite Weapon:</div><div class="value"><?= $favweapon ?></div></div>
    </div>
</section>

<?php
    ob_flush();
    flush();
?>



<!-- Stats -->
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title">Statistics Summary</div>
  <div class="hlstats-card-body hlstats-card-grid">
      <div><div class="label">Activity:</div>
      <div class="value meter-ratio">
<?=$playerdata['activity']?>%
      </div></div>

     <div><div class="label">Points:</div>
      <div class="value"><?php echo nf($playerdata['skill']); ?></div></div>

      <div><div class="label">Rank:</div>
      <div class="value">
           <?php

               if ($playerdata['hideranking'] == 1) {
                   $rank = "Hidden";
               } elseif ($playerdata['hideranking'] == 2) {
                   $rank = "<span style=\"color:red;\">Banned</span>";
               } else {
                   $rank = $playerdata['rank_position'];
               }
      
               if (is_numeric($rank)) {
                   echo '<b>' . nf($rank) . '</b>';
               } else {
                   echo "<b> $rank</b>";
               }
           ?>
       </div></div>
              <div><div class="label">Kills per Minute:</div>
              <div class="value">
                  <?php
                      echo ($playerdata['connection_time'] > 0)
                          ? sprintf('%.2f', ($playerdata['kills'] / ($playerdata['connection_time'] / 60)))
                          : '-';
                  ?>
              </div></div>
      
      
          <div><div class="label">Kills per Death:</div>
              <div class="value">
                  <?php
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
                      echo $playerdata['kpd'] . " ($realkpd)";
                  ?>
              </div></div>
          <div><div class="label">Headshots per Kill:</div>
              <div class="value">
                  <?php
                      $db->query("
                          SELECT
                              IFNULL(SUM(hlstats_Events_Frags.headshot=1) / COUNT(*), '-')
                          FROM
                              hlstats_Events_Frags
                          WHERE
                              hlstats_Events_Frags.killerId = '$player'
                      ");
                      list($realhpk) = $db->fetch_row();
                      echo $playerdata['hpk'] . " ($realhpk)";
                  ?>
             </div></div>
      
          <div><div class="label">Shots per Kill:</div>
          <div class="value">
                  <?php
                      $db->query("
                          SELECT
                              IFNULL(ROUND((SUM(hlstats_Events_Statsme.hits) / SUM(hlstats_Events_Statsme.shots) * 100), 2), 0.0) AS accuracy,
                              SUM(hlstats_Events_Statsme.shots) AS shots,
                              SUM(hlstats_Events_Statsme.hits) AS hits,
                              SUM(hlstats_Events_Statsme.kills) AS kills
                          FROM
                              hlstats_Events_Statsme
                          WHERE
                              hlstats_Events_Statsme.playerId='$player'
                      ");
                      list($playerdata['accuracy'], $sm_shots, $sm_hits, $sm_kills) = $db->fetch_row();
                      echo ($sm_kills > 0) ? sprintf('%.2f', ($sm_shots / $sm_kills)) : '-';
                  ?>
          </div></div>
      
          <div><div class="label">Weapon Accuracy:</div>
          <div class="value">
                  <?php
                      echo $playerdata['acc'] . '%';
                      echo " (" . sprintf('%.0f', $playerdata['accuracy']) . '%)';
                  ?>
          </div></div>
      
          <div><div class="label">Headshots:</div>
          <div class="value">
                  <?php
                      if ($playerdata['headshots'] == 0) {
                          echo nf($realheadshots);
                      } else {
                          echo nf($playerdata['headshots']);
                      }
                      echo ' (' . nf($realheadshots) . ')';
                  ?>
          </div></div>
      
          <div><div class="label">Kills:</div>
          <div class="value"><?php echo nf($playerdata['kills']) . ' (' . nf($realkills) . ')'; ?></div></div>
      
          <div><div class="label">Deaths:</div>
          <div class="value"><?php echo nf($playerdata['deaths']) . ' (' . nf($realdeaths) . ')'; ?></div></div>
      
      
          <div><div class="label">Longest Kill Streak:</div>
         <div class="value">
                  <?php
                      $db->query("SELECT hlstats_Players.kill_streak FROM hlstats_Players WHERE hlstats_Players.playerId = '$player'");
                      list($kill_streak) = $db->fetch_row();
                      echo nf($kill_streak);
                  ?>
          </div></div>
      
           <div><div class="label">Longest Death Streak:</div>
          <div class="value">
                  <?php
                      $db->query("SELECT hlstats_Players.death_streak FROM hlstats_Players WHERE hlstats_Players.playerId = '$player'");
                      list($death_streak) = $db->fetch_row();
                      echo nf($death_streak);
                  ?>
              </div></div>
      
           <div><div class="label">Suicides:</div>
          <div class="value"><?php echo nf($playerdata['suicides']); ?></div></div>
      
           <div><div class="label">Teammate Kills:</div>
          <div class="value"><?php echo nf($playerdata['teamkills']) . ' (' . nf($realteamkills) . ')'; ?></div></div>

  </div>
</section>
</div>

<div>
<div style="float:right;">
   <a href="?mode=kills&amp;game=<?=$game?>&amp;player=<?=$player?>">Kill Statistics&nbsp;&rarr;</a>
</div>
</div>