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

$player = valid_request(intval($_GET['player'] ?? 0), true);
$game = valid_request(strval($_GET['game'] ?? ''), false);

if (!$player) {
    exit;
}

if (!$game) {
    $db->query("SELECT game FROM hlstats_players WHERE playerId = '$player' LIMIT 1");
    list($game) = $db->fetch_row();
}

$db->query("
    SELECT
        p.playerId,
        p.lastName,
        p.flag,
        p.country,
        p.state,
        p.city,
        pu.uniqueId,
        CAST(LEFT(pu.uniqueId,1) AS unsigned)
            + CAST('76561197960265728' AS unsigned)
            + CAST(MID(pu.uniqueId, 3,10)*2 AS unsigned) AS communityId
    FROM
        hlstats_players AS p
    LEFT JOIN
        hlstats_PlayerUniqueIds AS pu
    ON
        pu.playerId = p.playerId
    WHERE
        p.playerId = '$player'
    LIMIT 1
");

if ($db->num_rows() != 1) {
    exit;
}

$steamdata = $db->fetch_array();
$uqid = $steamdata['uniqueId'];
$coid = $steamdata['communityId'];

$name = $steamdata['lastName'];
$status = 'Unknown';
$avatarFull = IMAGE_PATH . '/unknown.jpg';
$memberSince = 'Private';
$vacBanned = '';
$profileUrl = 'https://steamcommunity.com/profiles/' . $coid;
$xml = '';

if ($coid !== '76561197960265728' && !preg_match('/^BOT/i', (string) $uqid)) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $profileUrl . '?xml=1');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
    $xml = curl_exec($curl);
    $curl=null;
}

$xmlDoc = $xml ? @simplexml_load_string($xml) : null;

if ($xmlDoc) {
    $steamID  = (string) ($xmlDoc->steamID ?? '');
    if (!empty($steamID)) $name = $steamID;
    $status = (string) ($xmlDoc->onlineState ?? $status);
    $vacBanned = (string) ($xmlDoc->vacBanned ?? '');
    if (!empty($vacBanned)) $vacBanned = ' ('.htmlspecialchars($vacBanned, ENT_COMPAT).' vacBanned)';
    $remoteAvatar = (string) ($xmlDoc->avatarFull ?? '');
    $memberSince = (string) ($xmlDoc->memberSince ?? $memberSince);

    if ($remoteAvatar !== '') {
        $avatarFull = saveAvatarLocally($remoteAvatar, $coid);
    }
}

function saveAvatarLocally(string $url, string $steamId): string
{
    $cacheDir = IMAGE_PATH . '/avatars';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    $localFile = $cacheDir . '/' . $steamId . '.' . $ext;
    $cacheTtl = 300; // 5 minutes

    if (file_exists($localFile) && (time() - filemtime($localFile)) < $cacheTtl) {
        return $localFile;
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
    $imageData = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl=null;

    if ($imageData !== false && $httpCode === 200) {
        file_put_contents($localFile, $imageData);
        return $localFile;
    }

    // Return stale cache if download failed
    if (file_exists($localFile)) {
        return $localFile;
    }

    return IMAGE_PATH . '/unknown.jpg';
}
if ($name !== $steamdata['lastName']) {
    $db->query("UPDATE hlstats_players SET lastName = '" . $db->escape($name) . "' WHERE playerId = '" . (int) $steamdata['playerId'] . "'");
}

$prefix = ($g_options['Mode'] == 'Normal') ? 'STEAM_0:' : '';
$steam = '<a href="' . htmlspecialchars($profileUrl, ENT_QUOTES) . '" target="_blank">' . $prefix . htmlspecialchars($uqid, ENT_COMPAT) . '</a>';
$location = '(Unknown)';

if (preg_match('/^BOT/i', (string) $uqid)) {
    $steamdata['flag'] = 'bot';
    $steam = htmlspecialchars($uqid, ENT_COMPAT);
    $location = '(Server)';
} else {
    $location = Location($steamdata['city'], $steamdata['state'], $steamdata['country'], $g_options['countrydata']);
}

$statusClass = $status == 'online'? ' green' : ($status != 'offline'? ' orange': ' red');
?>
<div class="hlstats-profile-head">
  <div class="hlstats-avatar">
    <img src="<?= htmlspecialchars($avatarFull, ENT_QUOTES)."?".time() ?>"
         class="hlstats-avatar-img"
         alt="Steam Community Avatar" />
  </div>

  <div class="hlstats-identity">
    <div class="hlstats-pname">
<?php if ($g_options['countrydata']) { ?>
      <span class="hlstats-flag">
        <img src="<?= getFlag($steamdata['flag']) ?>"
             alt="<?= htmlspecialchars($steamdata['country'] ?? '', ENT_QUOTES) ?>"
             title="<?= htmlspecialchars($steamdata['country'] ?? '', ENT_QUOTES) ?>" />
      </span>
<?php } ?>
      <span><?= htmlspecialchars($name, ENT_COMPAT) ?></span>
    </div>

    <div class="hlstats-meta">
<?php if ($g_options['countrydata']) { ?>
      <span><strong>Location:</strong> <?= htmlspecialchars($location, ENT_COMPAT) ?></span>
<?php } ?>
      <span><strong>Steam:</strong> <?= $steam ?></span>
      <span><strong>Status: </strong><strong class="<?= $statusClass ?>"><?= ucfirst(htmlspecialchars($status, ENT_COMPAT)) ?></strong><?php if (!empty($vacBanned)) echo '<strong class="red"> '.$vacBanned.'</strong>'; ?></span>
      <span><strong>Member Since:</strong> <?= htmlspecialchars($memberSince, ENT_COMPAT) ?></span>
    </div>
  </div>
</div>
<span id="ingame-steam-member-since-value-<?= (int) $player ?>"
      data-member-since="<?= htmlspecialchars($memberSince, ENT_QUOTES) ?>"
      hidden><?= htmlspecialchars($memberSince, ENT_COMPAT) ?></span>