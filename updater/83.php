<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }
    

    echo "Updating awards and actions for Counter-Strike 2<br />";

    // hlstats_Actions
    $db->query("
        INSERT IGNORE INTO `hlstats_Actions` (`game`, `code`, `reward_player`, `reward_team`, `team`, `description`, `for_PlayerActions`, `for_PlayerPlayerActions`, `for_TeamActions`, `for_WorldActions`) VALUES
        ('cs2', 'Defuse_Aborted', -2, 0, 'CT', 'Bomb defuse aborted', '1', '0', '0', '0')
    ");

    echo "Done.<br />";

    $dbversion = 83;

    // Perform database schema update notification
    print "Updating database schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
