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

/*
 * AMXBans admins: the admins of the GoldSrc servers, kept in the AMXBans database (DB_AMXNAME on this MySQL server,
 * tables named DB_AMXPREFIX_*), listed, added, changed and removed here as the AMXBans website does it; and the RCON
 * password of each AMXBans server (the plugin registers a server without one), used here to reload the admins and on
 * the bans pages for the map and players, and on a server's page to list its players and kick or ban them (with the
 * plugin's own commands, as the AMXBans website does).
 * The AMXBans plugin loads a row of _amxadmins on the servers of its _admins_servers rows while days = 0 (no end) or
 * expired = created + days * 86400 is to come. steamid holds the Steam ID, IP address, clan tag or name the account
 * flags tell (c, d, b or none), with "e" when no password is checked, "a" to kick a wrong one and "k" for a
 * case-sensitive name or tag; the password is kept in plain text, as AMX Mod X compares it. A server's own
 * custom_flags, when set, replace the admin's access flags there.
 */
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

if ($auth->userdata['acclevel'] < 100) {
    die ('Access denied!');
}

require_once INCLUDE_PATH . '/amxbans.php';   // tables, RCON, players, bans (shared with the bans pages)

// AMX Mod X access flags
const AMXADM_FLAGS = array(
    'a' => 'Immunity', 'b' => 'Reserved slot', 'c' => 'Kick', 'd' => 'Ban and unban', 'e' => 'Slay and slap', 'f' => 'Change map',
    'g' => 'Change cvars', 'h' => 'Run configs', 'i' => 'Admin chat', 'j' => 'Votes', 'k' => 'Server password', 'l' => 'RCON',
    'm' => 'Custom level A', 'n' => 'Custom level B', 'o' => 'Custom level C', 'p' => 'Custom level D', 'q' => 'Custom level E',
    'r' => 'Custom level F', 's' => 'Custom level G', 't' => 'Custom level H', 'u' => 'Menus', 'z' => 'No admin rights',
);
const AMXADM_TYPES = array('steam' => 'Steam ID', 'ip' => 'IP address', 'name' => 'Name', 'tag' => 'Clan tag');
// What can be done to a player on a server: a kick, or a ban of so many minutes (0: permanent)
const AMXADM_ACTIONS = array('kick' => 'Kick', '30' => 'Ban for 30 minutes', '60' => 'Ban for 1 hour', '1440' => 'Ban for 1 day',
    '10080' => 'Ban for 1 week', '43200' => 'Ban for 30 days', '0' => 'Ban permanently');

/**
 * Whether AMXBans is set up and its admin tables can be read.
 */
function amxadmReady()
{
    global $db;

    return defined('DB_AMXNAME') && DB_AMXNAME !== ''
        && $db->query("SELECT 1 FROM " . amxTable('amxadmins') . ", " . amxTable('admins_servers') . ", " . amxTable('serverinfo') . " LIMIT 0", false) !== false;
}

/**
 * The AMXBans servers by id, with a 'label': the HLstatsZ server at the same address, else the hostname AMXBans keeps.
 * Read again with $refresh.
 */
function amxadmServers($refresh = false)
{
    global $db;
    static $servers = null;

    if ($servers !== null && !$refresh) {
        return $servers;
    }
    $names = array();
    $db->query("SELECT address, port, publicaddress, name FROM hlstats_Servers");
    foreach ($db->fetch_row_set() ?: array() as $row) {
        $names[$row['address'] . ':' . $row['port']] = $row['name'];
        if (trim((string) $row['publicaddress']) !== '') {
            $names[trim($row['publicaddress'])] = $row['name'];
        }
    }
    $servers = array();
    $db->query("SELECT id, timestamp, hostname, address, gametype, rcon FROM " . amxTable('serverinfo') . " ORDER BY id");
    foreach ($db->fetch_row_set() ?: array() as $row) {
        $name = (string) ($names[$row['address']] ?? '');
        $row['label'] = $name !== '' ? html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : (trim((string) $row['hostname']) !== '' ? trim($row['hostname']) : (string) $row['address']);
        $servers[(int) $row['id']] = $row;
    }
    return $servers;
}

/**
 * An admin, with its servers ('servers': server id => custom_flags, use_static_bantime), or null.
 */
function amxadmAdmin($id)
{
    global $db;

    $db->query("SELECT id, username, password, access, flags, steamid, nickname, ashow, created, expired, days FROM " . amxTable('amxadmins') . " WHERE id = " . (int) $id);
    $admin = $db->fetch_array();
    if (!$admin) {
        return null;
    }
    $admin['servers'] = array();
    $db->query("SELECT server_id, custom_flags, use_static_bantime FROM " . amxTable('admins_servers') . " WHERE admin_id = " . (int) $id);
    foreach ($db->fetch_row_set() ?: array() as $row) {
        $admin['servers'][(int) $row['server_id']] = $row;
    }
    return $admin;
}

/**
 * How the servers recognise an admin, from the account flags: 'steam' (c), 'ip' (d), 'tag' (b) or 'name'.
 */
function amxadmType($flags)
{
    $flags = (string) $flags;
    return strpos($flags, 'c') !== false ? 'steam' : (strpos($flags, 'd') !== false ? 'ip' : (strpos($flags, 'b') !== false ? 'tag' : 'name'));
}

/**
 * The fields of the form for an admin of the database, or for a new one.
 */
function amxadmValues($admin)
{
    if (!$admin) {
        return array('type' => 'steam', 'pass' => 'no', 'access' => '', 'servers' => array(), 'ends_mode' => 'never', 'reload' => '1');
    }
    $flags = (string) $admin['flags'];
    $type  = amxadmType($flags);
    $slot  = array('steam' => 'steam', 'ip' => 'ip', 'name' => 'player', 'tag' => 'tag')[$type];
    $days  = (int) $admin['days'];
    return array(
        'type'      => $type,
        $slot       => (string) $admin['steamid'],
        'pass'      => strpos($flags, 'e') === false ? 'yes' : 'no',
        'username'  => (string) $admin['username'],
        'nickname'  => (string) $admin['nickname'],
        'access'    => (string) $admin['access'],
        'servers'   => array_keys($admin['servers']),
        'ends_mode' => $days > 0 ? 'date' : 'never',
        'ends'      => $days > 0 ? date('Y-m-d', (int) $admin['expired']) : '',
        'kick'      => strpos($flags, 'a') !== false ? '1' : '',
        'casesens'  => strpos($flags, 'k') !== false ? '1' : '',
        'ashow'     => (int) $admin['ashow'] ? '1' : '',
        'reload'    => '1',
    );
}

/**
 * The fields sent, to show them again after an error.
 */
function amxadmPosted(array $post)
{
    $values = array();
    foreach (array('type', 'steam', 'ip', 'player', 'tag', 'pass', 'username', 'nickname', 'ends_mode', 'ends', 'kick', 'casesens', 'ashow', 'reload') as $key) {
        $values[$key] = is_string($post[$key] ?? null) ? trim($post[$key]) : '';
    }
    $values['access']  = implode('', array_intersect(array_keys(AMXADM_FLAGS), array_filter((array) ($post['access'] ?? array()), 'is_string')));
    $values['servers'] = array_map('intval', array_filter((array) ($post['servers'] ?? array()), 'is_scalar'));
    return $values;
}

/**
 * The values of an admin checked as AMXBans checks them: array(row to write, errors); $target is the admin edited.
 */
function amxadmCheck(array $post, $target)
{
    global $db;

    $in     = amxadmPosted($post);
    $now    = time();
    $id     = $target ? (int) $target['id'] : 0;
    $errors = array();

    // Who: a Steam ID, an IP address, a name or a clan tag
    $type = isset(AMXADM_TYPES[$in['type']]) ? $in['type'] : 'steam';
    $auth = '';
    if ($type === 'steam') {
        $auth = (string) amxSteamId($in['steam']);
        if ($auth === '') {
            $errors[] = 'Steam ID: enter it as STEAM_0:1:123, [U:1:246], 7656… or a steamcommunity.com/profiles/ link.';
        }
    } elseif ($type === 'ip') {
        $auth = $in['ip'];
        if (!filter_var($auth, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $errors[] = 'IP address: enter one such as 203.0.113.7.';
        }
    } else {
        $auth  = $type === 'tag' ? $in['tag'] : $in['player'];
        $label = $type === 'tag' ? 'Clan tag' : 'In-game name';
        if ($auth === '') {
            $errors[] = "$label: enter it.";
        } elseif (mb_strlen($auth) > 31) {
            $errors[] = "$label: use 31 characters at most.";
        } elseif (strpos($auth, '"') !== false) {
            $errors[] = "$label: leave out the double quote (\").";
        }
    }
    if ($auth !== '' && !$errors) {
        $db->query("SELECT username, nickname FROM " . amxTable('amxadmins') . " WHERE steamid = '" . $db->escape($auth) . "' AND id <> $id LIMIT 1");
        if ($other = $db->fetch_array()) {
            $errors[] = AMXADM_TYPES[$type] . ': admin ' . (trim((string) $other['nickname']) !== '' ? $other['nickname'] : $other['username']) . ' already has it.';
        }
    }

    // Names: the one of the ban lists, the one shown in game
    $username = $in['username'];
    if ($username === '') {
        $errors[] = 'Name: enter a name for this admin.';
    } elseif (mb_strlen($username) > 31) {
        $errors[] = 'Name: use 31 characters at most.';
    }
    if (mb_strlen($in['nickname']) > 31) {
        $errors[] = 'Nickname: use 31 characters at most.';
    }
    $nickname = $in['nickname'] !== '' ? $in['nickname'] : $username;

    // Password: plain text; left empty on an admin that has one, it stays
    $pass     = $in['pass'] === 'yes';
    $password = is_string($post['password'] ?? null) ? trim($post['password']) : '';
    if ($type === 'name' && !$pass) {
        $errors[] = 'Password: anyone can take a name, so an admin recognised by name needs one.';
    } elseif ($pass) {
        if ($password === '' && $target && (string) $target['password'] !== '') {
            $password = (string) $target['password'];
        } elseif (strlen($password) < 4 || strlen($password) > 31) {
            $errors[] = 'Password: use 4 to 31 characters.';
        } elseif (strpos($password, '"') !== false) {
            $errors[] = 'Password: leave out the double quote (").';
        }
    } else {
        $password = '';
    }

    // Access flags; account flags: how the admin is recognised, "e" without a password, "a" to kick a wrong
    // password (always for a name, as AMXBans wants), "k" for a case-sensitive name or tag
    if ($in['access'] === '') {
        $errors[] = 'Access: choose at least one flag (z for no admin rights).';
    }
    $flags = str_split(array('steam' => 'c', 'ip' => 'd', 'tag' => 'b', 'name' => '')[$type] . ($pass ? '' : 'e')
        . ($pass && ($type === 'name' || $in['kick'] === '1') ? 'a' : '') . (($type === 'name' || $type === 'tag') && $in['casesens'] === '1' ? 'k' : '')) ?: array();
    sort($flags);

    // End: days counted from the creation, as AMXBans counts them; the last day given is covered whole
    $created = $target && (int) $target['created'] > 0 ? (int) $target['created'] : $now;
    $days    = 0;
    $expired = 0;
    if ($in['ends_mode'] === 'date') {
        $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['ends']) ? strtotime($in['ends'] . ' 23:59:59') : false;
        if ($end === false) {
            $errors[] = 'Last day: pick it.';
        } elseif ($target && (int) $target['days'] > 0 && $in['ends'] === date('Y-m-d', (int) $target['expired'])) {
            $days    = (int) $target['days'];   // the same day: kept as it is
            $expired = (int) $target['expired'];
        } elseif ($end < $now) {
            $errors[] = 'Last day: pick one from today on.';
        } else {
            $days    = max(1, (int) ceil(($end - $created) / 86400));
            $expired = $created + $days * 86400;
        }
    }

    return array(array(
        'username' => $username,
        'password' => $password,
        'access'   => $in['access'],
        'flags'    => implode('', $flags),
        'steamid'  => $auth,
        'nickname' => $nickname,
        'ashow'    => $in['ashow'] === '1' ? 1 : 0,
        'created'  => $created,
        'expired'  => $expired,
        'days'     => $days,
        'servers'  => array_values(array_intersect($in['servers'], array_keys(amxadmServers()))),
    ), $errors);
}

/**
 * Writes an admin (a new one without $target) and its servers; returns its id. Links to the servers kept stay as
 * they are (their own flags and static ban time), new ones are added as the AMXBans website adds them.
 */
function amxadmSave(array $row, $target)
{
    global $db;

    $e      = fn($value) => "'" . $db->escape((string) $value) . "'";
    $fields = 'username = ' . $e($row['username']) . ', password = ' . $e($row['password']) . ', access = ' . $e($row['access'])
        . ', flags = ' . $e($row['flags']) . ', steamid = ' . $e($row['steamid']) . ', nickname = ' . $e($row['nickname'])
        . ', ashow = ' . (int) $row['ashow'] . ', created = ' . (int) $row['created'] . ', expired = ' . (int) $row['expired'] . ', days = ' . (int) $row['days'];
    $before = $target ? array_keys($target['servers']) : array();
    if ($target) {
        $id = (int) $target['id'];
        $db->query("UPDATE " . amxTable('amxadmins') . " SET $fields WHERE id = $id");
        $db->query("DELETE FROM " . amxTable('admins_servers') . " WHERE admin_id = $id" . ($row['servers'] ? ' AND server_id NOT IN (' . implode(',', array_map('intval', $row['servers'])) . ')' : ''));
    } else {
        $db->query("INSERT INTO " . amxTable('amxadmins') . " SET $fields");
        $id = (int) $db->insert_id();
    }
    foreach (array_diff($row['servers'], $before) as $server) {
        $db->query("INSERT INTO " . amxTable('admins_servers') . " (admin_id, server_id, custom_flags, use_static_bantime) VALUES ($id, " . (int) $server . ", '', 'yes')");
    }
    return $id;
}

/**
 * A line in the AMXBans log, with the name of the HLstatsZ admin (amxLog).
 */
function amxadmLog($remarks, $action = 'AMXXAdmin config')
{
    global $auth;

    amxLog($action, $remarks, $auth->username);
}

/**
 * Reloads the admins of these servers (amx_reloadadmins over GoldSrc RCON); returns a line per server.
 * A server without an RCON password in AMXBans loads them at its next map.
 */
function amxadmReload(array $ids)
{
    $servers = amxadmServers();
    $lines   = array();
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $server = $servers[$id] ?? null;
        if (!$server) {
            continue;
        }
        $label = htmlspecialchars($server['label']);
        if ((string) $server['rcon'] === '') {
            $lines[] = "$label: no RCON password in AMXBans, the change comes at the next map.";
            continue;
        }
        list($ok, $said) = amxRcon($server, 'amx_reloadadmins');
        $lines[] = $ok ? "$label: admins reloaded." : "$label: not reloaded (" . htmlspecialchars($said) . '), the change comes at the next map.';
    }
    return $lines;
}

/**
 * Who a ban from this page is recorded with: the AMXBans admin of the signed-in Steam account, else the HLstatsZ admin.
 */
function amxadmWho()
{
    global $auth;

    $admin = amxWebAdmin($_SESSION['ID64'] ?? '');
    if ($admin['name'] === 'HLstatsZ admin' && (string) $auth->username !== '' && $auth->username !== 'SteamAdmin') {
        $admin['name'] = (string) $auth->username;
    }
    return $admin;
}

/**
 * The fields of the ban of a player who is not on the server, as sent (reason made fit for a console command).
 */
function amxadmOfflinePosted(array $post)
{
    $get = fn($key) => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
    return array(
        'type'   => $get('type') === 'ip' ? 'ip' : 'steam',
        'steam'  => $get('steam'),
        'ip'     => $get('ip'),
        'name'   => mb_substr($get('name'), 0, 100),
        'length' => $get('length'),
        'reason' => amxClean($get('reason'), 1000),
    );
}

/**
 * Asks a server for its "status" over GoldSrc RCON with the password AMXBans keeps: array(answered, what it said
 * (host name, map, players) or why it did not answer), as plain text.
 */
function amxadmTest(array $server)
{
    list($ok, $output) = amxRcon($server, 'status');
    if (!$ok) {
        return array(false, $output);
    }
    $info = array();
    if (preg_match('/^\s*hostname\s*:\s*(.+?)\s*$/m', $output, $mm)) {
        $info[] = $mm[1];
    }
    if (preg_match('/^\s*map\s*:\s*(\S+)/m', $output, $mm)) {
        $info[] = $mm[1];
    }
    if (preg_match('/^\s*players\s*:\s*(\d+)\s+(?:humans?|active)\b[^(]*\((\d+)/m', $output, $mm)) {
        $info[] = $mm[1] . '/' . $mm[2] . ' players';
    }
    return array(true, $info ? implode(' · ', $info) : 'it answered.');
}

/**
 * The address of this page, of an admin's page with an id or of a server's page; HTML-escaped.
 */
function amxadmUrl($id = 0, $server = 0)
{
    global $g_options;

    return htmlspecialchars($g_options['scripturl'] . '?mode=admin&task=tools_amxadmins' . ($id ? '&id=' . (int) $id : '') . ($server ? '&server=' . (int) $server : ''));
}

/**
 * The form of an admin ($id 0: a new one). The rows of the choices not made are hidden by admin.css.
 */
function amxadmForm(array $v, $id, $token)
{
    $servers = amxadmServers();
    $sel     = fn($value, $option) => (string) ($value ?? '') === $option ? ' selected' : '';
    $chk     = fn($on) => $on ? ' checked' : '';
    $val     = fn($key) => htmlspecialchars((string) ($v[$key] ?? ''));
?>
<form method="post" action="<?= amxadmUrl($id) ?>" class="hlstats-amx-form">
<input type="hidden" name="amx_do" value="save">
<input type="hidden" name="amx_token" value="<?= $token ?>">
<input type="hidden" name="amx_id" value="<?= (int) $id ?>">
<div class="hlstats-admin-propgroup">
<b><?= $id ? 'Admin' : 'Add an admin' ?></b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">Recognised by:</td>
    <td class="left"><select name="type">
<?php foreach (AMXADM_TYPES as $type => $label) { ?>
        <option value="<?= $type ?>"<?= $sel($v['type'] ?? 'steam', $type) ?>><?= $label ?></option>
<?php } ?>
    </select></td>
</tr>
<tr class="amx-when-steam">
    <td class="left">Steam ID:</td>
    <td class="left"><input type="text" name="steam" value="<?= $val('steam') ?>" placeholder="STEAM_0:1:123, [U:1:246], 7656… or a /profiles/ link" autocomplete="off" spellcheck="false"></td>
</tr>
<tr class="amx-when-ip">
    <td class="left">IP address:</td>
    <td class="left"><input type="text" name="ip" value="<?= $val('ip') ?>" maxlength="15" placeholder="203.0.113.7" autocomplete="off" spellcheck="false"></td>
</tr>
<tr class="amx-when-name">
    <td class="left">In-game name:</td>
    <td class="left"><input type="text" name="player" value="<?= $val('player') ?>" maxlength="31" autocomplete="off" spellcheck="false"></td>
</tr>
<tr class="amx-when-tag">
    <td class="left">Clan tag:</td>
    <td class="left"><input type="text" name="tag" value="<?= $val('tag') ?>" maxlength="31" placeholder="In the player's name" autocomplete="off" spellcheck="false"></td>
</tr>
<tr>
    <td class="left">Password:</td>
    <td class="left"><select name="pass">
        <option value="no"<?= $sel($v['pass'] ?? 'no', 'no') ?>>Not asked</option>
        <option value="yes"<?= $sel($v['pass'] ?? 'no', 'yes') ?>>Asked (the player sets it with setinfo _pw)</option>
    </select></td>
</tr>
<tr class="amx-when-pass">
    <td class="left">The password:</td>
    <td class="left"><input type="text" name="password" value="" maxlength="31" autocomplete="off" spellcheck="false" placeholder="<?= $id ? 'Leave empty to keep the current one' : '4 to 31 characters' ?>"></td>
</tr>
<tr>
    <td class="left">Name:</td>
    <td class="left"><input type="text" name="username" value="<?= $val('username') ?>" maxlength="31" placeholder="On the ban lists"></td>
</tr>
<tr>
    <td class="left">Nickname:</td>
    <td class="left"><input type="text" name="nickname" value="<?= $val('nickname') ?>" maxlength="31" placeholder="Shown in game; the name when empty"></td>
</tr>
<tr>
    <td class="left">Ends:</td>
    <td class="left"><select name="ends_mode">
        <option value="never"<?= $sel($v['ends_mode'] ?? 'never', 'never') ?>>Never</option>
        <option value="date"<?= $sel($v['ends_mode'] ?? 'never', 'date') ?>>On a day</option>
    </select></td>
</tr>
<tr class="amx-when-date">
    <td class="left">Last day:</td>
    <td class="left"><input type="date" name="ends" value="<?= $val('ends') ?>" min="<?= date('Y-m-d') ?>"></td>
</tr>
</tbody>
</table>
</div>
</div>
<div class="hlstats-admin-propgroup">
<b>Access <small>AMX Mod X flags</small></b>
<div class="hlstats-amx-checks">
<?php foreach (AMXADM_FLAGS as $letter => $meaning) { ?>
    <label><input type="checkbox" name="access[]" value="<?= $letter ?>"<?= $chk(strpos((string) ($v['access'] ?? ''), $letter) !== false) ?>><code><?= $letter ?></code> <?= htmlspecialchars($meaning) ?></label>
<?php } ?>
</div>
</div>
<div class="hlstats-admin-propgroup">
<b>Admin on these servers</b>
<?php if ($servers) { ?>
<div class="hlstats-amx-checks is-wide">
<?php foreach ($servers as $sid => $server) { ?>
    <label><input type="checkbox" name="servers[]" value="<?= (int) $sid ?>"<?= $chk(in_array((int) $sid, array_map('intval', $v['servers'] ?? array()), true)) ?>><span><?= htmlspecialchars($server['label']) ?> <small><?= htmlspecialchars((string) $server['address']) ?></small></span></label>
<?php } ?>
</div>
<?php } else { ?>
<p class="hlstats-amx-muted">No GoldSrc server is in AMXBans yet: a server shows up once the AMXBans plugin runs on it.</p>
<?php } ?>
</div>
<div class="hlstats-admin-propgroup">
<b>Options</b>
<div class="hlstats-amx-checks is-wide">
    <label class="amx-when-pass"><input type="checkbox" name="kick" value="1"<?= $chk(($v['kick'] ?? '') === '1') ?>>Kick a player who gives a wrong password (always for a name)</label>
    <label class="amx-when-text"><input type="checkbox" name="casesens" value="1"<?= $chk(($v['casesens'] ?? '') === '1') ?>>The name or clan tag is case-sensitive</label>
    <label><input type="checkbox" name="ashow" value="1"<?= $chk(($v['ashow'] ?? '') === '1') ?>>Listed on the AMXBans website</label>
    <label><input type="checkbox" name="reload" value="1"<?= $chk(($v['reload'] ?? '') === '1') ?>>Reload the admins on their servers now (RCON)</label>
</div>
</div>
<div class="hlstats-admin-apply">
    <input type="submit" value="<?= $id ? 'Save changes' : 'Add admin' ?>">
</div>
</form>
<?php
}

echo '<div class="panel">';
if (!amxadmReady()) {
    message('warning', 'AMXBans is not set up: in config.php, set DB_AMXNAME to the AMXBans database (on the MySQL server of HLstatsZ) and DB_AMXPREFIX to its table prefix.');
    echo '</div>';
    return;
}

$token  = $_SESSION['amxadm_token'] ??= bin2hex(random_bytes(16));
$id     = (int) ($_GET['id'] ?? 0);
$sid    = (int) ($_GET['server'] ?? 0);
$values  = null;   // the fields sent, shown again after an error
$offline = null;   // the same for the ban of a player who is not on the server

/*
 * Changes
 */
$do = is_string($_POST['amx_do'] ?? null) ? $_POST['amx_do'] : '';
if ($do !== '' && (!is_string($_POST['amx_token'] ?? null) || !hash_equals($token, $_POST['amx_token']))) {
    message('warning', 'This form has expired. Open the page again.');
} elseif ($do === 'server') {
    // A server's RCON password: set, kept (left empty) or removed; then tried with "status"
    $sid    = (int) ($_POST['amx_server'] ?? 0);
    $server = amxadmServers()[$sid] ?? null;
    $new    = is_string($_POST['rcon'] ?? null) ? trim($_POST['rcon']) : '';
    if (!$server) {
        message('warning', 'This server is no longer in AMXBans.');
        $sid = 0;
    } elseif (strlen($new) > 32 || strpos($new, '"') !== false) {
        message('warning', 'RCON password: use 32 characters at most, without a double quote (").');
    } else {
        $label = htmlspecialchars($server['label']);
        if (($_POST['rcon_clear'] ?? '') === '1') {
            $db->query("UPDATE " . amxTable('serverinfo') . " SET rcon = '' WHERE id = $sid");
            message('success', "$label: its RCON password is removed.");
        } else {
            if ($new !== '') {
                $db->query("UPDATE " . amxTable('serverinfo') . " SET rcon = '" . $db->escape($new) . "' WHERE id = $sid");
                $server['rcon'] = $new;
            }
            list($ok, $said) = amxadmTest($server);
            message($ok ? 'success' : 'warning', $label . ': ' . ($new !== '' ? 'RCON password saved. ' : '')
                . ($ok ? 'The server answered: ' : 'The test failed: ') . htmlspecialchars($said));
        }
        amxadmServers(true);
    }
} elseif ($do === 'player') {
    // A kick or a ban of a player on the server: the ban is written as the AMXBans website writes it (by Steam ID, or
    // by IP address for a player without Steam), then the player is kicked over RCON; the plugin keeps them out after
    $sid    = (int) ($_POST['amx_server'] ?? 0);
    $server = amxadmServers()[$sid] ?? null;
    $uid    = (int) ($_POST['amx_userid'] ?? 0);
    $act    = is_string($_POST['amx_act'] ?? null) ? $_POST['amx_act'] : '';
    $raw    = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
    $reason = amxClean($raw, 1000);   // quoted in a console command: no quote, no command separator, no control character
    if (!$server) {
        message('warning', 'This server is no longer in AMXBans.');
        $sid = 0;
    } elseif ($uid <= 0) {
        message('warning', 'Pick a player.');
    } elseif (!isset(AMXADM_ACTIONS[$act])) {
        message('warning', 'Pick a kick or a ban.');
    } elseif ($act !== 'kick' && $reason === '') {
        message('warning', 'Reason: a ban needs one.');
    } elseif (mb_strlen($reason) > 100) {
        message('warning', 'Reason: use 100 characters at most.');
    } else {
        list($ok, $players) = amxPlayers($server);
        $player = $ok ? (array_values(array_filter($players, fn($p) => $p['userid'] === $uid))[0] ?? null) : null;
        $name   = $player ? htmlspecialchars($player['name']) : '';
        if (!$ok) {
            message('warning', 'The server did not answer: ' . htmlspecialchars($players));
        } elseif (!$player) {
            message('warning', 'This player is no longer on the server.');
        } elseif ($player['kind'] !== 0) {
            message('warning', "$name is " . ($player['kind'] === 1 ? 'a bot.' : 'HLTV.'));
        } elseif ($player['immune']) {
            message('warning', "$name has immunity.");
        } else {
            $kick = $act === 'kick';
            $type = $kick ? '' : amxBanType($player['authid'], $player['ip']);
            $who  = 'nick: ' . $player['name'] . ' <' . $player['authid'] . '><' . $player['ip'] . '>';
            if (!$kick && $type === '') {
                message('warning', "$name: neither a Steam ID nor an IP address to ban.");
            } elseif (!$kick && ($bid = amxActiveBan($type, $player['authid'], $player['ip']))) {
                message('warning', "$name is already banned (ban #$bid).");
            } else {
                if (!$kick) {
                    amxAddBan(array('type' => $type, 'authid' => $player['authid'], 'ip' => $player['ip'], 'name' => $player['name'],
                        'minutes' => (int) $act, 'reason' => $reason), $server, amxadmWho());
                    amxadmLog("$who banned for " . (int) $act . ' minutes', 'Add ban online');
                }
                list($ok, $reply) = amxKick($server, $uid, $kick ? $reason : amxBanMessage((int) $act, $reason));
                if ($kick) {
                    if ($ok) {
                        amxadmLog("$who kicked", 'Kick online');
                    }
                    message($ok ? 'success' : 'warning', $ok ? "$name is kicked." : "$name: not kicked. " . htmlspecialchars($reply));
                } else {
                    message('success', "$name is banned" . substr(AMXADM_ACTIONS[$act], 3) . '.'
                        . ($ok ? '' : ' The kick failed (' . htmlspecialchars($reply) . '): the plugin keeps them out when they join again.'));
                }
            }
        }
    }
} elseif ($do === 'banoffline') {
    // A ban of a player who is not on the server, by Steam ID or IP address, given on this server; if they are on it
    // after all, they are kicked
    $sid     = (int) ($_POST['amx_server'] ?? 0);
    $server  = amxadmServers()[$sid] ?? null;
    $offline  = amxadmOfflinePosted($_POST);
    $errors  = array();
    $steamid = $offline['type'] === 'steam' ? amxSteamId($offline['steam']) : null;
    if (!$server) {
        message('warning', 'This server is no longer in AMXBans.');
        $sid = 0;
    } else {
        if ($offline['type'] === 'steam' && !$steamid) {
            $errors[] = 'Steam ID: enter it as STEAM_0:1:123, [U:1:246], 7656… or a steamcommunity.com/profiles/ link.';
        } elseif ($offline['type'] === 'ip' && !filter_var($offline['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $errors[] = 'IP address: enter one such as 203.0.113.7.';
        }
        if (!isset(AMXADM_ACTIONS[$offline['length']]) || $offline['length'] === 'kick') {
            $errors[] = 'Length: pick one.';
        }
        if ($offline['reason'] === '') {
            $errors[] = 'Reason: a ban needs one.';
        } elseif (mb_strlen($offline['reason']) > 100) {
            $errors[] = 'Reason: use 100 characters at most.';
        }
        $type = $offline['type'] === 'steam' ? 'S' : 'SI';
        $ip   = $offline['type'] === 'ip' ? $offline['ip'] : '';
        if (!$errors && ($bid = amxActiveBan($type, (string) $steamid, $ip))) {
            $errors[] = 'This player is already banned (ban #' . $bid . ').';
        }
        if ($errors) {
            message('warning', 'Check these fields:<br>' . implode('<br>', array_map('htmlspecialchars', $errors)));
        } else {
            $minutes = (int) $offline['length'];
            amxAddBan(array('type' => $type, 'authid' => (string) $steamid, 'ip' => $ip, 'name' => $offline['name'], 'minutes' => $minutes,
                'reason' => $offline['reason']), $server, amxadmWho());
            amxadmLog('nick: ' . ($offline['name'] !== '' ? $offline['name'] : 'Unknown') . ' <' . $steamid . '><' . $ip . "> banned for $minutes minutes", 'Add ban');
            // On the server after all: out now
            $kicked = false;
            list($ok, $players) = (string) $server['rcon'] !== '' ? amxPlayers($server) : array(false, array());
            foreach ($ok ? $players : array() as $p) {
                if ($p['kind'] === 0 && !$p['immune'] && ($type === 'S' ? $p['authid'] === $steamid : $p['ip'] === $ip)) {
                    $kicked = amxKick($server, $p['userid'], amxBanMessage($minutes, $offline['reason']))[0] || $kicked;
                }
            }
            $label = htmlspecialchars($offline['name'] !== '' ? $offline['name'] : ($steamid ?: $ip));
            message('success', "$label is banned" . substr(AMXADM_ACTIONS[$offline['length']], 3) . '.' . ($kicked ? ' They were on the server: kicked.' : ''));
            $offline = null;
        }
    }
} elseif ($do !== '') {
    $postId = (int) ($_POST['amx_id'] ?? 0);
    $target = $postId > 0 ? amxadmAdmin($postId) : null;
    if ($postId > 0 && !$target) {
        message('warning', 'This admin no longer exists.');
        $id = 0;
    } elseif ($do === 'delete' && $target) {
        $db->query("DELETE FROM " . amxTable('amxadmins') . " WHERE id = $postId LIMIT 1");
        $db->query("DELETE FROM " . amxTable('admins_servers') . " WHERE admin_id = $postId");
        amxadmLog('Deleted admin: ' . $target['username']);
        $lines = amxadmReload(array_keys($target['servers']));
        message('success', htmlspecialchars($target['username']) . ' is no longer an AMXBans admin.' . ($lines ? '<br>' . implode('<br>', $lines) : ''));
        $id = 0;
    } elseif ($do === 'save') {
        list($row, $errors) = amxadmCheck($_POST, $target);
        if ($errors) {
            message('warning', 'Check these fields:<br>' . implode('<br>', array_map('htmlspecialchars', $errors)));
            $values = amxadmPosted($_POST);
            $id     = $postId;
        } else {
            $saved = amxadmSave($row, $target);
            amxadmLog(($target ? 'Edited admin: ' : 'Added admin: ') . $row['username'] . ' (nick: ' . $row['nickname'] . ')');
            $lines = ($_POST['reload'] ?? '') === '1' ? amxadmReload(array_merge($target ? array_keys($target['servers']) : array(), $row['servers'])) : array();
            message('success', htmlspecialchars($row['username']) . ($target ? ' has been updated.' : ' is now an AMXBans admin.') . ($lines ? '<br>' . implode('<br>', $lines) : ''));
            $id = $target ? $saved : 0;
        }
    }
}

/*
 * One admin: its form and its removal
 */
if ($id > 0) {
    $admin = amxadmAdmin($id);
    if (!$admin) {
        message('warning', 'This admin no longer exists.');
    } else {
        $name = trim((string) $admin['nickname']) !== '' ? trim($admin['nickname']) : trim((string) $admin['username']);
        echo '<p><a href="' . amxadmUrl() . '">&larr;&nbsp;All AMXBans admins</a></p>';
        amxadmForm($values ?? amxadmValues($admin), $id, $token);
?>
<form method="post" action="<?= amxadmUrl(0) ?>" class="hlstats-amx-delete" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Remove ' . $name . ' from the AMXBans admins?')) ?>);">
    <input type="hidden" name="amx_do" value="delete">
    <input type="hidden" name="amx_token" value="<?= $token ?>">
    <input type="hidden" name="amx_id" value="<?= $id ?>">
    <div class="hlstats-admin-apply">
        <input type="submit" value="Delete this admin">
    </div>
</form>
</div>
<?php
        return;
    }
}

/*
 * One server: its RCON password
 */
if ($sid > 0) {
    $server = amxadmServers()[$sid] ?? null;
    if (!$server) {
        message('warning', 'This server is no longer in AMXBans.');
    } else {
        $set = (string) $server['rcon'] !== '';
        echo '<p><a href="' . amxadmUrl() . '">&larr;&nbsp;All AMXBans servers</a></p>';
?>
<form method="post" action="<?= amxadmUrl(0, $sid) ?>" class="hlstats-amx-server">
<input type="hidden" name="amx_do" value="server">
<input type="hidden" name="amx_token" value="<?= $token ?>">
<input type="hidden" name="amx_server" value="<?= $sid ?>">
<div class="hlstats-admin-propgroup">
<b>Server</b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">Name:</td>
    <td class="left"><?= htmlspecialchars($server['label']) ?></td>
</tr>
<tr>
    <td class="left">Address:</td>
    <td class="left"><?= htmlspecialchars((string) $server['address']) ?> <span class="hlstats-amx-muted">&middot; <?= htmlspecialchars((string) $server['gametype']) ?></span></td>
</tr>
<tr>
    <td class="left">Last seen:</td>
    <td class="left"><?= (int) $server['timestamp'] > 0 ? date('Y-m-d H:i', (int) $server['timestamp']) : '&ndash;' ?> <span class="hlstats-amx-muted">&middot; the plugin checks in at each map</span></td>
</tr>
<tr>
    <td class="left">RCON password:</td>
    <td class="left"><input type="password" name="rcon" value="" maxlength="32" autocomplete="new-password" spellcheck="false"
        placeholder="<?= $set ? 'Set: leave empty to keep it and test it again' : 'The rcon_password of the server' ?>"></td>
</tr>
</tbody>
</table>
</div>
</div>
<?php if ($set): ?>
<div class="hlstats-admin-propgroup">
<div class="hlstats-amx-checks is-wide">
    <label><input type="checkbox" name="rcon_clear" value="1">Remove the RCON password</label>
</div>
</div>
<?php endif; ?>
<div class="hlstats-admin-apply">
    <input type="submit" value="Save and test">
</div>
</form>
<div class="hlstats-admin-propgroup">
<b>Players online</b>
<?php
        if (!$set) {
            echo '<p class="hlstats-amx-muted">Set its RCON password to see who is on the server, and to kick or ban them.</p>';
        } else {
            list($ok, $players) = amxPlayers($server);
            if (!$ok) {
                echo '<p class="hlstats-amx-muted">The server did not answer: ' . htmlspecialchars($players) . '</p>';
            } elseif (!$players) {
                echo '<p class="hlstats-amx-muted">Nobody is on the server.</p>';
            } else {
?>
<form method="post" action="<?= amxadmUrl(0, $sid) ?>" class="hlstats-amx-players"
    onsubmit="var p = this.querySelector('input[name=amx_userid]:checked'), a = this.elements.amx_act; return !p || a.value === 'kick' || confirm(a.options[a.selectedIndex].text + ': ' + p.closest('tr').cells[1].textContent + '?');">
<input type="hidden" name="amx_do" value="player">
<input type="hidden" name="amx_token" value="<?= $token ?>">
<input type="hidden" name="amx_server" value="<?= $sid ?>">
<div class="hlstats-admin-table-wrap hlstats-scrollbar">
<table>
    <tr>
        <th></th>
        <th class="left">Player</th>
        <th>#</th>
        <th class="left">Steam ID</th>
        <th class="left">IP address</th>
        <th></th>
    </tr>
<?php foreach ($players as $p): $can = $p['kind'] === 0 && !$p['immune']; ?>
    <tr>
        <td><input type="radio" name="amx_userid" value="<?= $p['userid'] ?>" id="amx-player-<?= $p['userid'] ?>"<?= $can ? '' : ' disabled' ?>></td>
        <td class="left"><label for="amx-player-<?= $p['userid'] ?>"><?= htmlspecialchars($p['name']) ?></label></td>
        <td><?= $p['userid'] ?></td>
        <td class="left"><?= htmlspecialchars($p['authid']) ?></td>
        <td class="left"><?= htmlspecialchars($p['ip']) ?></td>
        <td class="left hlstats-amx-muted"><?= $p['kind'] === 1 ? 'bot' : ($p['kind'] === 2 ? 'HLTV' : ($p['immune'] ? 'immunity' : '')) ?></td>
    </tr>
<?php endforeach; ?>
</table>
</div>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">Do:</td>
    <td class="left"><select name="amx_act">
<?php foreach (AMXADM_ACTIONS as $value => $label) { ?>
        <option value="<?= $value ?>"><?= $label ?></option>
<?php } ?>
    </select></td>
</tr>
<tr>
    <td class="left">Reason:</td>
    <td class="left"><input type="text" name="reason" value="" maxlength="100" placeholder="Shown to the player; a ban needs one"></td>
</tr>
</tbody>
</table>
</div>
<div class="hlstats-admin-apply">
    <input type="submit" value="Apply to the player picked">
</div>
</form>
<?php
            }
        }
        echo '</div>';
        $o   = $offline ?? array('type' => 'steam', 'steam' => '', 'ip' => '', 'name' => '', 'length' => '1440', 'reason' => '');
        $sel = fn($value, $option) => (string) $value === (string) $option ? ' selected' : '';
?>
<form method="post" action="<?= amxadmUrl(0, $sid) ?>" class="hlstats-amx-form hlstats-amx-offline"
    onsubmit="var l = this.elements.namedItem('length'); return confirm('Ban ' + (this.elements.namedItem('name').value.trim() || 'this player') + ' ' + l.options[l.selectedIndex].text.toLowerCase() + '?');">
<input type="hidden" name="amx_do" value="banoffline">
<input type="hidden" name="amx_token" value="<?= $token ?>">
<input type="hidden" name="amx_server" value="<?= $sid ?>">
<div class="hlstats-admin-propgroup">
<b>Ban a player who is not on the server</b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">Ban by:</td>
    <td class="left"><select name="type">
        <option value="steam"<?= $sel($o['type'], 'steam') ?>>Steam ID</option>
        <option value="ip"<?= $sel($o['type'], 'ip') ?>>IP address</option>
    </select></td>
</tr>
<tr class="amx-when-steam">
    <td class="left">Steam ID:</td>
    <td class="left"><input type="text" name="steam" value="<?= htmlspecialchars($o['steam']) ?>" placeholder="STEAM_0:1:123, [U:1:246], 7656… or a /profiles/ link" autocomplete="off" spellcheck="false"></td>
</tr>
<tr class="amx-when-ip">
    <td class="left">IP address:</td>
    <td class="left"><input type="text" name="ip" value="<?= htmlspecialchars($o['ip']) ?>" maxlength="15" placeholder="203.0.113.7" autocomplete="off" spellcheck="false"></td>
</tr>
<tr>
    <td class="left">Player name:</td>
    <td class="left"><input type="text" name="name" value="<?= htmlspecialchars($o['name']) ?>" maxlength="100" placeholder="As the bans list shows it (optional)"></td>
</tr>
<tr>
    <td class="left">Length:</td>
    <td class="left"><select name="length">
<?php foreach (AMXADM_ACTIONS as $value => $label) { if ($value === 'kick') { continue; } ?>
        <option value="<?= $value ?>"<?= $sel($o['length'], $value) ?>><?= ucfirst(substr($label, 4)) ?></option>
<?php } ?>
    </select></td>
</tr>
<tr>
    <td class="left">Reason:</td>
    <td class="left"><input type="text" name="reason" value="<?= htmlspecialchars($o['reason']) ?>" maxlength="100" placeholder="Shown to the player when they try to join"></td>
</tr>
</tbody>
</table>
</div>
</div>
<div class="hlstats-admin-apply">
    <input type="submit" value="Ban">
</div>
</form>
</div>
<?php
        return;
    }
}

/*
 * All of them, and a new one
 */
$now     = time();
$servers = amxadmServers();
$db->query("SELECT id, username, nickname, steamid, access, flags, expired, days FROM " . amxTable('amxadmins') . " ORDER BY nickname, username, id");
$admins  = $db->fetch_row_set() ?: array();
$links   = array();
if ($admins) {
    $db->query("SELECT admin_id, server_id, custom_flags FROM " . amxTable('admins_servers') . " WHERE admin_id IN (" . implode(',', array_map('intval', array_column($admins, 'id'))) . ")");
    foreach ($db->fetch_row_set() ?: array() as $row) {
        $custom = trim((string) $row['custom_flags']);
        $links[(int) $row['admin_id']][] = ($servers[(int) $row['server_id']]['label'] ?? 'Server #' . (int) $row['server_id']) . ($custom !== '' ? " (flags $custom)" : '');
    }
}
?>
<div class="hlstats-admin-propgroup">
<b>Admins</b>
<div class="hlstats-admin-note">
    Admins of the GoldSrc servers, kept in AMXBans: its plugin loads them on their servers at each map.
    Their flags are the AMX Mod X ones: there <b>a</b> is immunity and <b>z</b> means no admin rights.
</div>
<div class="hlstats-admin-table-wrap hlstats-scrollbar">
<table>
    <tr>
        <th class="left">Admin</th>
        <th class="left">Recognised by</th>
        <th class="left">Access</th>
        <th class="left">Servers</th>
        <th>Ends</th>
        <th></th>
    </tr>
<?php foreach ($admins as $row):
    $name    = trim((string) $row['nickname']) !== '' ? trim($row['nickname']) : trim((string) $row['username']);
    $active  = (int) $row['days'] === 0 || (int) $row['expired'] > $now;   // as the AMXBans plugin loads them
    $flags   = preg_replace('/[^a-z]/', '', strtolower((string) $row['access']));
    $meaning = array_map(fn($flag) => AMXADM_FLAGS[$flag] ?? $flag, str_split($flags));
    $places  = $links[(int) $row['id']] ?? array();
?>
    <tr<?= $active ? '' : ' class="hlstats-amx-off"' ?>>
        <td class="left"><?= htmlspecialchars($name) ?><?= trim((string) $row['username']) !== '' && trim($row['username']) !== $name ? ' <span class="hlstats-amx-muted">' . htmlspecialchars($row['username']) . '</span>' : '' ?></td>
        <td class="left"><?= htmlspecialchars((string) $row['steamid']) ?> <span class="hlstats-amx-muted">&middot; <?= AMXADM_TYPES[amxadmType($row['flags'])] . (strpos((string) $row['flags'], 'e') === false ? ' + password' : '') ?></span></td>
        <td class="left"><?= $flags !== '' ? '<code data-tooltip="' . htmlspecialchars(implode(', ', $meaning)) . '">' . $flags . '</code>' : '&ndash;' ?></td>
        <td class="left"><?= $places ? htmlspecialchars(implode(', ', $places)) : '<span class="hlstats-amx-muted">none</span>' ?></td>
        <td class="nowrap"><?= (int) $row['days'] === 0 ? 'never' : ($active ? date('Y-m-d', (int) $row['expired']) : 'ended ' . date('Y-m-d', (int) $row['expired'])) ?></td>
        <td><a href="<?= amxadmUrl((int) $row['id']) ?>">Edit</a></td>
    </tr>
<?php endforeach; if (!$admins): ?>
    <tr><td class="left" colspan="6">AMXBans has no admin yet.</td></tr>
<?php endif; ?>
</table>
</div>
</div>
<div class="hlstats-admin-propgroup">
<b>Servers</b>
<div class="hlstats-admin-note">
    A server shows up here once the AMXBans plugin runs on it. With its RCON password, HLstatsZ reloads the admins
    right after a change, shows its map and players on the bans pages, and lists its players to kick or ban them.
</div>
<div class="hlstats-admin-table-wrap hlstats-scrollbar">
<table>
    <tr>
        <th class="left">Server</th>
        <th class="left">Address</th>
        <th>Last seen</th>
        <th>RCON</th>
        <th></th>
    </tr>
<?php foreach ($servers as $serverId => $server): ?>
    <tr>
        <td class="left"><?= htmlspecialchars($server['label']) ?></td>
        <td class="left"><?= htmlspecialchars((string) $server['address']) ?></td>
        <td class="nowrap"><?= (int) $server['timestamp'] > 0 ? date('Y-m-d H:i', (int) $server['timestamp']) : '&ndash;' ?></td>
        <td><?= (string) $server['rcon'] !== '' ? 'set' : '<span class="hlstats-amx-muted">not set</span>' ?></td>
        <td><a href="<?= amxadmUrl(0, $serverId) ?>">Edit</a></td>
    </tr>
<?php endforeach; if (!$servers): ?>
    <tr><td class="left" colspan="5">No GoldSrc server is in AMXBans yet.</td></tr>
<?php endif; ?>
</table>
</div>
</div>
<?php
amxadmForm($id === 0 && $values !== null ? $values : amxadmValues(null), 0, $token);
echo '</div>';
