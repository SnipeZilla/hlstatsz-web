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

	if ($auth->userdata["acclevel"] < 80) {
		die ("Access denied!");
	}

	$id = -1;
	if (isset($_GET['id']) && is_numeric($_GET['id'])) {
		$id = valid_request($_GET['id'], true);
	}
?>
<div class="panel">
<form method="post" action="<?php echo $g_options['scripturl'] . "?mode=admin&amp;task=$selTask&amp;id=$id&amp;" . strip_tags(session_id()); ?>">
<?php

  // get available country flag files
	$flagselect = '';
	$result = $db->query("SELECT `flag`,`name` FROM hlstats_Countries ORDER BY `name`");
    while ($rowdata = $db->fetch_row($result))
    {
        $flagselect.=";".$rowdata[0]."/".$rowdata[1];
    }
	$flagselect.=";";

	$playerGame = '';
	if ($id > 0) {
		$db->query("SELECT game FROM hlstats_Players WHERE playerId = " . (int)$id);
		$gameRow = $db->fetch_row();
		$playerGame = $gameRow[0] ?? '';
	}

	$clanOptions = [];
	$clanResult = $db->query("
		SELECT clanId, tag, name
		FROM hlstats_Clans
		WHERE game = '" . $db->escape($playerGame) . "'
		  AND hidden = 0
		ORDER BY name
	");
	while ($crow = $db->fetch_row($clanResult)) {
		$clanOptions[] = ['id' => (int)$crow[0], 'label' => '[' . $crow[1] . '] ' . $crow[2]];
	}

	$proppage = new PropertyPage("hlstats_Players", "playerId", $id, array(
		new PropertyPage_Group("Profile", array(
			new PropertyPage_Property("lastName", "Name", "text"),
			new PropertyPage_Property("fullName", "Real Name", "text"),
			new PropertyPage_Property("homepage", "Homepage URL", "text"),
			new PropertyPage_Property("flag", "Country Flag", "select", $flagselect),
			new PropertyPage_Property("skill", "Points", "text"),
			new PropertyPage_Property("kills", "Kills", "text"),
			new PropertyPage_Property("deaths", "Deaths", "text"),
			new PropertyPage_Property("headshots", "Headshots", "text"),
			new PropertyPage_Property("suicides", "Suicides", "text"),
			new PropertyPage_Property("hideranking", "Hide Ranking", "select", "0/No;1/Yes;2/Flag as Banned;"),
		))
	));

	if (isset($_POST['fullName']))
	{
		$proppage->update();
		$clanId = max(0, (int)($_POST['clan_id'] ?? 0));
		$db->query("UPDATE hlstats_Players SET clan = $clanId WHERE playerId = " . (int)$id);
		message("success", "Profile updated successfully.");
	}
	$playerId = $db->escape($id);
	$result = $db->query("
		SELECT
			*
		FROM
			hlstats_Players
		WHERE
			playerId='$playerId'
	");
	if ($db->num_rows() < 1) die("No player exists with ID #$id");

	$data = $db->fetch_array($result);

	// find the display label for the player's current clan
	$currentClanId    = (int)($data['clan'] ?? 0);
	$currentClanLabel = '';
	foreach ($clanOptions as $co) {
		if ($co['id'] === $currentClanId) {
			$currentClanLabel = $co['label'];
			break;
		}
	}

	printSectionTitle('<span>'.$data['lastName'].'</span>'.
                      '<em><a href="' . $g_options['scripturl'] . "?mode=playerinfo&amp;player=$id&amp;" . strip_tags(session_id()) . '">'.
                      ' (view player details)</a></em>');

		$proppage->draw($data);
?>

<!-- Clan membership row (separate from PropertyPage — uses text search + datalist) -->
<div class="hlstats-admin-propgroup">
<b>Clan Membership</b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
	<td class="left">Member of Clan:</td>
	<td class="left">
		<input type="text"
		       id="clan_search"
		       list="clan_list"
		       size="35"
		       class="textbox"
		       value="<?= htmlspecialchars($currentClanLabel) ?>"
		       placeholder="Type tag or name to search…"
		       autocomplete="off" />
		<input type="hidden" name="clan_id" id="clan_id" value="<?= $currentClanId ?>" />
		<datalist id="clan_list">
			<option value="" data-id="0">— None —</option>
			<?php foreach ($clanOptions as $co): ?>
			<option value="<?= htmlspecialchars($co['label']) ?>" data-id="<?= $co['id'] ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<script>
		(function () {
			var input  = document.getElementById('clan_search');
			var hidden = document.getElementById('clan_id');

			input.addEventListener('change', function () {
				var val  = this.value.trim();
				var opts = document.querySelectorAll('#clan_list option');

				if (val === '' || val === '— None —') {
					hidden.value = '0';
					return;
				}
				for (var i = 0; i < opts.length; i++) {
					if (opts[i].value === val) {
						hidden.value = opts[i].dataset.id || '0';
						return;
					}
				}
				// Typed text doesn't match any option — leave hidden unchanged
			});
		})();
		</script>
	</td>
</tr>
</tbody>
</table>
</div>
</div>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>
</form>

<?php
    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $col = array("ipAddress","eventTime");
    if (!in_array($sort, $col)) {
        $sort      = "eventTime";
        $sortorder = "DESC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 10 : 0;


	$tblIps = new Table
	(
		array
		(
			new TableColumn
			(
				'ipAddress',
				'IP Address',
				'width=40'
			),
			new TableColumn
			(
				'eventTime',
				'Last Used',
				'width=60'
			)
		),
		'ipAddress',
		'eventTime',
		'eventTime'
	);
	$result = $db->query
	("
		SELECT
			ipAddress,
			eventTime
		FROM
			hlstats_Events_Connects
		WHERE
			playerId = $playerId
		GROUP BY
			ipAddress
		ORDER BY
			$sort $sortorder
		LIMIT 10 OFFSET $start
	");
$total=$db->num_rows();
?>

<?php
	printSectionTitle('Player IP Addresses');
?>

<div class="responsive-table">
  <table class="ip-table">
    <tr>
        <th class="left<?= isSorted('eventTime',$sort,$sortorder) ?>"><?= headerUrl('eventTime', ['sort','sortorder']) ?>Last Used</a></th>
        <th class="left<?= isSorted('ipAddress',$sort,$sortorder) ?>"><?= headerUrl('ipAddress', ['sort','sortorder']) ?>IP Address</a></th>
    </tr>
    <?php

        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                    <td class="nowrap left">'.str_replace(" ","<br>@",$res['eventTime']).'</td>
                    <td class="nowrap left">'.htmlspecialchars($res['ipAddress']).'</td>
                  </tr>';
        }
   ?>
   </table>
</div>
   <?php
       echo Pagination($total, $_GET['page'] ?? 1, 10, 'page', true);
  ?>
</div>
