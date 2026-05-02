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

	$dcId = valid_request($_GET['dcId'] ?? '', true);

	$db->query("SELECT name, addr, descr FROM hlstats_Servers_VoiceComm WHERE serverId=" . intval($dcId));
	$s = $db->fetch_array();

	if (!$s) {
		error("Discord server not found", 1);
		return;
	}

	$guild_id = $s['addr'];
	$server_name = $s['name'];

	$widget_url = 'https://discord.com/api/guilds/' . urlencode($guild_id) . '/widget.json';
	$ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
	$widget_json = @file_get_contents($widget_url, false, $ctx);

	if ($widget_json === false) {
		$widget = null;
	} else {
		$widget = json_decode($widget_json, true);
	}

	function show($tpl, $array)
	{
		$template = PAGE_PATH."/templates/discord/$tpl";

		if ($fp = @fopen("$template.html", "r")) {
			$tpl = @fread($fp, filesize("$template.html"));
			fclose($fp);
		} else {
			return '';
		}

		foreach ($array as $value => $code) {
			$tpl = str_replace("[$value]", $code, $tpl);
		}
		return $tpl;
	}

	if ($widget && !isset($widget['code'])) {
		$name     = htmlspecialchars($widget['name'] ?? $server_name);
		$presence = intval($widget['presence_count'] ?? 0);
		$channels = $widget['channels'] ?? [];
		$members  = $widget['members'] ?? [];
		$invite   = $widget['instant_invite'] ?? '';

		// Status dot colours matching Discord's palette
		$status_colors = [
			'online' => '#43b581',
			'idle'   => '#faa61a',
			'dnd'    => '#f04747',
		];

		// Split members: those in a voice channel (have channel_id) vs. just online
		$voice_members  = []; // keyed by channel_id
		$online_members = [];
		foreach ($members as $member) {
			if (isset($member['channel_id'])) {
				$voice_members[$member['channel_id']][] = $member;
			} else {
				$online_members[] = $member;
			}
		}

		// Build voice channels block
		$channel_html = '';
		foreach ($channels as $channel) {
			$ch_name = htmlspecialchars($channel['name']);
			$ch_id   = $channel['id'];

			$ch_members = '';
			foreach ($voice_members[$ch_id] ?? [] as $member) {
				$m_name    = htmlspecialchars($member['username'] ?? 'Unknown');
				$m_status  = $member['status'] ?? 'online';
				$dot_color = $status_colors[$m_status] ?? '#43b581';
				$ch_members .= '<tr><td class="left">'
					. '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . $dot_color . ';margin-right:5px;"></span>'
					. '<span style="font-weight:bold;">' . $m_name . '</span>'
					. '</td></tr>';
			}

			if ($ch_members) {
				$ch_members = '<table>' . $ch_members . '</table>';
			}

			$channel_html .= '<tr><td class="left">'
				. '<span style="color:#FE7200;font-weight:bold;">&#128266; ' . $ch_name . '</span>'
				. $ch_members
				. '</td></tr>';
		}

		if (empty($channel_html)) {
			$channel_html = '<tr><td class="left">No voice channels visible</td></tr>';
		}

		// Build online members block (not in a voice channel)
		$members_html = '';
		foreach ($online_members as $member) {
			$m_name    = htmlspecialchars($member['username'] ?? 'Unknown');
			$m_status  = $member['status'] ?? 'online';
			$dot_color = $status_colors[$m_status] ?? '#43b581';
			$game_html = '';
			if (!empty($member['game']['name'])) {
				$game_html = ' <span style="color:#888;font-size:0.9em;">&mdash; ' . htmlspecialchars($member['game']['name']) . '</span>';
			}
			$members_html .= '<tr><td class="left">'
				. '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . $dot_color . ';margin-right:5px;"></span>'
				. '<span style="font-weight:bold;">' . $m_name . '</span>'
				. $game_html
				. '</td></tr>';
		}

		if (empty($members_html)) {
			$members_html = '<tr><td class="left">No members online</td></tr>';
		}

		$invite_html = '';
		if (!empty($invite)) {
			$invite_html = '<a href="' . htmlspecialchars($invite) . '" target="_blank">' . htmlspecialchars($invite) . '</a>';
		}

		$outp_str = show("discord", array(
			"head"           => "Discord Overview",
			"name"           => $name,
			"presence"       => $presence,
			"channels_count" => count($channels),
			"invite"         => $invite_html,
			"channel_head"   => "Voice Channels",
			"uchannels"      => $channel_html,
			"members_head"   => "Online Members",
			"umembers"       => $members_html,
		));

		echo $outp_str;
	} else {
		error("Could not fetch Discord widget. Make sure the Server Widget is enabled in Discord server settings (Server Settings &gt; Engagement &gt; Enable Server Widget) and that the Guild ID is correct.", 1);

		echo show("discord", array(
			"head"           => "Discord Overview",
			"name"           => htmlspecialchars($server_name),
			"presence"       => '-',
			"channels_count" => '-',
			"invite"         => '-',
			"channel_head"   => "Voice Channels",
			"uchannels"      => '<tr><td class="left">Widget not available</td></tr>',
			"members_head"   => "Online Members",
			"umembers"       => '<tr><td class="left">Widget not available</td></tr>',
		));
	}
?>
