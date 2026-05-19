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
	define('DISCORD', 2);

	if ($db->num_rows($resultVoices) >= 1) {
		printSectionTitle('Voice Server');
?>
		<table>
			<tr>
				<th class="hlstats-main-column left">Server Name</th>
				<th class="hide">Server Address</th>
				<th class="hide">Password</th>
				<th>Channels</th>
				<th>Slots used</th>
				<th class="hide-2">Notes</th>
			</tr>
<?php
		ob_flush();
		flush();

		$ts_servers = [];
		$discord_servers = [];
		while ($row = $db->fetch_array($resultVoices)) {
			if ($row['serverType'] == TS) {
				$ts_servers[] = [
					'serverId'  => $row['serverId'],
					'name'      => $row['name'],
					'addr'      => $row['addr'],
					'password'  => $row['password'],
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
			}
		}

		if (!is_dir('./cache')) mkdir('./cache', 0755, true);

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
				<span class="hlstats-icon"><img src="<?php echo IMAGE_PATH; ?>/teamspeak3/ts3.png" alt="tsicon" /></span>
				<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=teamspeak&amp;game=$game&amp;tsId=".$ts_server['serverId']; ?>"><?php echo htmlspecialchars(trim($ts_server['name'])); ?></a></span>
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
					$ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
					$widget_json = @file_get_contents($widget_url, false, $ctx);
					if ($widget_json !== false) {
						$dc_widget = json_decode($widget_json, true);
						if (is_array($dc_widget)) {
							@file_put_contents($dc_cache_file, json_encode($dc_widget), LOCK_EX);
						}
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
				<span class="hlstats-icon"><img src="<?php echo IMAGE_PATH; ?>/discord/discord.svg" alt="dcicon" width="16" height="16" /></span>
				<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=discord&amp;game=$game&amp;dcId=".$dc_server['serverId']; ?>"><?php echo htmlspecialchars(trim($dc_server['name'])); ?></a></span>
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
?>
		</table>
<?php
	}
	// VOICECOMM MODULE END
?>
