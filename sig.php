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
ob_start(); // buffer all output so stray bytes from includes never block headers

// Load database classes
require ('config.php');
require (INCLUDE_PATH . '/class_db.php');
require (INCLUDE_PATH . '/functions.php');

if (defined('DEBUG') && DEBUG === true) {
	ini_set('log_errors', '1');
	ini_set('error_log', __DIR__ . '/_error.txt');
	error_reporting(-1);
	ini_set('display_errors', '0');
}

$db = new DB_mysql(DB_ADDR, DB_USER, DB_PASS, DB_NAME);

$g_options = getOptions();

/*
 * Drawing helpers of the signature card (FreeType text, colors with opacity, rounded corners)
 */

// A color given as 'rrggbb', with an opacity from 0 to 1 (GD's alpha: 0 opaque .. 127 transparent)
function sigColor($im, string $hex, float $opacity = 1.0): int
{
	[$r, $g, $b] = sscanf($hex, '%02x%02x%02x');
	return imagecolorallocatealpha($im, $r, $g, $b, (int) round(127 * (1 - $opacity)));
}

// Width of a text, in pixels
function sigTextWidth(string $font, float $size, string $text): int
{
	$box = imageftbbox($size, 0, $font, $text);
	return $box[2] - $box[0];
}

// A text cut with an ellipsis to fit a width
function sigFit(string $font, float $size, string $text, int $width): string
{
	if (sigTextWidth($font, $size, $text) <= $width) {
		return $text;
	}
	while (mb_strlen($text, 'UTF-8') > 1 && sigTextWidth($font, $size, $text . "\u{2026}") > $width) {
		$text = mb_substr($text, 0, -1, 'UTF-8');
	}
	return rtrim($text) . "\u{2026}";
}

// A text from its left edge and baseline, over a soft shadow; returns its right edge
function sigText($im, string $font, float $size, int $x, int $y, int $color, string $text, bool $shadow = true): int
{
	if ($shadow) {
		imagefttext($im, $size, 0, $x + 1, $y + 1, imagecolorallocatealpha($im, 0, 0, 0, 80), $font, $text);
	}
	$box = imagefttext($im, $size, 0, $x, $y, $color, $font, $text);
	return $box[2];
}

// Anti-aliased rounded corners: the pixels outside them turn transparent
function sigRoundCorners($im, int $radius): void
{
	$w = imagesx($im);
	$h = imagesy($im);
	imagealphablending($im, false);
	for ($y = 0; $y < $radius; $y++) {
		for ($x = 0; $x < $radius; $x++) {
			$cover = max(0, min(1, $radius - hypot($radius - $x - 0.5, $radius - $y - 0.5) + 0.5));
			if ($cover >= 1) {
				continue;
			}
			foreach (array(array($x, $y), array($w - 1 - $x, $y), array($x, $h - 1 - $y), array($w - 1 - $x, $h - 1 - $y)) as [$px, $py]) {
				$c = imagecolorsforindex($im, imagecolorat($im, $px, $py));
				$alpha = 127 - (int) round((127 - $c['alpha']) * $cover);
				imagesetpixel($im, $px, $py, imagecolorallocatealpha($im, $c['red'], $c['green'], $c['blue'], $alpha));
			}
		}
	}
	imagealphablending($im, true);
}

// How far a text moves the pen (its ink box would drop a trailing space)
function sigAdvance(string $font, float $size, string $text): int
{
	return imageftbbox($size, 0, $font, $text . 'H')[2] - imageftbbox($size, 0, $font, 'H')[2];
}

// A text in the first of $fonts that has each of its characters (player names mix scripts and symbols),
// cut with an ellipsis to fit $width; the characters no font has are left out
function sigTextFallback($im, array $fonts, float $size, int $x, int $y, int $color, string $text, int $width): void
{
	$missing = array();   // a font lacking a character draws its .notdef box, as for this private-use one
	foreach ($fonts as $font) {
		$missing[$font] = imageftbbox($size, 0, $font, "\u{E000}");
	}
	$runs = array();      // [font, text], one per change of font
	foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
		$use = null;
		foreach ($fonts as $font) {
			if ($char === ' ' || imageftbbox($size, 0, $font, $char) !== $missing[$font]) {
				$use = $font;
				break;
			}
		}
		if ($use === null) {
			continue;
		}
		if ($runs && $runs[count($runs) - 1][0] === $use) {
			$runs[count($runs) - 1][1] .= $char;
		} else {
			$runs[] = array($use, $char);
		}
	}
	$measure = fn($runs) => array_sum(array_map(fn($run) => sigAdvance($run[0], $size, $run[1]), $runs));
	if ($measure($runs) > $width) {
		$ellipsis = sigAdvance($fonts[0], $size, "\u{2026}");
		while ($runs && $measure($runs) + $ellipsis > $width) {
			$last = count($runs) - 1;
			$runs[$last][1] = mb_substr($runs[$last][1], 0, -1, 'UTF-8');
			if ($runs[$last][1] === '') {
				array_pop($runs);
			}
		}
		$runs[] = array($fonts[0], "\u{2026}");
	}
	foreach ($runs as [$font, $part]) {
		sigText($im, $font, $size, $x, $y, $color, $part);
		$x += sigAdvance($font, $size, $part);
	}
}

// A small triangle pointing up or down (the trend of the points)
function sigTriangle($im, int $x, int $y, bool $up, int $color): void
{
	$points = $up ? array($x, $y + 5, $x + 6, $y + 5, $x + 3, $y) : array($x, $y, $x + 6, $y, $x + 3, $y + 5);
	imagefilledpolygon($im, $points, $color);
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
					ob_end_clean();
					header('HTTP/1.0 304 Not Modified');
					exit;
				}
			}

			$mod_date = date('D, d M Y H:i:s \G\M\T', $file_timestamp);
			ob_end_clean();
			header('Content-Type: image/png');
			header('Last-Modified: ' . $mod_date);
			readfile(IMAGE_PATH . '/progress/sig_' . $player_id . '.png');
			exit;
		}
	}

	////
	//// Main
	////

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
                    FROM hlstats_Players 
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
                    FROM hlstats_Players 
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

	// The card's accent: links pick one with background=1..11 (or random), the numbers of the old background images
	$background = 'random';
	if ((isset($_GET['background'])) && ( (($_GET['background'] > 0) && ($_GET['background'] < 12)) || ($_GET['background'] == 'random')) )
		$background = valid_request($_GET['background'], 0);
	if ($background == 'random')
		$background = rand(1, 11);
	$accents = array(1 => '4aa3df', '9cb36b', 'a4c639', 'e64e4e', '3f8fd8', 'f7a531', 'e0533d', 'ff7a2e', 'cbb89a', 'd9443c', '5fb3b3');
	$accent  = $accents[(int) $background] ?? $accents[1];

	$W = 400;
	$H = 75;
	$image = imagecreatetruecolor($W, $H);
	imagesavealpha($image, true);
	imagealphablending($image, true);

	// Ground: a dark gradient, the colors of the site's default theme
	for ($y = 0; $y < $H; $y++) {
		$t = $y / ($H - 1);
		imageline($image, 0, $y, $W - 1, $y, imagecolorallocate($image, (int) (31 - 13 * $t), (int) (43 - 17 * $t), (int) (58 - 22 * $t)));
	}

	// The game's art (banner.jpg) fading in on the right
	$art = null;
	foreach (array($playerdata['game'], $realgame) as $dir) {
		if ($dir && is_file(IMAGE_PATH . "/games/$dir/banner.jpg")) {
			$art = @imagecreatefromjpeg(IMAGE_PATH . "/games/$dir/banner.jpg");
			break;
		}
	}
	if ($art) {
		$aw    = 250;
		$scale = max($aw / imagesx($art), $H / imagesy($art));
		$sw    = (int) round($aw / $scale);
		$sh    = (int) round($H / $scale);
		$strip = imagecreatetruecolor($aw, $H);
		imagecopyresampled($strip, $art, 0, 0, (int) ((imagesx($art) - $sw) / 2), (int) ((imagesy($art) - $sh) / 2), $aw, $H, $sw, $sh);
		for ($i = 0; $i < $aw; $i++) {
			$t = $i / ($aw - 1);
			$pct = (int) round(50 * $t * $t * (3 - 2 * $t));
			if ($pct > 0) {
				imagecopymerge($image, $strip, $W - $aw + $i, 0, $i, 0, 1, $H, $pct);
			}
		}
		unset($art, $strip);
	}

	$regular = realpath(IMAGE_PATH . '/sig/font/NotoSans-Regular.ttf');
	$bold    = realpath(IMAGE_PATH . '/sig/font/NotoSans-Bold.ttf') ?: $regular;
	$text    = sigColor($image, 'eef3f8');
	$muted   = sigColor($image, 'eef3f8', .62);
	$label   = sigColor($image, 'eef3f8', .58);
	$site    = preg_replace('#^https?://(www\.)?#i', '', rtrim((string) ($g_options['siteurl'] ?? ''), '/'));
	$hours   = floor($playerdata['connection_time'] / 3600);
	$played  = $hours > 0 ? nf($hours) . ' h played' : '';

	if (function_exists('imagefttext') && $regular) {
		// Rank: a panel on the left, its number in the accent color
		imagefilledrectangle($image, 0, 0, 77, $H - 1, sigColor($image, '000000', .28));
		imagefilledrectangle($image, 0, 0, 77, 1, sigColor($image, $accent));
		imageline($image, 78, 0, 78, $H - 1, sigColor($image, 'ffffff', .07));

		$rankText = is_numeric($rank) ? '#' . nf($rank) : $rank;
		$rankSize = 15;
		while ($rankSize > 8 && sigTextWidth($bold, $rankSize, $rankText) > 64) {
			$rankSize -= .5;
		}
		$center = fn($font, $size, $str) => 39 - (int) round(sigTextWidth($font, $size, $str) / 2);
		sigText($image, $bold, 6, $center($bold, 6, 'RANK'), 20, $label, 'RANK', false);
		sigText($image, $bold, $rankSize, $center($bold, $rankSize, $rankText), 45, is_numeric($rank) ? sigColor($image, $accent) : sigColor($image, 'e64e4e'), $rankText);
		if (is_numeric($rank)) {
			$of = 'of ' . nf($playerdata['total_rows']);
			sigText($image, $regular, 6.5, $center($regular, 6.5, $of), 60, $muted, $of, false);
		}

		// Name, after the flag
		$x = 90;
		if ($show_flags > 0 && ($flag = @imagecreatefrompng(getFlag($playerdata['flag'], 'path')))) {
			imagecopyresampled($image, $flag, $x, 11, 0, 0, 18, 12, imagesx($flag), imagesy($flag));
			$x += 24;
			unset($flag);
		}
		$fallback = realpath(IMAGE_PATH . '/sig/font/DejaVuSans.ttf');
		sigTextFallback($image, array_values(array_filter(array($bold, $fallback))), 11, $x, 23, $text, $pl_name, $W - 10 - $x);

		// Statistics: a value over its label, in columns; the last ones go when they do not fit
		$change  = (int) $playerdata['last_skill_change'];
		$columns = array(
			array(nf($playerdata['skill']), 'POINTS', $change),
			array(nf($playerdata['kills']), 'KILLS', 0),
			array(is_numeric($playerdata['kpd']) ? nf($playerdata['kpd'], 2) : '-', 'K/D', 0),
			array(is_numeric($playerdata['hpk']) ? $playerdata['hpk'] . '%' : '-', 'HS', 0),
			array($playerdata['activity'] . '%', 'ACTIVE', 0),
		);
		$widths = array();
		foreach ($columns as $k => [$value, $name, $delta]) {
			$trend = $delta ? 10 + sigTextWidth($bold, 6.5, (string) abs($delta)) : 0;
			$widths[$k] = max(sigTextWidth($bold, 9.5, $value) + $trend, sigTextWidth($bold, 6, $name)) + 17;
		}
		while (count($columns) > 3 && 90 + array_sum($widths) - 17 > $W - 10) {
			array_pop($columns);
			array_pop($widths);
		}
		$x = 90;
		foreach ($columns as $k => [$value, $name, $delta]) {
			$right = sigText($image, $bold, 9.5, $x, 46, $text, $value);
			if ($delta) {
				$color = sigColor($image, $delta > 0 ? '46c26a' : 'e64e4e');
				sigTriangle($image, $right + 4, 39, $delta > 0, $color);
				sigText($image, $bold, 6.5, $right + 12, 46, $color, (string) abs($delta), false);
			}
			sigText($image, $bold, 6, $x, 57, $label, $name, false);
			$x += $widths[$k];
		}

		// Footer: time played and the site, left of the HLstatsZ mark
		$footer = implode("  \u{00B7}  ", array_filter(array($played, $site)));
		sigText($image, $regular, 6.5, 90, 70, $muted, sigFit($regular, 6.5, $footer, 354 - 90), false);
	} else {
		// No FreeType in this PHP: GD's built-in font
		imagestring($image, 3, 10, 6, $pl_name, $text);
		imagestring($image, 2, 10, 24, 'Rank ' . (is_numeric($rank) ? '#' . nf($rank) . ' of ' . nf($playerdata['total_rows']) : $rank) . ' - ' . nf($playerdata['skill']) . ' points', $text);
		imagestring($image, 2, 10, 38, 'Kills ' . nf($playerdata['kills']) . ' - K/D ' . nf($playerdata['kpd'], 2) . ' - HS ' . $playerdata['hpk'] . '%', $text);
		imagestring($image, 2, 10, 52, implode(' - ', array_filter(array($played, $site))), $muted);
	}

	if ($watermark = @imagecreatefrompng(IMAGE_PATH . '/watermark.png')) {
		imagecopy($image, $watermark, $W - 38, $H - 21, 0, 0, imagesx($watermark), imagesy($watermark));
		unset($watermark);
	}

	sigRoundCorners($image, 8);

	@imagepng($image, IMAGE_PATH.'/progress/sig_'.$player_id.'.png');
	$mod_date = date('D, d M Y H:i:s \G\M\T', time());
	ob_end_clean();
	header('Content-Type: image/png');
	header('Last-Modified: ' . $mod_date);
	imagepng($image);
	unset($image);

}
?>
