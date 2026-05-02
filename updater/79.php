<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }
    
    // Fix `tau_cannon`
    $db->query("UPDATE hlstats_Awards SET `name` = 'Gauss King' WHERE `code` = 'tau_cannon'");
    $db->query("UPDATE hlstats_Weapons SET `name` = 'Tau Cannon / Rail Gun' WHERE `code` = 'tau_cannon'");
    
    // Fix `gluon gun`
    $db->query("UPDATE hlstats_Awards SET `name` = 'Egon King' WHERE `code` = 'gluon gun'");
    $db->query("UPDATE hlstats_Weapons SET `name` = 'Egon / Gluon Gun' WHERE `code` = 'gluon gun'");


    echo "Adding support for Counter-Strike 2<br />";

    //
    // Add support for Counter-Strike 2
    //
    $db->query("
        INSERT IGNORE INTO `hlstats_Games` (`code`, `name`, `realgame`, `hidden`) VALUES
            ('cs2', 'Counter-Strike 2', 'cs2', '1');
    ");
    $db->query("
        INSERT IGNORE INTO `hlstats_Games_Supported` VALUES ('cs2', 'Counter-Strike 2'); 
    ");
    // Copy from CS:GO
    $db->query("
        INSERT IGNORE INTO `hlstats_Games_Defaults` (`code`, `parameter`, `value`) 
        SELECT 'cs2', parameter, value FROM `hlstats_Games_Defaults` WHERE code = 'csgo';
    ");
    $db->query("
        INSERT IGNORE INTO `hlstats_Actions`(`game`, `code`, `reward_player`, `reward_team`, `team`, `description`, `for_PlayerActions`, `for_PlayerPlayerActions`, `for_TeamActions`, `for_WorldActions`)
        SELECT 'cs2', code, reward_player, reward_team, team, description, for_PlayerActions, for_PlayerPlayerActions, for_TeamActions, for_WorldActions FROM hlstats_Actions WHERE game='csgo';
    ");
    $db->query("
        INSERT IGNORE INTO hlstats_Awards (game, awardType, code, name, verb)
        SELECT 'cs2', awardType, code, name, verb FROM hlstats_Awards WHERE game='csgo';
    ");
    $db->query("
        INSERT IGNORE INTO hlstats_Ribbons (game, awardCode, awardCount, special, image, ribbonName)
        SELECT 'cs2', awardCode, awardCount, special, image, ribbonName FROM hlstats_Ribbons WHERE game='csgo';
    ");
    $db->query("
        INSERT IGNORE INTO hlstats_Ranks (game, image, minKills, maxKills, rankName)
        SELECT 'cs2', image, minKills, maxKills, rankName FROM hlstats_Ranks WHERE game='csgo';
    ");
    $db->query("
        INSERT IGNORE INTO hlstats_Teams (game, code, name, hidden, playerlist_bgcolor, playerlist_color, playerlist_index)
        SELECT 'cs2', code, name, hidden, playerlist_bgcolor, playerlist_color, playerlist_index FROM hlstats_Teams WHERE game='csgo';
    ");
    $db->query("
        INSERT IGNORE INTO hlstats_Weapons (game, code, name, modifier)
        SELECT 'cs2', code, name, modifier FROM hlstats_Weapons WHERE game='csgo';
    ");

    echo "Done.<br />";

    $dbversion = 79;

    // Perform database schema update notification
    print "Updating database schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
