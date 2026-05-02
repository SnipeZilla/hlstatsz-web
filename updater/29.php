<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

$tf2games = [];
$tf2servers = [];

$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame = 'tf'");
while ($rowdata = $db->fetch_row($result)) {
    $tf2games[] = "'" . $db->escape($rowdata[0]) . "'";
}

if (count($tf2games) > 0) {

    $gamestring = implode(',', $tf2games);

    $db->query("
        UPDATE hlstats_Awards a
        LEFT JOIN hlstats_Awards b
            ON b.game = a.game
           AND b.awardType = a.awardType
           AND b.code = 'unique_pickaxe'
        SET a.code = 'unique_pickaxe'
        WHERE a.code = 'pickaxe'
          AND a.game IN ($gamestring)
          AND b.code IS NULL
    ");

    $db->query("
        UPDATE hlstats_Weapons w
        LEFT JOIN hlstats_Weapons x
            ON x.game = w.game
           AND x.code = 'unique_pickaxe'
        SET w.code = 'unique_pickaxe'
        WHERE w.code = 'pickaxe'
          AND w.game IN ($gamestring)
          AND x.code IS NULL
    ");

    $db->query("
        UPDATE hlstats_Ribbons r
        LEFT JOIN hlstats_Ribbons y
            ON y.game = r.game
           AND y.awardCode = 'unique_pickaxe'
        SET r.awardCode = 'unique_pickaxe'
        WHERE r.awardCode = 'pickaxe'
          AND r.game IN ($gamestring)
          AND y.awardCode IS NULL
    ");

    // Servers
    $result = $db->query("SELECT serverId FROM hlstats_Servers WHERE game IN ($gamestring)");
    while ($rowdata = $db->fetch_row($result)) {
        $tf2servers[] = $db->escape($rowdata[0]);
    }

    if (count($tf2servers) > 0) {


        $db->query("UPDATE hlstats_Events_Frags SET weapon = 'unique_pickaxe' WHERE weapon = 'pickaxe'");
        $db->query("UPDATE hlstats_Events_Suicides SET weapon = 'unique_pickaxe' WHERE weapon = 'pickaxe'");
        $db->query("UPDATE hlstats_Events_Teamkills SET weapon = 'unique_pickaxe' WHERE weapon = 'pickaxe'");
    }
}

$db->query("UPDATE hlstats_Options SET value = '29' WHERE keyname = 'dbversion'");

?>
