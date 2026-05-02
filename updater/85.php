<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

function addIndex($db, $table, $indexName, $indexDefinition)
{
    // Check if index already exists
    $sql = "
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND INDEX_NAME = '$indexName'
        LIMIT 1
    ";

    $res = $db->query($sql);

    if ($res && $res->num_rows > 0) {
        echo "<b>$table:</b> index <b>$indexName</b> already exists<br />";
        return;
    }

    // Add the index
    $alter = "ALTER TABLE `$table` ADD INDEX `$indexName` $indexDefinition;";
    if ($db->query($alter)) {
        echo "<b>$table:</b> added index <b>$indexName</b><br />";
    } else {
        echo "<b>$table:</b> FAILED to add index <b>$indexName</b>: " . $db->error . "<br />";
    }

    ob_flush();
    flush();
}

// ---------------------------------------------
// List of event tables + required indexes
// ---------------------------------------------

$tables = [

    'hlstats_Events_Admin' => [
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_ChangeName' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_ChangeRole' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_ChangeTeam' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Chat' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Connects' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Disconnects' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Entries' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Frags' => [
        ['killer_eventTime', '(killerId, eventTime)'],
        ['victim_eventTime', '(victimId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Latency' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_PlayerActions' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_PlayerPlayerActions' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Rcon' => [
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Statsme' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Statsme2' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_StatsmeLatency' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_StatsmeTime' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Suicides' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_TeamBonuses' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Events_Teamkills' => [
        ['killer_eventTime', '(killerId, eventTime)'],
        ['victim_eventTime', '(victimId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

    'hlstats_Players_History' => [
        ['playerId_eventTime', '(playerId, eventTime)'],
        ['eventTime', '(eventTime)']
    ],

];

// ---------------------------------------------
// Execute migration
// ---------------------------------------------

echo "<h3>HLstatsZ Event Table Index Migration</h3>";

foreach ($tables as $table => $indexes) {
    echo "<br /><b>Processing $table</b><br />";
    foreach ($indexes as $idx) {
        list($name, $definition) = $idx;
        addIndex($db, $table, $name, $definition);
    }
}

echo "<br /><b>Index migration complete.</b><br />";
ob_flush();
flush();

// -----------------------------------------
// Add hlstats_Players.lastPing
// -----------------------------------------
$col_exists = $db->query("
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hlstats_Players'
      AND COLUMN_NAME = 'lastPing'
");

list($exists) = $col_exists->fetch_row();

if (!$exists) {
    echo "<br /><b> Adding lastPing column to hlstats_Players...</b><br />";

    $db->query("
        ALTER TABLE hlstats_Players
        ADD COLUMN lastPing INT UNSIGNED NULL DEFAULT NULL
        AFTER lastAddress
    ");

    echo "<b>lastPing column added.</b><br />";
}

echo "<b>lastPing migration </b>...";

ob_flush();
flush();

$result = $db->query("
    SELECT e.playerId, e.ping
    FROM hlstats_Events_Latency e
    JOIN (
        SELECT playerId, MAX(eventTime) AS last_event
        FROM hlstats_Events_Latency
        GROUP BY playerId
    ) t ON e.playerId = t.playerId AND e.eventTime = t.last_event
");


while ($row = $result->fetch_assoc()) {
    $playerId = (int)$row['playerId'];
    $ping     = (int)$row['ping'];

    $db->query("
        UPDATE hlstats_Players
        SET lastPing = $ping
        WHERE playerId = $playerId
    ");
}

echo "<b> complete.</b><br />";

ob_flush();
flush();

$dbversion = 85;

echo "Updating database schema numbers.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
