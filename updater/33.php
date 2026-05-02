<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

//
// TF2
//
$tf2games = [];
$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame = 'tf'");
while ($rowdata = $db->fetch_row($result)) {
    $tf2games[] = $db->escape($rowdata[0]);
}

foreach ($tf2games as $game)
{
    $db->query("
        INSERT IGNORE INTO hlstats_Weapons (game, code, name, modifier) VALUES
            ('$game','paintrain','The Pain Train',2),
            ('$game','sledgehammer','The Homewrecker',2)
    ");

    $exists = $db->query("
        SELECT 1 FROM hlstats_Awards
        WHERE game='$game' AND awardType='W' AND code='paintrain'
    ");
    if ($exists->num_rows == 0) {
        $db->query("
            INSERT INTO hlstats_Awards (awardType, game, code, name, verb)
            VALUES ('W','$game','paintrain','The Pain Train','kills with The Pain Train')
        ");
    }

    $exists = $db->query("
        SELECT 1 FROM hlstats_Awards
        WHERE game='$game' AND awardType='W' AND code='sledgehammer'
    ");
    if ($exists->num_rows == 0) {
        $db->query("
            INSERT INTO hlstats_Awards (awardType, game, code, name, verb)
            VALUES ('W','$game','sledgehammer','The Homewrecker','kills with The Homewrecker')
        ");
    }
}

//
// L4D2
//
$l4d2games = [];
$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame = 'l4d2'");
while ($rowdata = $db->fetch_row($result)) {
    $l4d2games[] = $db->escape($rowdata[0]);
}

foreach ($l4d2games as $game)
{

    $db->query("
        INSERT IGNORE INTO hlstats_Weapons (game, code, name, modifier) VALUES
            ('$game','golfclub','Golf Club',1.5),
            ('$game','rifle_m60','M60',1)
    ");

    $exists = $db->query("
        SELECT 1 FROM hlstats_Awards
        WHERE game='$game' AND awardType='W' AND code='golfclub'
    ");
    if ($exists->num_rows == 0) {
        $db->query("
            INSERT INTO hlstats_Awards (awardType, game, code, name, verb)
            VALUES ('W','$game','golfclub','Golf Club','kills with the Golf Club')
        ");
    }

    $exists = $db->query("
        SELECT 1 FROM hlstats_Awards
        WHERE game='$game' AND awardType='W' AND code='rifle_m60'
    ");
    if ($exists->num_rows == 0) {
        $db->query("
            INSERT INTO hlstats_Awards (awardType, game, code, name, verb)
            VALUES ('W','$game','rifle_m60','M60','kills with M60')
        ");
    }
}

$db->query("UPDATE hlstats_Options SET value='1.6.8' WHERE keyname='version'");
$db->query("UPDATE hlstats_Options SET value='33' WHERE keyname='dbversion'");

?>
