<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

$dbversion = 61;
$version = "1.6.12";

$changed_weapons = array(
    'glovesurgent'               => 'gloves_running_urgently',
    'sydneysleeper'              => 'sydney_sleeper',
    'lochnload'                  => 'loch_n_load',
    'brassbeast'                 => 'brass_beast',
    'bear_claws'                 => 'warrior_spirit',
    'obj_sentrygun_mini'         => 'obj_minisentry',
    'tf_projectile_healing_bolt' => 'crusaders_crossbow'
);

$tfgames = [];
$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame='tf'");
while ($rowdata = $db->fetch_row($result)) {
    $tfgames[] = $db->escape($rowdata[0]);
}

foreach ($tfgames as $game)
{
    $tfservers = [];
    $result = $db->query("SELECT serverId FROM hlstats_Servers WHERE game='$game'");
    while ($rowdata = $db->fetch_row($result)) {
        $tfservers[] = $db->escape($rowdata[0]);
    }

    if (!$tfservers) {
        continue;
    }

    if (count($tfservers) == 1) {
        $serverclause = "serverId=".$tfservers[0];
    } else {
        $serverclause = "serverId IN (".implode(",", $tfservers).")";
    }

    foreach ($changed_weapons as $old => $new)
    {
        $db->query("
            UPDATE hlstats_Awards a
            LEFT JOIN hlstats_Awards b
                ON b.game=a.game
               AND b.awardType=a.awardType
               AND b.code='$new'
            SET a.code='$new'
            WHERE a.game='$game'
              AND a.code='$old'
              AND a.awardType='W'
              AND b.code IS NULL
        ");

        $db->query("
            UPDATE hlstats_Ribbons r
            LEFT JOIN hlstats_Ribbons x
                ON x.game=r.game
               AND x.awardCode='$new'
               AND x.awardCount=r.awardCount
               AND x.special=r.special
            SET r.awardCode='$new'
            WHERE r.game='$game'
              AND r.awardCode='$old'
              AND x.awardCode IS NULL
        ");

        $db->query("
            UPDATE hlstats_Weapons w
            LEFT JOIN hlstats_Weapons y
                ON y.game=w.game
               AND y.code='$new'
            SET w.code='$new',
                w.kills = w.kills + IFNULL(
                    (SELECT COUNT(weapon)
                     FROM hlstats_Events_Frags
                     WHERE weapon='$old' AND $serverclause),
                0)
            WHERE w.game='$game'
              AND w.code='$old'
              AND y.code IS NULL
        ");

        $db->query("UPDATE hlstats_Events_Frags SET weapon='$new' WHERE weapon='$old' AND $serverclause");
        $db->query("UPDATE hlstats_Events_Statsme SET weapon='$new' WHERE weapon='$old' AND $serverclause");
        $db->query("UPDATE hlstats_Events_Statsme2 SET weapon='$new' WHERE weapon='$old' AND $serverclause");
        $db->query("UPDATE hlstats_Events_Suicides SET weapon='$new' WHERE weapon='$old' AND $serverclause");
        $db->query("UPDATE hlstats_Events_Teamkills SET weapon='$new' WHERE weapon='$old' AND $serverclause");
    }

    $db->query("
        INSERT IGNORE INTO hlstats_Awards
            (awardType, game, code, name, verb)
        VALUES
            ('W', '$game', 'ullapool_caber', 'Boom Sticka', 'Caber BOOM kills')
    ");

    $db->query("
        INSERT IGNORE INTO hlstats_Weapons
            (game, code, name, modifier, kills)
        VALUES
            ('$game', 'ullapool_caber_explosion', 'The Ullapool Caber BOOM', 2.0,
             IFNULL((SELECT COUNT(weapon)
                     FROM hlstats_Events_Frags
                     WHERE weapon='ullapool_caber_explosion'
                       AND $serverclause), 0))
    ");

    $db->query("
        INSERT IGNORE INTO hlstats_Ribbons
            (awardCode, awardCount, special, game, image, ribbonName)
        VALUES
            ('ullapool_caber_explosion', 1, 0, '$game', '1_ullapool_caber_explosion.png', 'Bronze Ullapool Caber BOOM'),
            ('ullapool_caber_explosion', 5, 0, '$game', '2_ullapool_caber_explosion.png', 'Silver Ullapool Caber BOOM'),
            ('ullapool_caber_explosion', 10, 0, '$game', '3_ullapool_caber_explosion.png', 'Gold Ullapool Caber BOOM')
    ");
}

$db->query("UPDATE hlstats_Options SET value='$version' WHERE keyname='version'");
$db->query("UPDATE hlstats_Options SET value='$dbversion' WHERE keyname='dbversion'");

?>
