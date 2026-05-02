<?php
	// VOICECOMM MODULE
	global $db, $resultVoices;
	
	define('TS', 0);
	define('VENT', 1);
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

		$i = 0;
		$j = 0;
		$k = 0;
		while ($row = $db->fetch_array($resultVoices)) {
			if ($row['serverType'] == TS) {
				$ts_servers[$i]['serverId'] = $row['serverId'];
				$ts_servers[$i]['name'] = $row['name'];
				$ts_servers[$i]['addr'] = $row['addr'];
				$ts_servers[$i]['password'] = $row['password'];
				$ts_servers[$i]['descr'] = $row['descr'];
				$ts_servers[$i]['queryPort'] = $row['queryPort'];
				$ts_servers[$i]['UDPPort'] = $row['UDPPort'];
				$i++;
			} else if ($row['serverType'] == VENT) {
				$vent_servers[$j]['serverId'] = $row['serverId'];
				$vent_servers[$j]['name'] = $row['name'];
				$vent_servers[$j]['addr'] = $row['addr'];
				$vent_servers[$j]['password'] = $row['password'];
				$vent_servers[$j]['descr'] = $row['descr'];
				$vent_servers[$j]['queryPort'] = $row['queryPort'];
				$j++;
			} else if ($row['serverType'] == DISCORD) {
				$discord_servers[$k]['serverId'] = $row['serverId'];
				$discord_servers[$k]['name'] = $row['name'];
				$discord_servers[$k]['addr'] = $row['addr'];
				$discord_servers[$k]['descr'] = $row['descr'];
				$k++;
			}
		}
		if (isset($ts_servers))
		{
			require_once(PAGE_PATH . '/teamspeak_class.php');
			foreach($ts_servers as $ts_server)
			{
				$settings = $teamspeakDisplay->getDefaultSettings();
				$settings['serveraddress'] = $ts_server['addr'];
				$settings['serverqueryport'] = $ts_server['queryPort'];
				$settings['serverudpport'] = $ts_server['UDPPort'];
				$ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);
				if ($ts_info['queryerror'] != 0) {
					$ts_channels = 'err';
					$ts_slots = $ts_info['queryerror'];
				} else {
					$ts_channels = count($ts_info['channellist']);
					$ts_slots = count($ts_info['playerlist']).'/'.$ts_info['serverinfo']['server_maxusers'];
				}
?>
        <tr>
			<td class="left">
				<span class="hlstats-icon"><img src="<?php echo IMAGE_PATH; ?>/teamspeak/teamspeak.gif" alt="tsicon" /></span>
				<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=teamspeak&amp;game=$game&amp;tsId=".$ts_server['serverId']; ?>"><?php echo trim($ts_server['name']); ?></a></span>
			</td>
			<td class="hide">
				<a href="teamspeak://<?php echo $ts_server['addr'].':'.$ts_server['UDPPort'] ?>/?channel=?password=<?php echo $ts_server['password']; ?>"><?php echo $ts_server['addr'].':'.$ts_server['UDPPort']; ?></a>
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
				<?php echo $ts_server['descr']; ?>
			</td>
		</tr>
<?php
			}
		}
		if (isset($vent_servers))
		{
			require_once(PAGE_PATH . '/ventrilostatus.php');
			foreach($vent_servers as $vent_server)
			{
				$ve_info = new CVentriloStatus;
				$ve_info->m_cmdcode	= 2;					// Detail mode.
				$ve_info->m_cmdhost = $vent_server['addr'];
				$ve_info->m_cmdport = $vent_server['queryPort'];
				/////////
				$rc = $ve_info->Request();
			//	if ($rc) {
			//		echo "CVentriloStatus->Request() failed. <strong>$ve_info->m_error</strong><br /><br />\n";
			//	} else {
					$ve_channels = $ve_info->m_channelcount;
					$ve_slots = $ve_info->m_clientcount.'/'.$ve_info->m_maxclients;
			//	}
		?>  
			<tr>
				<td class="left">
					<span class="hlstats-icon"><img src="<?php echo IMAGE_PATH; ?>/ventrilo/ventrilo.png" alt="venticon" /></span>
					<span class="hlstats-name"><a href="<?php echo $g_options['scripturl'] . "?mode=ventrilo&amp;game=$game&amp;veId=".$vent_server['serverId']; ?>"><?php echo $vent_server['name']; ?></a></span>
				</td>
				<td class="hide">
					<a href="ventrilo://<?php echo $vent_server['addr'].':'.$vent_server['queryPort'] ?>/servername=<?php echo $ve_info->m_name; ?>">
					<?php echo $vent_server['addr'].':'.$vent_server['queryPort']; ?>
					</a></td>
				<td class="hide">
					<?php echo $vent_server['password']; ?>
				</td>
				<td>
					<?php echo $ve_channels; ?>
				</td>
				<td>
					<?php echo $ve_slots; ?>
				</td>
				<td class="hide-2">
					<?php echo $vent_server['descr']; ?>
				</td>
			</tr>
<?php
			}
		}
		if (isset($discord_servers))
		{
			foreach($discord_servers as $dc_server)
			{
				$dc_channels = '-';
				$dc_slots = '-';
				$widget_url = 'https://discord.com/api/guilds/' . urlencode($dc_server['addr']) . '/widget.json';
				$ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
				$widget_json = @file_get_contents($widget_url, false, $ctx);
				if ($widget_json !== false) {
					$widget = json_decode($widget_json, true);
					if (isset($widget['channels'])) {
						$dc_channels = count($widget['channels']);
					}
					if (isset($widget['presence_count'])) {
						$dc_slots = $widget['presence_count'] . ' online';
					}
					if (isset($widget['instant_invite'])) {
						$dc_invite = $widget['instant_invite'];
					}
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
				unset($dc_invite);
			}
		}
?>
    </table>
<?php
	}
	// VOICECOMM MODULE END
?>