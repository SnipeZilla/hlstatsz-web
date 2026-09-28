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

if ($auth->userdata['acclevel'] < 100) {
    die ('Access denied!');
}

/*
 * Bans settings: the bans pages of HLstatsZ (SourceBans and AMXBans bans, pages/sourcebans.php) or a link to an
 * external SourceBans site, and where players report a player or appeal a ban: a forum thread, a Discord channel...
 * Those two are linked from the bans pages (menu, details of an active ban), the Banned Players page and the kick
 * message of a ban given from HLstatsZ. The ban databases are set in config.php (DB_SBNAME, DB_AMXNAME); when a
 * database or its tables do not exist yet, this page creates them (includes/bansdb.php).
 */

require_once INCLUDE_PATH . '/bansdb.php';

// Options of this page (hlstats_Options, values of 128 characters at most): the URLs are http(s) or relative to this
// site, empty for none; the addresses of reports and appeals are for the web only (opttype 2)
$bansOptions = array('Sourcebans_Site', 'sourcebans_address', 'report_url', 'appeal_url');
$bansUrls    = array('sourcebans_address' => 'SourceBans site', 'report_url' => 'Report a player', 'appeal_url' => 'Appeal a ban');

$token   = $_SESSION['bansset_token'] ??= bin2hex(random_bytes(16));
$tokenOk = is_string($_POST['bans_token'] ?? null) && hash_equals($token, $_POST['bans_token']);
$values  = array();
$db->query("SELECT keyname, value FROM hlstats_Options WHERE keyname IN ('" . implode("', '", $bansOptions) . "')");
foreach ($db->fetch_row_set() ?: array() as $row) {
    $values[$row['keyname']] = html_entity_decode((string) $row['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

echo '<div class="panel">';

if (($_POST['bans_do'] ?? '') === 'save') {
    $posted = array(
        'Sourcebans_Site' => ($_POST['Sourcebans_Site'] ?? '') === '1' ? '1' : '0',
    );
    $errors = array();
    foreach ($bansUrls as $key => $label) {
        $url = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        if ($url !== '' && (strlen($url) > 128 || !preg_match('#^(https?://[^\s"\'<>]+|/[^\s"\'<>]*)$#i', $url))) {
            $errors[] = "$label: enter a full address (https://…) or one on this site (/…), without spaces or quotes, 128 characters at most.";
        }
        $posted[$key] = $url;
    }
    if (!$tokenOk) {
        message('warning', 'This form has expired. Open the page again.');
    } elseif ($errors) {
        message('warning', 'Check these fields:<br>' . implode('<br>', array_map('htmlspecialchars', $errors)));
        $values = $posted;
    } else {
        foreach ($posted as $key => $value) {
            $db->query("SELECT 1 FROM hlstats_Options WHERE keyname = '$key'");
            $db->query($db->num_rows() ? "UPDATE hlstats_Options SET value = '" . $db->escape($value) . "' WHERE keyname = '$key'"
                : "INSERT INTO hlstats_Options (keyname, value, opttype) VALUES ('$key', '" . $db->escape($value) . "', "
                    . (in_array($key, array('report_url', 'appeal_url'), true) ? 2 : 1) . ")");
            $g_options[$key] = $value;
        }
        $values = $posted;
        message('success', 'Bans settings saved.');
    }
}

// The ban databases, seen over a connection of their own; SourceBans needs a first admin when its admins table is new
$h          = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$code       = fn($s) => '<code>' . $h($s) . '</code>';
$needsOwner = fn($s) => $s['kind'] === 'sb' && ($s['state'] !== 'partial' || in_array('admins', $s['missing'], true));
list($link) = sqlConnect();
$dbs        = array();
foreach (array_keys(BANS_DBS) as $kind) {
    $dbs[$kind] = bansDbInspect($kind, $link);
}

// The owner offered: the admin signed in with Steam, else the first Steam admin of config.php
$me = (string) ($_SESSION['ID64'] ?? '');
if ($me === '' && defined('STEAM_ADMIN') && !empty(STEAM_ADMIN)) {
    $me = (string) ((array) STEAM_ADMIN)[0];
}
$ownerIn     = is_string($_POST['bans_owner'] ?? null) ? trim($_POST['bans_owner']) : $me;
$ownerNameIn = is_string($_POST['bans_owner_name'] ?? null) ? trim($_POST['bans_owner_name']) : 'Owner';

if (($_POST['bans_do'] ?? '') === 'create' && is_string($_POST['bans_kind'] ?? null) && isset($dbs[$_POST['bans_kind']])) {
    $state = $dbs[$_POST['bans_kind']];
    $title = BANS_DBS[$state['kind']]['title'];
    $owner = array('authid' => '', 'name' => '');
    $fail  = '';
    if (!$tokenOk) {
        $fail = 'This form has expired. Open the page again.';
    } elseif (!in_array($state['state'], array('nodb', 'empty', 'partial'), true)) {
        $fail = "$title: there is nothing to create any more.";
    } elseif ($needsOwner($state)) {
        $owner = array('authid' => (string) amxSteamId($ownerIn), 'name' => $ownerNameIn);
        if ($owner['authid'] === '') {
            $fail = "$title owner: enter a Steam ID (7656119…, STEAM_0:1:23456 or [U:1:46913]).";
        } elseif ($owner['name'] === '' || mb_strlen($owner['name']) > 64 || strpos($owner['name'], "'") !== false || strcasecmp($owner['name'], 'CONSOLE') === 0) {
            $fail = "$title owner: a name of 1 to 64 characters, without an apostrophe, other than CONSOLE.";
        }
    }
    if ($fail !== '') {
        message('warning', $h($fail));
    } else {
        $done = bansDbCreate($state, $link, $owner, (string) $auth->username);
        if (!$done['ok']) {
            message('warning', $h("$title: " . $done['error']));
        } else {
            $count = count($done['created']);
            $what  = $done['database'] ? 'The database ' . $code($state['name']) . " and its $count tables were created"
                : ($state['state'] === 'partial' ? ($count === 1 ? 'The missing table was' : "The $count missing tables were") . ' created in '
                    . $code($state['name']) . ': ' . $h(implode(', ', $done['created']))
                : "$count tables were created in " . $code($state['name']));
            $who   = $done['owner'] ? ' ' . $h($owner['name']) . ' (' . $h($owner['authid']) . ') is its owner: sign in with Steam to manage it on the bans pages.' : '';
            $next  = $state['kind'] === 'sb'
                ? 'Your servers\' SourceBans plugin must use this database with the prefix ' . $code($state['prefix'])
                    . ': DatabasePrefix in addons/sourcemod/configs/sourcebans/sourcebans.cfg and the "sourcebans" entry of databases.cfg (SourceBans++), or Database and Prefix in the SourceBans settings of HLstatsZ-Classics (CS2). Add the servers on the bans pages: Admin &rsaquo; Servers.'
                : 'Your GoldSrc servers\' AMXBans plugin must use this database: amx_sql_host, amx_sql_user, amx_sql_pass and amx_sql_db in addons/amxmodx/configs/sql.cfg, and amx_sql_prefix ' . $code($state['prefix'])
                    . ' in amxbans.cfg. It adds each server when it starts; admins and RCON passwords are set in Bans &rsaquo; AMXBans.';
            // One element: the message box lays out its children in a row
            message('success', "<div>$title: $what.$who<br>$next The MySQL user of the plugin must be allowed to connect from the game servers.</div>");
            $dbs[$state['kind']] = bansDbInspect($state['kind'], $link);
        }
    }
}
if ($link) {
    mysqli_close($link);
}

/**
 * The status of a ban database, and its form when something can be created.
 */
$status = function (array $s) use ($h, $code, $needsOwner, $token, $ownerIn, $ownerNameIn, $g_options) {
    $def   = BANS_DBS[$s['kind']];
    $title = $def['title'];
    $n     = count(bansDbTables($s['kind']));
    switch ($s['state']) {
        case 'unset':
            return '<span class="hlstats-amx-muted">not set (' . $def['name'] . ' in config.php)</span>';
        case 'invalid':
            return '&#9888;&#65039; ' . $def['name'] . ' or ' . $def['prefix'] . ' in config.php: <span class="hlstats-amx-muted">letters, digits and _ only (and - in a database name).</span>';
        case 'connect':
            return '&#9888;&#65039; <span class="hlstats-amx-muted">the database server does not answer.</span>';
        case 'noaccess':
            return '&#9888;&#65039; ' . $code($s['name']) . ' <span class="hlstats-amx-muted">DB_USER of config.php has no rights on this database: give it all rights on it (in your hosting panel).</span>';
        case 'elsewhere':
            return '&#9888;&#65039; ' . $code($s['name']) . " has $title tables with the prefix " . implode(' and ', array_map($code, $s['others'])) . ', not ' . $code($s['prefix'])
                . ': set ' . $def['prefix'] . " to '" . $h($s['others'][0]) . "' in config.php.";
        case 'clash':
            return '&#9888;&#65039; ' . $code($s['name']) . ' has tables with the prefix ' . $code($s['prefix']) . " that are not $title's ("
                . implode(', ', array_map(fn($t) => $code($s['prefix'] . '_' . $t), $s['present'])) . '): set another ' . $def['prefix'] . ' in config.php.';
        case 'ok':
            return '&#9989; ' . $code($s['name']) . ' &middot; prefix ' . $code($s['prefix']) . " &middot; $n tables"
                . ($s['kind'] === 'amx' ? ' &middot; <a href="?mode=admin&amp;task=tools_amxadmins">its admins and servers</a>' : '');
    }
    // nodb, empty, partial: what is missing, and the form that creates it
    if ($s['state'] === 'nodb') {
        $text     = '&#9888;&#65039; The database ' . $code($s['name']) . ' does not exist.';
        $button   = 'Create the database and its tables';
        $question = "Create the database {$s['name']} and its $n $title tables?";
    } elseif ($s['state'] === 'empty') {
        $text     = '&#9888;&#65039; ' . $code($s['name']) . " has no $title tables with the prefix " . $code($s['prefix']) . '.';
        $button   = "Create the $n tables";
        $question = "Create the $n $title tables in {$s['name']}?";
    } else {
        $missing  = count($s['missing']);
        $text     = '&#9888;&#65039; ' . $code($s['name']) . ' &middot; prefix ' . $code($s['prefix']) . ": $missing of $n tables missing ("
            . $h(implode(', ', $s['missing'])) . ').';
        $button   = $missing === 1 ? 'Create the missing table' : 'Create the missing tables';
        $question = ($missing === 1 ? "Create the missing $title table" : "Create the $missing missing $title tables") . " in {$s['name']}? The tables there are left as they are.";
    }
    $form = '<form method="post" class="hlstats-bansdb-create" action="' . $h($g_options['scripturl'] . '?mode=admin&task=bans_settings')
        . '" onsubmit="return confirm(' . $h(json_encode($question)) . ')">'
        . '<input type="hidden" name="bans_do" value="create"><input type="hidden" name="bans_token" value="' . $h($token) . '">'
        . '<input type="hidden" name="bans_kind" value="' . $h($s['kind']) . '">';
    if ($needsOwner($s)) {
        $form .= '<label>Owner <input type="text" name="bans_owner" value="' . $h($ownerIn) . '" placeholder="Steam ID" size="20" spellcheck="false"></label>'
            . '<label>named <input type="text" name="bans_owner_name" value="' . $h($ownerNameIn) . '" maxlength="64" size="12"></label>';
    }
    $form .= '<button type="submit" class="hlstats-btn is-primary is-small">' . $button . '</button></form>'
        . '<div class="hlstats-amx-muted">Tables named ' . $code($s['prefix'] . '_bans') . ', ' . $code($s['prefix'] . '_' . ($s['kind'] === 'sb' ? 'admins' : 'amxadmins'))
        . '&hellip;: the prefix of config.php and _, as the plugins name them.'
        . ($needsOwner($s) ? ' The owner, you with your Steam ID, manages SourceBans on the bans pages.' : '') . '</div>';
    return $text . $form;
};
$val = fn($key) => htmlspecialchars((string) ($values[$key] ?? ''));
?>
<div class="hlstats-admin-note">
    The bans pages show the bans of SourceBans and of AMXBans (GoldSrc servers), read from their databases.
    Reports and appeals go where your players already are: a forum thread, a Discord channel&hellip;
</div>
<div class="hlstats-admin-propgroup">
<b>Ban databases</b>
<div class="responsive-table">
<table class="responsive-task hlstats-bansdb">
<tbody>
<tr>
    <td class="left">SourceBans:</td>
    <td class="left"><?= $status($dbs['sb']) ?></td>
</tr>
<tr>
    <td class="left">AMXBans:</td>
    <td class="left"><?= $status($dbs['amx']) ?></td>
</tr>
</tbody>
</table>
</div>
<p class="hlstats-amx-muted">Set in config.php: DB_SBNAME and DB_SBPREFIX, DB_AMXNAME and DB_AMXPREFIX, on the MySQL server of
    HLstatsZ (HLstatsZ's own database will do). A database or tables that don't exist yet can be created here; what exists is
    never changed.</p>
</div>
<form method="post" action="<?= htmlspecialchars($g_options['scripturl'] . '?mode=admin&task=bans_settings') ?>">
<input type="hidden" name="bans_do" value="save">
<input type="hidden" name="bans_token" value="<?= $token ?>">
<div class="hlstats-admin-propgroup">
<b>Bans pages</b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">SOURCEBANS in the menu leads to:</td>
    <td class="left"><select name="Sourcebans_Site">
        <option value="1"<?= ($values['Sourcebans_Site'] ?? '0') === '1' ? ' selected' : '' ?>>The bans pages of HLstatsZ (SourceBans and AMXBans)</option>
        <option value="0"<?= ($values['Sourcebans_Site'] ?? '0') !== '1' ? ' selected' : '' ?>>An external SourceBans site (below), or nothing</option>
    </select></td>
</tr>
<tr>
    <td class="left">External SourceBans site:</td>
    <td class="left"><input type="text" name="sourcebans_address" value="<?= $val('sourcebans_address') ?>" maxlength="128" placeholder="https://www.yoursite.com/sourcebans/ or /sourcebans/" spellcheck="false"></td>
</tr>
</tbody>
</table>
</div>
</div>
<div class="hlstats-admin-propgroup">
<b>Reports and appeals</b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">Report a player:</td>
    <td class="left"><input type="text" name="report_url" value="<?= $val('report_url') ?>" maxlength="128" placeholder="https://discord.gg/… or a forum thread" spellcheck="false"></td>
</tr>
<tr>
    <td class="left">Appeal a ban:</td>
    <td class="left"><input type="text" name="appeal_url" value="<?= $val('appeal_url') ?>" maxlength="128" placeholder="https://discord.gg/… or a forum thread" spellcheck="false"></td>
</tr>
</tbody>
</table>
</div>
<p class="hlstats-amx-muted">Left empty, a link is not shown. They show in the "Your status" card of the bans dashboard, which
    tells a player signed in with Steam whether a ban or comm block is on them; the appeal also under an active ban or
    block, on the Banned Players page, and in the kick message of a ban given from HLstatsZ when it fits.</p>
</div>
<div class="hlstats-admin-apply">
    <input type="submit" value="Apply">
</div>
</form>
</div>
