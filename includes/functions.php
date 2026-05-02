<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner
            
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
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
 * @return Array All the options from the options/perlconfig table
 */
function getOptions()
{
	global $db;
	$result = $db->query("SELECT `keyname`,`value` FROM hlstats_Options WHERE opttype >= 1");
	while ($rowdata = $db->fetch_row($result))
	{
		$options[$rowdata[0]] = $rowdata[1];
	}
	if ( !count($options) )
	{
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
 * getSortArrow()
 * 
 * @param mixed $sort
 * @param mixed $sortorder
 * @param mixed $name
 * @param mixed $longname
 * @param string $var_sort
 * @param string $var_sortorder
 * @param string $sorthash
 * @return string Returns the code for a sort arrow <IMG> tag.
 */
function getSortArrow($sort, $sortorder, $name, $longname, $var_sort = 'sort', $var_sortorder =
	'sortorder', $sorthash = '', $ajax = false)
{
	global $g_options;

	if ($sortorder == 'asc')
	{
		$sortimg = 'sort-ascending.gif';
		$othersortorder = 'desc';
	}
	else
	{
		$sortimg = 'sort-descending.gif';
		$othersortorder = 'asc';
	}
	
	$arrowstring = '<a href="' . $g_options['scripturl'] . '?' . makeQueryString($var_sort, $name,
		array($var_sortorder));

	if ($sort == $name)
	{
		$arrowstring .= "&amp;$var_sortorder=$othersortorder";
		$jsarrow = "'" . $var_sortorder . "': '" . $othersortorder . "'";
	}
	else
	{
		$arrowstring .= "&amp;$var_sortorder=$sortorder";
		$jsarrow = "'" . $var_sortorder . "': '" . $sortorder . "'";
	}

	if ($sorthash)
	{
		$arrowstring .= "#$sorthash";
	}

	$arrowstring .= '" class="head"';
	
	if ( $ajax )
	{
		$arrowstring .= " onclick=\"Tabs.refreshTab({'$var_sort': '$name', $jsarrow, forceReload: true}); return false;\"";
	}
	
	$arrowstring .= ' title="Change sorting order">' . "$longname</a>";

	if ($sort == $name)
	{
		$arrowstring .= '&nbsp;<img src="' . IMAGE_PATH . "/$sortimg\"" .
			" style=\"padding-left:4px;padding-right:4px;\" alt=\"$sortimg\" />";
	}


	return $arrowstring;
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
 * @return The 'currentvalue' will be given the SELECTED attribute.
 */
function getSelect($name, $values, $currentvalue = '')
{
	$select = "<select name=\"$name\">\n";

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
		$select .= "\t<option value=\"$currentvalue\" selected=\"selected\">$currentvalue</option>\n";
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
    if (file_exists($path . '.png'))
	{
		$ext = 'png';
	} elseif (file_exists($path . '.gif'))
	{
		$ext = 'gif';
	} elseif (file_exists($path . '.jpg'))
	{
		$ext = 'jpg';
	}
	else
	{
		$ext = '';
	}

	if ($ext)
	{
		$size = getImageSize("$path.$ext");

		return array('url' => "$url.$ext", 'path' => "$path.$ext", 'width' => $size[0], 'height' => $size[1],
			'size' => $size[3]);
	}

    return false;
}

function mystripslashes($text)
{
	// Deprecated, throws an warning in php 7.4 and above
	// return get_magic_quotes_gpc() ? stripslashes($text) : $text;
	return $text;
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
	echo "<h2>{$title}</h2>";
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
                 '<div class="hlstats-page hlstats-page-entries">Showing '.
                 $entries.' to '.$maxEntries.' of '.$items.' entries</div>'.
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
    return '<a href="'.$query.'" onclick="Fetch.run(\'' . $query . '\','.$idJs.');return false;" title="Order '.$col.' '.strtoupper($a).'">';
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

?>
