<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

function addIndex86($db, $table, $indexName, $indexDefinition)
{
    $res = $db->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND INDEX_NAME = '$indexName'
        LIMIT 1
    ");

    if ($res && $res->num_rows > 0) {
        echo "<b>$table:</b> index <b>$indexName</b> already exists<br />";
        return;
    }

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
// Ribbon & award lookup indexes
// ---------------------------------------------

echo "<h3>HLstatsZ Ribbon Index Migration</h3>";

$tables = [

    // No indexes existed — ribbonId filter was a full table scan
    'hlstats_Players_Ribbons' => [
        ['ribbonId_playerId', '(ribbonId, playerId)'],
    ],

    // PK is (awardTime, awardId, playerId, game) — joins on (playerId, awardId, game) skipped it entirely
    'hlstats_Players_Awards' => [
        ['player_award', '(playerId, awardId, game)'],
    ],

];

foreach ($tables as $table => $indexes) {
    echo "<br /><b>Processing $table</b><br />";
    foreach ($indexes as $idx) {
        list($name, $definition) = $idx;
        addIndex86($db, $table, $name, $definition);
    }
}

echo "<br /><b>Index migration complete.</b><br />";
ob_flush();
flush();

// ---------------------------------------------
// Add Sourcebans option
// ---------------------------------------------
echo "<h3>HLstatsZ SourceBans Option</h3>";

echo "<br /><b>Adding Sourcebans option for external or internal page...</b><br />";
$db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('Sourcebans_Site', 'External', 1)");
$db->query("INSERT IGNORE INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('Sourcebans_Site', 0,'External', 1)");
$db->query("INSERT IGNORE INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('Sourcebans_Site', 1,'Internal', 0)");
echo "  &rarr; <b>Sourcebans_Site</b> inserted (skipped if already exists)<br />";

ob_flush();
flush();

// ---------------------------------------------
// Seed default footer links
// ---------------------------------------------
echo "<h3>HLstatsZ Footer Links Option</h3>";

echo "<br /><b>Seeding default footer links...</b><br />";

$defaults = [
    'footer_link1_label' => 'SnipeZilla',
    'footer_link1_url'   => 'https://snipezilla.com',
    'footer_link2_label' => 'Steam',
    'footer_link2_url'   => 'https://steamcommunity.com/groups/snipezilla',
    'footer_link3_label' => 'AlliedMods',
    'footer_link3_url'   => 'https://forums.alliedmods.net/forumdisplay.php?f=156',
];

foreach ($defaults as $key => $value) {
    $k = $db->escape($key);
    $v = $db->escape($value);
    $db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('$k', '$v', 1)");
    echo "  &rarr; <b>$key</b> inserted (skipped if already exists)<br />";
}

ob_flush();
flush();

$dbversion = 86;

echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>