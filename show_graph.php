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
	  
			if ($key == 'PHP_SELF') {
				if ((strrchr($entry, '/') !== '/hlstats.php') &&
					(strrchr($entry, '/') !== '/show_graph.php') &&
					(strrchr($entry, '/') !== '/sig.php') &&
					(strrchr($entry, '/') !== '/sig2.php') &&
					(strrchr($entry, '/') !== '/index.php') &&
					(strrchr($entry, '/') !== '/status.php') &&
					(strrchr($entry, '/') !== '/top10.php') &&
					(strrchr($entry, '/') !== '/config.php') &&
					(strrchr($entry, '/') !== '/') &&
					($entry !== '')) {
					header('Location: https://'.$_SERVER['HTTP_HOST'].'/hlstats.php');    
					exit;
				}    
			}
			$_SERVER[$key] = $entry;
		}
	}

	define('IN_HLSTATS', true);

	// Load database classes
	require ('config.php');
	require (INCLUDE_PATH . '/class_db.php');
	require (INCLUDE_PATH . '/functions.php');
	require (INCLUDE_PATH . '/functions_graph.php');

	if (defined('DEBUG') && DEBUG === true) {
		ini_set('log_errors', 'On');
		error_reporting(-1);
		ini_set('error_log', '_error.txt');
	} else {
		error_reporting(0);
	}

	$db_classname = 'DB_' . DB_TYPE;
	if (class_exists($db_classname)) {
		$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
	} else {
		error('Database class does not exist.  Please check your config.php file for DB_TYPE');
	}

	$g_options = getOptions();

	$server_id = 1;
	if (isset($_GET['server_id']) && is_numeric($_GET['server_id'])) {
		$server_id = valid_request($_GET['server_id'], true);
	}

	$player = 1;
	if (isset($_GET['player']) && is_numeric($_GET['player'])) {
		$player = valid_request($_GET['player'], true);
	}

	$game = "unset";
	if (isset($_GET['game'])) {
		$game = valid_request($_GET['game'], false);
	}

	$game_escaped = $db->escape($game);

	$bar_type = 0; // 0 == serverinfo last 100 entries
	// 1 == ?!
	// 2 == player trend history
	// 3 == masterserver load

	if (isset($_GET['type']) && is_numeric($_GET['type'])) {
		$bar_type = valid_request($_GET['type'], true);
	}
		
	$selectedStyle = (isset($_COOKIE['style']) && $_COOKIE['style']) ? $_COOKIE['style'] : $g_options['style'];

    $theme_name = preg_replace('/\.css$/', '', $selectedStyle);

$theme_file = "./styles/themes/{$theme_name}/{$theme_name}.php";

if ($theme_name == "default" || !file_exists($theme_file)) {
    $theme_name = "hlstatsz";
    $theme_file = "./styles/{$theme_name}.php";
}

require $theme_file;
$width  = $theme['width'];
$height = $theme['height'];

	$server_load_type = 1;
	if (isset($_GET['range']) && is_numeric($_GET['range'])) {
		$server_load_type = valid_request($_GET['range'], true);
	}

	switch ($server_load_type) {
		case 1:
			$avg_step = 1;
			$update_interval = IMAGE_UPDATE_INTERVAL;
			break;
		case 2:
			$avg_step = 7;
			$update_interval = 60 * 60 * 6; // 6 Hours
			break;
		case 3:
			$avg_step = 33;
			$update_interval = 60 * 60 * 12; // 12 Hours
			break;
		case 4:
			$avg_step = 400;
			$update_interval = 60 * 60 * 24; // 24 Hours
			break;
		default:
			$avg_step = 1;
			$update_interval = IMAGE_UPDATE_INTERVAL;
			break;
	}

	if ($bar_type != 2)
	{
		$cache_image = IMAGE_PATH . '/progress/server_' . $width . '_' . $height . '_' . $bar_type . '_' . $game . '_' . $server_id . '_' . $theme_name . '_' . $server_load_type . '.png';
    if (file_exists($cache_image))
		{
			$file_timestamp = filemtime($cache_image);
			if ($file_timestamp + $update_interval > time())
			{
                if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                    $ims = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                    if ($ims !== false && $ims >= $file_timestamp) {
                        header('HTTP/1.1 304 Not Modified');
                        exit;
                    }
                }
				$mod_date = date('D, d M Y H:i:s \G\M\T', $file_timestamp);
				header('Last-Modified:' . $mod_date);
				$image = imagecreatefrompng(IMAGE_PATH . '/progress/server_' . $width . '_' . $height . '_' . $bar_type . '_' . $game . '_' . $server_id . '_' . $theme_name . '_' . $server_load_type . '.png');
				imagepng($image);
				unset($image);
				exit;
			}
		}
	}

	$legend_x = 0;
	$max_pos_y = array();

	$image = imagecreatetruecolor($width, $height);
	imagealphablending($image, false);

	if (function_exists('imageantialias'))
		imageantialias($image, true);

	// load bgimage if exists...
	$drawbg = true;
$fw = imagefontwidth($theme['font_size']);
$fh = imagefontheight($theme['font_size']);

$font_color = imagecolorallocate($image,
    $theme['font_color'][0],
    $theme['font_color'][1],
    $theme['font_color'][2]
);

$axis_color = imagecolorallocate($image,
    $theme['axis_color'][0],
    $theme['axis_color'][1],
    $theme['axis_color'][2]
);

$grid_color = imagecolorallocate($image,
    $theme['grid_color'][0],
    $theme['grid_color'][1],
    $theme['grid_color'][2]
);

$grid_dash_color = imagecolorallocate($image,
    $theme['grid_dash_color'][0],
    $theme['grid_dash_color'][1],
    $theme['grid_dash_color'][2]
);

$map_label_color = imagecolorallocate($image,
    $theme['map_label_color'][0],
    $theme['map_label_color'][1],
    $theme['map_label_color'][2]
);

$map_block_color = imagecolorallocate($image,
    $theme['map_block_color'][0],
    $theme['map_block_color'][1],
    $theme['map_block_color'][2]
);

$map_sep_color = imagecolorallocate($image,
    $theme['map_sep_color'][0],
    $theme['map_sep_color'][1],
    $theme['map_sep_color'][2]
);


$metric_colors = [];
foreach ($theme['metric_colors'] as $metric => $rgb) {
    $metric_colors[$metric] = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
}

if ($theme['background']['type'] == 'gradient') {
    $top  = $theme['background']['gradient_top'];
    $bot  = $theme['background']['gradient_bottom'];
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = $top[0] * (1 - $ratio) + $bot[0] * $ratio;
        $g = $top[1] * (1 - $ratio) + $bot[1] * $ratio;
        $b = $top[2] * (1 - $ratio) + $bot[2] * $ratio;
        $bg_color = imagecolorallocate($image, round($r), round($g), round($b));
        imageline($image, 0, $y, $width, $y, $bg_color);
    }
} else {
    $bg = $theme['background']['solid_color'];
    $bg_color = imagecolorallocate($image, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);
}

	if ($bar_type == 0)
	{
		$indent_x = array(max(40, $fw * 6), max(40, $fw * 6));
		$indent_y = array(max(15, $fh * 2), max(15, $fh * 2));

		// background
		if ($drawbg)
		{
			imagefilledrectangle($image, 0, 0, $width, $height, $bg_color); // background color
			imagerectangle($image, $indent_x[0], $indent_y[0], $width - $indent_x[1], $height - $indent_y[1], $axis_color);
			imagefilledrectangle($image, $indent_x[0] + 1, $indent_y[0] + 1, $width - $indent_x[1] - 1, $height - $indent_y[1] - 1, $grid_color);
		}

		$limit = '';
		if ($avg_step < 10)
			$limit = ' LIMIT 0, 2500';

		// entries
		$data_array = array();
		$result = $db->query("SELECT timestamp, act_players, min_players, max_players, map, uptime, fps FROM hlstats_server_load WHERE server_id=$server_id ORDER BY timestamp DESC$limit");
		// TSGK
		$last_map = 0;
		// TSGK

		$i = 0;
		$avg_values = array();
		while ($rowdata = $db->fetch_array($result))
		{
			$i++;
            $avg_values[] = array(
                'timestamp'   => $rowdata['timestamp'],
                'act_players' => $rowdata['act_players'],
                'min_players' => $rowdata['min_players'],
                'max_players' => $rowdata['max_players'],
                'uptime'      => $rowdata['uptime'],
                'fps'         => $rowdata['fps'],
                'map'         => $rowdata['map']
            );
        
			if ($i == $avg_step)
			{
				$insert_values = array();
				$insert_values['timestamp'] = $avg_values[ceil($avg_step / 2) - 1]['timestamp'];
				$insert_values['act_players'] = 0;
				$insert_values['min_players'] = 0;
				$insert_values['max_players'] = 0;
				$insert_values['uptime'] = 0;
				$insert_values['fps'] = 0;
				$insert_values['map'] = "";

				foreach ($avg_values as $entry)
				{
					$insert_values['act_players'] += $entry['act_players'];
					$insert_values['min_players'] += $entry['min_players'];
					$insert_values['max_players'] += $entry['max_players'];
					$insert_values['uptime'] += $entry['uptime'];
					$insert_values['fps'] += $entry['fps'];
					$insert_values['map'] = $entry['map'];
				}
				$insert_values['act_players'] = round($insert_values['act_players'] / $avg_step);
				$insert_values['uptime'] = round($insert_values['uptime'] / $avg_step);
				$insert_values['fps'] = round($insert_values['fps'] / $avg_step);
				$insert_values['min_players'] = round($insert_values['min_players'] / $avg_step);
				$insert_values['max_players'] = round($insert_values['max_players'] / $avg_step);

            $data_array[] = array(
                'timestamp'   => $insert_values['timestamp'],
                'act_players' => $insert_values['act_players'],
                'min_players' => $insert_values['min_players'],
                'max_players' => $insert_values['max_players'],
                'uptime'      => $insert_values['uptime'],
                'fps'         => $insert_values['fps'],
                'map'         => $insert_values['map']
            );
				$avg_values = array();
				$i = 0;
			}

		}
		//print_r($data_array);

		$last_map = '';
		if ($avg_step == 1 && !empty($data_array)) 
		{
			$result = $db->query("SELECT act_players, max_players FROM hlstats_Servers WHERE serverId=$server_id");
			$rowdata = $db->fetch_array($result);
			$rowdata['uptime'] = 0;
            array_unshift($data_array, array(
                'timestamp'   => time(),
                'act_players' => $rowdata['act_players'],
                'min_players' => $data_array[0]['min_players'],
                'max_players' => $rowdata['max_players'],
                'uptime'      => $data_array[0]['uptime'],
                'fps'         => $data_array[0]['fps'],
                'map'         => ''
            ));

		}

		if (!empty($data_array) && count($data_array) > 1)
		{
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'max_players', 0, 1, 0, 1, [$metric_colors['max_players'], $metric_colors['max_players'], $font_color, $axis_color, $grid_color, $grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'min_players', 0, 0, 0, 1, [$metric_colors['min_players'], $metric_colors['min_players'], $font_color, $axis_color, $grid_color, $grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'act_players', 0, 0, 1, 1, [$metric_colors['act_players'], $metric_colors['act_players'], $font_color, $axis_color, $grid_color, $grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 2, 'uptime',      0, 0, 1, 1, [$metric_colors['uptime'], $metric_colors['uptime'], $font_color, $axis_color, $grid_color, $grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'fps',         0, 0, 0, 1, [$metric_colors['fps'], $metric_colors['fps'], $font_color, $axis_color, $grid_color,$grid_dash_color]);

        //drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'max_players', 0, 1, 0, 0, [$metric_colors['max_players'], $metric_colors['max_players'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
		}

		if (!empty($data_array) && $width >= 800)
		{
			if ($avg_step == 1)
			{
				$result = $db->query("SELECT avg(act_players) as players FROM hlstats_server_load WHERE server_id=$server_id AND timestamp>=" . (time() - 3600));
				$rowdata = $db->fetch_array($result);
				$players_last_hour = sprintf("%.1f", $rowdata['players']);

				$result = $db->query("SELECT avg(act_players) as players FROM hlstats_server_load WHERE server_id=$server_id AND timestamp>=" . (time() - 86400));
				$rowdata = $db->fetch_array($result);
				$players_last_day = sprintf("%.1f", $rowdata['players']);

				$str = 'Average Players Last 24h: ' . $players_last_day . ' Last 1h: ' . $players_last_hour;
				$str_width = ($fw * strlen($str)) + 2;
				imagestring($image, $theme['font_size'], $width - $indent_x[1] - $str_width, $indent_y[0]-$fh-4, $str, $font_color);
			}
		}

	} elseif ($bar_type == 1)
	{
		$indent_x = array(max(35, $fw * 6), max(35, $fw * 6));
		$indent_y = array(max(15, $fh * 2), max(15, $fh * 2));

		// background
		if ($drawbg)
		{
        imagefilledrectangle($image, 0, 0, $width, $height, $bg_color); // background color
        imagerectangle($image, $indent_x[0], $indent_y[0], $width - $indent_x[1], $height - $indent_y[1], $axis_color);
        imagefilledrectangle($image, $indent_x[0] + 1, $indent_y[0] + 1, $width - $indent_x[1] - 1, $height - $indent_y[1] - 1, $grid_color);
		}

		// entries
        $data_array = array();
        $result = $db->query("SELECT timestamp, players, kills, headshots, act_slots, max_slots FROM hlstats_Trend WHERE game='{$game_escaped}' ORDER BY timestamp DESC LIMIT 0, 350");
		while ($rowdata = $db->fetch_array($result))
		{
        $data_array[] = array(
            'timestamp' => $rowdata['timestamp'],
            'players'   => $rowdata['players'],
            'kills'     => $rowdata['kills'],
            'headshots' => $rowdata['headshots'],
            'act_slots' => $rowdata['act_slots'],
            'max_slots' => $rowdata['max_slots']
        );
		}

		$players_data = $db->query("SELECT count(playerId) as player_count FROM hlstats_Players WHERE game='{$game_escaped}'");
		$rowdata = $db->fetch_array($players_data);
		$total_players = $rowdata['player_count'];

		if (!empty($data_array) && count($data_array) > 1)
		{
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'kills',     0, 0, 0, 0, [$metric_colors['kills'], $metric_colors['kills'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'headshots', 0, 0, 0, 1, [$metric_colors['headshots'], $metric_colors['headshots'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'players',   0, 0, 0, 1, [$metric_colors['players'], $metric_colors['players'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 2, 'max_slots', 0, 0, 0, 1, [$metric_colors['max_slots'], $metric_colors['players'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 2, 'act_slots', 0, 0, 1, 1, [$metric_colors['act_slots'], $metric_colors['players'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'kills',     0, 1, 0, 1, [$metric_colors['kills'], $metric_colors['kills'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
		}

		if (!empty($data_array) && $width >= 800)
		{
			$result = $db->query("SELECT players FROM hlstats_Trend WHERE game='{$game_escaped}' AND timestamp<=" . (time() - 3600) . " ORDER by timestamp DESC LIMIT 0,1");
			$rowdata = $db->fetch_array($result);
			$players_last_hour = $total_players - $rowdata['players'];

			$result = $db->query("SELECT players FROM hlstats_Trend WHERE game='{$game_escaped}' AND timestamp<=" . (time() - 86400) . " ORDER by timestamp DESC LIMIT 0,1");
			$rowdata = $db->fetch_array($result);
			$players_last_day = $total_players - $rowdata['players'];

			$str = 'New Players Last 24h: ' . $players_last_day . ' Last 1h: ' . $players_last_hour;
			$str_width = ($fw * strlen($str)) + 2;
			imagestring($image, $theme['font_size'], $width - $indent_x[1] - $str_width, $indent_y[0]-$fh-4, $str, $font_color);
		}

	} elseif ($bar_type == 2)
	{
		// PLAYER HISTORY GRAPH
		$indent_x = array(max(40, $fw * 5), max(40, $fw * 5));
		$indent_y = array(max(15, $fh * 2), max(15, $fh * 2));

		if (file_exists($iconpath . "/trendgraph.png")) {
			$trendgraph_bg = $iconpath . "/trendgraph.png";
		} else {
			$trendgraph_bg = IMAGE_PATH . "/graph/trendgraph.png";
		}

		$background_img = imagecreatefrompng($trendgraph_bg);
		if ($background_img)
			{
				imagecopy($image, $background_img, 0, 0, 0, 0, 400, 152);
				unset($background_img);
				$drawbg = false;
			}

		// background
		if ($drawbg)
		{
        imagefilledrectangle($image, 0, 0, $width, $height, $bg_color); // background color
        imagerectangle($image, $indent_x[0], $indent_y[0], $width - $indent_x[1], $height - $indent_y[1], $axis_color);
        imagefilledrectangle($image, $indent_x[0] + 1, $indent_y[0] + 1, $width - $indent_x[1] - 1, $height - $indent_y[1] - 1, $grid_color);
		}

		// entries
		$deletedays = $g_options['DeleteDays'];
		if ($deletedays == 0)
			$deletedays = 365;

		// define first day's timestamp range
		$ts = strtotime(date('Y-m-d'));
		$data_array = array();
		$arcount = 0;
		$result = $db->query("SELECT eventTime, skill, kills, deaths, headshots, connection_time, UNIX_TIMESTAMP(eventTime) AS ts FROM hlstats_Players_History WHERE playerId=" . $player . " ORDER BY eventTime DESC LIMIT 0, " . $deletedays);
		while (($rowdata = $db->fetch_array($result)) && ($arcount < $deletedays))
		{
			//echo $rowdata['eventTime']." - ".date("Y-m-d", $ts)."\n";
			while (($rowdata['eventTime'] != date("Y-m-d", $ts)) && ($arcount < $deletedays))
			{
				// insert null value
				$data_array[] = array('timestamp' => $ts, 'skill' => $rowdata['skill'], 'kills' => 0, 'headshots' => 0, 'deaths' => 0, 'time' => 0);
				$ts -= 86400;
				$arcount++;
			}

            $data_array[] = array(
                'timestamp' => $rowdata['ts'],
                'skill'     => $rowdata['skill'],
                'kills'     => $rowdata['kills'],
                'headshots' => $rowdata['headshots'],
                'deaths'    => $rowdata['deaths'],
                'time'      => $rowdata['connection_time']
            );
			$arcount++;
			$ts -= 86400;
		}

		while (($arcount < $deletedays))
		{
			// insert null value
			$data_array[] = array('timestamp' => $ts, 'skill' => $rowdata['skill'], 'kills' => 0, 'headshots' => 0, 'deaths' => 0, 'time' => 0);
			$ts -= 86400;
			$arcount++;
		}

		$deletedays = count($data_array);

		$first_entry = 10; // disable tsgk map function

		if (!empty($data_array) && count($data_array) > 1)
		{
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'kills',     0, 1, 0, 1, [$metric_colors['kills'], $metric_colors['kills'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'headshots', 0, 0, 0, 1, [$metric_colors['headshots'], $metric_colors['headshots'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 0, 'skill',     0, 0, 0, 1, [$metric_colors['skill'], $metric_colors['skill'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
        drawItems($image, array('width' => $width, 'height' => $height, 'indent_x' => $indent_x, 'indent_y' => $indent_y), $data_array, 2, 'deaths',    0, 0, 0, 1, [$metric_colors['deaths'], $metric_colors['deaths'], $font_color, $axis_color, $grid_color,$grid_dash_color]);
		}

		$str = $deletedays . ' days Trend';
		$str_width = ($fw * strlen($str)) + 2;
		imagestring($image, $theme['font_size'], $width - $indent_x[1] - $str_width, $indent_y[0]-$fh-4, $str, $font_color);
	}

	imageTrueColorToPalette($image, 0, 65535);

    header('Content-Type: image/png');
    header('Cache-Control: private, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

	if ($bar_type != 2)
	{
		@imagepng($image, IMAGE_PATH . '/progress/server_' . $width . '_' . $height . '_' . $bar_type . '_' . $game . '_' . $server_id . '_' . $theme_name . '_' . $server_load_type . '.png');
		$mod_date = date('D, d M Y H:i:s \G\M\T', time());
		header('Last-Modified:'.$mod_date);
		imagepng($image);
		unset($image);
	}
	else
	{
		imagepng($image);
		unset($image);
	}
?>
