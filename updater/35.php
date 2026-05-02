<?php

if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}
echo "skipping this one...<br>";

$db->query("UPDATE hlstats_Options SET value='35' WHERE keyname='dbversion'");

?>
