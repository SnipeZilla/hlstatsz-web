<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }
    

    echo "Updating server settings<br />";

    // hlstats_Actions
    $db->query("
        UPDATE hlstats_Servers_Config_Default
        SET description = 'If enabled, bots are not tracked 1=on(default) 0=off -1=hidden from stats.'
        WHERE parameter = 'IgnoreBots';
    ");

    echo "Done.<br />";

    $dbversion = 82;

    // Perform database schema update notification
    print "Updating database schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
