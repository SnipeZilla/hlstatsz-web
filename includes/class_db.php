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

class DB_mysql
{
	public $db_addr;
	public $db_user;
	public $db_pass;
	public $db_name;

	public $link;
	public $last_result;
	public $last_query;
	public $last_insert_id;
	public $profile = 0;
	public $querycount = 0;
	public $last_calc_rows = 0;

	// The last error of the connection, a database selection or a query: array(MySQL error number, message)
	public $last_error = array(0, '');
	// false: a failed query that would show its error and end the page is recorded in $failures and returns false
	// (the installer runs the database updates so)
	public $exit_on_error = true;
	public $failures = array();

	// $exit false: without a connection or the database, the page goes on ($link false, $db_name null) and
	// $last_error says why (hlstats.php then shows the installer)
	function __construct($db_addr, $db_user, $db_pass, $db_name, $exit = true)
	{
		$this->db_addr = $db_addr;
		$this->db_user = $db_user;
		$this->db_pass = $db_pass;

		$this->querycount = 0;

		// mysqli throws on errors since PHP 8.1
		try {
			$this->link = @mysqli_connect($db_addr, $db_user, $db_pass);
		} catch (\mysqli_sql_exception $e) {
			$this->link = false;
			$this->last_error = array((int) $e->getCode(), $e->getMessage());
		}

		if ( $this->link )
		{
			mysqli_set_charset($this->link, DB_CHARSET);
			$query_str = "SET collation_connection = " . DB_COLLATE;
			mysqli_query($this->link, $query_str);

			if ( $db_name != '' && !$this->select_db($db_name) && $exit )
			{
				$this->error("Could not select database '$db_name'. Check that the value of DB_NAME in config.php is set correctly.");
			}

			return $this->link;
		}
		else
		{
			if (!$this->last_error[0]) {
				$this->last_error = array((int) mysqli_connect_errno(), (string) mysqli_connect_error());
			}
			if ($exit) {
				$this->error('Could not connect to database server. Check that the values of DB_ADDR, DB_USER and DB_PASS in config.php are set correctly.');
			}
		}
	}

	function data_seek($row_number, $query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}
		if ( $query_id )
		{
			return @mysqli_data_seek($query_id, $row_number);
		}
		return false;
	}

	function fetch_array($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id )
		{
			return @mysqli_fetch_array($query_id, MYSQLI_ASSOC);
		}
		return false;
	}

	function fetch_row($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id )
		{
			return @mysqli_fetch_row($query_id);
		}
		return false;
	}

	function fetch_row_set($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id )
		{
			$rowset = array();
			while ( $row = $this->fetch_array($query_id) )
				$rowset[] = $row;

			return $rowset;
		}
		return false;
	}

	function free_result($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id )
		{
			return @mysqli_free_result($query_id);
		}
		return false;
	}

	function insert_id()
	{
		return $this->last_insert_id;
	}

	function num_rows($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id )
		{
			return @mysqli_num_rows($query_id);
		}
		return false;
	}

	function calc_rows()
	{
		return $this->last_calc_rows;
	}

	function query($query, $showerror=true, $calcrows=false)
	{
		$this->last_query = $query;
		$starttime = microtime(true);
		try {
			$this->last_result = @mysqli_query($this->link, $query);
			if ($this->last_result === false) {
				$this->last_error = array((int) mysqli_errno($this->link), (string) mysqli_error($this->link));
			}
		} catch (\mysqli_sql_exception $e) {
			$this->last_result = false;
			$this->last_error = array((int) $e->getCode(), $e->getMessage());
		}
		$endtime = microtime(true);

		$this->last_insert_id = @mysqli_insert_id($this->link);

		if($calcrows == true)
		{
			// SQL_CALC_FOUND_ROWS / FOUND_ROWS() removed in MySQL 9.
			// Count by wrapping the query (minus ORDER BY / LIMIT) in a subquery.
			$count_query = preg_replace('/\s+ORDER\s+BY\s+.+?(?=\s+LIMIT\s|\s*$)/is', '', $query);
			$count_query = preg_replace('/\s+LIMIT\s+.+$/is', '', $count_query);
			$calc_result = @mysqli_query($this->link, "SELECT COUNT(*) AS rowcount FROM ($count_query) AS _cq");
			if($row = mysqli_fetch_assoc($calc_result))
			{
				$this->last_calc_rows = $row['rowcount'];
			}
		}

		$this->querycount++;

		if ( $this->last_result )
		{
			if($this->profile)
			{
				$backtrace = debug_backtrace();
				$profilequery = "insert into hlstats_sql_web_profile (source, run_count, run_time) values ".
					"('".basename($backtrace[0]['file']).':'.$backtrace[0]['line']."',1,'".($endtime-$starttime)."')"
					."ON DUPLICATE KEY UPDATE run_count = run_count+1, run_time=run_time+".($endtime-$starttime);
				@mysqli_query($this->link, $profilequery);
			}
			return $this->last_result;
		}
		else
		{
			if ($showerror && $this->exit_on_error)
			{
				$this->error('Bad query.');
			}
			if ($showerror)
			{
				$this->failures[] = array('query' => $query, 'errno' => $this->last_error[0], 'error' => $this->last_error[1]);
			}
			return false;
		}
	}

	function result($row, $field, $query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id && @mysqli_data_seek($query_id, $row) )
		{
			$data = @mysqli_fetch_assoc($query_id);
			return isset($data[$field]) ? $data[$field] : false;
		}
		return false;
	}

	function select_db($db_name)
	{
		try {
			if (@mysqli_select_db($this->link, $db_name)) {
				$this->db_name = $db_name;
				return true;
			}
			$this->last_error = array((int) mysqli_errno($this->link), (string) mysqli_error($this->link));
		} catch (\mysqli_sql_exception $e) {
			$this->last_error = array((int) $e->getCode(), $e->getMessage());
		}
		return false;
	}

	function escape($string)
	{
		if ( $this->link )
		{
			return @mysqli_real_escape_string($this->link, $string);
		}
	
		return $string;	
	}

	function error($message, $exit=true)
	{
		list($errno, $error) = $this->last_error;
		error(
			"<b>Database Error</b><br />\n<br />\n" .
			"<i>Error Diagnostic:</i><br />\n$message<br /><br />\n" .
			"<i>Server Error:</i> (" . $errno . ") " . htmlspecialchars((string) $error) . "<br /><br />\n" .
			"<i>Last SQL Query:</i><br />\n<pre>" . htmlspecialchars((string) $this->last_query) . "</pre>",
			$exit
		);
	}
}
?>
