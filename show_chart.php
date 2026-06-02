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

	foreach ($_SERVER as $key => $entry) {
		if ($key !== 'HTTP_COOKIE') {
			$search_pattern  = array('/<script>/', '/<\/script>/', '/[^A-Za-z0-9.\-\/=:;_?#&~]/');
			$replace_pattern = array('', '', '');
			$entry = preg_replace($search_pattern, $replace_pattern, $entry);
			$_SERVER[$key] = $entry;
		}
	}

	define('IN_HLSTATS', true);
	ob_start();

	require ('config.php');
	require (INCLUDE_PATH . '/class_db.php');
	require (INCLUDE_PATH . '/functions.php');

	if (defined('DEBUG') && DEBUG === true) {
		ini_set('display_errors', '1');
		ini_set('log_errors', '1');
		error_reporting(-1);
		ini_set('error_log', '_error.txt');
	} else {
		error_reporting(0);
	}

	$db_classname = 'DB_' . DB_TYPE;
	if (class_exists($db_classname)) {
		$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
	} else {
		http_response_code(500);
		echo json_encode(['error' => 'db class missing']);
		exit;
	}

	$g_options = getOptions();

	$server_id = 1;
	if (isset($_GET['server_id']) && is_numeric($_GET['server_id'])) {
		$server_id = valid_request($_GET['server_id'], true);
	}

	$player = 0;
	if (isset($_GET['player']) && is_numeric($_GET['player'])) {
		$player = valid_request($_GET['player'], true);
	}

	$game = 'unset';
	if (isset($_GET['game'])) {
		$game = valid_request($_GET['game'], false);
	}
	$game_escaped = $db->escape($game);

	$realgame = 'unset';
	$toMin   = 1;
	if (isset($_GET['realgame'])) {
		$realgame = valid_request($_GET['realgame'], false);
		if ($realgame == 'cs2') $toMin = 60;
	}


	$bar_type = 0;
	if (isset($_GET['type']) && is_numeric($_GET['type'])) {
		$bar_type = valid_request($_GET['type'], true);
	}

	$range = 1;
	if (isset($_GET['range']) && is_numeric($_GET['range'])) {
		$range = valid_request($_GET['range'], true);
	}

	switch ($range) {
		case 2: $avg_step = 7;   $update_interval = 60 * 60 * 6;  $time_window = 7   * 86400; break;
		case 3: $avg_step = 33;  $update_interval = 60 * 60 * 12; $time_window = 31  * 86400; break;
		case 4: $avg_step = 400; $update_interval = 60 * 60 * 24; $time_window = 365 * 86400; break;
		case 1:
		default:
			$avg_step = 1;
			$update_interval = defined('IMAGE_UPDATE_INTERVAL') ? IMAGE_UPDATE_INTERVAL : 300;
			$time_window = 86400; // no time filter
			$range = 1;
			break;
	}

	ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: public, max-age=300');
	header('X-Content-Type-Options: nosniff');

	if ($bar_type == 0) {
		$cacheFile = './cache/hlstatsz_chart_' . md5($server_id . 'type=0' . $time_window) . '.json';
		if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $update_interval) {
			echo file_get_contents($cacheFile);
			exit;
		}

		$limit = '';
		if ($avg_step < 10) {
			$limit = ' LIMIT 0, 2500';
		}
		$time_filter = $time_window > 0 ? ' AND timestamp >= ' . (time() - $time_window) : '';

		$raw = [];
		$result = $db->query(
			"SELECT timestamp, act_players, min_players, max_players, map, uptime, fps
			 FROM hlstats_server_load
			 WHERE server_id=$server_id$time_filter
			 ORDER BY timestamp DESC$limit"
		);

		$buf = [];
		$i = 0;
		$out = [];
		while ($row = $db->fetch_array($result)) {
			$i++;
			$buf[] = $row;
			if ($i == $avg_step) {
				$mid = $buf[(int)ceil($avg_step / 2) - 1];
				$agg = ['t' => (int)$mid['timestamp'], 'act' => 0, 'min' => 0, 'max' => 0, 'uptime' => 0, 'fps' => 0, 'map' => ''];
				foreach ($buf as $e) {
					$agg['act']    += (int)$e['act_players'];
					$agg['min']    += (int)$e['min_players'];
					$agg['max']    += (int)$e['max_players'];
					$agg['uptime'] += (int)$e['uptime'] / $toMin;
					$agg['fps']    += (float)$e['fps'];
					$agg['map']     = $e['map'];
				}
				$agg['act']    = (int)round($agg['act']    / $avg_step);
				$agg['min']    = (int)round($agg['min']    / $avg_step);
				$agg['max']    = (int)round($agg['max']    / $avg_step);
				$agg['uptime'] = (int)round($agg['uptime'] / $avg_step);
				$agg['fps']    = round($agg['fps'] / $avg_step, 1);
				$out[] = $agg;
				$buf = [];
				$i = 0;
			}
		}
		if ($i > 0 && !empty($buf)) {
			$mid = $buf[max(0, (int)ceil($i / 2) - 1)];
			$agg = ['t' => (int)$mid['timestamp'], 'act' => 0, 'min' => 0, 'max' => 0, 'uptime' => 0, 'fps' => 0, 'map' => ''];
			foreach ($buf as $e) {
				$agg['act']    += (int)$e['act_players'];
				$agg['min']    += (int)$e['min_players'];
				$agg['max']    += (int)$e['max_players'];
				$agg['uptime'] += (int)$e['uptime'];
				$agg['fps']    += (float)$e['fps'];
				$agg['map']     = $e['map'];
			}
			$agg['act']    = (int)round($agg['act']    / $i);
			$agg['min']    = (int)round($agg['min']    / $i);
			$agg['max']    = (int)round($agg['max']    / $i);
			$agg['uptime'] = (int)round($agg['uptime'] / $i);
			$agg['fps']    = round($agg['fps'] / $i, 1);
			$out[] = $agg;
		}

		if ($avg_step == 1 && !empty($out)) {
			$lr = $db->query("SELECT act_players, max_players FROM hlstats_Servers WHERE serverId=$server_id");
			if ($lrow = $db->fetch_array($lr)) {
				array_unshift($out, [
					't'      => time(),
					'act'    => (int)$lrow['act_players'],
					'min'    => (int)$out[0]['min'],
					'max'    => (int)$lrow['max_players'],
					'uptime' => (int)$out[0]['uptime'],
					'fps'    => (float)$out[0]['fps'],
					'map'    => '',
				]);
			}
		}

		$out = array_reverse($out);

		// Averages strip
		$avg_24h = null;
		$avg_1h  = null;
		if ($range == 1) {
			$r = $db->query("SELECT AVG(act_players) AS p FROM hlstats_server_load WHERE server_id=$server_id AND timestamp>=" . (time() - 3600));
			$row = $db->fetch_array($r); $avg_1h = $row ? round((float)$row['p'], 1) : null;
			$r = $db->query("SELECT AVG(act_players) AS p FROM hlstats_server_load WHERE server_id=$server_id AND timestamp>=" . (time() - 86400));
			$row = $db->fetch_array($r); $avg_24h = $row ? round((float)$row['p'], 1) : null;
		}

		$json = json_encode([
			'type'      => 0,
			'server_id' => $server_id,
			'range'     => $range,
			'avg_1h'    => $avg_1h,
			'avg_24h'   => $avg_24h,
			'data'      => $out,
		]);
        

		if (!is_dir('./cache')) mkdir('./cache', 0755, true);
		@file_put_contents($cacheFile, $json, LOCK_EX);

		echo $json;
		exit;
	}

	if ($bar_type == 2) {
		// Player history trend 
		if (!$player) {
			http_response_code(400);
			echo json_encode(['error' => 'missing player']);
			exit;
		}
		

		$deletedays = (int)($g_options['MinActivity'] ?? 0);
		if ($deletedays <= 0|| $deletedays  > 90) {
			$deletedays = 28;
		}

		$ts = strtotime(date('Y-m-d'));
		$data = [];
		$arcount = 0;
		$result = $db->query(
			"SELECT eventTime, skill_change, kills, deaths, headshots, connection_time, UNIX_TIMESTAMP(eventTime) AS ts
			 FROM hlstats_Players_History
			 WHERE playerId = $player
			 ORDER BY eventTime DESC
			 LIMIT 0, $deletedays"
		);
		$last_skill = 0;
		while (($row = $db->fetch_array($result)) && $arcount < $deletedays) {
			//while ($row['eventTime'] != date('Y-m-d', $ts) && $arcount < $deletedays) {
			//	$data[] = ['t' => $ts, 'skill' => (int)$row['skill'], 'kills' => 0, 'headshots' => 0, 'deaths' => 0, 'time' => 0];
			//	$ts -= 86400;
			//	$arcount++;
			//}
			$data[] = [
				't'         => (int)$row['ts'],
				'skill'     => (int)$row['skill_change'],
				'kills'     => (int)$row['kills'],
				'headshots' => (int)$row['headshots'],
				'deaths'    => (int)$row['deaths'],
				'time'      =>  round((int)$row['connection_time']/60,1),
			];
			//$last_skill = (int)$row['skill_change'];
			$arcount++;
			$ts -= 86400;
		}
		while ($arcount < $deletedays) {
			$data[] = ['t' => $ts, 'skill' => $last_skill, 'kills' => 0, 'headshots' => 0, 'deaths' => 0, 'time' => 0];
			$ts -= 86400;
			$arcount++;
		}
		$data = array_reverse($data);

		echo json_encode([
			'type'   => 2,
			'player' => $player,
			'days'   => $arcount,
			'data'   => $data,
		]);
		exit;
	}

	if ($bar_type == 1) {
		$cacheFile = './cache/hlstatsz_chart_' . md5($game_escaped . 'type=1' . $time_window) . '.json';
		if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $update_interval) {
			echo file_get_contents($cacheFile);
			exit;
		}
		$out = [];
		$result = $db->query(
			"SELECT timestamp, players, kills, headshots, act_slots, max_slots
			 FROM hlstats_Trend
			 WHERE game='{$game_escaped}'
			 ORDER BY timestamp DESC
			 LIMIT 0, 350"
		);
		while ($row = $db->fetch_array($result)) {
			$out[] = [
				't'         => (int)$row['timestamp'],
				'players'   => (int)$row['players'],
				'kills'     => (int)$row['kills'],
				'headshots' => (int)$row['headshots'],
				'act_slots' => (int)$row['act_slots'],
				'max_slots' => (int)$row['max_slots'],
			];
		}
		$out = array_reverse($out);

		// New-player counts (mirrors show_graph.php bar_type=1 caption)
		$tp_res   = $db->query("SELECT count(playerId) AS pc FROM hlstats_Players WHERE game='{$game_escaped}'");
		$tp_row   = $db->fetch_array($tp_res);
		$total_pl = (int)($tp_row['pc'] ?? 0);

		$r1h  = $db->query("SELECT players FROM hlstats_Trend WHERE game='{$game_escaped}' AND timestamp<=" . (time() - 3600)  . " ORDER BY timestamp DESC LIMIT 1");
		$row1h = $db->fetch_array($r1h);
		$new_1h  = $row1h  ? max(0, $total_pl - (int)$row1h['players'])  : null;

		$r24h = $db->query("SELECT players FROM hlstats_Trend WHERE game='{$game_escaped}' AND timestamp<=" . (time() - 86400) . " ORDER BY timestamp DESC LIMIT 1");
		$row24h = $db->fetch_array($r24h);
		$new_24h = $row24h ? max(0, $total_pl - (int)$row24h['players']) : null;

		$json = json_encode([
			'type'    => 1,
			'game'    => $game,
			'new_24h' => $new_24h,
			'new_1h'  => $new_1h,
			'data'    => $out,
		]);

		if (!is_dir('./cache')) mkdir('./cache', 0755, true);
		@file_put_contents($cacheFile, $json, LOCK_EX);

		echo $json;

		exit;
	}

	http_response_code(400);
	echo json_encode(['error' => 'unsupported type']);
