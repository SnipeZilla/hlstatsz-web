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

	if ($auth->userdata['acclevel'] < 80) {
		die ('Access denied!');
	}
?>
<div class="panel">
<?php

function check_writable() {

	$ok = '';
	$f = IMAGE_PATH."/games/";
	if (!is_writable($f)) 
		$ok .= "<li>I have no permission to write to '$f'";
	
	if ($ok != '') {
		echo 'FATAL:<br><UL>';
		echo $ok;
		echo '</UL><br>Correct this before continuing';
		die();
	}
	return true; 
}

function getTableFields($table,$auto_increment) {
   // get a field array of specified table
   global $db;

   $db->query("SHOW COLUMNS FROM $table;");
   $res = array();
   while ($r=$db->fetch_array())
   {  
      if ((!$auto_increment) && ($r['Extra']=='auto_increment'))
      {  
         continue;
      }
      else
      {  
         array_push($res,$r['Field']);
      }
   }
   return $res;
}

function copySettings($table,$game1,$game2) {
	global $db;
	
	$db->query("SELECT game FROM $table WHERE game='$game2' LIMIT 1;");
	if ($db->num_rows()!=0)
		$ret = 'Target gametype exists, nothing done!';
	else {
		$db->query("SELECT count(game) AS cnt FROM $table WHERE game='$game1';");
		$r = $db->fetch_array();
		if ($r['cnt']==0)
			$ret = 'No data existent for source gametype.';
		else {
			$ret = $r['cnt'].' entries copied!';
			$fields = '';
			$ignoreFields = array('game','id','d_winner_id','d_winner_count','g_winner_id','g_winner_count','count','picked','kills','deaths','headshots');
			foreach (getTableFields($table,0) AS $field) {
				if (!in_array($field, $ignoreFields)) {
					if ($fields!='')
						$fields .= ', ';
					$fields .= $field;
				}
			}
			$SQL = "INSERT INTO $table ($fields,game) SELECT $fields,'$game2' FROM $table WHERE game='$game1';";
			$db->query($SQL);
		}
	}  
	return $ret."</li>";
	ob_flush();
	flush();
}

function mkdir_recursive($pathname) {
	is_dir(dirname($pathname)) || mkdir_recursive(dirname($pathname));
	return is_dir($pathname) || @mkdir($pathname);
}

function copyFile($source,$dest) {
	if ($source != '') {
		$source = IMAGE_PATH."/games/$source";
		$dest = IMAGE_PATH."/games/$dest";
		
		if (!is_file($source))
			$ret = "File not found $source (dest: $dest)<br>";
		else {
			mkdir_recursive(dirname($dest));
			if (!copy($source,$dest))
				$ret = 'FAILED';
			else
				$ret = 'OK';
		}
		return "Copying '$source' to '$dest': $ret</li>";
	}
	return '';
}

function scanCopyFiles($source,$dest) {
	global $files;
	$d = dir(IMAGE_PATH.'/games/'.$source);

	if ($d !== false) {
		while (($entry=$d->read()) !== false) {
			if (is_file(IMAGE_PATH.'/games/'.$source.'/'.$entry) && ($entry != '.') && ($entry != '..'))
				$files[] = array($source.'/'.$entry,$dest.'/'.$entry);
			if (is_dir(IMAGE_PATH.'/games/'.$source.'/'.$entry) && ($entry != '.') && ($entry != '..'))
				scanCopyFiles($source.'/'.$entry,$dest.'/'.$entry); 
		}
		$d->close();
	}
}

	if (isset($_POST['confirm'])) {
		$game1 = '';
		if (!empty($_POST['game1']))
				$game1 = $_POST['game1'];

		$game2 = '';
		if (!empty($_POST['game2']))
				$game2 = $_POST['game2'];

		$game2name = '';
		if (!empty($_POST['game2name']))
				$game2name = $_POST['game2name'];

		if ($game1 && $game2 && $game2name) {
			echo '<ul><br />';
			check_writable();
			$game2 = valid_request($game2 ?? '', false);
			$game2name = valid_request($game2name ?? '', false);
			echo '<li>hlstats_Games ...';
			$db->query("SELECT code FROM hlstats_Games WHERE code='$game2' LIMIT 1;");
			if ($db->num_rows()!=0) {
				echo '</ul>';
				message('warning','Target gametype exists, nothing done!');
			} else {
				$db->query("INSERT INTO hlstats_Games (code,name,hidden,realgame) SELECT '$game2', '$game2name', '0', realgame FROM hlstats_Games WHERE code='$game1'");
				echo 'OK</li>';
				
				$dbtables = array();
				array_push($dbtables,
					'hlstats_Actions',
					'hlstats_Awards',
					'hlstats_Ribbons',
					'hlstats_Ranks',
					'hlstats_Roles',
					'hlstats_Teams',
					'hlstats_Weapons'
					);
	
				foreach ($dbtables as $dbt) {
					echo "<li>$dbt ... ";
					echo copySettings($dbt,$game1,$game2);
					ob_flush();
					flush();
				}
	
				echo '</ul><br /><br /><br />';	
				echo '<ul>';
					
				$files = array(
					array(
					'',
					''
					)
				);
	
				scanCopyFiles("$game1/","$game2/");
	
				foreach ($files as $f) {
					echo '<li>';
					echo copyFile($f[0],$f[1]);
				}
				echo '</ul>';
				echo 'Done.<br />';
			}
		} else {
			message('warning',!$game1 ? 'Existing gametype not selected': (!$game2 ? 'Missing \'New gametype code\'': 'Missing \'New gametype name\'' ));
		}
echo '&larr;<a href="?mode=admin&task=tools_settings_copy">Return</a>';
	} else {
		$result = $db->query("SELECT code, name FROM hlstats_Games ORDER BY code;");
		unset($games);
		$games[] = '<option value="" selected="selected">Please select</option>';
		while ($rowdata = $db->fetch_row($result))
		{
			$games[] = "<option value=\"$rowdata[0]\">$rowdata[0] - $rowdata[1]</option>";
		}
message('warning','Are you sure to copy all settings from the selected gametype to the new gametype name?<br>
All existing images will be copied also to the new gametype!');
?>
<div class="hlstats-admin-note">
<p>
Enter the codes and full names for all the games you want to collect statistics for. (Game codes should be the same as the mod folder name, e.g. "valve".)
</p>

</div>
<form method="post">
	<table class="responsive-task">
		<tr>
			<td class="left" data-label="Existing gametype">

<input type="hidden" name="confirm" value="1" />
 Existing gametype:
</td> 
<td class="left">
 <select Name="game1">
 <?php foreach ($games as $g) echo $g; ?>
 </select>
</td>
		</tr>
		<tr>
			<td class="left" data-label="New gametype code">
 New gametype code:
</td> 
<td class="left">
 <input type="text" size="10" placeholder="newcode" name="game2">
</td>
		</tr>
		<tr>
			<td class="left" data-label="New gametype name">	
 New gametype name: 
</td>
<td class="left">
 <input type="text" size="26" placeholder="New Game" name="game2name">
</td>

</table>
<div class="hlstats-admin-apply">
  <input type="submit" value="Copy selected gametype to the new name" class="submit">
</div>
</form>
<?php
	}
?>
<div>