<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

function addIndex87($db, $table, $indexName, $indexDefinition)
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
// Map stats sort indexes
// ---------------------------------------------
echo "<h3>hlstats_Maps_Counts sort indexes</h3>";

$mapIndexes = [
    ['game_kills',     '(game, kills, headshots)'],
    ['game_headshots', '(game, headshots, kills)'],
];

foreach ($mapIndexes as $idx) {
    list($name, $definition) = $idx;
    addIndex87($db, 'hlstats_Maps_Counts', $name, $definition);
}

ob_flush();
flush();

// ---------------------------------------------
// Player ranking performance indexes
// ---------------------------------------------

echo "<h3>hlstats_Players Ranking Index Migration</h3>";

$tables = [

    'hlstats_Players' => [
        // Covers game/hideranking WHERE filter + RANK() ORDER BY for skill-based ranking
        ['idx_players_ranking_skill', '(game, hideranking, skill DESC, kills DESC)'],
        // Same filter + RANK() ORDER BY for kills-based ranking
        ['idx_players_ranking_kills', '(game, hideranking, kills DESC, deaths ASC)'],
        // Country clan rankings: filter by (game, hideranking) then group by flag without a sort
        ['idx_players_country', '(game, hideranking, flag)'],
    ],

    'hlstats_Players_History' => [
        // Covers game filter + playerId JOIN + date range filter used in time-scoped ranking
        ['idx_history_rank', '(game, playerId, eventTime)'],
    ],

    'hlstats_PlayerUniqueIds' => [
        // Ensures the LEFT JOIN on playerId uses an index
        ['idx_uid_playerid', '(playerId)'],
    ],

];

foreach ($tables as $table => $indexes) {
    echo "<br /><b>Processing $table</b><br />";
    foreach ($indexes as $idx) {
        list($name, $definition) = $idx;
        addIndex87($db, $table, $name, $definition);
    }
}
// ---------------------------------------------
// Fix hlstats_Options opttype
// ---------------------------------------------
echo "<h3>hlstats_Options opttype</h3>";

echo "<br /><b>Fixing hlstats_Options opttype value=2...</b><br />";


$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='footer_link1_label'");
$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='footer_link1_url'");
$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='footer_link2_label'");
$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='footer_link2_url'");
$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='footer_link3_label'");
$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='footer_link3_url'");

$db->query("UPDATE hlstats_Options SET opttype=2 WHERE keyname='Sourcebans_Site'");

echo "  &rarr; <b>opttype fixed</b><br />";
ob_flush();
flush();

// ---------------------------------------------
// Add SG556 CS2
// ---------------------------------------------
echo "<h3>Weapon code SG556 for CS2</h3>";

$db->query("INSERT IGNORE INTO hlstats_Awards (awardType, game, code, name, verb) VALUES ('W', 'cs2', 'sg556', 'SG 553', 'kills with sg553')");
$db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES ('sg556', 1, 0, 'cs2', '1_sg553.png', 'Award of SG 553')");
$db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES ('sg556', 5, 0, 'cs2', '2_sg553.png', 'Bronze SG 553')");
$db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES ('sg556', 12, 0, 'cs2', '3_sg553.png', 'Silver SG 553')");
$db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES ('sg556', 20, 0, 'cs2', '4_sg553.png', 'Gold SG 553')");
$db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES ('sg556', 30, 0, 'cs2', '5_sg553.png', 'Platinum SG 553')");
$db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES ('sg556', 50, 0, 'cs2', '6_sg553.png', 'Supreme SG 553')");
$db->query("INSERT IGNORE INTO hlstats_Weapons (game, code, name, modifier) VALUES ('cs2', 'sg556', 'SG 553', 1.00)");


echo "  &rarr; <b>SG556 Ribbons & weapon added</b><br />";
ob_flush();
flush();


$dbversion = 87;

echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
