<?php
/*
HLstatsZ - Real-time player and clan rankings and statistics
Originally HLstatsX Community Edition by Nicholas Hastings (2008–20XX)
Based on ELstatsNEO by Malte Bayer, HLstatsX by Tobias Oetzel, and HLstats by Simon Garner

HLstats > HLstatsX > HLstatsX:CE > HLStatsZ
HLstatsZ continues a long lineage of open-source server stats tools for Half-Life and Source games.
This version is released under the GNU General Public License v2 or later.

For current support and updates:
   https://snipezilla.com
   https://github.com/SnipeZilla
   https://forums.alliedmods.net/forumdisplay.php?f=156
*/
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

global $db;

if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
}


if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
	require_once __DIR__ . '/../vendor/autoload.php';
}

// The lists come from SourceBans and AMXBans (GoldSrc servers), merged; either one is enough (sbOn(), sbAmx()).
// Without both, a notice, not errors.
$sbReady = false;
if (defined('DB_SBNAME') && DB_SBNAME !== '') {
    try {
        $sbReady = $db->select_db(DB_SBNAME);
    } catch (Throwable $e) {
        error_log('SourceBans: ' . $e->getMessage());
    }
}
define('SB_ON', $sbReady);

require_once PAGE_PATH . '/sourcebans/sourcebans_functions.php';
require_once PAGE_PATH . '/sourcebans/sourcebans_admin.php';
require_once PAGE_PATH . '/sourcebans/sourcebans_amx.php';   // AMXBans (GoldSrc), when DB_AMXNAME is set

if (!sbOn() && !sbAmx()) {
    printSectionTitle('SourceBans');
    echo '<p class="sb-empty">' . t('sb.unavailable') . '</p>';
    return;
}

$task = isset($_GET['task']) ? $_GET['task'] : '';

if (!is_ajax()) {
    sbDetailsScript();

    // Admin menu and tools for SourceBans admins signed in with Steam; kick, ban and unban on AMXBans servers for
    // their admins (sbAmxRights)
    $adminPages = sbAdminPages();
    $amxRights  = sbAmxRights();
    if ($adminPages) {
        $nav = '<button class="hlstats-dropbtn" type="button" aria-haspopup="true" aria-expanded="false">Admin <span class="caret">&#9660;</span></button>'
             . '<div class="hlstats-dropmenu">';
        foreach ($adminPages as $page => $title) {
            $nav .= '<a href="?mode=sourcebans&amp;task=' . $page . '"' . ($task === $page ? ' class="hlstats-sublink active"' : ' class="hlstats-sublink"') . '>' . $title . '</a>';
        }
        $nav .= '</div>';
        echo '<script>document.getElementById("sb-dropdown").innerHTML = ' . json_encode($nav, JSON_HEX_TAG) . ';</script>';
    }
    if ($adminPages || $amxRights['servers']) {
        sbAdminScript();
    }
    if ($adminPages) {
        sbBanDialogs();
    }
    if ($amxRights['servers']) {
        sbAmxDialogs();
    }
}

$valid_modes = array(
	'sourcebans_servers',
	'sourcebans_bans',
	'sourcebans_comms',
	'sourcebans_blocked',
	'sourcebans_details',
	'sourcebans_status',
	'sourcebans_search',
	'admin_api',
);
if (!sbOn()) {
	// Comm blocks and the log of blocked joins are SourceBans' alone: AMXBans alone shows the dashboard instead
	$valid_modes = array_diff($valid_modes, array('sourcebans_comms', 'sourcebans_blocked'));
}

if (isset(SB_ADMIN_PAGES[$task])) {
    if (!sbAdmin()) {
        printSectionTitle('SourceBans Admin');
        echo '<p class="sb-empty">Sign in with Steam as a SourceBans admin to use this page.</p>';
    } elseif (!isset(sbAdminPages()[$task])) {
        printSectionTitle('SourceBans Admin');
        echo '<p class="sb-empty">Your admin rights do not include this page.</p>';
    } else {
        include PAGE_PATH . "/sourcebans/$task.php";
    }
} elseif ($task && in_array($task, $valid_modes)) {
    include PAGE_PATH . "/sourcebans/$task.php";
} else {
   include PAGE_PATH.'/sourcebans/sourcebans_home.php';
}

$db->select_db(DB_NAME);

?>