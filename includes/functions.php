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

if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
}

/**
Secure Cookie
*/
function myCookie($name, $value, $lifetime)
{
    $path = dirname($_SERVER['SCRIPT_NAME']);
    if ($path === '.' || $path === '\\' || $path === '/') {
        $path = '/';
    } else {
        $path = rtrim($path, '/\\') . '/';
    }

    setcookie($name, $value, [
        'expires'  => $lifetime,
        'path'     => $path,
        'domain'   => "",
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * getOptions()
 * 
 * @param bool $exit false: without options (no table, an empty one), return false instead of an error page
 * @return Array All the options from the options/perlconfig table
 */
function getOptions($exit = true)
{
	global $db;
	$options = array();
	$result  = $db->query("SELECT `keyname`,`value` FROM hlstats_Options WHERE opttype >= 1", $exit);
	while ($result && ($rowdata = $db->fetch_row($result)))
	{
		$options[$rowdata[0]] = $rowdata[1];
	}
	if ( !count($options) )
	{
		if (!$exit) {
			return false;
		}
		error('Warning: Could not find any options in table <b>hlstats_Options</b>, database <b>' .
			DB_NAME . '</b>. Check HLstats configuration.');
	}
	return $options;
}

// Test if flags exists
/**
 * getFlag()
 *
 * @param string $flag
 * @param string $type
 * @return string Either the flag or default flag if none exists
 */
function getFlag($flag, $type='url')
{
	$image = getImage('/flags/'.strtolower($flag ?? ''));
	if ($image)
		return $image[$type];
	else
		return IMAGE_PATH.'/flags/0.png';
}

/**
 * getFlagByIP()
 * Look up 2-letter ISO country code from an IP using GeoLite2-Country.mmdb.
 * Requires the geoip2/geoip2 Composer package (vendor/autoload.php).
 *
 * @param string $ip  IPv4 or IPv6 address
 * @return string     Lowercase ISO country code (e.g. 'us', 'de') or '' on failure
 */
function getFlagByIP(string $ip): string
{
	static $reader = null;
	static $unavailable = false;

	if ($unavailable) return '';

	if ($reader === null) {
		$mmdb = PAGE_PATH . '/sourcebans/GeoLite2-Country.mmdb';
		if (!file_exists($mmdb) || !class_exists('\GeoIp2\Database\Reader')) {
			$unavailable = true;
			echo 'GeoIP2 database or library not available. Country flags by IP will not work.<br>';	
			return '';
		}
		try {
			$reader = new \GeoIp2\Database\Reader($mmdb);
		} catch (\Exception $e) {
			echo 'Initializing GeoIP2 reader...<br>';
			$unavailable = true;
			return '';
		}
	}

	try {
		$record = $reader->country($ip);
		return strtolower($record->country->isoCode ?? '');
	} catch (\Exception $e) {
		return '';
	}
}

/**
 * valid_request()
 * 
 * @param string $str
 * @param boolean $numeric
 * @return mixed request
 */
function valid_request($str, $numeric = false)
{
	$search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/\\\\@{}дцьДЦЬ ]/");
	$replace_pattern = array('');
	if (empty($search_pattern) || count($search_pattern) == 0) return '';
	$str = preg_replace($search_pattern, $replace_pattern, $str);

	if (!$numeric) {
		return htmlspecialchars($str, ENT_QUOTES);
	}

	if (is_numeric($str)) {
		return intval($str);
	}

	return -1;
}

/**
 * TimeStamp()
 * 
 * @param integer $timestamp
 * @return string Formatted Timestamp
 */
function TimeStamp($seconds)
{
    $hours   = floor((int)$seconds / 3600);
    $minutes = floor(((int)$seconds % 3600) / 60);
    $seconds = (int)$seconds % 60;
    return  sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
}

/**Safe number_format() wrapper */
function nf($value, int $decimals = 0, string $decPoint = '.', string $thousandsSep = ','): string
{
   if ($value === null || $value === '' || !is_numeric($value)) {
        return $decimals > 0
            ? number_format(0, $decimals, $decPoint, $thousandsSep)
            : '0';
   }
   return number_format((float)$value, $decimals, $decPoint, $thousandsSep);
}

/**
 * t()
 * Translate a key using the active language file from lang/<code>.json.
 */
function loadLangStrings(): array
{
    static $strings = null;
    if ($strings === null) {
        global $g_options;
        $base = json_decode(file_get_contents(__DIR__ . '/../lang/us.json'), true) ?? [];

        $configured = !empty($g_options['Language']) ? trim($g_options['Language']) : '';
        if ($configured) {
            $langs = array_values(array_filter(
                array_slice(array_map('trim', explode(';', $configured)), 0, 15),
                fn($l) => preg_match('/^[a-z]{2}$/', $l)
            ));
            $cookie = isset($_COOKIE['lang']) ? preg_replace('/[^a-z]/', '', $_COOKIE['lang']) : '';
            $lang = in_array($cookie, $langs) ? $cookie : ($langs[0] ?? 'us');
        } else {
            $lang = 'us';
        }

        if ($lang !== 'us') {
            $file = __DIR__ . '/../lang/' . $lang . '.json';
            $overlay = file_exists($file)
                ? (json_decode(file_get_contents($file), true) ?? [])
                : [];
            $strings = array_merge($base, $overlay);
        } else {
            $strings = $base;
        }
    }
    return $strings;
}

function t(string $key, array $vars = []): string
{
    $str = loadLangStrings()[$key] ?? $key;
    return $vars ? strtr($str, $vars) : $str;
}

/**
 * formatDate()
 * Returns a fully localized date+time string using the active language's locale.
 * Requires the PHP intl extension; falls back to date() if not available.
 *
 * @param int    $timestamp       Unix timestamp
 * @param int    $dateStyle       IntlDateFormatter::FULL|LONG|MEDIUM|SHORT|NONE  (default FULL)
 * @param int    $timeStyle       IntlDateFormatter::FULL|LONG|MEDIUM|SHORT|NONE  (default SHORT)
 * @param string $fallbackPattern date() format string used when intl is unavailable
 * @return string
 */
function formatDate(
    int    $timestamp,
    int    $dateStyle       = IntlDateFormatter::FULL,
    int    $timeStyle       = IntlDateFormatter::SHORT,
    string $fallbackPattern = 'l j F Y H:i'
): string {
    if (!extension_loaded('intl')) {
        return date($fallbackPattern, $timestamp);
    }

    $locale = t('locale');
    $fmt = new IntlDateFormatter($locale, $dateStyle, $timeStyle);
    return $fmt->format($timestamp);
}

/**
 * error()
 * Formats and outputs the given error message. Optionally terminates script
 * processing.
 * 
 * @param mixed $message
 * @param bool $exit
 * @return void
 */
function error($message, $exit = true)
{
    global $g_options;
?>
    <table style="margin-top:15px;">
        <tr>
            <td class="errorhead">ERROR</td>
        </tr>
        <tr>
            <td class="errortext"><?php echo $message; ?></td>
        </tr>
    </table>
<?php
    if ($exit) {
        if (!is_ajax())
            pageFooter();
        exit;
    }
}


//
// string makeQueryString (string key, string value, [array notkeys])
//
// Generates an HTTP GET query string from the current HTTP GET variables,
// plus the given 'key' and 'value' pair. Any current HTTP GET variables
// whose keys appear in the 'notkeys' array, or are the same as 'key', will
// be excluded from the returned query string.
//

/**
 * makeQueryString()
 * 
 * @param mixed $key
 * @param mixed $value
 * @param mixed $notkeys
 * @return
 */
function makeQueryString($key, $value, $notkeys = array())
{
	if (!is_array($notkeys)) {
		$notkeys = array();
	}

	$querystring = '';
	foreach ($_GET as $k => $v) {
		$v = valid_request($v, false);
		if ($k && $k != $key && !in_array($k, $notkeys)) {
			$querystring .= urlencode($k) . '=' . rawurlencode($v) . '&amp;';
		}
	}

	$querystring .= urlencode($key) . '=' . urlencode($value);

	return $querystring;
}

//
// void pageHeader (array title, array location)
//
// Prints the page heading.
//

/**
 * pageHeader()
 * 
 * @param mixed $title
 * @param mixed $location
 * @return
 */
function pageHeader($title = '', $location = '')
{
	global $db, $g_options;
	if ( defined('PAGE') && PAGE == 'HLSTATS' )
		include (PAGE_PATH . '/header.php');
	elseif ( defined('PAGE') && PAGE == 'INGAME' )
		include (PAGE_PATH . '/ingame/header.php');
}


//
// void pageFooter (void)
//
// Prints the page footer.
//

/**
 * pageFooter()
 * 
 * @return
 */
function pageFooter()
{
	global $g_options;
	if ( defined('PAGE') && PAGE == 'HLSTATS' )
		include (PAGE_PATH . '/footer.php');
	elseif ( defined('PAGE') && PAGE == 'INGAME' )
		include (PAGE_PATH . '/ingame/footer.php');
}

/**
 * getSelect()
 * Returns the HTML for a SELECT box, generated using the 'values' array.
 * Each key in the array should be a OPTION VALUE, while each value in the
 * array should be a corresponding descriptive name for the OPTION.
 * 
 * @param mixed $name
 * @param mixed $values
 * @param string $currentvalue
 * @param string $id  the id of the SELECT, for a <label for>
 * @return The 'currentvalue' will be given the SELECTED attribute.
 */
function getSelect($name, $values, $currentvalue = '', $id = '')
{
	$select = "<select name=\"$name\"" . ($id !== '' ? " id=\"$id\"" : '') . ">\n";

	$gotcval = false;

	foreach ($values as $k => $v)
	{
		$select .= "\t<option value=\"$k\"";

		if ($k == $currentvalue)
		{
			$select .= ' selected="selected"';
			$gotcval = true;
		}

		$select .= ">$v</option>\n";
	}

	if ($currentvalue && !$gotcval)
	{
		// a value that is not in the list can come from the URL: escaped
		$current = htmlspecialchars((string) $currentvalue, ENT_QUOTES);
		$select .= "\t<option value=\"$current\" selected=\"selected\">$current</option>\n";
	}

	$select .= '</select>';

	return $select;
}

/**
 * getLink()
 * 
 * @param mixed $url
 * @param integer $maxlength
 * @param string $type
 * @param string $target
 * @return
 */
 
function getLink($url, $type = 'https://', $target = '_blank')
{
    $urld=parse_url($url, PHP_URL_PATH);
    $encoded_path = array_map('urlencode', explode('/', $path));
    $url = str_replace($path, implode('/', $encoded_path), $url);
    if (filter_var($url, FILTER_VALIDATE_URL)) {

       return sprintf('<a href="%s" target="%s">%s</a>',$url, $target, htmlspecialchars($url, ENT_COMPAT));
    }
    return false;
}

/**
 * getEmailLink()
 * 
 * @param string $email
 * @param integer $maxlength
 * @return string Formatted email tag
 */
function getEmailLink($email, $maxlength = 40)
{
	if (preg_match('/(.+)@(.+)/', $email, $regs))
	{
		if (strlen($email) > $maxlength)
		{
			$email_title = substr($email, 0, $maxlength - 3) . '...';
		}
		else
		{
			$email_title = $email;
		}

		$email = str_replace('"', urlencode('"'), $email);
		$email = str_replace('<', urlencode('<'), $email);
		$email = str_replace('>', urlencode('>'), $email);

		return "<a href=\"mailto:$email\">" . htmlspecialchars($email_title, ENT_COMPAT) . '</a>';
	}

	else
	{
		return '';
	}
}

/**
 * getImage()
 * 
 * @param string $filename
 * @return mixed Either the image if exists, or false otherwise
 */
function getImage($filename)
{
	preg_match('/^(.*\/)(.+)$/', $filename, $matches);
	$relpath = $matches[1];
	$realfilename = $matches[2];
	
	$path = IMAGE_PATH . $filename;
	$url = IMAGE_PATH . $relpath . rawurlencode($realfilename);

	// check if image exists
    if (file_exists($path . '.svg'))
	{
		$ext = 'svg';
	} elseif (file_exists($path . '.png'))
	{
		$ext = 'png';
	} elseif (file_exists($path . '.jpg'))
	{
		$ext = 'jpg';
	} elseif (file_exists($path . '.gif'))
	{
		$ext = 'gif';
	}
	else
	{
		$ext = '';
	}

	if ($ext)
	{
		// PHP before 8.5 reads no size from an SVG
		$size = @getImageSize("$path.$ext") ?: array(0, 0, 0, '');

		return array('url' => "$url.$ext", 'path' => "$path.$ext", 'width' => $size[0], 'height' => $size[1],
			'size' => $size[3]);
	}

    return false;
}

function getRealGame($game)
{
	global $db;
	$result = $db->query("SELECT realgame, name from hlstats_Games WHERE code='$game'");
	list($realgame, $realname) = $db->fetch_row($result);
    return [$realgame, $realname];
}

function printSectionTitle($title)
{
	echo "<h1>{$title}</h1>";
}

function getStyleText($style)
{
	return "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"./css/$style.css\" />\n";
}

function getJSText($js)
{
	return "\t<script type=\"text/javascript\" src=\"".INCLUDE_PATH."/js/$js.js\"></script> \n";
}

if (!function_exists('file_get_contents')) {
      function file_get_contents($filename, $incpath = false, $resource_context = null)
      {
          if (false === $fh = fopen($filename, 'rb', $incpath)) {
              trigger_error('file_get_contents() failed to open stream: No such file or directory', E_USER_WARNING);
              return false;
          }
  
          clearstatcache();
          if ($fsize = @filesize($filename)) {
              $data = fread($fh, $fsize);
          } else {
              $data = '';
              while (!feof($fh)) {
                  $data .= fread($fh, 8192);
              }
          }
  
          fclose($fh);
          return $data;
      }
}

/**
 * Convert colors Usage:  color::hex2rgb("FFFFFF")
 * 
 * @author      Tim Johannessen <root@it.dk>
 * @version    1.0.1
 */
function hex2rgb($hexVal = '')
{
	$hexVal = preg_replace('[^a-fA-F0-9]', '', $hexVal);
	if (strlen($hexVal) != 6)
	{
		return 'ERR: Incorrect colorcode, expecting 6 chars (a-f, 0-9)';
	}
	$arrTmp = explode(' ', chunk_split($hexVal, 2, ' '));
	$arrTmp = array_map('hexdec', $arrTmp);
	return array('red' => $arrTmp[0], 'green' => $arrTmp[1], 'blue' => $arrTmp[2]);
}

function updateQueryKey(array $keys): string {

    $queryArray = [];

    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $queryArray);
    }

    if (array_key_exists("type", $queryArray)){
        unset($queryArray["type"]);
    }

    foreach ($keys as $key => $value) {
        if ($value == '' && array_key_exists($key, $queryArray)){
            unset($queryArray[$key]);
        }

        if ($value !== '') {
            $queryArray[$key] = $value;
        }
    }

    return http_build_query($queryArray);

}

function isSteamAdmin(?string $id64): bool
{
	if (!defined('STEAM_ADMIN') || empty(STEAM_ADMIN) || $id64 === null)
	{
		return false;
	}

	return in_array($id64, array_map('strval', (array) STEAM_ADMIN), true);
}

function clearAdminSession(bool $regenerate = true): void
{
	$keys = array('loggedin', 'username', 'password', 'authpasswordhash', 'acclevel', 'authsessionStart');

	foreach ($keys as $key)
	{
		unset($_SESSION[$key]);
	}

	if ($regenerate && session_status() === PHP_SESSION_ACTIVE)
	{
		session_regenerate_id(true);
	}
}

function startAdminSession(array $sessionData): void
{
	clearAdminSession(false);

	if (session_status() === PHP_SESSION_ACTIVE)
	{
		session_regenerate_id(true);
	}

	foreach ($sessionData as $key => $value)
	{
		$_SESSION[$key] = $value;
	}
}

function clearAuthSession(bool $regenerate = true): void
{
	clearAdminSession(false);
	unset($_SESSION['ID64']);

	if ($regenerate && session_status() === PHP_SESSION_ACTIVE)
	{
		session_regenerate_id(true);
	}
}

function Pagination($items, $currentPage, $perpage = 50, $page = 'page', $ajax = true, $id = null, $his = true) {
    // Validate inputs
    $perpage     = max(1, (int)$perpage);
    $totalPages  = max(1, ceil((int)$items / $perpage));
    $currentPage = max(1, min((int)$currentPage, $totalPages));
    $maxLinks    = 3;
    $maxEntries  = min((int)$items, $currentPage*$perpage);
    $entries     = min($maxEntries, (($currentPage-1)*$perpage)+1); 

    $idJs  = $id === null ? 'null' : "'".addslashes($id)."'";
    $hisJs = $his ? 'true' : 'false';

    $updatedQuery = $id ? updateQueryKey([ $page => '', 'ajax' => $id]) : updateQueryKey([ $page => '']);
    $baseUrl = $_SERVER['PHP_SELF'].'?'.$updatedQuery.'&amp;'.$page.'=';

    $html = '<div class="hlstats-pagination">'.
                 '<div class="hlstats-page hlstats-page-entries">'.
                    t('pagination',
                      ["{from}"  => $entries,
                       "{limit}" => $maxEntries,
                       "{total}" => $items]).
                 '</div>'.
                 '<div class="hlstats-page hlstats-pages">';

    // Previous button
    if ($currentPage > 1) {
        
        $html .= '<a href="' . $baseUrl . '1"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . '1\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-nav">«</button></a>';
        $html .= '<a href="' . $baseUrl . ($currentPage - 1) . '"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . ($currentPage - 1) . '\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-nav">‹</button></a>';
    } else {
        $html .= '<button class="hlstats-page-nav disabled">«</button>';
        $html .= '<button class="hlstats-page-nav disabled"><span>‹</span></button>';
    }

    // Calculate start and end page numbers
    $half = floor($maxLinks / 2);
    $start = max(1, $currentPage - $half);
    $end   = min($totalPages, $start + $maxLinks - 1);

    // Adjust start if we're near the end
    if ($end - $start + 1 < $maxLinks) {
        $start = max(1, $end - $maxLinks + 1);
    }

    // Show first page and ellipsis if needed
    if ($start > 1) {
        $html .= '<a href="' . $baseUrl . '1"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . '1\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-button">1</button></a>';
        if ($start > 2) {
            $html .= '<button class="hlstats-page-button disabled"><span>…</span></button>';
        }
    }

    // Page number links
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<button class="hlstats-page-button current">' . $i . '</button>';
        } else {
            $html .= '<a href="' . $baseUrl . $i . '"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . $i .'\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-button">' . $i . '</button></a>';
        }
    }

    // Show last page and ellipsis if needed
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<button class="hlstats-page-button disabled">…</button>';
        }
        $html .= '<a href="' . $baseUrl . $totalPages . '"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . $totalPages .'\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-button">' . $totalPages . '</button></a>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . ($currentPage + 1) . '"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . ($currentPage + 1) .'\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-nav">›</button></a>';
        $html .= '<a href="' . $baseUrl . $totalPages . '"'.($ajax? ' onclick="Fetch.run(\'' . $baseUrl . $totalPages .'\','.$idJs.','.$hisJs.');return false;" ': '' ).'><button class="hlstats-page-nav">»</button></a>';
    } else {
        $html .= '<button class="hlstats-page-nav disabled">›</button>';
        $html .= '<button class="hlstats-page-nav disabled">»</button>';
    }

    $html .= '</div></div>';
    return $html;
}

function isSorted($col, $sort, $sortorder) {
   $a = $col == $sort ? ' active' : '';
   $b = $a ? ( strtolower($sortorder) == 'desc' ? ' desc' : ' asc' ) : ' desc';
   return $a.$b;
}

function headerUrl($col, array $key, $id = null)
{
    global $sort, $sortorder;

    $a = (strtolower($sortorder) == 'asc') ? 'desc' : 'asc';
	$idJs  = $id === null ? 'null' : "'".addslashes($id)."'";
    $query = '?'.updateQueryKey([$key[0] => $col, $key[1] => $a, 'ajax' => $id]);
    return '<a href="'.$query.'" onclick="Fetch.run(\'' . $query . '\','.$idJs.');return false;">';
}

function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function ToSteam2($steamID64) {
    if (!is_numeric($steamID64) || bccomp($steamID64, '76561197960265728') < 0) {
        return $steamID64;
    }

    $accountID = bcsub($steamID64, '76561197960265728');
    $Y = bcmod($accountID, '2');
    $Z = bcdiv(bcsub($accountID, $Y), '2');

    return "STEAM_0:$Y:$Z";
}

function getAddress() {

    $http = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                ? "https://" 
                : "http://";

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    return $http . $host . $requestUri;
}

function active($modes, $current) {
    return in_array($current, (array)$modes) ? ' active' : '';
}

// 0. none
// 1. city, country
// 2. state, country
// 3. city, state, country
// 4. country
function Location($city='', $state='', $country='', $opt=0): string
{
    $location = [];

    if ($city && ($opt == 1 || $opt == 3) ) {
        $location[] = htmlspecialchars($city, ENT_COMPAT);
    }

    if ($state && ($opt == 2 || $opt == 3)) {
        $location[] = htmlspecialchars($state, ENT_COMPAT);
    }

    if ($country && $opt > 0) {
        $location[] = htmlspecialchars($country, ENT_COMPAT);
    }

    return !empty($location) ? implode(', ', $location) : '(Unknown)';
}

/**
 * A translated form label without its colon ("Kills:" -> "Kills", "Localisation :" -> "Localisation"),
 * for headings, tiles and tooltips.
 */
function tLabel(string $key, array $vars = []): string
{
    return preg_replace('/\s*:\s*$/u', '', t($key, $vars));
}

/**
 * Headline tiles: a grid of small cards, each [label, value, note] (the note may be ''); $class adds to the grid.
 */
function kpiTiles(array $tiles, string $class = ''): void
{
    echo '<div class="hlstats-cards-grid hlstats-kpis' . ($class !== '' ? ' ' . $class : '') . '">' . "\n";
    foreach ($tiles as list($label, $value, $note)) {
        echo '    <div class="hlstats-card hlstats-kpi">'
           . '<div class="hlstats-kpi-label">' . $label . '</div>'
           . '<div class="hlstats-kpi-value">' . $value . '</div>'
           . ($note !== '' ? '<div class="hlstats-kpi-note">' . $note . '</div>' : '')
           . "</div>\n";
    }
    echo "</div>\n";
}

/**
 * The Favorites card of a player or a clan: its most played server and map, and its most used weapon.
 * $fav: 'server_id', 'server_name', 'map', 'weapon' (code), 'weapon_name' (each may be empty);
 * $days: the days of events HLstatsZ keeps (DeleteDays), which the favorites come from.
 */
function profileFavorites(string $game, string $realgame, array $fav, int $days): void
{
    $rows = array();
    if (!empty($fav['server_id'])) {
        $rows[] = array('server', t('th.server'), 'hlstats.php?game=' . urlencode($game) . '&amp;mode=servers&amp;server_id=' . (int) $fav['server_id'],
            htmlspecialchars(html_entity_decode($fav['server_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT), '');
    }
    if (!empty($fav['map'])) {
        $rows[] = array('map', t('th.map'), 'hlstats.php?game=' . urlencode($game) . '&amp;mode=mapinfo&amp;map=' . urlencode($fav['map']),
            htmlspecialchars($fav['map'], ENT_COMPAT), '');
    }
    if (!empty($fav['weapon'])) {
        $image  = getImage('/games/' . $game . '/weapons/' . $fav['weapon']);
        if (empty($image)) {
            $image = getImage('/games/' . $realgame . '/weapons/' . $fav['weapon']);
        }
        $rows[] = array('target', t('th.weapon'), 'hlstats.php?mode=weaponinfo&amp;weapon=' . urlencode($fav['weapon']) . '&amp;game=' . urlencode($game),
            htmlspecialchars($fav['weapon_name'] ?: $fav['weapon'], ENT_COMPAT), $image ? $image['url'] : '');
    }
?>
<section class="hlstats-section hlstats-card">
    <div class="hlstats-card-head">
        <div class="hlstats-card-title"><?= t('favorites') ?></div>
<?php if ($days) { ?>
        <span class="hlstats-card-note"><?= t('last.activity.days', ['{activity}' => $days]) ?></span>
<?php } ?>
    </div>
<?php if (!$rows) { ?>
    <p class="hlstats-no-data center"><em><?= t('not.enough.data') ?></em></p>
<?php } else { ?>
    <div class="hlstats-profile-favs">
<?php foreach ($rows as list($icon, $label, $url, $name, $image)) { ?>
        <div class="hlstats-profile-fav">
            <span class="hlstats-profile-fav-icon"><?= svgIcon($icon, 20) ?></span>
            <span class="hlstats-profile-fav-text">
                <span class="hlstats-profile-fav-label"><?= $label ?></span>
                <a class="hlstats-profile-fav-value" href="<?= $url ?>"><?= $name ?></a>
            </span>
<?php if ($image) { ?>
            <img class="hlstats-profile-fav-image" src="<?= $image ?>" alt="" />
<?php } ?>
        </div>
<?php } ?>
    </div>
<?php } ?>
</section>
<?php
}

/**
 * Inline stroke icon drawn in the text color (24 px grid), for buttons and labels.
 */
function svgIcon(string $name, int $size = 16): string
{
    static $paths = [
        'pin'      => '<path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'check'    => '<path d="m5 12 5 5 9-10"/>',
        'users'    => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 11a3 3 0 1 0 0-6M21 20a6 6 0 0 0-4-5.6"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'list'     => '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'medal'    => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
        'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'server'   => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'map'      => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
        'target'   => '<circle cx="12" cy="12" r="8"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
        'pencil'   => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'alert'    => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

?>
