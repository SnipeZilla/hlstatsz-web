<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

function addIndex88($db, $table, $indexName, $indexDefinition)
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
// Country Data
// ---------------------------------------------
echo "<h3>Extend Country Data Options</h3>";

echo "<br /><b>Extending option for Country Data...</b><br />";
$db->query("DELETE FROM hlstats_Options_Choices WHERE keyname='countrydata'");
$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('countrydata', 0,'Hide', 1)");
$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('countrydata', 1,'Show City, Country', 0)");
$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('countrydata', 2,'Show State, Country', 0)");
$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('countrydata', 3,'Show City, State, Country', 0)");
$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('countrydata', 4,'Show Country', 0)");

echo "<br /><b>Inserted new options for Country Data<br />";
ob_flush();
flush();

// ---------------------------------------------
// Player ranking performance indexes
// ---------------------------------------------

echo "<h3>hlstats_Players Ranking Index Migration</h3>";

$result = $db->query("
SELECT INDEX_NAME 
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '".DB_NAME."'   -- <-- DB name
  AND TABLE_NAME = 'hlstats_Players' -- table
  AND INDEX_NAME <> 'PRIMARY'
GROUP BY INDEX_NAME;
");
while (list($res) = $db->fetch_row($result))
{
   $db->query("ALTER TABLE `hlstats_Players` DROP INDEX `$res`");
   echo "hlstats_Players &rarr; DROPPED INDEX $res.<br>";

}

$result = $db->query("
SELECT INDEX_NAME 
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '".DB_NAME."'   -- <-- DB name
  AND TABLE_NAME = 'hlstats_Players_History' -- table
  AND INDEX_NAME <> 'PRIMARY'
GROUP BY INDEX_NAME;
");
while (list($res) = $db->fetch_row($result))
{
   $db->query("ALTER TABLE `hlstats_Players_History` DROP INDEX `$res`");
   echo "hlstats_Players_History &rarr; DROPPED INDEX $res.<br>";

}

$result = $db->query("
SELECT INDEX_NAME 
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '".DB_NAME."'   -- <-- DB name
  AND TABLE_NAME = 'hlstats_PlayerUniqueIds' -- table
  AND INDEX_NAME <> 'PRIMARY'
GROUP BY INDEX_NAME;
");
while (list($res) = $db->fetch_row($result))
{
   $db->query("ALTER TABLE `hlstats_PlayerUniqueIds` DROP INDEX `$res`");
   echo "hlstats_PlayerUniqueIds &rarr; DROPPED INDEX $res.<br>";

}

$tables = [

    'hlstats_Players' => [
        ['idx_players_skill_kills', '(skill DESC, kills DESC)'],
        ['idx_players_skill_kills_conn', '(skill DESC, kills DESC, connection_time DESC)'],
        ['idx_clan_rank', '(game, hideranking, clan)'],
        ['idx_players_country', '(game, hideranking, flag)'],
    ],

    'hlstats_Players_History' => [
        ['idx_history_player_game', '(playerId, game)'],
        ['idx_history_eventTime', '(eventTime)'],
        ['idx_history_skill_kills_time', '(skill_change DESC, kills DESC, eventTime DESC)'],
    ],

    'hlstats_PlayerUniqueIds' => [
        ['idx_uid_playerId', '(playerId)'],
    ],
];

foreach ($tables as $table => $indexes) {
    echo "<br /><b>Processing $table</b><br />";
    foreach ($indexes as $idx) {
        list($name, $definition) = $idx;
        addIndex88($db, $table, $name, $definition);
    }
}

// ---------------------------------------------
// Add Ribbons
// ---------------------------------------------
echo "<h3>Adding Ribbons for L4D2</h3>";
    $db->query("
        INSERT IGNORE INTO hlstats_Ribbons (game, awardCode, awardCount, special, image, ribbonName)
        SELECT 'l4d2', awardCode, awardCount, special, image, ribbonName FROM hlstats_Ribbons WHERE game='l4d';
    ");

echo "  &rarr; <b>L4D2 Ribbons added...</b><br />";
ob_flush();
flush();

// ---------------------------------------------
// Add SG556 CS2
// ---------------------------------------------
echo "<h3>Rename Weapon code SG556 for CS2</h3>";

$db->query("UPDATE hlstats_Awards SET name='SG 556', verb='kills with sg556' WHERE code ='sg556'");
$db->query("UPDATE hlstats_Ribbons SET ribbonName='Award of SG 556' WHERE awardCode='SG556'");
$db->query("UPDATE hlstats_Ribbons SET ribbonName='Bronze SG 556' WHERE awardCode='SG556'");
$db->query("UPDATE hlstats_Ribbons SET ribbonName= 'Silver SG 556' WHERE awardCode='SG556'");
$db->query("UPDATE hlstats_Ribbons SET ribbonName= 'Gold SG 556' WHERE awardCode='SG556'");
$db->query("UPDATE hlstats_Ribbons SET ribbonName= 'Platinum SG 556' WHERE awardCode='SG556'");
$db->query("UPDATE hlstats_Ribbons SET ribbonName= 'Supreme SG 556' WHERE awardCode='SG556'");
$db->query("UPDATE hlstats_Weapons SET name='SG 556' WHERE code='SG556'");


echo "  &rarr; <b>SG556 Ribbons & Weapon renamed</b><br />";
ob_flush();
flush();


$dbversion = 88;

echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
