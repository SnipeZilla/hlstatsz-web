<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

function addIndexIfMissing($table, $indexName, $indexSQL) {
    global $db;

    $exists = $db->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND INDEX_NAME = '$indexName'
    ");

    if ($exists && $exists->num_rows > 0) {
        echo "<b>$table:</b> index <b>$indexName</b> already exists<br />";
        return;
    }

    $db->query("ALTER TABLE `$table` ADD KEY `$indexName` $indexSQL;");
    echo "<b>$table:</b> added index <b>$indexName</b><br />";
}

addIndexIfMissing('hlstats_Events_Chat', 'playerId', '(playerId)');
addIndexIfMissing('hlstats_Events_Chat', 'serverId', '(serverId)');

$db->query("
    UPDATE hlstats_Options
    SET `value` = '14'
    WHERE `keyname` = 'dbversion'
");

?>
