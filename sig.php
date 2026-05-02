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

Originally idea for sig.php by Tankster
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
header("Content-Type: image/png");

// Load database classes
require ('config.php');
require (INCLUDE_PATH . '/class_db.php');
require (INCLUDE_PATH . '/functions.php');

if (defined('DEBUG') && DEBUG === true) {
    ini_set('log_errors', 'On');
    error_reporting(-1);
    ini_set('error_log', '_error.txt');
} else {
    error_reporting(0);
}

$db_classname = 'DB_' . DB_TYPE;
if (class_exists($db_classname))
{
	$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
}
else
{
	error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

$g_options = getOptions();

function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
	$opacity=$pct;
	// getting the watermark width
	$w = imagesx($src_im);
	// getting the watermark height
	$h = imagesy($src_im);
	 
	// creating a cut resource
	$cut = imagecreatetruecolor($src_w, $src_h);
	// copying that section of the background to the cut
	imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
	// inverting the opacity
	$opacity = 100 - $opacity;
	 
	// placing the watermark now
	imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
	imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity);
}

function f_num($number) {
	if (($number >= 10) &&($number < 20))
		return $number.'th';
	else {
		switch ($number % 10) {
			case 1:
				return $number.'st';
				break;
			case 2:
				return $number.'nd';
				break;
			case 3:
				return $number.'rd';
				break;
			default:
				return $number.'th';
				break;
		}
	}
}

	if (!isset($g_options['scripturl']))
		$g_options['scripturl'] = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');

	$player_id = 0;  
	if (isset($_GET['player_id'])) {
		$player_id = valid_request($_GET['player_id'], 1);
		$db->query("
			SELECT
				p.game,
				g.realgame
			FROM
				hlstats_Players p
			LEFT JOIN
				hlstats_Games g ON g.code = p.game
			WHERE
				p.playerId = '$player_id'
			LIMIT 1
		");
		list($game_escaped, $realgame) = $db->fetch_row();



	} elseif (isset($_GET['steam_id']) && isset($_GET['game'])) {
		$steam_id = valid_request($_GET['steam_id'], 0);
		$steam_id = preg_replace('/^STEAM_\d+?\:/i','',$steam_id);
		$game = valid_request($_GET['game'], 0);

		$steam_id_escaped=$db->escape($steam_id);
		$game_escaped=$db->escape($game);
		
		// Obtain realgame from hlstats_Games
		$db->query("
			SELECT
				realgame
			FROM
				hlstats_Games
			WHERE
				code = '$game_escaped'
		");
		$realgame = $db->fetch_row();
		
		// Obtain player_id from the steam_id and game code
		$db->query("
			SELECT
				playerId
			FROM
				hlstats_PlayerUniqueIds
			WHERE
				uniqueId = '{$steam_id_escaped}' AND
				game = '{$game_escaped}'
		");
		
		if ($db->num_rows() != 1)
		error("No such player '$player'.");
		list($player_id) = $db->fetch_row();
	}
	
	$show_flags = $g_options['countrydata'];
	if ((isset($_GET['show_flags'])) && (is_numeric($_GET['show_flags'])))
		$show_flags = valid_request($_GET['show_flags'], 1);



	if (file_exists(IMAGE_PATH.'/progress/sig_'.$player_id.'.png')) {
		$file_timestamp = @filemtime(IMAGE_PATH.'/progress/sig_'.$player_id.'.png');
		if ($file_timestamp + IMAGE_UPDATE_INTERVAL > time()) {
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				$browser_timestamp = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
				if ($browser_timestamp + IMAGE_UPDATE_INTERVAL > time()) {
					header('HTTP/1.0 304 Not Modified');
					exit; 
				}
			}

			$mod_date = date('D, d M Y H:i:s \G\M\T', $file_timestamp);
			header('Last-Modified: ' . $mod_date);
			readfile(IMAGE_PATH . '/progress/sig_' . $player_id . '.png');
			exit;
		}  
	}

	////
	//// Main
	////

if ((isset($_GET['color'])) && (is_string($_GET['color'])))
	$color = hex2rgb(valid_request($_GET['color'], 0));
if ((isset($_GET['caption_color'])) && (is_string($_GET['caption_color'])))
	$caption_color = hex2rgb(valid_request($_GET['caption_color'], 0));
if ((isset($_GET['link_color'])) && (is_string($_GET['link_color'])))
	$link_color = hex2rgb(valid_request($_GET['link_color'], 0));
  
if ($player_id > 0) {
    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
    }
    $db->query("WITH RankedPlayers AS (
                    SELECT
                        RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 DESC) AS rank_position,
                        playerId,
                        last_event,
                        connection_time,
                        game,
                        lastName,
                        flag,
                        country,
                        kills,
                        deaths,
                        skill,
                        shots,
                        hits,
                        headshots,
                        suicides,
                        last_skill_change,
                        activity,
                        IFNULL(ROUND(headshots/kills * 100), '-') AS hpk, 
                        IFNULL(kills/deaths, '-') AS kpd, 
                        IFNULL(ROUND((hits / shots * 100), 1), 0.0) AS acc, 
                        hideranking,
                        COUNT(*) OVER() AS total_rows
                    FROM hlstats_players 
                    WHERE lastAddress <> ''
                          AND hideranking = 0
                          AND game='{$game_escaped}'
                )
                SELECT *
                FROM RankedPlayers
                WHERE playerId='$player_id'
                ORDER BY rank_position DESC
                LIMIT 1
               ");    
    
	if ($db->num_rows() != 1) {
    $db->query(" SELECT
                        playerId,
                        last_event,
                        connection_time,
                        game,
                        lastName,
                        flag,
                        country,
                        kills,
                        deaths,
                        skill,
                        shots,
                        hits,
                        headshots,
                        suicides,
                        last_skill_change,
                        activity,
                        IFNULL(ROUND(headshots/kills * 100), '-') AS hpk, 
                        IFNULL(kills/deaths, '-') AS kpd, 
                        IFNULL(ROUND((hits / shots * 100), 1), 0.0) AS acc, 
                        hideranking
                    FROM hlstats_players 
                    WHERE lastAddress <> ''
                          AND game='{$game_escaped}'
                          AND playerId='$player_id'
                LIMIT 1
               ");            
        
        if ($db->num_rows() != 1) {
            error("No such player '$player_id'.");
        }  else {
            $playerdata = $db->fetch_array();
            $db->free_result();
        
            $db->query("
                SELECT
                    COUNT(*) as count
                FROM
                    hlstats_Players
                WHERE
                    game='{$game_escaped}'
                    AND lastAddress <> ''
                    AND hideranking = 0
                    ");
            $count_row = $db->fetch_array();
            $playerdata['total_rows'] = $count_row['count'];
            $db->free_result();
        }
    } else {
        $playerdata = $db->fetch_array();
        $db->free_result();
    }

	$pl_name = html_entity_decode($playerdata['lastName'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

	$pl_name = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $pl_name);
	$pl_name = trim($pl_name);
	if ($pl_name === '') {
    	$pl_name = $playerdata['lastName']; // raw fallback so at least something shows
	}

    function iso($text)
    { 
      return iconv("UTF-8","ISO-8859-1//IGNORE",$text);
    }

	if(!function_exists('imagefttext')) {
		if (strlen($pl_name) > 30) {
			$pl_shortname =	substr($pl_name, 0, 27) . '...';
		} else {
			$pl_shortname	= $pl_name;
			$pl_name		= htmlspecialchars(iso($pl_name), ENT_COMPAT);
			$pl_shortname	= htmlspecialchars($pl_shortname, ENT_COMPAT);
			$pl_urlname		= urlencode($playerdata['lastName']);
		}
	} else {
		if (mb_strlen($pl_name, 'UTF-8') > 30) {
    		$pl_name = mb_substr($pl_name, 0, 27, 'UTF-8') . '...';
		}
	}

	if ($playerdata['hideranking'] == 1)
		$rank = 'Hidden';
	elseif ($playerdata['hideranking'] == 2)
		$rank = 'Banned';
	else
		$rank = $playerdata['rank_position'];


	if ($playerdata['activity'] == -1)
		$playerdata['activity'] = 0;

	$skill_change = '0';
	if ($playerdata['last_skill_change'] > 0)
		$skill_change = $playerdata['last_skill_change'];
	else if ($playerdata['last_skill_change'] < 0)
		$skill_change = $playerdata['last_skill_change'];  
	
	$background='random';
	if ((isset($_GET['background'])) && ( (($_GET['background'] > 0) && ($_GET['background'] < 12)) || ($_GET['background']=='random')) )
		$background = valid_request($_GET['background'], 0);

	if ($background == 'random')
		$background = rand(1,11);
	
	$hlx_sig_image = getImage('/games/'.$playerdata['game'].'/sig/'.$background);
	if ($hlx_sig_image)
	{
		$hlx_sig = $hlx_sig_image['path'];
	}
	elseif ($hlx_sig_image = getImage('/games/'.$realgame.'/sig/'.$background))
	{
		$hlx_sig = $hlx_sig_image['path'];
	}
	else
	{
		$hlx_sig = IMAGE_PATH."/sig/$background.png";
	}

	switch ($background) {
		case 1:		$caption_color = array('red' => 0, 'green' => 0, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 0, 'blue' => 255);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
		case 2:		$caption_color = array('red' => 147, 'green' => 23, 'blue' => 18);
					$link_color = array('red' => 147, 'green' => 23, 'blue' => 18);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 3:		$caption_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$link_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 4:		$caption_color = array('red' => 255, 'green' => 203, 'blue' => 4);
					$link_color = array('red' => 255, 'green' => 203, 'blue' => 4);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 5:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 102, 'blue' => 204);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 6:		$caption_color = array('red' => 0, 'green' => 0, 'blue' => 0);
					$link_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 7:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 100, 'green' => 100, 'blue' => 100);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
		case 8:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 9:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 0, 'blue' => 0);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
		case 10:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 11:		$caption_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$link_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		default:		$caption_color = array('red' => 0, 'green' => 0, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 155, 'blue' => 0);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
}

	$image			= imagecreatetruecolor(400, 75);

        imagealphablending($image, false);
        imagesavealpha($image, true);

	$white			= imagecolorallocate($image, 255, 255, 255); 
	$bgray			= imagecolorallocate($image, 192, 192, 192); 
	$yellow			= imagecolorallocate($image, 255, 255,   0); 
	$black			= imagecolorallocate($image,   0,   0,   0); 
	$red			= imagecolorallocate($image, 255,   0,   0); 
	$green			= imagecolorallocate($image,   0, 155,   0); 
	$blue			= imagecolorallocate($image,   0,   0, 255); 
	$grey_shade		= imagecolorallocate($image, 204, 204, 204); 
	$font_color		= imagecolorallocate($image, $color['red'], $color['green'], $color['blue']);
	$caption_color	= imagecolorallocate($image, $caption_color['red'], $caption_color['green'], $caption_color['blue']);
	$link_color		= imagecolorallocate($image, $link_color['red'], $link_color['green'], $link_color['blue']);


	$background_img = imagecreatefrompng($hlx_sig);

	if ($background_img) {
		imagecopy($image, $background_img, 0, 0, 0, 0, 400, 75);
		unset($background_img);
	}   

	if ($background == 0)
		imagerectangle($image, 0, 0, 400, 75, $bgray);

	$start_header_name = 9;
	if ($show_flags > 0)  {
		$flag = imagecreatefrompng(getFlag($playerdata['flag'],'path'));
		if ($flag) {
			imagecopy($image, $flag, 8, 4, 0, 0, 18, 12); 
			$start_header_name += 22;
			unset($flag);
		}
	}
        imagealphablending($image, true);
	$timestamp   = ($playerdata['connection_time']);
	$days        = floor($timestamp / 86400);
	$hours       = $days * 24;  
	$hours       += (floor($timestamp / 3600) % 24);
	if ($hours < 10)
		$hours = '0'.$hours; 
	$min         = ( floor($timestamp / 60) % 60); 
	if ($min < 10)
		$min = '0'.$min; 
	$sec         = floor($timestamp % 60);
	if ($sec < 10)
		$sec = '0'.$sec; 
	$con_time = $hours.':'.$min.':'.$sec;

	if ($playerdata['last_skill_change'] == '')
		$playerdata['last_skill_change'] = 0;
	if ($playerdata['last_skill_change'] == 0)
		$trend_image_name = IMAGE_PATH.'/t1.gif';
	elseif ($playerdata['last_skill_change'] > 0)
		$trend_image_name = IMAGE_PATH.'/t0.gif';
	elseif ($playerdata['last_skill_change'] < 0)
		$trend_image_name = IMAGE_PATH.'/t2.gif';
	$trend = imagecreatefromgif($trend_image_name);

	if(function_exists('imagefttext'))
	{
		$font = realpath(IMAGE_PATH.'/sig/font/NotoSans-Regular.ttf');
		if ($font && file_exists($font)) {
			imagefttext($image, 10, 0, 30, 15, $caption_color, $font, $pl_name);
		} else {
			error_log("NotoSans-Regular.ttf not found in hlstatsimg/sig/font". PHP_EOL, 3, '_error.txt');
			imagestring($image, 9, $start_header_name, 2, $pl_name, $caption_color);
		}
	}
	else
	{
		imagestring($image, 9, $start_header_name, 2, $playerdata['lastName'], $caption_color);
	}

	imagestring($image, 2, 15, 22, 'Position ', $font_color);
	if (is_numeric($rank)) {
		imagestring($image, 3, 70, 22, nf($rank), $font_color);
		$start_pos_x = 71 + (imagefontwidth(3) * strlen(nf($rank))) + 7;
	} else {
		imagestring($image, 3, 70, 22, $rank, $font_color);
		$start_pos_x = 71 + (imagefontwidth(3) * strlen($rank)) + 7;
	}
	$ranktext = 'of '.$playerdata['total_rows'].' players with '.$playerdata['skill'].' (';
	imagestring($image, 2, $start_pos_x, 22, $ranktext, $font_color);
	
	$start_pos_x += (imagefontwidth(2) * strlen($ranktext));
	
	if ($trend) {
		imagecopy($image, $trend, $start_pos_x, 26, 0, 0, 7, 7);
		$start_header_name += 22;
		unset($trend);
		$start_pos_x += 10;
	}
	imagestring($image, 2, $start_pos_x, 22, $skill_change.') points', $font_color);
	imagestring($image, 2,  15, 34, 'Kills: '.$playerdata['kills'].', Deaths: '.$playerdata['deaths'].' ('.nf($playerdata['kpd'], 2, '.', '').'), Headshots: '.$playerdata['headshots'].' ('.$playerdata['hpk'].'%)', $font_color);
	imagestring($image, 2,  15, 45, 'Activity: '.$playerdata['activity'].'%, Time: '.$con_time.' hours', $font_color);
	imagestring($image, 2,  15, 56, 'Statistics: ', $font_color);imagestring($image, 2,  85, 56, $g_options['siteurl'], $link_color);

	$watermark = imagecreatefrompng(IMAGE_PATH.'/watermark.png');
	imagecopymerge_alpha($image, $watermark, 364, 54, 0, 0, 32, 16, 0);

	@imagepng($image, IMAGE_PATH.'/progress/sig_'.$player_id.'.png');
	$mod_date = date('D, d M Y H:i:s \G\M\T', time());
	Header('Last-Modified:'.$mod_date);

	imagepng($image);
	unset($image);
	unset($watermark);

}
?>
