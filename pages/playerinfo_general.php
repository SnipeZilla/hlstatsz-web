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
$asterisk = $g_options['DeleteDays'] ? '*' : '';
$prefix = ($g_options['Mode'] == 'Normal') ? 'STEAM_0:' : '';
$steamProfileUrl = "https://steamcommunity.com/profiles/$coid";
$steam  = "<a href=\"$steamProfileUrl\" target=\"_blank\">$prefix$uqid</a>";
$location='(Unknown)';

if (preg_match('/^BOT/i', (string)$uqid)) {
    $playerdata['flag'] = 'bot';
    $prefix = '';
    $steam = $uqid;
    $location='(Server)';
} else {
    $location = Location($playerdata['city'], $playerdata['state'], $playerdata['country'], $g_options['countrydata']);
}

if ($playerdata['clan']) {
    $clan = '<a href="?mode=claninfo&amp;clan=' . $playerdata['clan'] . '">⚔️ ' . htmlspecialchars($playerdata['clan_name'], ENT_COMPAT) . '</a>';
} else {
    $clan= '(none)';
}
if ($playerdata['homepage']) {
    $homepage = '<a href="' . $playerdata['homepage'] . '" target="_blank">🌐 ' . htmlspecialchars($playerdata['homepage'], ENT_COMPAT) . '</a>';
} else {
    $homepage= '(Not Specified)';
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
$weaponlink_open  = "<a href=\"hlstats.php?mode=weaponinfo&amp;weapon=$fav_weapon&amp;game=$game\">";
$weaponlink_close = "</a>";

if ($image) {
    $favweapon =  $weaponlink_open . "<img src=\"" . $image['url'] . "\" alt=\"".$weap_name."\" title=\"".$weap_name."\" />" . $weaponlink_close;
} else {
    $favweapon =  "<strong>".$weaponlink_open.$weap_name.$weaponlink_close."</strong>";
}
?>

<?php printSectionTitle('Player Information'); ?>
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title">Player Profile</div>

    <div id="steam-profile-<?= (int)$player ?>" class="hlstats-steam-profile-shell">
        <div class="hlstats-profile-head">
            <div class="hlstats-avatar">
                <img src="<?= htmlspecialchars(IMAGE_PATH . '/unknown.jpg', ENT_QUOTES) ?>"
                         class="hlstats-avatar-img"
                         alt="Steam Community Avatar" />
            </div>

            <div class="hlstats-identity">
                <div class="hlstats-pname">
                <?php if ($g_options['countrydata']) { ?>
                    <span class="hlstats-flag">
                        <img src="<?= getFlag($playerdata['flag']) ?>"
                                 alt="<?= htmlspecialchars($playerdata['country'] ?? '', ENT_QUOTES) ?>"
                                 title="<?= htmlspecialchars($playerdata['country'] ?? '', ENT_QUOTES) ?>" />
                    </span>
                <?php  } ?>
                    <span><?= htmlspecialchars($playerdata['lastName'], ENT_COMPAT) ?></span>
                </div>

                <div class="hlstats-meta">
                    <?php if ($g_options['countrydata']) { ?>
                    <span><strong>Location:</strong> <?= htmlspecialchars($location, ENT_COMPAT) ?></span>
                    <?php } ?>
                    <span><strong>Steam:</strong> <?= $steam ?></span>
                    <span><strong>Status:</strong> <span class="hlstats-status">Loading...</span></span>
                    <span><strong>Member Since:</strong> <span id="steam-member-since-<?= (int)$player ?>">Loading...</span></span>
                </div>
            </div>
        </div>
    </div>
      <form method="post" class="contents">

  <div class="hlstats-card-body hlstats-card-grid">
      <div class="label">Karma:</div><div class="value<?php echo $hideranking != 2 ? ' green':' red';?>"><strong><?= $statusmsg ?></strong></div>
      <?php if (isset($_SESSION['ID64']) && $_SESSION['ID64'] == $coid): 
        if ($playerdata['clan']) {
            $clan = '<a href="?mode=claninfo&amp;clan=' . $playerdata['clan'] . '">⚔️ </a>';
        } else { $clan = ''; }
        if ($playerdata['homepage']) {
            $homepage = '<a href="' . $playerdata['homepage'] . '" target="_blank">🌐 </a>';
        } else { $homepage = ''; }
      
      ?>

    <form method="post">

      <div class="label">Member of Clan:</div><div class="value"><?= $clan ?><input class="hlstats-user" type="text" name="clan_name" value="<?= htmlspecialchars($playerdata['clan_name'] ?? '') ?>" placeholder="[TAG] Name"></div>
      <div class="label">Homepage:</div><div class="value"><?= $homepage ?><input class="hlstats-user" type="text" name="homepage" value="<?= htmlspecialchars($playerdata['homepage'] ?? '') ?>"></div>

      <div class="label"></div><div class="value"><input type="submit" value="Update"><?php if ($error) echo ' <span class="red">' . $error . '</span>'; ?></div>


      <?php else: ?>

      <div class="label">Member of Clan:</div><div class="value"><?= $clan ?></div>

      <div class="label">Homepage:</div><div class="value"><?= $homepage ?></div>

      <?php endif; ?>
      <div class="label">First Connect:</div>
      <div class="value"><?= $playerdata['createdate'] ? date('D. M. jS, Y @ H:i:s', $playerdata['createdate']) : '(Unknown)' ?></div>

      <div class="label">Last Connect:</div>
      <div class="value"><?= $playerdata['last_event'] ? date('D. M. jS, Y @ H:i:s', $playerdata['last_event']) : '(Unknown)' ?></div>

      <div class="label">Last Ping:</div>
      <div class="value">
        <?php
          $ping = (int)($playerdata['lastPing'] ?? 0);
          $latency = $ping ? (int)round($ping / 2) : 0;
          echo $ping ? ($ping . " ms (Latency: $latency ms)") : '-';
        ?>
      </div>

      <div class="label">Favorite Server:<?= $asterisk ?></div>
      <div class="value">
        <a href="hlstats.php?game=<?= urlencode($game) ?>&amp;mode=servers&amp;server_id=<?= (int)$favServerId ?>">
          <?= htmlspecialchars($favServerName, ENT_COMPAT) ?>
        </a>
      </div>

      <div class="label">Favorite Map:<?= $asterisk ?></div>
      <div class="value">
        <a href="hlstats.php?game=<?= urlencode($game) ?>&amp;mode=mapinfo&amp;map=<?= urlencode($favMap) ?>">
          <?= htmlspecialchars($favMap, ENT_COMPAT) ?>
        </a>
      </div>

      <div class="label">Favorite Weapon:<?= $asterisk ?></div><div class="value"><?= $favweapon ?></div>
    </div>
  </form>

</section>

<?php
ob_flush();
flush();
?>

<!-- Stats -->
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title">Statistics Summary</div>
  <div class="hlstats-card-body hlstats-card-grid">
      <div class="label">Rank:</div>
      <div class="value">
           <?php

               if ($playerdata['hideranking'] == 1) {
                   $rank = "Hidden";
               } elseif ($playerdata['hideranking'] == 2) {
                   $rank = "<span class=\"red\">Banned</span>";
               } else {
                   $rank = $playerdata['rank_position'];
               }
      
               if (is_numeric($rank)) {
                   echo '<b>' . nf($rank) . '</b>';
               } else {
                   echo "<b> $rank</b>";
               }
           ?>
       </div>
      <div class="label">Activity:</div>
      <div class="value meter-ratio">
        <div class="meter-container">
          <meter min="0" max="100" low="25" high="50" optimum="75" value="<?=$playerdata['activity']?>"></meter>
          <div class="meter-value" id="meterText"><?=$playerdata['activity']?>%</div>
        </div>
      </div>
<?php if ($g_options['rankingtype']!='kills') { ?>
      <div class="label">Points:</div>
      <div class="value"><?php echo nf($playerdata['skill']); ?></div>
<?php } ?>
          <div class="label">Kills:</div>
          <div class="value"><?php echo nf($playerdata['kills']) . ' (' . nf($realkills) . $asterisk .')'; ?></div>
          <div class="label">Headshots:</div>
          <div class="value">
                  <?php
                      if ($playerdata['headshots'] == 0) {
                          echo nf($realheadshots);
                      } else {
                          echo nf($playerdata['headshots']);
                      }
                      echo ' (' . nf($realheadshots)  . $asterisk .')';
                  ?>
          </div>

          <div class="label">Deaths:</div>
          <div class="value"><?php echo nf($playerdata['deaths']) . ' (' . nf($realdeaths) . $asterisk .')'; ?></div>

       <div class="label">Kills per Minute:</div>
       <div class="value">
       <?php
        echo ($playerdata['connection_time'] > 0)
              ? sprintf('%.2f', ($playerdata['kills'] / ($playerdata['connection_time'] / 60)))
              : '-';
        ?>
              </div>
          <div class="label">Kills per Death:</div>
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
                      echo $playerdata['kpd'] . ' ('.$realkpd . $asterisk .')';
                  ?>
              </div>
          <div class="label">Headshots per Kill:</div>
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
                      echo $playerdata['hpk'] . ' ('.$realhpk . $asterisk .')';
                  ?>
             </div>
      
          <div class="label">Shots per Kill:</div>
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
          </div>

          <div class="label">Longest Kill Streak:</div>
         <div class="value">
                  <?php
                      $db->query("SELECT hlstats_Players.kill_streak FROM hlstats_Players WHERE hlstats_Players.playerId = '$player'");
                      list($kill_streak) = $db->fetch_row();
                      echo nf($kill_streak);
                  ?>
          </div>
      
          <div class="label">Longest Death Streak:</div>
          <div class="value">
                  <?php
                      $db->query("SELECT hlstats_Players.death_streak FROM hlstats_Players WHERE hlstats_Players.playerId = '$player'");
                      list($death_streak) = $db->fetch_row();
                      echo nf($death_streak);
                  ?>
              </div>
      
          <div class="label">Suicides:</div>
          <div class="value"><?php echo nf($playerdata['suicides']); ?></div>
      
          <div class="label">Teammate Kills:</div>
          <div class="value"><?php echo nf($playerdata['teamkills']) . ' (' . nf($realteamkills)  . $asterisk .')'; ?></div>
          <div class="label">Weapon Accuracy:</div>
          <div class="value">
                  <?php
                      echo $playerdata['acc'] . '%';
                      echo " (" . sprintf('%.0f', $playerdata['accuracy']) . '%' . $asterisk .')';
                  ?>
          </div>

      <div class="hlstats-card-foot">
          <?php
              echo '🗓️ <b>'
                  . htmlspecialchars($playerdata['lastName'], ENT_COMPAT) . '</b>\'s History: ';
              echo '<a href="' . $g_options['scripturl'] . "?mode=playerhistory&amp;player=$player\">Events</a> &nbsp;|&nbsp; ";
              echo '<a href="' . $g_options['scripturl'] . "?mode=playersessions&amp;player=$player\">Sessions</a> &nbsp;|&nbsp; ";
      
              $resultCount = $db->query("
                  SELECT COUNT(*) FROM hlstats_Players_Awards WHERE hlstats_Players_Awards.playerId = $player
              ");
              list($numawards) = $db->fetch_row($resultCount);
              echo "<a href=\"" . $g_options['scripturl'] . "?mode=playerawards&amp;player=$player\">Awards&nbsp;($numawards)</a>";
      
              if ($g_options["nav_globalchat"] == 1) {
                  echo " &nbsp;|&nbsp; <a href=\"" . $g_options['scripturl'] . "?mode=chathistory&amp;player=$player\">Chat</a>";
              }
      
              echo "<div class=\"hlstats-find\">";
              echo "<a href=\"" . $g_options['scripturl'] . "?mode=search&amp;st=player&amp;q=$pl_urlname\">"
                  . "🔍"
                  . "Find other players with the same name</a>";
              echo "</div>";
          ?>
      </div>
  </div>
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

if ($db->num_rows() == 0) {
    $rankKillsNeeded = 0;
    $rankPercent = 0;
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
    $histimage = getImage('/ranks/' . $result['image'] . '_small');
        $rankHistory .= '<div class=" hlstats-rank hlstats-award has-winner">
               <div class="hlstats-award-title">'.$result['rankName'].'</div>
               <div class="hlstats-award-icon"><img src="' . $histimage['url'] . '" title="' . $result['rankName'] . '" alt="' . $result['rankName'] . '" /></div>
              </div>';
}

$banner = IMAGE_PATH."/games/$game/banner.jpg";



 printSectionTitle('Ranks'); ?>
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
            <div class="hlstats-card-title">Current rank</div>
            <div class="hlstats-award has-winner">
            <div class="hlstats-award-title"><?= $rankName ?></div>
            <div class="hlstats-award-icon">
                <?php echo '<img src="'.$rankimage['url']."\" alt=\"$rankName\" title=\"$rankName\" />"; ?>
            </div>
            </div>
            <div class="hlstats-card-foot">
                 <div class="hlstats-rankmeter meter-container">
                   <meter value="80" min="0" max="100" low="25" high="50" optimum="75" value="<?= $rankPercent ?>"></meter>
                   <div class="meter-value" id="meterText"><?= $rankPercent ?>%</div>
                 </div>            
                 <div>Kills needed: <b><?php echo $rankKillsNeeded." (".nf($rankPercent, 0, '.', '');?>%)</b></div>
            </div>
<?php
if (file_exists($banner)) {
    echo '<img src="'.$banner.'" alt="$game">';
} else {
    $banner = IMAGE_PATH."/games/$realgame/banner.jpg";
    if (file_exists($banner)) {
        echo '<img src="'.$banner.'" alt="$game">';
    }
}

?>
</section>
<section class="hlstats-section hlstats-card">
            <div class="hlstats-card-title">Rank history</div>
            <div class="hlstats-card-body hlstats-center"><?php echo $rankHistory; ?></div>
</section>
</div>
<?php
ob_flush();
flush();

printSectionTitle('Miscellaneous Statistics'); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-card-title">Player Trend</div> 
    <img src="trend_graph.php?time=<?=time()?>&amp;player=<?= $player ?>" alt="Player Trend Graph" />
  </section>

  <section class="hlstats-section hlstats-card">
    <div class="hlstats-card-title">Forum Signature</div> 
    <?php
        if ($g_options['modrewrite'] == 0) {
            $imglink  = $script_path.'/sig.php?player_id='.$player.'&amp;background='.$g_options['sigbackground'];
            $jimglink = $script_path.'/sig.php?player_id='.$player.'&background='.$g_options['sigbackground'];
        } else {
            $imglink  = $script_path.'/sig-'.$player.'-'.$g_options['sigbackground'].'.png';
            $jimglink = $imglink;
        }

        echo "<div class=\"hlstats-forum-signature\"><img src=\"$imglink\" title=\"Copy &amp; Paste the whole URL below in your forum signature\" alt=\"forum sig image\"/></div>";

    ?>


    <div class="hlstats-siglinks">
        <span style="cursor:pointer;" onclick="return setForumText(1);return false">bbCode 1 (phpBB, SMF)</span>
        &nbsp;|&nbsp;
        <span style="cursor:pointer;" onclick="return setForumText(2);return false">bbCode 2 (IPB)</span>
        &nbsp;|&nbsp;
        <span style="cursor:pointer;" onclick="window.open('<?=$imglink?>', '_blank');return false">Direct Image</span>
    </div>

    <?php
        echo '<textarea class="hlstats-sigbox" rows="4" cols="70" id="siglink" readonly="readonly" onclick="document.getElementById(\'siglink\').select();">[url='
            . "$script_path/hlstats.php?mode=playerinfo&amp;player=$player"
            . "][img]$imglink" . '[/img][/url]</textarea>';
    ?>
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

 printSectionTitle('Awards');
 ?>
<div class="hlstats-cards-grid">

<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title">Ribbons</div>
     <div class="hlstats-card-body hlstats-center"><?php echo $ribbonList; ?></div>
</section>

<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-title">Global Awards</div>
    <div class="hlstats-card-body hlstats-center"><?php echo $GlobalAwardsList; ?></div>
</section>

</div>


<?php }
}
 ?>
