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

    if (!empty($_GET['ajax']) && $_GET['ajax'] === 'voicecomm') {
        global $db;
        $resultVoices = $db->query("
            SELECT serverId, name, addr, password, descr, queryPort, UDPPort, serverType
            FROM hlstats_Servers_VoiceComm
        ");
        include(PAGE_PATH . '/voicecomm_serverlist.php');
        exit;
    }

    // Contents
    $resultGames = $db->query("
        SELECT
            code,
            name,
            realgame
        FROM
            hlstats_Games
        WHERE
            hidden='0'
        ORDER BY
            realgame, name ASC
    ");
    $resultVoices = $db->query("
        SELECT
            serverId,
            name,
            addr,
            password,
            descr,
            queryPort,
            UDPPort,
            serverType
        FROM
            hlstats_Servers_VoiceComm
        ");
    $num_games = $db->num_rows($resultGames);
    $num_voices = $db->num_rows($resultVoices);

    $game = (!empty($_GET['game'])) ? valid_request($_GET['game'], false) : null;

    if (($num_games == 1 && !$num_voices) || !empty($game)) {

        if ($num_games == 1) {
            list($game) = $db->fetch_row($resultGames);
        }
        include(PAGE_PATH . '/game.php');

    } else {
      if ($num_games) {
        unset($_SESSION['game']);

        printSectionTitle('Games');
    
if ($g_options['show_google_map'] == 1) {
?>
    <table class="hlstats-map">
        <tr>
            <td><div id="map"></div></td>
        </tr>  
</table>
<?php
}
?>
            <table>
                <tr>
                    <th class="hlstats-main-description left responsive">Game</th>
                    <th class="hide-2"></th>
                    <th class="hide-2"></th>
                    <th>Players</th>
                    <th class="hide">Top Player</th>
                    <th class="hide-2">Top Clan</th>
                </tr>
<?php
        $nonhiddengamestring = "(";
        while ($gamedata = $db->fetch_row($resultGames))
        {
            $nonhiddengamestring .= "'$gamedata[0]',";
            $result = $db->query("
                SELECT
                    playerId,
                    lastName,
                    activity
                FROM
                    hlstats_Players
                WHERE
                    game='$gamedata[0]'
                    AND hideranking=0
                    AND lastAddress <> ''
                ORDER BY
                    ".$g_options['rankingtype']." DESC,
                    (kills/IF(deaths=0,1,deaths)) DESC
                LIMIT 1
            ");
        
            if ($db->num_rows($result) == 1)
            {
                $topplayer = $db->fetch_row($result);
            }
            else
            {
                $topplayer = false;
            }

            $result = $db->query("
            SELECT
                c.clanId,
                c.name,
                AVG(p.skill) AS skill,
                AVG(p.kills) AS kills,
                COUNT(p.playerId) AS numplayers
            FROM
                hlstats_Clans AS c
            INNER JOIN
                hlstats_Players AS p
                    ON p.clan = c.clanId
                    AND p.game = c.game
                    AND p.hideranking = 0
            WHERE
                c.game = '".$gamedata[0]."'
                AND c.hidden = 0
            GROUP BY
                c.clanId
            HAVING
                numplayers >= 2
            ORDER BY
                ".$g_options['rankingtype']." DESC
            LIMIT 1;
            ");

            if ($db->num_rows($result) == 1)
            {
                $topclan = $db->fetch_row($result);
            }
            else
            {
                $topclan = false;
            }

            $result= $db->query("
                SELECT
                    SUM(act_players) AS `act_players`,
                    SUM(max_players) AS `max_players`
                FROM
                    hlstats_Servers
                WHERE
                    hlstats_Servers.game='$gamedata[0]'
            ");
                            
            $numplayers = $db->fetch_array($result);
        if ($numplayers['act_players'] == 0 and $numplayers['max_players'] == 0) {
                $numplayers = false;
        } else {
                $player_string = $numplayers['act_players'].'/'.$numplayers['max_players'];
        }
?>
                <tr>
                    <td class="hlstats-main-column left">
                <a href="<?= $g_options['scripturl'] . "?game=$gamedata[0]" ?>">
                
            <?php $image = getImage("/games/$gamedata[0]/game"); 
                  if (!$image) $image = getImage("/games/$gamedata[2]/game"); ?>

                <span class="hlstats-icon"><img src="<?php echo ($image ? $image['url'] : IMAGE_PATH . '/game.png' ); ?>" alt="Game" /></span>
                <span class="hlstats-name"><?= $gamedata[1] ?><span></a>
                </td>
                        <td class="hide-2">
                <a href="<?= $g_options['scripturl'] . "?mode=players&amp;game=$gamedata[0]" ?>">🎮 Players</a>
                        </td>
                        <td class="hide-2">
                <a href="<?= $g_options['scripturl'] . "?mode=clans&amp;game=$gamedata[0]" ?>">⚔️ Clans</a>
                        </td>
                <td><?php echo ($numplayers ? $player_string : '-'); ?></td>
                <td class="hide">
            <?php if ($topplayer) { ?>
                    <a href="<?= $g_options['scripturl'] . "?mode=playerinfo&amp;player=" . $topplayer[0] . "&amp;game=" . $gamedata[0] ?>"><?= htmlspecialchars($topplayer[1]) ?></a>
            <?php } else { echo '-'; } ?>
                </td>
                <td class="hide-2">
            <?php if ($topclan) { ?>
                    <a href="<?= $g_options['scripturl'] . "?mode=claninfo&amp;clan=" . $topclan[0] . "&amp;game=" . $gamedata[0] ?>"><?= htmlspecialchars($topclan[1]) ?></a>
            <?php } else { echo '-'; } ?>
                </td>
            </tr>
<?php
        }
?>
            </table>

<?php
}
        if ($num_voices) {
            $voicecomm_url = htmlspecialchars($_SERVER['PHP_SELF'] . '?mode=contents&ajax=voicecomm');
            echo '<div id="voicecomm-container" data-fetch-url="' . $voicecomm_url . '"></div>';
            echo '<script>Fetch.run(' . json_encode($_SERVER['PHP_SELF'] . '?mode=contents&ajax=voicecomm') . ', "voicecomm-container",false);</script>';
        }

        if (!empty($nonhiddengamestring)) {
        printSectionTitle('General Statistics');
        
        $nonhiddengamestring = preg_replace('/,$/', ')', $nonhiddengamestring);
        
        $result = $db->query("SELECT COUNT(playerId) FROM hlstats_Players WHERE game IN $nonhiddengamestring");
        list($num_players) = $db->fetch_row($result);
        $num_players = nf($num_players);

        $result = $db->query("SELECT COUNT(clanId) FROM hlstats_Clans WHERE game IN $nonhiddengamestring");
        list($num_clans) = $db->fetch_row($result);
        $num_clans = nf($num_clans );

        $result = $db->query("SELECT COUNT(serverId) FROM hlstats_Servers WHERE game IN $nonhiddengamestring");
        list($num_servers) = $db->fetch_row($result);
        $num_servers = nf($num_servers);
        
        $result = $db->query("SELECT SUM(kills) FROM hlstats_Servers WHERE game IN $nonhiddengamestring");
        list($num_kills) = $db->fetch_row($result);
        $num_kills = nf($num_kills);

        $result = $db->query("
            SELECT 
                eventTime
            FROM
                hlstats_Events_Frags
            ORDER BY
                id DESC
            LIMIT 1
        ");
        list($lastevent) = $db->fetch_row($result);
?>
        <div>
            <ul>
                <li>
                    <strong><?= $num_players ?></strong> players and <strong><?= $num_clans ?></strong> clans ranked in <strong><?= $num_games ?>
                    </strong> games on <strong><?= $num_servers ?></strong> servers with <strong><?=$num_kills ?></strong> kills.
                </li>
                <?php if ($lastevent) { ?>
                <li>Last Kill <strong><?= date('l, F j, Y g:i A', strtotime($lastevent)) ?></strong></li>
                <?php } ?>
                <li>All statistics are generated in <strong>Real-Time</strong>.
                   <?php if ($g_options['DeleteDays']) { ?>
                   Event history is kept for each player's most recent <strong><?= $g_options['DeleteDays'] ?></strong> days of activity.
                   <?php } ?>
                </li>
            </ul>
        </div>
<?php
      }
    }
?>
