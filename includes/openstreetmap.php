<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner
            
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/
global $db, $g_options, $clandata, $clan;

function BgColor($name) {
    switch ($name) {
        case "server":             return "#4d9933"; //4d9933
        
        case "Blue":               return "#336699";
        case "Red":                return "#99334d";
        ////
        case "#Dustbowl_team1":    return "#20a3df";
        case "#Dustbowl_team2":    return "#df2020";
        case "#Hunted_team1":      return "#684769";
        case "#Hunted_team2":      return "#c04dc4";
        case "#Hunted_team3":      return "#9e1a1a";

        case "CT":                 return "#338099";
        case "TERRORIST":          return "#998033";

        case "Counter-Terrorists": return "#0000ff";
        case "Terrorists":         return "#ff0000";

        case "Agathia Knights":    return "#338099";
        case "The Mason Order":    return "#996633";

        case "Americans":          return "#005aad";
        case "British":            return "#f1f1f1";

        case "#DDD_Team_Blue":     return "#338099";
        case "#DDD_Team_Red":      return "#993333";

        case "Allies":             return "#338099";
        case "Axis":               return "#993333";

        case "Corps":              return "#338099";
        case "Punks":              return "#993333";

        case "#FF_TEAM_BLUE":      return "#336699";
        case "#FF_TEAM_GREEN":     return "#339933";
        case "#FF_TEAM_RED":       return "#993333";
        case "#FF_TEAM_YELLOW":    return "#b8b82c";
        case "Attackers":          return "#ff0000";
        case "Defenders":          return "#00ff00";

        case "DESPERADOS":         return "#338099";
        case "VIGILANTES":         return "#993333";

        case "Janus":              return "#338099";
        case "MI6":                return "#993333";

        case "Hidden":             return "#338099";
        case "IRIS":               return "#993333";

        case "Combine":            return "#338099";
        case "Rebels":             return "#993333";

        case "Iraqi Insurgents":   return "#338099";
        case "U.S. Marines":       return "#993333";

        case "Infected":           return "#993333";
        case "Survivor":           return "#338099";
        //
        case "Undead":             return "#993333";

        case "Consortium":         return "#338099";
        case "Empire":             return "#993333";
        
        case "alien1team":         return "#338099";
        case "marine1team":        return "#993333";

        case "Jinrai":             return "#338099";
        case "NSF":                return "#993333";

        case "Knights":            return "#334d99";
        case "Pirates":            return "#994d33";
        case "Vikings":            return "#997033";

        case "Goa'uld":            return "#993333";
        case "Tau'ri":             return "#338099";

        case "hgrunt":             return "#336699";
        case "scientist":          return "#339933";

        case "clan":               return "#ff9800";


        default:                   return "#808080";    
    }
}

$players = array();
if ( $type ==  "main" ) {
    // Servers
    if (empty($game)) {
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
                    WHERE game = '$game'
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
    if (empty($game)){
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
                       AND s.game='$game'
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