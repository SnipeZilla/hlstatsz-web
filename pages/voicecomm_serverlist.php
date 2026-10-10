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
	// VOICECOMM MODULE
	global $db, $resultVoices;

	define('TS', 0);
	define('STEAM_COMMUNITY', 1);
	define('DISCORD', 2);
	define('MUMBLE', 3);

	if ($db->num_rows($resultVoices) >= 1) {

		if (!is_dir('./cache')) mkdir('./cache', 0755, true);

		$voicecommCacheFile = './cache/hlstatsz_voicecomm_page.html';
		$voicecommCacheTtl  = 20; // seconds before a cached copy is considered stale
		$forceRefresh       = !empty($_GET['refresh']);

		if (!$forceRefresh && is_file($voicecommCacheFile)) {
			if ((time() - filemtime($voicecommCacheFile)) > $voicecommCacheTtl) {
				// tell the client this copy is stale so it can silently fetch a fresh one
				echo '<div data-voicecomm-stale hidden></div>';
			}
			readfile($voicecommCacheFile);
			return;
		}

		ob_start();

		$ts_servers      = [];
		$discord_servers = [];
		$steam_servers   = [];
		$mumble_servers  = [];
		while ($row = $db->fetch_array($resultVoices)) {
			if ($row['serverType'] == TS) {
				$ts_servers[] = [
					'serverId'  => $row['serverId'],
					'name'      => $row['name'],
					'addr'      => $row['addr'],
					'password'  => $row['password'] ?? '',
					'descr'     => $row['descr'],
					'queryPort' => $row['queryPort'],
					'UDPPort'   => $row['UDPPort'],
				];
			} else if ($row['serverType'] == DISCORD) {
				$discord_servers[] = [
					'serverId' => $row['serverId'],
					'name'     => $row['name'],
					'addr'     => $row['addr'],
					'descr'    => $row['descr'],
				];
			} else if ($row['serverType'] == STEAM_COMMUNITY) {
				$steam_servers[] = [
					'serverId' => $row['serverId'],
					'name'     => $row['name'],
					'addr'     => $row['addr'],
					'descr'    => $row['descr'],
				];
			} else if ($row['serverType'] == MUMBLE) {
				$mumble_servers[] = [
					'serverId' => $row['serverId'],
					'name'     => $row['name'],
					'addr'     => $row['addr'],
					'descr'    => $row['descr'],
					'UDPPort'  => $row['UDPPort'],
				];
			}
		}

		if ($ts_servers || $discord_servers || $steam_servers || $mumble_servers) {
			printSectionTitle(t('title.commservers'));
?>
		<table>
			<tr>
				<th class="hlstats-main-column left"><?= t('server.name') ?></th>
				<th class="hide"><?= t('server.address') ?></th>
				<th class="hide"><?= t('password') ?></th>
				<th><?= t('channels') ?></th>
				<th><?= t('slots.used') ?></th>
				<th class="hide-2"><?= t('notes') ?></th>
			</tr>
<?php
			if ($ts_servers) {
				require_once(PAGE_PATH . '/teamspeak_class.php');
				foreach ($ts_servers as $ts_server) {
					$ts3   = new TeamSpeak3Query($ts_server['addr'], $ts_server['queryPort'], $ts_server['UDPPort'], 5);
					$tsRes = $ts3->query(60);

					if ($tsRes['error']) {
						$ts_channels = 'err';
						$ts_slots    = htmlspecialchars($tsRes['error']);
						$ts_port     = $ts_server['UDPPort'];
						$ts_link     = $ts_server['addr'] . ':' . $ts_port;
					} else {
						$si          = $tsRes['serverinfo'];
						$ts_channels = count($tsRes['channels']);
						$realUsers   = 0;
						foreach ($tsRes['clients'] as $c) {
							if ((int)($c['client_type'] ?? 0) !== 0) continue;
							$realUsers++;
						}
						$ts_slots = $realUsers . '/' . (int)($si['virtualserver_maxclients'] ?? 0);
						$ts_port  = (int)($si['virtualserver_port'] ?? $ts_server['UDPPort']);
						$ts_link  = $ts_server['addr'] . ':' . $ts_port;
					}
?>
			<tr>
				<td class="left">
					<span class="hlstats-icon small"><img src="<?php echo IMAGE_PATH; ?>/teamspeak3/ts3.png" alt="tsicon" /></span>
					<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=teamspeak&amp;tsId=".$ts_server['serverId']; ?>"><?php echo htmlspecialchars(trim($ts_server['name'])); ?></a></span>
				</td>
				<td class="hide">
					<a href="ts3server://<?php echo htmlspecialchars($ts_server['addr']); ?>?port=<?php echo (int)$ts_port; ?><?php if (!empty($ts_server['password'])) echo '&amp;password=' . urlencode($ts_server['password']); ?>&amp;nickname=<?php echo urlencode($tsNickname ?? 'WebGuest'); ?>"><?php echo htmlspecialchars($ts_link); ?></a>
				</td>
				<td class="hide">
					<?php echo $ts_server['password']; ?>
				</td>
				<td>
					<?php echo $ts_channels; ?>
				</td>
				<td>
					<?php echo $ts_slots; ?>
				</td>
				<td class="hide-2">
					<?php echo htmlspecialchars($ts_server['descr']); ?>
				</td>
			</tr>
<?php
				}
			}

			if ($discord_servers) {
				foreach ($discord_servers as $dc_server) {
					$dc_channels = '-';
					$dc_slots    = '-';
					$dc_invite   = '';

					$dc_cache_ttl  = 60;
					$dc_cache_file = './cache/hlstatsz_dc_' . md5($dc_server['addr']) . '.json';
					$dc_widget     = null;
					if (is_file($dc_cache_file) && (time() - filemtime($dc_cache_file)) < $dc_cache_ttl) {
						$dc_widget = json_decode(file_get_contents($dc_cache_file), true);
					}
					if (!is_array($dc_widget)) {
						$widget_url  = 'https://discord.com/api/guilds/' . urlencode($dc_server['addr']) . '/widget.json';
						// The certificates HLstatsZ checks https with (hlstatsSsl()): without them PHP on Windows fails now and then
						$ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true], 'ssl' => hlstatsSsl()]);
						$widget_json = @file_get_contents($widget_url, false, $ctx);
						if ($widget_json !== false) {
							$dc_widget = json_decode($widget_json, true);
							if (is_array($dc_widget)) {
								@file_put_contents($dc_cache_file, json_encode($dc_widget), LOCK_EX);
							}
						}
						// Discord did not answer: what it said last time
						if (!is_array($dc_widget) && is_file($dc_cache_file)) {
							$dc_widget = json_decode(file_get_contents($dc_cache_file), true);
						}
					}
					if (is_array($dc_widget)) {
						if (isset($dc_widget['channels']))       $dc_channels = count($dc_widget['channels']);
						if (isset($dc_widget['presence_count'])) $dc_slots    = $dc_widget['presence_count'] . ' online';
						if (isset($dc_widget['instant_invite'])) $dc_invite   = $dc_widget['instant_invite'];
					}
?>
			<tr>
				<td class="left">
					<span class="hlstats-icon small"><img src="<?php echo IMAGE_PATH; ?>/discord/discord.svg" alt="dcicon" /></span>
					<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=discord&amp;dcId=".$dc_server['serverId']; ?>"><?php echo htmlspecialchars(trim($dc_server['name'])); ?></a></span>
				</td>
				<td class="hide">
<?php if (!empty($dc_invite)): ?>
					<a href="<?php echo htmlspecialchars($dc_invite); ?>" target="_blank"><?php echo htmlspecialchars($dc_invite); ?></a>
<?php else: ?>
					<?php echo htmlspecialchars($dc_server['addr']); ?>
<?php endif; ?>
				</td>
				<td class="hide">
					-
				</td>
				<td>
					<?php echo $dc_channels; ?>
				</td>
				<td>
					<?php echo $dc_slots; ?>
				</td>
				<td class="hide-2">
					<?php echo htmlspecialchars($dc_server['descr'] ?? ''); ?>
				</td>
			</tr>
<?php
				}
			}

			if ($steam_servers) {
				foreach ($steam_servers as $sc_server) {
					$sc_slug      = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sc_server['addr']);
					$sc_group_url = 'https://steamcommunity.com/groups/' . urlencode($sc_slug);
					$sc_cache_file = './cache/hlstatsz_sc_' . md5($sc_slug) . '.xml';

					$sc_xml_raw = false;
					if (is_file($sc_cache_file) && (time() - filemtime($sc_cache_file)) < 300) {
						$sc_xml_raw = file_get_contents($sc_cache_file);
					} else {
						$url = 'https://steamcommunity.com/groups/' . urlencode($sc_slug) . '/memberslistxml/?xml=1';
						$ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true, 'user_agent' => 'HLstatsZ/1.0'], 'ssl' => hlstatsSsl()]);
						$sc_xml_raw = @file_get_contents($url, false, $ctx);
						if ($sc_xml_raw !== false) {
							@file_put_contents($sc_cache_file, $sc_xml_raw, LOCK_EX);
						} elseif (is_file($sc_cache_file)) {
							$sc_xml_raw = file_get_contents($sc_cache_file);
						}
					}

					$sc_data    = $sc_xml_raw ? @simplexml_load_string($sc_xml_raw) : null;
					$sc_online  = '&mdash;';
					$sc_members = '&mdash;';
					$sc_icon    = IMAGE_PATH . '/unknown.jpg';

					if ($sc_data && isset($sc_data->groupDetails)) {
						$d = $sc_data->groupDetails;
						$sc_online  = number_format((int)($d->membersOnline ?? 0));
						$sc_members = number_format((int)($d->memberCount   ?? 0));
						$av = trim((string)($d->avatarIcon ?? ''));
						if ($av !== '') $sc_icon = $av;
					}
?>
			<tr>
				<td class="left">
					<span class="hlstats-icon small"><img src="<?php echo IMAGE_PATH; ?>/steamcommunity/steamcommunity.png" alt="steamicon" /></span>
					<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=steamcommunity&amp;scId=".$sc_server['serverId']; ?>"><?php echo htmlspecialchars(trim($sc_server['name'])); ?></a></span>
				</td>
				<td class="hide">
					<a href="<?php echo htmlspecialchars($sc_group_url, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($sc_group_url); ?></a>
				</td>
				<td class="hide">
					-
				</td>
				<td>
					-
				</td>
				<td>
					<?php echo $sc_online . ' / ' . $sc_members; ?>
				</td>
				<td class="hide-2">
					<?php echo htmlspecialchars($sc_server['descr'] ?? ''); ?>
				</td>
			</tr>
<?php
				}
			}

			if ($mumble_servers) {
				require_once(PAGE_PATH . '/mumble_class.php');
				foreach ($mumble_servers as $mb_server) {
					$mb    = new MumbleQuery($mb_server['addr'], $mb_server['UDPPort'], 3);
					$mbRes = $mb->query(30);

					if ($mbRes['error']) {
						$mb_slots = htmlspecialchars($mbRes['error']);
					} else {
						$mb_slots = $mbRes['users'] . '/' . $mbRes['maxusers'];
					}
					$mb_link = $mb_server['addr'] . ':' . $mb_server['UDPPort'];
?>
			<tr>
				<td class="left">
					<span class="hlstats-icon small"><img src="<?php echo IMAGE_PATH; ?>/mumble/mumble.svg" alt="mumbleicon" /></span>
					<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=mumble&amp;mbId=".$mb_server['serverId']; ?>"><?php echo htmlspecialchars(trim($mb_server['name'])); ?></a></span>
				</td>
				<td class="hide">
					<a href="mumble://<?php echo htmlspecialchars($mb_link); ?>/"><?php echo htmlspecialchars($mb_link); ?></a>
				</td>
				<td class="hide">
					-
				</td>
				<td>
					-
				</td>
				<td>
					<?php echo $mb_slots; ?>
				</td>
				<td class="hide-2">
					<?php echo htmlspecialchars($mb_server['descr'] ?? ''); ?>
				</td>
			</tr>
<?php
				}
			}
?>
		</table>
<?php
		}

		$rendered = ob_get_clean();
		file_put_contents($voicecommCacheFile, $rendered, LOCK_EX);
		echo $rendered;
	}
	// VOICECOMM MODULE END
?>
