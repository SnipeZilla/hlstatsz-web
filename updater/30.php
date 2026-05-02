<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

$exists = $db->query("
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hlstats_Events_Chat'
      AND INDEX_NAME = 'message'
");

if ($exists->num_rows == 0) {
    $db->query("
        CREATE FULLTEXT INDEX message ON hlstats_Events_Chat (message)
    ");
} else {
    echo "<b>hlstats_Events_Chat:</b> FULLTEXT index <b>message</b> already exists<br />";
}

$db->query("
    UPDATE hlstats_Options
    SET `value` = '30'
    WHERE `keyname` = 'dbversion'
");

?>
