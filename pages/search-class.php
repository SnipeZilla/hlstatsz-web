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

// Search Class
	class Search
	{
		var $query;
		var $type;
		var $game;
		var $uniqueid_string = 'Steam ID';
		var $uniqueid_string_plural = 'Steam IDs';

		function __construct($query, $type, $game)
		{
			global $g_options;

			$this->query = trim($query);
			$this->type = $type;
			$this->game = $game;

			if ($g_options['Mode'] == 'LAN')
			{
				$this->uniqueid_string = 'IP Address';
				$this->uniqueid_string_plural = 'IP Addresses';
			}
		}

		function drawForm ($getvars = array(), $searchtypes = -1, $style = '')
		{
			global $g_options, $db;

			if (!is_array($searchtypes))
			{
				$searchtypes = array(
					'player' => t('search.in.player'),
					'uniqueid' => t('search.in.steamid')
				);
				if ($g_options['Mode'] != 'LAN' && isset($_SESSION['loggedin']) && $_SESSION['acclevel'] >= 80) {
					$searchtypes['ip'] = t('search.in.ip');
				}
				$searchtypes['clan'] = t('search.in.clan');
			}

			$games = array ();
			$games[''] = t('search.all');
			$result = $db->query("
				SELECT
					hlstats_Games.code,
					hlstats_Games.name
				FROM
					hlstats_Games
				WHERE
					hlstats_Games.hidden = '0'
				ORDER BY
					hlstats_Games.name
			");
			while ($rowdata = $db->fetch_row($result))
			{
				$games[$rowdata[0]] = $rowdata[1];
			}

			if ($style === 'header') {
				?>
				  <div class="hlstats-search-row" style="margin-bottom:10px;">
					<label for="hlzSearchIn"><?= t('search.in') ?></label>
					<?php echo getSelect('st', $searchtypes, $this->type, 'hlzSearchIn'); ?>
				  </div>
			
				  <div class="hlstats-search-row">
					<label for="hlzSearchGame"><?= t('search.game') ?></label>
					<?php echo getSelect('game', $games, $this->game, 'hlzSearchGame'); ?>
				  </div>

				<?php
				return;
			}
		}

		function drawResults ($link_player=-1, $link_clan=-1)
		{
			global $g_options, $db, $mode, $sort, $sortorder;
			if ($link_player == -1) $link_player = "mode=playerinfo&amp;player=%k";
			if ($link_clan == -1) $link_clan = "mode=claninfo&amp;clan=%k";
            $from_admin = (is_ajax() && isset($_GET['task']) && $_GET['task'] == 'tools_editdetails');
            // A result's link ($link_player / $link_clan with %k = its ID), and a name as players.php shows it
            $resultUrl  = fn($link, $key) => $g_options['scripturl'] . '?' . str_replace('%k', urlencode((string) $key), $link);
            $resultName = fn($name) => htmlspecialchars(html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT);

    if (!is_ajax() || (!empty($_GET['ajax']) && $_GET['ajax']=='search')) {
	printSectionTitle(t('title.search.results'));
    }
    if ($this->type == 'uniqueid') {
        $this->query = ToSteam2($this->query);
    }
    
			$sr_query = preg_replace('/^STEAM_\d+?\:/i','',$this->query);
			$sr_query = $db->escape($sr_query);
			$sr_query = preg_replace('/\s/', '%', $sr_query);
			if ($this->type == 'player')
			{
            /********************Name**************************/
				if ($this->game)
					$andgame = "AND hlstats_Players.game='" . $this->game . "'";
				else
					$andgame = '';

    $sort2     = "name";

    $col = array("player_id","name","gamename");
    if (!in_array($sort, $col)) {
        $sort      = "player_id";
        $sortorder = "DESC";
    }

    if ($sort == "name") {
        $sort2 = "player_id";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
  

				$result = $db->query
				("
					SELECT
						hlstats_PlayerNames.playerId AS player_id,
						hlstats_PlayerNames.name,
						hlstats_Players.flag,
						hlstats_Players.country,
						hlstats_Games.name AS gamename,
						hlstats_Games.code
					FROM
						hlstats_PlayerNames
					LEFT JOIN
						hlstats_Players
					ON
						hlstats_Players.playerId = hlstats_PlayerNames.playerId
					LEFT JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_Players.game
					WHERE
						hlstats_Games.hidden = '0'
						AND hlstats_Players.hideranking <> '1'
						AND LOWER(hlstats_PlayerNames.name) LIKE LOWER('%$sr_query%')
						$andgame
					ORDER BY
						$sort $sortorder,
						$sort2 $sortorder
					LIMIT
						30 OFFSET $start
				");
				$resultCount = $db->query
				("
					SELECT
						COUNT(*)
					FROM
						hlstats_PlayerNames
					LEFT JOIN
						hlstats_Players
					ON
						hlstats_Players.playerId = hlstats_PlayerNames.playerId
					LEFT JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_Players.game
					WHERE
						hlstats_Games.hidden = '0'
						AND hlstats_Players.hideranking <> '1'
						AND LOWER(hlstats_PlayerNames.name) LIKE LOWER('%$sr_query%')
						$andgame
				");
                list($numitems) = $db->fetch_row($resultCount);
if (!is_ajax() || (!empty($_GET['ajax']) && $_GET['ajax']=='search') || $from_admin) {
  echo '<div id="searchname">';
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-numeric left<?= isSorted('player_id',$sort,$sortorder) ?>"><?= headerUrl('player_id', ['sort','sortorder'], 'searchname') ?>ID</a></th>
        <th class="left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['sort','sortorder'], 'searchname').t('player') ?></a></th>
        <th class="left<?= isSorted('gamename',$sort,$sortorder) ?>"><?= headerUrl('gamename', ['sort','sortorder'], 'searchname').t('game') ?></a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap left">'.$res['player_id'].'</td>
                  <td class="left">';
                  if ($g_options['countrydata']) {
                    echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                  }
                  echo '<a href="'.$resultUrl($link_player, $res['player_id']).'&amp;game='.urlencode((string) $res['code']).'"><span class="hlstats-name">'.$resultName($res['name']).'&nbsp;</span></a>
                   </td>
                  <td class="left">'.$res['gamename'].'</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'searchname', $mode !== 'admin');
      if (is_ajax() && !empty($_GET['ajax']) && $_GET['ajax'] == 'searchname') exit;


  ?>
</div>
<?php
            /********************End Name**************************/
			}
			elseif ($this->type == 'uniqueid')
			{
            /********************Unique ID**************************/
				if ($this->game)
					$andgame = "AND hlstats_PlayerUniqueIds.game='" . $this->game . "'";
				else
					$andgame = '';
                
                
    $sort2     = "lastName";

    $col = array("uniqueId","lastName","gamename","playerId");
    if (!in_array($sort, $col)) {
        $sort      = "uniqueId";
        $sortorder = "DESC";
    }

    if ($sort == "lastName") {
        $sort2 = "uniqueId";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
  
				$result = $db->query
				("
					SELECT
						hlstats_PlayerUniqueIds.uniqueId,
						hlstats_PlayerUniqueIds.playerId,
						hlstats_Players.lastName,
						hlstats_Players.flag,
						hlstats_Players.country,
						hlstats_Games.name AS gamename
					FROM
						hlstats_PlayerUniqueIds
					LEFT JOIN
						hlstats_Players
					ON
						hlstats_Players.playerId = hlstats_PlayerUniqueIds.playerId
					LEFT JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_PlayerUniqueIds.game
					WHERE
						hlstats_Players.hideranking <> '1' AND
						hlstats_Games.hidden = '0' AND
						hlstats_PlayerUniqueIds.uniqueId LIKE '%$sr_query%'
						$andgame
					ORDER BY
						$sort $sortorder,
						$sort2 $sortorder
					LIMIT
						30 OFFSET $start
				");
				$resultCount = $db->query
				("
					SELECT
						COUNT(*)
					FROM
						hlstats_PlayerUniqueIds
					LEFT JOIN
						hlstats_Players
					ON
						hlstats_Players.playerId = hlstats_PlayerUniqueIds.playerId
					LEFT JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_PlayerUniqueIds.game
					WHERE
						hlstats_Players.hideranking <> '1' AND
						hlstats_Games.hidden = '0' AND
						hlstats_PlayerUniqueIds.uniqueId LIKE '%$sr_query%'
						$andgame
				");
				list($numitems) = $db->fetch_row($resultCount);

                
if (!is_ajax() || (!empty($_GET['ajax']) && $_GET['ajax']=='search') || $from_admin) {
  echo '<div id="searchsteam">';
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="left<?= isSorted('uniqueId',$sort,$sortorder) ?>"><?= headerUrl('uniqueId', ['sort','sortorder'], 'searchsteam') ?>SteamID</a></th>
        <th class="hlstats-main-description left<?= isSorted('lastName',$sort,$sortorder) ?>"><?= headerUrl('lastName', ['sort','sortorder'], 'searchsteam').t('player') ?></a></th>
        <th class="left<?= isSorted('gamename',$sort,$sortorder) ?>"><?= headerUrl('gamename', ['sort','sortorder'], 'searchsteam').t('game') ?></a></th>
        <th class="hlstats-numeric left<?= isSorted('playerId',$sort,$sortorder) ?>"><?= headerUrl('playerId', ['sort','sortorder'], 'searchsteam') ?>ID</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap left">'.$res['uniqueId'].'</td>
                  <td class="left">';
                  if ($g_options['countrydata']) {
                    echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                  }
                  echo '<a href="'.$resultUrl($link_player, $res['playerId']).'"><span class="hlstats-name">'.$resultName($res['lastName']).'&nbsp;</span></a>
                   </td>
                  <td class="left">'.$res['gamename'].'</td>
                  <td class="nowrap left">'.$res['playerId'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'searchsteam', $mode !== 'admin');
if (is_ajax() && !empty($_GET['ajax']) && $_GET['ajax'] == 'searchsteam') exit;

  ?>
</div>
<?php

			}
            /********************End Unique ID**************************/
			elseif ($this->type == 'ip')
			{
            /********************IP**************************/
				if (!isset($_SESSION['loggedin']) || $_SESSION['acclevel'] < 80) {
					die ("Access denied!");
				}


				if ($this->game)
					$andgame = "AND hlstats_Players.game='" . $this->game . "'";
				else
					$andgame = '';
                
                
   $sort2     = "name";

    $col = array("player_id","name","gamename");
    if (!in_array($sort, $col)) {
        $sort      = "player_id";
        $sortorder = "DESC";
    }

    if ($sort == "name") {
        $sort2 = "player_id";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
   
				$result = $db->query
				("
					SELECT
						connects.playerId AS player_id,
						hlstats_Players.lastname AS name,
						hlstats_Players.flag,
						hlstats_Players.country,
						hlstats_Games.name AS gamename
					FROM
						(
							SELECT
								playerId,
								ipAddress
							FROM
								`hlstats_Events_Connects`
							GROUP BY
								playerId,
								ipAddress
						) AS connects
					LEFT JOIN
						hlstats_Players
					ON
						hlstats_Players.playerId = connects.playerId
					LEFT JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_Players.game
					WHERE
						hlstats_Games.hidden = '0'
						AND hlstats_Players.hideranking <> '1'
						AND connects.ipAddress LIKE '$sr_query%'
						$andgame
					ORDER BY
						$sort $sortorder,
						$sort2 $sortorder
					LIMIT
						30 OFFSET $start
				");
				$resultCount = $db->query
				("
					SELECT
						COUNT(*)
					FROM
						(
							SELECT
								playerId,
								ipAddress
							FROM
								`hlstats_Events_Connects`
							GROUP BY
								playerId,
								ipAddress
						) AS connects
					LEFT JOIN
						hlstats_Players
					ON
						hlstats_Players.playerId = connects.playerId
					LEFT JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_Players.game
					WHERE
						hlstats_Games.hidden = '0'
						AND hlstats_Players.hideranking <> '1'
						AND connects.ipAddress LIKE '$sr_query%'
						$andgame
				");
				list($numitems) = $db->fetch_row($resultCount);

                
if (!is_ajax() || (!empty($_GET['ajax']) && $_GET['ajax']=='search') || $from_admin ) {
  echo '<div id="searchip">';
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-numeric left<?= isSorted('player_id',$sort,$sortorder) ?>"><?= headerUrl('player_id', ['sort','sortorder'], 'searchip') ?>ID</a></th>
        <th class="left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['sort','sortorder'], 'searchip').t('player') ?></a></th>
        <th class="left<?= isSorted('gamename',$sort,$sortorder) ?>"><?= headerUrl('gamename', ['sort','sortorder'], 'searchip').t('game')?></a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap left">'.$res['player_id'].'</td>
                  <td class="left">';
                  if ($g_options['countrydata']) {
                   echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                  }
                  echo '<a href="'.$resultUrl($link_player, $res['player_id']).'"><span class="hlstats-name">'.$resultName($res['name']).'&nbsp;</span></a>
                   </td>
                  <td class="left">'.$res['gamename'].'</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'searchip', $mode !== 'admin');
   if (is_ajax() && !empty($_GET['ajax']) && $_GET['ajax'] == 'searchip') exit;
  ?>
</div>
<?php
			}
            /********************End IP**************************/
			elseif ($this->type == 'clan')
			{
				if ($this->game)
					$andgame = "AND hlstats_Clans.game='" . $this->game . "'";
				else
					$andgame = "";
                
    $sort2     = "name";

    $col = array("tag","name","gamename","clanId");
    if (!in_array($sort, $col)) {
        $sort      = "clanId";
        $sortorder = "DESC";
    }

    if ($sort == "name") {
        $sort2 = "clanId";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
    
				$result = $db->query
				("
					SELECT
						hlstats_Clans.clanId,
						hlstats_Clans.tag,
						hlstats_Clans.name,
						hlstats_Games.name AS gamename
					FROM
						hlstats_Clans
					LEFT JOIN hlstats_Games ON
						hlstats_Games.code = hlstats_Clans.game
					WHERE
						hlstats_Games.hidden = '0'
						AND (
							LOWER(hlstats_Clans.tag) LIKE LOWER('%$sr_query%')
							OR LOWER(hlstats_Clans.name) LIKE LOWER('%$sr_query%')
						)
						$andgame
					ORDER BY
						$sort $sortorder,
						$sort2 $sortorder
					LIMIT
						30 OFFSET $start
				");
				$resultCount = $db->query
				("
					SELECT
						COUNT(*)
					FROM
						hlstats_Clans
					LEFT JOIN hlstats_Games ON
						hlstats_Games.code = hlstats_Clans.game
					WHERE
						hlstats_Games.hidden = '0'
						AND (
							LOWER(hlstats_Clans.tag) LIKE LOWER('%$sr_query%')
							OR LOWER(hlstats_Clans.name) LIKE LOWER('%$sr_query%')
						)
						$andgame
				");
list($numitems) = $db->fetch_row($resultCount);
                
if (!is_ajax() || (!empty($_GET['ajax']) && $_GET['ajax']=='search') || $from_admin) {
  echo '<div id="searchclan">';
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="left<?= isSorted('tag',$sort,$sortorder) ?>"><?= headerUrl('tag', ['sort','sortorder'], 'searchclan').t('th.tag') ?></a></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['sort','sortorder'], 'searchclan').t('name') ?></a></th>
        <th class="hleft<?= isSorted('gamename',$sort,$sortorder) ?>"><?= headerUrl('gamename', ['sort','sortorder'], 'searchclan').t('game') ?></a></th>
        <th class="hlstats-numeric left<?= isSorted('clanId',$sort,$sortorder) ?>"><?= headerUrl('clanId', ['sort','sortorder'], 'searchclan') ?>ID</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap left">'.$resultName($res['tag']).'</td>
                  <td class="left">
                       <a href="'.$resultUrl($link_clan, $res['clanId']).'"><span class="hlstats-name">⚔️ '.$resultName($res['name']).'</span></a>
                   </td>
                  <td class="left">'.$res['gamename'].'</td>
                  <td class="left">'.$res['clanId'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'searchclan', $mode !== 'admin');
      if (is_ajax() && !empty($_GET['ajax']) && $_GET['ajax'] == 'searchclan') exit
  ?>
</div>
<?php
			}
?>
</div>
<?php
		}
	}
?>
