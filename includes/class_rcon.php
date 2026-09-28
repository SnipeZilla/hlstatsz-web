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

class Rcon
{
	const SERVERDATA_AUTH           = 3;
	const SERVERDATA_AUTH_RESPONSE  = 2;
	const SERVERDATA_EXECCOMMAND    = 2;
	const SERVERDATA_RESPONSE_VALUE = 0;

	const ID_AUTH = 1;
	const ID_EXEC = 2;
	const ID_END  = 3;

	const IDLE = 0.4;

	const GRACE = 0.1;

	// Seconds allowed for the TCP handshake. The OS resends an unanswered SYN after about 1, 3 and 7 s,
	// so a single dropped packet must not end the attempt.
	const CONNECT_TIMEOUT = 8;

	private $host;
	private $port;
	private $password;
	private $goldsrc;
	private $timeout;
	private $socket = null;
	private $buffer = '';
	private $closed = false;

	public $error = '';

	function __construct($host, $port, $password, $goldsrc = false, $timeout = 3)
	{
		$this->host     = (strpos($host, ':') !== false) ? "[$host]" : $host;
		$this->port     = (int) $port;
		$this->password = (string) $password;
		$this->goldsrc  = (bool) $goldsrc;
		$this->timeout  = (float) $timeout;
	}

	/**
	 * Runs a command and returns the server's reply, or false on failure (reason in $this->error).
	 */
	function execute($command)
	{
		$this->error  = '';
		$this->buffer = '';
		$this->closed = false;

		try {
			$output = $this->goldsrc ? $this->goldsrcExecute($command) : $this->sourceExecute($command);
			return rtrim(ltrim(self::clean($output), "\n"));
		} catch (RuntimeException $e) {
			$this->error = $e->getMessage();
			return false;
		} finally {
			if ($this->socket) {
				@fclose($this->socket);
				$this->socket = null;
			}
		}
	}

	/**
	 * Normalises line endings and strips control characters, keeping tabs and newlines.
	 */
	static function clean($text)
	{
		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);
	}

	private function sourceExecute($command)
	{
		$this->connect('tcp');

		$this->sourceWrite(self::ID_AUTH, self::SERVERDATA_AUTH, $this->password);
		$until = microtime(true) + $this->timeout;
		do {
			$packet = $this->sourceRead($until);
			if ($packet === null) {
				throw new RuntimeException($this->closed
					? 'The server closed the connection during authentication. Too many failed attempts can get the web server\'s IP banned (sv_rcon_banpenalty).'
					: 'No reply to the RCON login (timed out).');
			}
		} while ($packet['type'] !== self::SERVERDATA_AUTH_RESPONSE);

		if ($packet['id'] === -1) {
			throw new RuntimeException('RCON login failed: wrong RCON password.');
		}

		$this->sourceWrite(self::ID_EXEC, self::SERVERDATA_EXECCOMMAND, $command);

		$output = '';
		$ended  = false;
		$until  = microtime(true) + self::IDLE;
		while (true) {
			$packet = $this->sourceRead($until);
			if ($packet === null) {
				if ($ended || $this->closed) {
					break;
				}
				$this->sourceEnd();
				$ended = true;
				$until = microtime(true) + $this->timeout;
				continue;
			}
			if ($packet['id'] === self::ID_END) {
				$until = microtime(true) + self::GRACE;
				continue;
			}
			if ($packet['type'] === self::SERVERDATA_RESPONSE_VALUE) {
				$output .= $packet['body'];
			}
			if (!$ended) {
				$this->sourceEnd();
				$ended = true;
			}
			$until = microtime(true) + self::IDLE;
		}

		return $output;
	}

	private function sourceEnd()
	{
		try {
			$this->sourceWrite(self::ID_END, self::SERVERDATA_RESPONSE_VALUE, '');
		} catch (RuntimeException $e) {
		}
	}

	private function sourceWrite($id, $type, $body)
	{
		$data   = pack('VV', $id, $type) . $body . "\x00\x00";
		$packet = pack('V', strlen($data)) . $data;

		while ($packet !== '') {
			$sent = @fwrite($this->socket, $packet);
			if (!$sent) {
				throw new RuntimeException('The connection was lost while sending the command.');
			}
			$packet = (string) substr($packet, $sent);
		}
	}

	private function sourceRead($until)
	{
		while (true) {
			if (strlen($this->buffer) >= 4) {
				$size = unpack('V', $this->buffer)[1];
				if ($size < 8 || $size > 65536) {
					throw new RuntimeException('Received an invalid RCON packet. Is this a Source engine server (check GameEngine in Server Details)?');
				}
				if (strlen($this->buffer) >= $size + 4) {
					$head = unpack('Vid/Vtype', $this->buffer, 4);
					$body = rtrim(substr($this->buffer, 12, $size - 8), "\0");
					$this->buffer = (string) substr($this->buffer, $size + 4);

					return array(
						'id'   => ($head['id'] >= 0x80000000) ? $head['id'] - 0x100000000 : $head['id'],
						'type' => $head['type'],
						'body' => $body,
					);
				}
			}

			$chunk = $this->receive($until, 8192);
			if ($chunk === null) {
				return null;
			}
			$this->buffer .= $chunk;
		}
	}

	private function goldsrcExecute($command)
	{
		$this->connect('udp');

		$this->goldsrcWrite("challenge rcon\n");
		$reply = $this->receive(microtime(true) + $this->timeout, 4096);
		if ($reply === null) {
			throw new RuntimeException($this->closed
				? "Nothing is listening on {$this->host}:{$this->port} (UDP). Check that the server is running and that the port is correct."
				: 'No reply from the server (timed out). Check that it is running and that the IP address and port are correct.');
		}
		if (!preg_match('/challenge rcon (-?\d+)/', $reply, $match)) {
			throw new RuntimeException('Unexpected reply to the RCON challenge: ' . trim(self::clean(substr($reply, 4))));
		}

		$this->goldsrcWrite('rcon ' . $match[1] . ' "' . $this->password . '" ' . $command . "\n");

		$output = '';
		$splits = array();
		$until  = microtime(true) + $this->timeout;
		while (($packet = $this->receive($until, 65535)) !== null) {
			if (strncmp($packet, "\xFE\xFF\xFF\xFF", 4) === 0 && strlen($packet) > 9) {
				$splits[substr($packet, 4, 4)][ord($packet[8]) >> 4] = substr($packet, 9);
			} else {
				$output .= self::goldsrcPayload($packet);
			}
			$until = microtime(true) + self::IDLE;
		}
		foreach ($splits as $pieces) {
			ksort($pieces);
			$output .= self::goldsrcPayload(implode('', $pieces));
		}

		if (preg_match('/^\s*Bad rcon_password/i', $output)) {
			throw new RuntimeException('RCON login failed: wrong RCON password.');
		}
		if (preg_match('/^\s*Bad challenge/i', $output)) {
			throw new RuntimeException('The server rejected the RCON challenge. Try again.');
		}

		return $output;
	}

	private function goldsrcWrite($data)
	{
		$packet = "\xFF\xFF\xFF\xFF" . $data;
		if (@fwrite($this->socket, $packet) !== strlen($packet)) {
			throw new RuntimeException('Could not send the command to the server.');
		}
	}

	// Strips the 0xFFFFFFFF header and the 'l' (print) type byte
	private static function goldsrcPayload($packet)
	{
		if (strncmp($packet, "\xFF\xFF\xFF\xFF", 4) === 0) {
			$packet = substr($packet, 4);
			if (isset($packet[0]) && $packet[0] === 'l') {
				$packet = substr($packet, 1);
			}
		}
		return $packet;
	}

	private function connect($transport)
	{
		$start = microtime(true);
		$this->socket = @stream_socket_client("$transport://{$this->host}:{$this->port}", $errno, $errstr, self::CONNECT_TIMEOUT);
		if (!$this->socket) {
			if (microtime(true) - $start >= self::CONNECT_TIMEOUT - 0.5) {
				throw new RuntimeException("No answer from {$this->host}:{$this->port} within " . self::CONNECT_TIMEOUT . ' s (TCP). Something is dropping the connection: a firewall or DDoS filter, packet loss, or the host is offline.');
			}
			throw new RuntimeException("Could not connect to {$this->host}:{$this->port}" . ($errstr !== '' ? " ($errstr)" : '') . '.');
		}
		stream_set_timeout($this->socket, (int) ceil($this->timeout));
	}

	/**
	 * Waits until $until (microtime) for data. Returns null on timeout or once the connection is closed.
	 */
	private function receive($until, $length)
	{
		while (($remaining = $until - microtime(true)) > 0) {
			$read   = array($this->socket);
			$write  = null;
			$except = null;
			$ready  = @stream_select($read, $write, $except, (int) $remaining, (int) (fmod($remaining, 1) * 1000000));
			if ($ready === false) {
				throw new RuntimeException('Network error while waiting for the server.');
			}
			if ($ready === 0) {
				return null;
			}

			$data = $this->goldsrc ? @stream_socket_recvfrom($this->socket, $length) : @fread($this->socket, $length);
			if ($data !== false && $data !== '') {
				return $data;
			}
			// UDP: an empty read after select means ICMP port unreachable
			if ($this->goldsrc || feof($this->socket)) {
				$this->closed = true;
				return null;
			}
		}
		return null;
	}
}
