<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

// ---------------------------------------------
// Web version
// ---------------------------------------------
echo "<h3>Web version</h3>";

$webversion = '2.00.91';
echo "Updating database schema webversion.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$webversion' WHERE `keyname` = 'webversion'");

// ---------------------------------------------
// Left 4 Dead 2 ribbons
// ---------------------------------------------
// A ribbon counts the days its player won its daily award. Left 4 Dead's 5 / 15 / 30 / 50 days are out of reach on
// Left 4 Dead 2, whose many players seldom win the same award twice: its ribbons take 1 / 3 / 6 / 10 days.
// Ribbons already there (copied from Left 4 Dead) are fixed, and the awards without ribbons get them.
echo "<h3>Left 4 Dead 2 ribbons</h3>";

$db->query("SELECT code FROM hlstats_Awards WHERE game = 'l4d2'");
$awards = array();
while (list($code) = $db->fetch_row()) {
    $awards[strtolower($code)] = true;
}

if (!$awards) {
    echo "&rarr; Left 4 Dead 2 has no awards: skipped<br />";
} else {
    // Ribbons of an award Left 4 Dead 2 does not have are never given (Left 4 Dead's killed_exploding and killed_gas
    // are its killed_boomer and killed_smoker)
    $never = "
        FROM hlstats_Ribbons AS r
        LEFT JOIN hlstats_Awards AS a ON a.game = r.game AND a.code = r.awardCode
        WHERE r.game = 'l4d2' AND r.special = 0 AND a.awardId IS NULL
    ";
    $db->query("DELETE pr FROM hlstats_Players_Ribbons AS pr JOIN (SELECT r.ribbonId $never) AS n ON n.ribbonId = pr.ribbonId");
    $db->query("DELETE r $never");
    echo "&rarr; " . mysqli_affected_rows($db->link) . " ribbons of awards Left 4 Dead 2 does not have removed<br />";

    // Two ribbons named like boomer_claw's and tank_claw's: named after their own awards
    $renamed = 0;
    foreach (array('bilebomb_tank' => array('Boom!', "Green Can't Be Healthy"), 'charger_pummel' => array('Lambs 2 Slaughter', 'Hulk Smash!')) as $code => list($from, $to)) {
        $db->query("
            UPDATE hlstats_Ribbons SET ribbonName = REPLACE(ribbonName, '" . $db->escape($from) . "', '" . $db->escape($to) . "')
            WHERE game = 'l4d2' AND awardCode = '$code' AND ribbonName LIKE '%" . $db->escape($from) . "'
        ");
        $renamed += mysqli_affected_rows($db->link);
    }
    echo "&rarr; $renamed ribbons renamed (bilebomb_tank: Green Can't Be Healthy, charger_pummel: Hulk Smash!)<br />";

    // Awards whose four ribbons still take Left 4 Dead's days: lowered (days set by hand stay as they are)
    $db->query("
        SELECT awardCode FROM hlstats_Ribbons
        WHERE game = 'l4d2' AND special = 0
        GROUP BY awardCode
        HAVING GROUP_CONCAT(awardCount ORDER BY awardCount) = '5,15,30,50'
    ");
    $codes = array();
    while (list($code) = $db->fetch_row()) {
        $codes[] = "'" . $db->escape($code) . "'";
    }
    $lowered = 0;
    if ($codes) {
        $db->query("
            UPDATE hlstats_Ribbons
            SET awardCount = CASE awardCount WHEN 5 THEN 1 WHEN 15 THEN 3 WHEN 30 THEN 6 WHEN 50 THEN 10 END
            WHERE game = 'l4d2' AND special = 0 AND awardCode IN (" . implode(', ', $codes) . ")
        ");
        $lowered = mysqli_affected_rows($db->link);
    }
    echo "&rarr; $lowered ribbons lowered from 5 / 15 / 30 / 50 to 1 / 3 / 6 / 10 days<br />";

    // The awards without ribbons get four. Award code => its ribbon's picture (Left 4 Dead's, in
    // hlstatsimg/games/l4d2/ribbons) and name
    $ribbons = array(
        'boomer_claw'      => array('boomer_claw',      'Boom!'),
        'bilebomb_tank'    => array('boomer_claw',      "Green Can't Be Healthy"),
        'headshot'         => array('headshot',         'Brain Salad'),
        'healed_teammate'  => array('healed_teammate',  'Field Medic'),
        'hunter_claw'      => array('hunter_claw',      'Grim Reaper'),
        'inferno'          => array('inferno',          'Cremator'),
        'killed_boomer'    => array('killed_exploding', 'Stomach Upset'),
        'killed_smoker'    => array('killed_gas',       'Tongue Twister'),
        'killed_hunter'    => array('killed_hunter',    'Hunter Punter'),
        'killed_survivor'  => array('killed_survivor',  'Dead Wreckening'),
        'killed_tank'      => array('killed_tank',      'Tankbuster'),
        'killed_witch'     => array('killed_witch',     'Inquisitor'),
        'latency'          => array('latency',          'Nothing Special'),
        'pipe_bomb'        => array('pipe_bomb',        'Pyrotechnician'),
        'pounce'           => array('pounce',           'Free 2 Fly'),
        'rescued_survivor' => array('rescued_survivor', 'Ground Cover'),
        'revived_teammate' => array('revived_teammate', 'Helping Hand'),
        'smoker_claw'      => array('smoker_claw',      'Chain Smoker'),
        'tank_claw'        => array('tank_claw',        'Lambs 2 Slaughter'),
        'charger_pummel'   => array('tank_claw',        'Hulk Smash!'),
        'tongue_grab'      => array('tongue_grab',      'Drag &amp; Drop'),
        'vomit'            => array('vomit',            'Barf Bagged'),
    );
    // Picture prefix => days won, title
    $tiers = array(1 => array(1, 'Bronze'), 2 => array(3, 'Silver'), 3 => array(6, 'Golden'), 4 => array(10, 'Bloody'));

    $db->query("SELECT DISTINCT awardCode FROM hlstats_Ribbons WHERE game = 'l4d2'");
    $have = array();
    while (list($code) = $db->fetch_row()) {
        $have[strtolower($code)] = true;
    }
    $values = array();
    foreach ($ribbons as $code => list($image, $name)) {
        if (isset($awards[$code]) && !isset($have[$code])) {
            foreach ($tiers as $prefix => list($days, $title)) {
                $values[] = "('$code', $days, 0, 'l4d2', '{$prefix}_$image.png', '" . $db->escape("$title $name") . "')";
            }
        }
    }
    $added = 0;
    if ($values) {
        $db->query("INSERT IGNORE INTO hlstats_Ribbons (awardCode, awardCount, special, game, image, ribbonName) VALUES " . implode(', ', $values));
        $added = mysqli_affected_rows($db->link);
    }
    echo "&rarr; $added ribbons added (" . (count($values) / 4) . " awards without ribbons)<br />";
    echo "Players get them at the next run of hlstats-awards.pl.<br />";
}
ob_flush();
flush();

// ---------------------------------------------
// Bans: report a player, appeal a ban
// ---------------------------------------------
// Where players report a player or appeal a ban: a forum thread, a Discord channel... (Admin > Bans > Bans Settings),
// linked from the bans pages. Web only (opttype 2); empty shows no link.
echo "<h3>Bans: report and appeal</h3>";

foreach (array('report_url', 'appeal_url') as $key) {
    $db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('$key', '', 2)");
    echo "&rarr; <b>$key</b> added (skipped if already exists)<br />";
}
// Saved on the settings page before this update: web only too, the address kept
$db->query("UPDATE hlstats_Options SET opttype = 2 WHERE keyname IN ('report_url', 'appeal_url')");

// Update 86 stored the choice as its label: 'External' is 0 and 'Internal' 1, as the settings and the menu read it
$db->query("UPDATE hlstats_Options SET `value` = IF(`value` = 'Internal', '1', '0') WHERE `keyname` = 'Sourcebans_Site' AND `value` NOT IN ('0', '1')");
echo "&rarr; <b>Sourcebans_Site</b>: " . mysqli_affected_rows($db->link) . " value fixed (External = 0, Internal = 1)<br />";
ob_flush();
flush();

$dbversion = 91;
echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
