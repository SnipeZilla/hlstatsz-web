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
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

class TeamSpeak3Query
{
    private $host;
    private $queryPort;
    private $voicePort;
    private $timeout;
    private $socket = null;

    public $error = null;

    public function __construct($host, $queryPort = 10011, $voicePort = 9987, $timeout = 5)
    {
        $this->host      = $host;
        $this->queryPort = (int) $queryPort;
        $this->voicePort = (int) $voicePort;
        $this->timeout   = (int) $timeout;
    }

    public function query($cacheTTL = 0)
    {
        if ($cacheTTL > 0) {
            if (!is_dir('./cache')) mkdir('./cache', 0755, true);
            $cacheKey  = md5($this->host . ':' . $this->queryPort . ':' . $this->voicePort);
            $cacheFile = './cache/hlstatsz_ts3_' . $cacheKey . '.json';
            if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (is_array($data)) return $data;
            }
        }

        $result = [
            'serverinfo' => [],
            'channels'   => [],
            'clients'    => [],
            'error'      => null,
        ];

        try {
            $this->connect();
            $this->command('use port=' . $this->voicePort);

            $serverinfo = $this->command('serverinfo');
            $parsed = $this->parseLine($serverinfo);
            $result['serverinfo'] = $parsed[0] ?? [];

            $result['channels'] = $this->parseLine(
                $this->command('channellist -topic -flags -voice -limits')
            );
            $result['clients'] = $this->parseLine(
                $this->command('clientlist -away -voice -times')
            );
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->error = $e->getMessage();
        }

        $this->disconnect();

        if ($cacheTTL > 0 && $result['error'] === null) {
            @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
        }

        return $result;
    }

    private function connect()
    {
        $errno  = 0;
        $errstr = '';
        $this->socket = @fsockopen($this->host, $this->queryPort, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            throw new Exception("Socket error: {$errstr} [{$errno}]");
        }
        @stream_set_timeout($this->socket, $this->timeout);

        $banner = trim(fgets($this->socket, 64));
        if ($banner !== 'TS3') {
            throw new Exception('Not a Teamspeak 3 ServerQuery port');
        }
        // Welcome line stays in the buffer; the first command's fread() loop absorbs it.
    }

    private function disconnect()
    {
        if ($this->socket) {
            @fputs($this->socket, "quit\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    // fread() returns any available bytes immediately without waiting for a newline,
    // which avoids the per-line blocking that fgets() can cause on some PHP/OS combos.
    private function command($cmd)
    {
        if (!$this->socket) {
            throw new Exception('Not connected');
        }
        fputs($this->socket, "$cmd\n");

        $response = '';
        do {
            $chunk = fread($this->socket, 8192);
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if ($meta['timed_out']) {
                    throw new Exception('Timeout reading TS3 response (cmd: ' . strtok($cmd, ' ') . ')');
                }
                break;
            }
            $response .= $chunk;
        } while (strpos($response, 'error id=') === false);

        if (preg_match('/error id=(\d+) msg=([^\r\n]*)/', $response, $m)) {
            $id = (int) $m[1];
            if ($id !== 0) {
                throw new Exception('TS3: ' . $this->unescape(trim($m[2])) . " (id={$id})");
            }
        }
        $body = preg_replace('/\s*error id=\d+ msg=[^\r\n]*[\r\n]*/', '', $response);
        return trim($body);
    }

    private function parseLine($raw)
    {
        $out = [];
        if ($raw === '' || $raw === null) return $out;
        foreach (explode('|', $raw) as $item) {
            $row = [];
            foreach (explode(' ', $item) as $kv) {
                if ($kv === '') continue;
                $parts = explode('=', $kv, 2);
                $row[$parts[0]] = isset($parts[1]) ? $this->unescape($parts[1]) : '';
            }
            $out[] = $row;
        }
        return $out;
    }

    private function unescape($s)
    {
        $find = ['\\\\', '\\/', '\\s', '\\p', '\\a', '\\b', '\\f', '\\n', '\\r', '\\t', '\\v'];
        $repl = [chr(92), chr(47), chr(32), chr(124), chr(7), chr(8), chr(12), chr(10), chr(13), chr(9), chr(11)];
        return str_replace($find, $repl, $s);
    }
}
?>
