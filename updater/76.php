<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

$dbversion = 76;
$version = "1.6.19-pre2";

// Get CS:GO games
$csgogames = [];
$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame='csgo'");
while ($rowdata = $db->fetch_row($result)) {
    $csgogames[] = $db->escape($rowdata[0]);
}

// Get CSS games (unused but kept for compatibility)
$cssgames = [];
$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame='css'");
while ($rowdata = $db->fetch_row($result)) {
    $cssgames[] = $db->escape($rowdata[0]);
}

print "Correcting CS:GO Actions. (<a href=\"http://tracker.hlxce.com/issues/1599\">#1599</a>)<br />";

$renames = [
    'All_Hostages_Rescued' => 'SFUI_Notice_All_Hostages_Rescued',
    'Bomb_Defused'         => 'SFUI_Notice_Bomb_Defused',
    'CTS_Win'              => 'SFUI_Notice_CTS_Win',
    'Target_Bombed'        => 'SFUI_Notice_Target_Bombed',
    'Terrorists_Win'       => 'SFUI_Notice_Terrorists_Win'
];

foreach ($csgogames as $game)
{
    foreach ($renames as $old => $new)
    {
        $db->query("
            UPDATE hlstats_Actions a
            LEFT JOIN hlstats_Actions b
                ON b.game = a.game
               AND b.team = a.team
               AND b.code = '$new'
            SET a.code = '$new'
            WHERE a.game = '$game'
              AND a.code = '$old'
              AND b.code IS NULL
        ");
    }
}

print "Updating database and version schema numbers.<br />";
$db->query("UPDATE hlstats_Options SET value='$dbversion' WHERE keyname='dbversion'");

?>
