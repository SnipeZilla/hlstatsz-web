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
function TeamColor($name) {
    // One palette for every game
    static $palette = array(
        'blue'   => '#3584d6',
        'red'    => '#dc4640',
        'gold'   => '#b98a14',
        'green'  => '#35994d',
        'purple' => '#a35fd0',
        'orange' => '#d46f1a',
    );

    // Team code (hlstats_Livestats.team) => palette color
    static $teams = array(
        'server'             => 'green',
        'clan'               => 'orange',

        // Team Fortress 2
        'Blue'               => 'blue',
        'Red'                => 'red',

        // Team Fortress Classic
        '#Dustbowl_team1'    => 'blue',
        '#Dustbowl_team2'    => 'red',
        '#Hunted_team1'      => 'purple',
        '#Hunted_team2'      => 'blue',
        '#Hunted_team3'      => 'red',

        // Counter-Strike (both spellings)
        'CT'                 => 'blue',
        'TERRORIST'          => 'gold',
        'Counter-Terrorists' => 'blue',
        'Terrorists'         => 'gold',

        // Age of Chivalry
        'Agathia Knights'    => 'blue',
        'The Mason Order'    => 'red',

        // Battle Grounds
        'Americans'          => 'blue',
        'British'            => 'red',

        // Dino D-Day
        '#DDD_Team_Blue'     => 'blue',
        '#DDD_Team_Red'      => 'red',

        // Day of Defeat
        'Allies'             => 'blue',
        'Axis'               => 'red',

        // Dystopia
        'Corps'              => 'blue',
        'Punks'              => 'red',

        // Fortress Forever
        '#FF_TEAM_BLUE'      => 'blue',
        '#FF_TEAM_GREEN'     => 'green',
        '#FF_TEAM_RED'       => 'red',
        '#FF_TEAM_YELLOW'    => 'gold',
        'Attackers'          => 'red',
        'Defenders'          => 'blue',

        // Fistful of Frags
        'DESPERADOS'         => 'red',
        'VIGILANTES'         => 'blue',

        // GoldenEye: Source
        'Janus'              => 'red',
        'MI6'                => 'blue',

        // The Hidden
        'Hidden'             => 'red',
        'IRIS'               => 'blue',

        // Half-Life 2 Deathmatch
        'Combine'            => 'blue',
        'Rebels'             => 'red',

        // Insurgency
        'Iraqi Insurgents'   => 'red',
        'U.S. Marines'       => 'blue',

        // Left 4 Dead
        'Infected'           => 'red',
        'Survivor'           => 'blue',

        'Undead'             => 'red',

        'Consortium'         => 'blue',
        'Empire'             => 'red',

        // Natural Selection
        'alien1team'         => 'red',
        'marine1team'        => 'blue',

        // NeoTokyo
        'Jinrai'             => 'blue',
        'NSF'                => 'red',

        // Pirates, Vikings and Knights
        'Knights'            => 'blue',
        'Pirates'            => 'red',
        'Vikings'            => 'gold',

        // Stargate
        "Goa'uld"            => 'red',
        "Tau'ri"             => 'blue',

        // Half-Life
        'hgrunt'             => 'blue',
        'scientist'          => 'green',
    );

    $name = (string) $name;
    return isset($teams[$name]) ? $palette[$teams[$name]] : null;
}

// grey
function BgColor($name) {
    $color = TeamColor($name);
    return $color === null ? '#858a93' : $color;
}
