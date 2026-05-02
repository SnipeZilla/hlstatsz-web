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
	// Clan Details
	
	$clan = valid_request(intval($_GET['clan'] ?? 0), true) or error('No clan ID specified.');
	
	$db->query("
		SELECT
			hlstats_Clans.tag,
			hlstats_Clans.name,
			hlstats_Clans.homepage,
			hlstats_Clans.game,
			hlstats_Clans.mapregion,
			SUM(hlstats_Players.kills) AS kills,
			SUM(hlstats_Players.deaths) AS deaths,
			SUM(hlstats_Players.headshots) AS headshots,
			SUM(hlstats_Players.connection_time) AS connection_time,
			COUNT(hlstats_Players.playerId) AS nummembers,
			ROUND(AVG(hlstats_Players.skill)) AS avgskill,
			TRUNCATE(AVG(activity),2) as activity
		FROM
			hlstats_Clans
		LEFT JOIN
			hlstats_Players
		ON
			hlstats_Players.clan = hlstats_Clans.clanId
		WHERE
			hlstats_Clans.clanId=$clan
			
		GROUP BY
			hlstats_Clans.clanId
	");

	if ($db->num_rows() != 1) {
		error("No such clan '$clan'.");
	}

	$clandata = $db->fetch_array();
	$db->free_result();
	
	$cl_name = preg_replace(' ', '&nbsp;', htmlspecialchars($clandata['name']));
	$cl_tag  = preg_replace(' ', '&nbsp;', htmlspecialchars($clandata['tag']));
	$cl_full = "$cl_tag $cl_name";
	
	$game = $clandata['game'];
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() != 1)
		$gamename = ucfirst($game);
	else
		list($gamename) = $db->fetch_row();

?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-card-title">Statistics Summary</div>
      <div class="hlstats-pname"><?= htmlspecialchars($clandata['name']) ?></div>
      <div class="hlstats-card-body hlstats-card-grid">
        <div><div class="label">Home Page:</div>
        <div class="value">
        <?php
            if ($url = getLink($clandata['homepage'])) {
                echo $url;
            } else {
                echo '(Not specified.)';
            }
        ?>
        </div></div>
        <div><div class="label">Activity:</div>
        <div class="value meter-ratio">
<?php echo $clandata['activity'].'%'; ?>
        </div></div>
        <div><div class="label">Members:</div>
        <div class="value"><?php echo $clandata['nummembers']." active members ($totalclanplayers total)"; ?></div></div>
        <div><div class="label">Avg. Member Points:</div>
        <div class="value"><?php echo nf($clandata['avgskill']); ?></div></div>
        <div><div class="label">Total Kills:</div>
        <div class="value"><?php echo nf($clandata['kills']); ?></div></div>
        <div><div class="label">Total Deaths:</div>
        <div class="value"><?php echo nf($clandata['deaths']); ?></div></div>
        <div><div class="label">Avg. Kills:</div>
        <?php if ($clandata['nummembers'] != 0) { ?>
        <div class="value"><?php echo nf($clandata['kills'] / ($clandata['nummembers'])); ?></div></div>
        <?php } else {
                  echo '<div class="value">-</div></div>';
              }?>
        <div><div class="label">Kills per Death:</div>
        <div class="value">
        <?php if ($clandata['deaths'] != 0) {
                  echo sprintf('<strong>%0.2f</strong>', $clandata['kills'] / $clandata['deaths']);
              } else {
                  echo '-';
              }?>
        </div></div>
        <div><div class="label">Kills per Minute:</div>
        <div class="value">
        <?php if ($clandata['connection_time'] > 0) {
                  echo sprintf("%.2f", ($clandata['kills'] / ($clandata['connection_time'] / 60)));
              } else {
                  echo '-'; 
              }?>
        </div></div>
        <div><div class="label">Total Connection Time:</div>
        <div class="value"><?php echo TimeStamp($clandata['connection_time']); ?></div></div>
        <div><div class="label">Avg. Connection Time:</div>
        <div class="value">
            <?php if ($clandata['connection_time'] > 0) {
                      echo TimeStamp($clandata['connection_time'] / ($clandata['nummembers']));
                  } else {
                      echo '-'; 
                  } ?>
        </div></div>
        <div><div class="label">Favorite Server:*</div>
        <div class="value">
            <?php
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

            list($favServerId,$favServerName) = $db->fetch_row();

            echo "<a href='hlstats.php?game=$game&amp;mode=servers&amp;server_id=$favServerId'>".htmlspecialchars($favServerName)."</a>";
            ?>
        </div></div>

        <div><div class="label">Favorite Map:*</div>
        <div class="value">
            <?php
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

            list($favMap) = $db->fetch_row();

            echo "<a href='hlstats.php?game=$game&amp;mode=mapinfo&amp;map=$favMap'>".htmlspecialchars($favMap)."</a>";
            ?>
         </div></div>

        <div><div class="label">Favorite Weapon:*</div>
        <div class="value">
            <?php
            $result = $db->query("
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

             $weap_name = "";
             $fav_weapon = "";
             
             while ($rowdata = $db->fetch_row($result))
             { 
                $fav_weapon = $rowdata[0];
                $weap_name = htmlspecialchars($rowdata[1]);
             }
             
             if ($fav_weapon == '')
                 $fav_weapon = 'Unknown';
             $image = getImage("/games/$game/weapons/$fav_weapon");
             // check if image exists
             $weaponlink = "<a href=\"hlstats.php?mode=weaponinfo&amp;weapon=$fav_weapon&amp;game=$game\">";
             
             if ($image) {
                $cellbody = "$weaponlink<img src=\"" . $image['url'] . "\" alt=\"$weap_name\" title=\"$weap_name\" />";
             } else {
                $cellbody = "$weaponlink<strong> $weaponlink$weap_name</strong>";
             }
             
            $cellbody .= "</a>";

            echo $cellbody;
            ?>
        </div></div>
      </div>
    </section>

</div>
<?php



    $sortorder = valid_request($_GET['sortorder'] ?? '', false);
    $sort      = valid_request($_GET['sort'] ?? '', false);

    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
    }
    $col = array("lastName","rank_position","skill","kills","deaths","activity","kpd","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = $rank_type1;
        $sortorder = "DESC";
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

echo '<div id="members"><div class="responsive-table">
<table class="players-table">
    <tr>
        <th class="'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'members') .'Rank</a></th>
        <th class="hlstats-main-column left'. isSorted('lastName', $sort, $sortorder) .'">'. headerUrl('lastName',['sort','sortorder'],'members') .'Player</a></th>';
        if ($g_options['rankingtype']!='kills') {
        echo '<th class="'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'members') .'Points</a></th>';
        }
        echo '
        <th class="'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'members') .'Kills</a></th>
        <th class="'. isSorted('percent', $sort, $sortorder) .'">'. headerUrl('percent',['sort','sortorder'],'members') .'Clan Kills</a></th>
        <th class="'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'members') .'K:D</a></th>
        <th class="'. isSorted('kpd', $sort, $sortorder) .'">'. headerUrl('kpd',['sort','sortorder'],'members') .'K:D</a></th>
        <th class="'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'members') .'Activity</a></th>
        <th class="'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'members') .'Connection Time</a></th>
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
             echo '<a href="?mode=statsme&amp;player='.$res['playerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['lastName']).' </span></a>
             </td>'
            .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap">'.nf($res['skill']).'</td>'):'').
             '<td class="nowrap">'.$res['kills'].'</td>
             <td class="meter-ratio nowrap">
                 '.$res['percent'].'%
             </td>
             <td class="nowrap">'.nf($res['deaths']).'</td>
             <td class="nowrap">'.$res['kpd'].'</td>
             <td class="meter-ratio nowrap">
                 '.$res['activity'].'%
             </td>
             <td class="nowrap">'.$time.'</td>
         </tr>';
     }

    echo  '</table></div>'.
          Pagination($total, $_GET['page'] ?? 1, 15, 'page', false, 'members');

?>
</div>
<div>
<div style="float:left;">
  <a href="?mode=clans&amp;game=<?=$game?>">&larr;&nbsp;Clan Rankings</a>
</div>
</div>
