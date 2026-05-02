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

	define('IN_HLSTATS', true);

	// Load database classes
	require ('config.php');
	require (INCLUDE_PATH . '/class_db.php');
	require (INCLUDE_PATH . '/functions.php');
	require (INCLUDE_PATH . '/pChart/pData.class');
	require (INCLUDE_PATH . '/pChart/pChart.class');

	$db_classname = 'DB_' . DB_TYPE;
	if (class_exists($db_classname)) {
		$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
	} else {
		error('Database class does not exist.  Please check your config.php file for DB_TYPE');
	}

	$g_options = getOptions();

	$selectedStyle = (isset($_COOKIE['style']) && $_COOKIE['style']) ? $_COOKIE['style'] : $g_options['style'];

$theme_name = !empty($_GET['theme'])? $_GET['theme'] : strtolower($selectedStyle);
$theme_name = preg_replace('/\.css$/', '', $theme_name);

$theme_file = "./styles/themes/{$theme_name}/{$theme_name}.php";

if ($theme_name == "default" || !file_exists($theme_file)) {
    $theme_name = "hlstatsz";
    $theme_file = "./styles/hlstatsz.php";
}

require $theme_file;

if ($theme['background']['type'] == 'gradient') {
    $bg_color = [
      'red'   => $theme['background']['gradient_top'][0],
      'green' => $theme['background']['gradient_top'][1],
      'blue'  => $theme['background']['gradient_top'][2]
    ];
} else {
    $bg_color = [
      'red'   => $theme['background']['solid_color'][0],
      'green' => $theme['background']['solid_color'][1],
      'blue'  => $theme['background']['solid_color'][2]
    ];
}

$color = [
  'red'   => $theme['font_color'][0],
  'green' => $theme['font_color'][1],
  'blue'  => $theme['font_color'][2]
];

$skillRGB   = $theme['metric_colors']['skill'];
$sessionRGB = $theme['metric_colors']['uptime'];
$grid = $theme['grid_color'];

	$player = valid_request(intval($_GET['player'] ?? ''), true);
	if (!$player) {
		exit();
	}

	$res = $db->query("SELECT UNIX_TIMESTAMP(eventTime) AS ts, skill, skill_change FROM hlstats_Players_History WHERE playerId = '$player' ORDER BY eventTime DESC LIMIT 30");
	$skill = array();
	$skill_change = array();
	$date = array();
	$rowcnt = $db->num_rows();
	$last_time = 0;
	for ($i = 1; $i <= $rowcnt; $i++)
	{
		$row = $db->fetch_array($res);
		array_unshift($skill, ($row['skill']==0)?0:($row['skill']/1000));
		array_unshift($skill_change, $row['skill_change']);
		if ($i == 1 || $i == round($rowcnt/2) || $i == $rowcnt)
		{
			array_unshift($date, date("M-j", $row['ts']));
			$last_time = $row['ts'];
		}
		else
		{
			array_unshift($date, '');
		}
	}
	
	$cache_image = IMAGE_PATH . "/progress/trend_{$theme_name}_{$player}.png";
	if (file_exists($cache_image))
	{
		$file_timestamp = @filemtime($cache_image);
		if ($file_timestamp + IMAGE_UPDATE_INTERVAL > time()) {
			header('Content-type: image/png');
			// header("Cache-Control: public, s-maxage=" . IMAGE_UPDATE_INTERVAL . ", max-age=" . IMAGE_UPDATE_INTERVAL); // Cache it in the browser
			readfile($cache_image);
			exit();
		}
	}
	
$w = $theme['trend']['width'];
$h = $theme['trend']['height'];
$Chart = new pChart($w, $h);

	$Chart->drawBackground($bg_color['red'], $bg_color['green'], $bg_color['blue']);
	
	$Chart->setGraphArea(50, 28, $w-60, $h-50);
	$Chart->drawGraphAreaGradient($grid[0], $grid[1], $grid[2], -50);
	
	if (count($date) < 2)
	{
		$Chart->setFontProperties(IMAGE_PATH . '/sig/font/DejaVuSans.ttf', 12);
		$Chart->drawTextBox(100, $grid[0], $grid[1], $grid[1], "Not Enough Session Data", 0, 0, 0, 0, ALIGN_LEFT, FALSE, 255, 255, 255, 0);
	}
	else
	{	
		$DataSet = new pData;
		$DataSet->AddPoint($skill, 'SerieSkill');
		$DataSet->AddPoint($skill_change, 'SerieSession');
		$DataSet->AddPoint($date, 'SerieDate');
		$DataSet->AddSerie('SerieSkill');
		$DataSet->SetAbsciseLabelSerie('SerieDate');
		$DataSet->SetSerieName('Skill', 'SerieSkill');
		$DataSet->SetSerieName('Session', 'SerieSession');

		$Chart->setFontProperties(IMAGE_PATH . '/sig/font/DejaVuSans.ttf', 9);
		$DataSet->SetYAxisName('Skill');
		$DataSet->SetYAxisUnit('K');
		$Chart->setColorPalette(0, $skillRGB[0], $skillRGB[1], $skillRGB[2]);
		$Chart->drawRightScale($DataSet->GetData(), $DataSet->GetDataDescription(),
			SCALE_NORMAL, $color['red'], $color['green'], $color['blue'], TRUE, 0, 0);
		$Chart->drawGrid(1, FALSE, $grid[0], $grid[1], $grid[2], 100);
		$Chart->setShadowProperties(3, 3, 0, 0, 0, 30, 4);
		$Chart->drawCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription());
		$Chart->clearShadow();
		$Chart->drawFilledCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription(), .1, 30);
		$Chart->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(), 1, 1, 255, 255, 255);
		
		$Chart->clearScale();

		$DataSet->RemoveSerie('SerieSkill');
		$DataSet->AddSerie('SerieSession');
		$DataSet->SetYAxisName('Session');
		$DataSet->SetYAxisUnit('');
		$Chart->setColorPalette(1, $sessionRGB[0], $sessionRGB[1], $sessionRGB[2]);
		$Chart->setColorPalette(2,   0, 0, 255);
		$Chart->drawScale($DataSet->GetData(), $DataSet->GetDataDescription(),
			SCALE_NORMAL, $color['red'], $color['green'], $color['blue'], TRUE, 0, 0);
		$Chart->setShadowProperties(3, 3, 0, 0, 0, 30, 4);
		$Chart->drawCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription());
		$Chart->clearShadow();
		$Chart->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(), 1, 1, 255, 255, 255);
		
		$Chart->setFontProperties(IMAGE_PATH . '/sig/font/DejaVuSans.ttf',9);
		$Chart->drawHorizontalLegend(235, -1, $DataSet->GetDataDescription(),
			0, 0, 0, 0, 0, 0, $color['red'], $color['green'], $color['blue'], FALSE);
	}
	
	$Chart->Render($cache_image);
	// header("Location: $cache_image");
	header('Content-type: image/png');
	// header("Cache-Control: public, s-maxage=" . IMAGE_UPDATE_INTERVAL . ", max-age=" . IMAGE_UPDATE_INTERVAL); // Cache it in the browser
	readfile($cache_image);

?>
