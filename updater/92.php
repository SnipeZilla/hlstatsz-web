<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

// ---------------------------------------------
// Themes: the style selector's list, the seasonal theme
// ---------------------------------------------
echo "<h3>Themes: style selector and seasonal theme</h3>";

foreach (array('styles_hidden', 'style_seasonal') as $key) {
    $db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('$key', '', 2)");
    echo "&rarr; <b>$key</b> added (skipped if already exists)<br />";
}
$db->query("UPDATE hlstats_Options SET opttype = 2 WHERE keyname IN ('styles_hidden', 'style_seasonal')");

$db->query("SELECT `value` FROM hlstats_Options WHERE `keyname` = 'style'");
list($style) = $db->fetch_row() ?: array('');
$style = themeName($style);
if ($style !== '' && isset(themeList(true)[$style]) && !isset(themeList()[$style])) {
    $db->query("UPDATE hlstats_Options SET `value` = '$style' WHERE `keyname` = 'style_seasonal'");
    $db->query("UPDATE hlstats_Options SET `value` = 'default' WHERE `keyname` = 'style'");
    echo "&rarr; the site's theme <b>$style</b> is a seasonal theme: switched on as the seasonal theme, the site's theme is Default<br />";
}
ob_flush();
flush();

$dbversion = 92;
echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
