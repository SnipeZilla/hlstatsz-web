<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

$dbversion = 57;
$version = "1.6.11-beta3";

$tfgames = [];
$result = $db->query("SELECT code FROM hlstats_Games WHERE realgame='tf'");
while ($rowdata = $db->fetch_row($result)) {
    $tfgames[] = $db->escape($rowdata[0]);
}

foreach ($tfgames as $game)
{

    $exists = $db->query("
        SELECT 1 FROM hlstats_Actions
        WHERE game='$game' AND code='dalokohs' AND team=''
    ");
    if ($exists->num_rows == 0) {
        $db->query("
            INSERT INTO hlstats_Actions
                (game, code, reward_player, reward_team, team, description,
                 for_PlayerActions, for_PlayerPlayerActions, for_TeamActions, for_WorldActions)
            VALUES
                ('$game','dalokohs',0,0,'','Ate a Dalokohs Bar','1','0','0','0')
        ");
    }

    $exists = $db->query("
        SELECT 1 FROM hlstats_Actions
        WHERE game='$game' AND code='dalokohs_healself' AND team=''
    ");
    if ($exists->num_rows == 0) {
        $db->query("
            INSERT INTO hlstats_Actions
                (game, code, reward_player, reward_team, team, description,
                 for_PlayerActions, for_PlayerPlayerActions, for_TeamActions, for_WorldActions)
            VALUES
                ('$game','dalokohs_healself',0,0,'','Ate Dalokohs Bar for Health','1','0','0','0')
        ");
    }

    // Award
    $db->query("
        INSERT IGNORE INTO hlstats_Awards
            (awardType, game, code, name, verb)
        VALUES
            ('O','$game','dalokohs','Dalokohs is delicious!','Dalokohs eaten')
    ");

    // Ribbons
    $db->query("
        INSERT IGNORE INTO hlstats_Ribbons
            (awardCode, awardCount, special, game, image, ribbonName)
        VALUES
            ('dalokohs',1,0,'$game','1_dalokohs.png','Bronze Dalokohs'),
            ('dalokohs',5,0,'$game','2_dalokohs.png','Silver Dalokohs'),
            ('dalokohs',10,0,'$game','3_dalokohs.png','Gold Dalokohs')
    ");
}

$db->query("UPDATE hlstats_Options SET value='$version' WHERE keyname='version'");
$db->query("UPDATE hlstats_Options SET value='$dbversion' WHERE keyname='dbversion'");

?>
