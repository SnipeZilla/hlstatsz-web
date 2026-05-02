<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

$dbversion = 63;
$version = "1.6.12";

function addColumnIfMissing($table, $column, $definition) {
    global $db;

    $exists = $db->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND COLUMN_NAME = '$column'
    ");

    if ($exists->num_rows == 0) {
        $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

// hlstats_Players
addColumnIfMissing('hlstats_Players', 'teamkills', "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `hits`");

// hlstats_Players_History
addColumnIfMissing('hlstats_Players_History', 'teamkills',    "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `game`");
addColumnIfMissing('hlstats_Players_History', 'kill_streak',  "INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `teamkills`");
addColumnIfMissing('hlstats_Players_History', 'death_streak', "INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `kill_streak`");

$db->query("
    UPDATE hlstats_Players
    SET teamkills = IFNULL(
        (SELECT COUNT(id)
         FROM hlstats_Events_Teamkills
         WHERE hlstats_Events_Teamkills.killerId = hlstats_Players.playerId),
    0)
");

$db->query("UPDATE hlstats_Options SET value='$version' WHERE keyname='version'");
$db->query("UPDATE hlstats_Options SET value='$dbversion' WHERE keyname='dbversion'");

?>
