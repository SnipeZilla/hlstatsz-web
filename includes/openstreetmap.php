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
global $db, $g_options, $clandata, $clan, $mode, $server_id;

require_once __DIR__ . '/team_colors.php';

$players = array();

if ( $type ==  "main" ) {
    // Servers
    $filter  = $mode == 'servers' ? "serverId='$server_id'" : "game='$game'";
    $filter2 = $mode == 'servers' ? "s.serverId='$server_id'" : "s.game='$game'";
    if (empty($game) && $mode != 'servers') {
        $db->query("SELECT 
                        s.serverId,
                        s.game,
                        IFNULL(NULLIF(s.publicaddress, ''), CONCAT(s.address, ':', s.port)) AS addr,
                        s.name,
                        s.act_players,
                        s.max_players,
                        s.act_map,
                        s.lat,
                        s.lng,
                        s.city,
                        s.country
                    FROM
                        hlstats_Servers AS s
                    INNER JOIN
                        hlstats_Games as g 
                        ON s.game = g.code
                    WHERE 
                       g.hidden = '0'
                       AND s.lat IS NOT NULL AND s.lng IS NOT NULL");

    } else {
        $db->query("SELECT
                        serverId,
                        game,
                        IFNULL(NULLIF(publicaddress, ''), CONCAT(address, ':', port)) AS addr,
                        name,
                        act_players,
                        max_players,
                        act_map,
                        lat,
                        lng,
                        city,
                        country
                    FROM
                        hlstats_Servers
                    WHERE
                        $filter
                        AND lat IS NOT NULL AND lng IS NOT NULL");
    }

    $servers = array();
    while ($row = $db->fetch_array()) {

        $servers[] = array('game'        => $row['game'],
                           'lat'         => $row['lat'],
                           'lng'         => $row['lng'],
                           'city'        => $row['city'],
                           'country'     => $row['country'],
                           'serverId'    => $row['serverId'],
                           'name'        => $row['name'],
                           'addr'        => $row['addr'],
                           'name'        => $row['name'],
                           'act_map'     => $row['act_map'],
                           'act_players' => $row['act_players'],
                           'max_players' => $row['max_players'],
                           'bg'          => BgColor("server"));

    }
    echo '<script type="application/json" id="servers-json">' . json_encode($servers) . '</script>';

    // Players
    if (empty($game) && $mode != 'servers'){
        $db->query("SELECT
                        l.player_id,
                        l.server_id,
                        
                        l.cli_lat,
                        l.cli_lng,
                        l.cli_city,
                        l.cli_state,
                        l.cli_country,
                        l.cli_flag,

                        l.name,
                        l.team,
                        t.name AS team_name,
                        l.ping,
                        l.kills,
                        l.deaths,
                        l.connected,

                        s.name AS server_name,
                        IFNULL(NULLIF(s.publicaddress, ''), CONCAT(s.address, ':', s.port)) AS server_addr,
                        s.act_map,
                        s.act_players,
                        s.max_players

                   FROM
                       hlstats_Livestats l
                   JOIN
                       hlstats_Servers s
                       ON s.serverId = l.server_id
                    LEFT JOIN hlstats_Teams t 
                        ON t.code = l.team AND t.game = s.game
                   WHERE
                       l.cli_lat IS NOT NULL
                       AND l.cli_lng IS NOT NULL
                       AND s.lat IS NOT NULL
                       AND s.lng IS NOT NULL
                 ");
    } else {
        $db->query("SELECT
                        l.player_id,
                        l.server_id,

                        l.cli_lat,
                        l.cli_lng,
                        l.cli_city,
                        l.cli_state,
                        l.cli_country,
                        l.cli_flag,

                        l.name,
                        l.team,
                        t.name AS team_name,
                        l.ping,
                        l.kills,
                        l.deaths,
                        l.connected,

                        s.name AS server_name,
                        IFNULL(NULLIF(s.publicaddress, ''), CONCAT(s.address, ':', s.port)) AS server_addr,
                        s.act_map,
                        s.act_players,
                        s.max_players

                   FROM
                       hlstats_Livestats l
                   JOIN
                       hlstats_Servers s
                       ON s.serverId = l.server_id
                    LEFT JOIN hlstats_Teams t
                        ON t.code = l.team AND t.game = s.game
                   WHERE
                       l.cli_lat IS NOT NULL
                       AND l.cli_lng IS NOT NULL
                       AND s.lat IS NOT NULL
                       AND s.lng IS NOT NULL
                       AND $filter2
                   ");
    }

    $players = array();
    while ($row = $db->fetch_array())
    {
        $time_str = TimeStamp(time() - $row['connected']);;
    
        $players[] = array('server_id'   => $row['server_id'],
                           'cli_lat'     => $row['cli_lat'],
                           'cli_lng'     => $row['cli_lng'],
                           'cli_city'    => $row['cli_city'],
                           'cli_state'   => $row['cli_state'],
                           'cli_country' => $row['cli_country'],
                           'cli_flag'    => $row['cli_flag'],
                           'server_name' => $row['server_name'],
                           'playerId'    => $row['player_id'],
                           'name'        => $row['name'],
                           'team'        => $row['team'],
                           'team_name'   => $row['team_name'],
                           'ping'        => $row['ping'],
                           'kills'       => $row['kills'],
                           'deaths'      => $row['deaths'],
                           'connected'   => $time_str,
                           'bg'          => BgColor($row['team']));
    }
}

if ( $type ==  "clan" && !empty($clan) ) {
    $players = array();
    $db->query("SELECT
                   playerId,
                   lastName,
                   country,
                   skill,
                   kills,
                   deaths,
                   lat,
                   lng,
                   city,
                   state,
                   country,
                   last_event
               FROM
                  hlstats_Players
               WHERE
                   clan=$clan
                   AND hideranking = 0
                   AND lat IS NOT NULL
              ");
    while ($row = $db->fetch_array())
    {
        $time_str = "Last seen: " .date("Y-m-d H:i:s", $row['last_event']);
    
        $players[] = array('cli_lat'     => $row['lat'],
                           'cli_lng'     => $row['lng'],
                           'cli_city'    => $row['city'],
                           'cli_state'   => $row['state'],
                           'cli_country' => $row['country'],
                           'playerId'    => $row['playerId'],
                           'name'        => $row['lastName'],
                           'kills'       => $row['kills'],
                           'deaths'      => $row['deaths'],
                           'connected'   => $time_str,
                           'team'        => '',
                           'team_name'   => '',
                           'bg'          => BgColor("clan"));
    }
}
echo '<script type="application/json" id="players-json">' . json_encode($players) . '</script>';

?>