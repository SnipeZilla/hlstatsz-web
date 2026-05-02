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

$name = $playerdata['lastName'];
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
    $avatarFull = (string) ($xmlDoc->avatarFull ?? $avatarFull);
    $memberSince = (string) ($xmlDoc->memberSince ?? $memberSince);
}

if ($name !== $playerdata['lastName']) {
    $playerdata['lastName'] = $name;

    $db->query("
        UPDATE hlstats_players
        SET lastName = '" . $db->escape($name) . "'
        WHERE playerId = '" . (int) $playerdata['playerId'] . "'
    ");
}

$prefix = ($g_options['Mode'] == 'Normal') ? 'STEAM_0:' : '';
$steam = '<a href="' . htmlspecialchars($profileUrl, ENT_QUOTES) . '" target="_blank">' . $prefix . htmlspecialchars($uqid, ENT_COMPAT) . '</a>';
$location = '(Unknown)';

if (preg_match('/^BOT/i', (string) $uqid)) {
    $playerdata['flag'] = 'bot';
    $steam = htmlspecialchars($uqid, ENT_COMPAT);
    $location = '(Server)';
} else {
    $location = Location($playerdata['city'], $playerdata['state'], $playerdata['country'], $g_options['countrydata']);
}

$statusClass = $status == 'offline'? ' red' : ($status == 'Unknown'? ' orange': ' green');
?>
<div class="hlstats-profile-head">
  <div class="hlstats-avatar">
    <img src="<?= htmlspecialchars($avatarFull, ENT_QUOTES) ?>"
         class="hlstats-avatar-img"
         alt="Steam Community Avatar" />
  </div>

  <div class="hlstats-identity">
    <div class="hlstats-pname">
<?php if ($g_options['countrydata']) { ?>
      <span class="hlstats-flag">
        <img src="<?= getFlag($playerdata['flag']) ?>"
             alt="<?= htmlspecialchars($playerdata['country'] ?? '', ENT_QUOTES) ?>"
             title="<?= htmlspecialchars($playerdata['country'] ?? '', ENT_QUOTES) ?>" />
      </span>
<?php } ?>
      <span><?= htmlspecialchars($name, ENT_COMPAT) ?></span>
    </div>

    <div class="hlstats-meta">
<?php if ($g_options['countrydata']) { ?>
      <span><strong>Location:</strong> <?= htmlspecialchars($location, ENT_COMPAT) ?></span>
<?php } ?>
      <span><strong>Steam:</strong> <?= $steam ?></span>
      <span><strong>Status:</strong><strong class="hlstats-status<?= $statusClass ?>"> <?= ucfirst(htmlspecialchars($status, ENT_COMPAT)) ?></strong><?php if (!empty($vacBanned)) echo '<strong class="red"> '.$vacBanned.'</strong>'; ?></span>
      <span><strong>Member Since:</strong> <?= htmlspecialchars($memberSince, ENT_COMPAT) ?></span>
    </div>
  </div>
</div>
<span id="steam-member-since-value-<?= (int) $player ?>"
      data-member-since="<?= htmlspecialchars($memberSince, ENT_QUOTES) ?>"
      hidden><?= htmlspecialchars($memberSince, ENT_COMPAT) ?></span>