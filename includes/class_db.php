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


/* Profile support:

To see SQL run counts and run times, set the $profile variable below to something
that evaluates as true.

Add the following table to your database:
CREATE TABLE IF NOT EXISTS `hlstats_sql_web_profile` (
`queryid` int(11) NOT NULL AUTO_INCREMENT,
`source` tinytext NOT NULL,
`run_count` int(11) NOT NULL,
`run_time` float NOT NULL,
PRIMARY KEY (`queryid`),
UNIQUE KEY `source` (`source`(64))
) ENGINE=MyISAM;
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

	function __construct($db_addr, $db_user, $db_pass, $db_name, $use_pconnect = false)
	{
		$this->db_addr = $db_addr;
		$this->db_user = $db_user;
		$this->db_pass = $db_pass;

		$this->querycount = 0;

		// Persistent connections use the "p:" prefix; regular connections do not.
		$host = $use_pconnect ? "p:$db_addr" : $db_addr;
		$this->link = @mysqli_connect($host, $db_user, $db_pass);

		if ( $this->link )
		{
			mysqli_set_charset($this->link, DB_CHARSET);
			$query_str = "SET collation_connection = " . DB_COLLATE;
			mysqli_query($this->link, $query_str);

			if ( $db_name != '' )
			{
				$this->db_name = $db_name;
				if ( !@mysqli_select_db($this->link, $db_name) )
				{
					@mysqli_close($this->link);
					$this->error("Could not select database '$db_name'. Check that the value of DB_NAME in config.php is set correctly.");
				}
			}

			return $this->link;
		}
		else
		{
			$this->error('Could not connect to database server. Check that the values of DB_ADDR, DB_USER and DB_PASS in config.php are set correctly.');
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
		} catch (\mysqli_sql_exception $e) {
			$this->last_result = false;
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
			if ($showerror)
			{
				$this->error('Bad query.');
			}
			else
			{
				return false;
			}
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
		if (@mysqli_select_db($this->link, $db_name)) {
			$this->db_name = $db_name;
			return true;
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
		error(
			"<b>Database Error</b><br />\n<br />\n" .
			"<i>Server Address:</i> $this->db_addr<br />\n" .
			"<i>Server Username:</i> $this->db_user<br /><br />\n" .
			"<i>Error Diagnostic:</i><br />\n$message<br /><br />\n" .
			"<i>Server Error:</i> (" . @mysqli_errno($this->link) . ") " . @mysqli_error($this->link) . "<br /><br />\n" .
			"<i>Last SQL Query:</i><br />\n<pre>$this->last_query</pre>",
			$exit
		);
	}
}
?>
