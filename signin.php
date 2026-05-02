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

function steamAuthScheme()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ? 'https://'
        : 'http://';
}

function steamAuthCurrentPath()
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH);

    return $path ?: '/';
}

function steamAuthFilteredQuery()
{
    $queryArray = [];

    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $queryArray);
    }

    foreach (array_keys($queryArray) as $key) {
        if (strpos($key, 'openid_') === 0 || $key === 'signout') {
            unset($queryArray[$key]);
        }
    }

    return $queryArray;
}

function steamAuthReturnToUrl()
{
    $query = http_build_query(steamAuthFilteredQuery());
    $url = steamAuthScheme() . ($_SERVER['HTTP_HOST'] ?? 'localhost') . steamAuthCurrentPath();

    if ($query !== '') {
        $url .= '?' . $query;
    }

    return $url;
}

function steamAuthNormalizeQuery(array $query)
{
    ksort($query);

    return http_build_query($query);
}

function steamAuthIsTrustedReturnTo($url)
{
    if (!is_string($url) || $url === '') {
        return false;
    }

    $returnToParts = parse_url($url);
    $expectedParts = parse_url(steamAuthReturnToUrl());

    if ($returnToParts === false || $expectedParts === false) {
        return false;
    }

    $returnToQuery = [];
    $expectedQuery = [];

    if (!empty($returnToParts['query'])) {
        parse_str($returnToParts['query'], $returnToQuery);
    }

    if (!empty($expectedParts['query'])) {
        parse_str($expectedParts['query'], $expectedQuery);
    }

    $returnToPort = isset($returnToParts['port']) ? (int) $returnToParts['port'] : null;
    $expectedPort = isset($expectedParts['port']) ? (int) $expectedParts['port'] : null;

    return strtolower($returnToParts['scheme'] ?? '') === strtolower($expectedParts['scheme'] ?? '')
        && strtolower($returnToParts['host'] ?? '') === strtolower($expectedParts['host'] ?? '')
        && $returnToPort === $expectedPort
        && ($returnToParts['path'] ?? '/') === ($expectedParts['path'] ?? '/')
        && steamAuthNormalizeQuery($returnToQuery) === steamAuthNormalizeQuery($expectedQuery);
}

function steamAuthSignPayload(array $steam)
{
    $payload = array(
        'ID64' => (string) ($steam['ID64'] ?? ''),
        'avatar' => (string) ($steam['avatar'] ?? ''),
    );

    return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), SECRET_KEY);
}

function steamAuthHasValidSignature(array $steam, $hasSteamAPI)
{
    if (!isset($steam['sig'], $steam['avatar'], $steam['ID64']) || !$hasSteamAPI) {
        return false;
    }

    $expected = steamAuthSignPayload($steam);
    if (hash_equals($expected, $steam['sig'])) {
        return true;
    }

    $legacy = hash_hmac('sha256', $steam['ID64'], SECRET_KEY);

    return hash_equals($legacy, $steam['sig']);
}

function steamAuthDecodeCookie($cookieValue)
{
    if (!is_string($cookieValue) || $cookieValue === '' || (strlen($cookieValue) % 2) !== 0 || !ctype_xdigit($cookieValue)) {
        return null;
    }

    $decoded = hex2bin($cookieValue);
    if ($decoded === false) {
        return null;
    }

    $steam = json_decode($decoded, true);

    return is_array($steam) ? $steam : null;
}

function steamAuthCookieValue(array $steam)
{
    $steam['sig'] = steamAuthSignPayload($steam);

    return bin2hex(json_encode($steam));
}

$hasSteamAPI = defined('STEAM_API') && is_string(STEAM_API) && preg_match('/\A[a-f0-9]{32}\z/i', STEAM_API) === 1;

if ( isset($_GET['openid_assoc_handle']) ) {
    $returnToUrl = steamAuthReturnToUrl();
    $claimedId = $_GET['openid_claimed_id'] ?? '';
    $returnTo = $_GET['openid_return_to'] ?? '';
    $isTrustedReturnTo = steamAuthIsTrustedReturnTo($returnTo);

    if (!$isTrustedReturnTo || empty($_GET['openid_signed']) || empty($_GET['openid_sig'])) {
        $returnTo = $returnToUrl;
    }

    // openid
    $params = [
        'openid.assoc_handle' => $_GET['openid_assoc_handle'],
        'openid.signed'       => $_GET['openid_signed'],
        'openid.sig'          => $_GET['openid_sig'],
        'openid.ns'           => 'http://specs.openid.net/auth/2.0',
    ];

    $signed = explode(',', $_GET['openid_signed']);
    foreach($signed as $item) {
        $key = 'openid_'.str_replace('.', '_', $item);
        if (!isset($_GET[$key])) {
            continue;
        }

        $val = $_GET[$key];
        $params['openid.'.$item] = stripslashes($val);
    }
    $params['openid.mode'] = 'check_authentication';

    $data = http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Accept-language: en\r\n".
            "Content-type: application/x-www-form-urlencoded\r\n".
            'Content-Length: '.strlen($data)."\r\n".
            "Referer: https://steamcommunity.com/\r\n".
            "Origin: https://steamcommunity.com\r\n",
            'content' => $data,
        ],
    ]);

    // Steam Validation:
    $result = file_get_contents('https://steamcommunity.com/openid/login', false, $context);
    $url = $returnTo;

    if ( $isTrustedReturnTo && preg_match("/is_valid:true$/",$result) ) {

        preg_match('#^https://steamcommunity.com/openid/id/([0-9]{17,25})#', $claimedId, $matches);
        $steamID64 = !empty($matches[1]) && is_numeric($matches[1]) ? $matches[1] : 0;
        
        if ($steamID64 > 0) {
            $apiResult = @file_get_contents('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='.STEAM_API.'&steamids='.$steamID64);

            if ($apiResult === false) {
                $responseHeaders = function_exists('http_get_last_response_headers')
                    ? http_get_last_response_headers()
                    : ($http_response_header ?? []);
                foreach ($responseHeaders as $header) {
                    if (preg_match('/^HTTP\/\S+\s+(401|403)/', $header)) {
                        error("STEAM_API &rarr; invalid_key");
                    }
                }
            }

            $response = json_decode($apiResult, true);

            if ( !empty($response['response']['players'][0]['steamid']) ) {

                $steam = array('ID64' => $response['response']['players'][0]['steamid'],
                               'avatar'  => $response['response']['players'][0]['avatarfull'],
                               'sig'     => '');

                $steam['sig'] = steamAuthSignPayload($steam);

                if ( $steam['ID64'] === $steamID64 ) {

                    clearAuthSession(false);
                    session_regenerate_id(true);
                    $_SESSION['ID64']=$steamID64;
                    myCookie('steam', steamAuthCookieValue($steam), time()+(24*3600));
                    header("Location: $url");
                    exit;
                }

            }

        }

    }

} else if (!empty($_GET['signout'])) {
    clearAuthSession();
    myCookie('steam', '', time() - 3600);
    $updatedQuery = updateQueryKey(['signout' => '']);
    $baseUrl = $_SERVER['PHP_SELF'].'?'.$updatedQuery;
    header("Location:$baseUrl");
    exit();

} else if ( isset($_COOKIE['steam']) ) {
    
    $steam = steamAuthDecodeCookie($_COOKIE['steam']);
    if ( !empty($steam) ) {

        if ( isset($steam['sig'], $steam['avatar'], $steam['ID64']) ) {

            if ( steamAuthHasValidSignature($steam, $hasSteamAPI) ) {
            
                $_SESSION['ID64']=$steam['ID64'];
                myCookie('steam', steamAuthCookieValue($steam), time()+(24*3600));
                $admin= STEAM_ADMIN === $steam['ID64'] ? '<a href="?mode=admin">admin</a>' : '';
                $updatedQuery = updateQueryKey(['signout' => 'true']);
                $baseUrl = $_SERVER['PHP_SELF'].'?'.$updatedQuery;
                return $admin.'<a href="'.$baseUrl.'">sign out</a><a href="?mode=search&q='.$steam['ID64'].
                       '&st=uniqueid&game="><img src="'.htmlspecialchars($steam['avatar'], ENT_QUOTES, 'UTF-8').'"/></a>';

            } else {

                unset($_SESSION['ID64']);
                myCookie('steam', '', time() - 3600);

            }

        }

    }
    
}

$settings = [
            'apikey'     => STEAM_API,
            'domainname' => $_SERVER['SERVER_NAME'],
            'loginpage'  => getAddress(),
            ];

$openid = [
    'openid.ns'         => 'http://specs.openid.net/auth/2.0',
    'openid.mode'       => 'checkid_setup',
    'openid.return_to'  => $settings['loginpage'],
    'openid.realm'      => getAddress(),
    'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
];

$steamurl='https://steamcommunity.com/openid/login'.'?'.http_build_query($openid, '', '&');
return $hasSteamAPI ? "<a href=\"$steamurl\">Sign in with Steam</a>" : '';

?>