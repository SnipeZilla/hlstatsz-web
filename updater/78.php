<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 78;
    $version = "1.6.19";

    // Fix `tau_cannon`
    $db->query("UPDATE IGNORE hlstats_Awards SET `name` = 'Gauss King' WHERE `code` = 'tau_cannon'");
    $db->query("UPDATE IGNORE hlstats_Weapons SET `name` = 'Tau Cannon / Rail Gun' WHERE `code` = 'tau_cannon'");
    
    // Fix `gluon gun`
    $db->query("UPDATE IGNORE hlstats_Awards SET `name` = 'Egon King' WHERE `code` = 'gluon gun'");
    $db->query("UPDATE IGNORE hlstats_Weapons SET `name` = 'Egon / Gluon Gun' WHERE `code` = 'gluon gun'");

    // Perform database schema update notification
    print "Updating database schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>