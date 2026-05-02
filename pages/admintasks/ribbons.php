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
	die("Access denied!");
}

// Count total ribbons
$total = $db->fetch_row(
    $db->query("SELECT COUNT(*) FROM hlstats_Ribbons WHERE game='" . $db->escape($gamecode) . "'")
)[0] ?? 0;

$start = (isset($_GET['page']) && $total > 30) ? ((int)$_GET['page'] - 1) * 30 : 0;


//  Awards for drop-down 
$ar = $db->query(
	"SELECT code, name FROM hlstats_Awards
	 WHERE game='" . $db->escape($gamecode) . "'
	 ORDER BY name",
	false,
);
if ($ar) {
	while ($row = $db->fetch_array($ar)) {
		$awards[$row['code']] = $row['name'];
	}
}

// Process POST 
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$posted = $_POST['ribbon'] ?? [];

	foreach ($posted as $rawId => $data) {
		$rid = (int)$rawId;
		if ($rid <= 0) continue;

		// Delete
		if (!empty($data['delete'])) {
			$db->query(
				"DELETE FROM hlstats_Ribbons
				 WHERE ribbonId='$rid'
				   AND game='" . $db->escape($gamecode) . "'",
				false
			);
			continue;
		}

		// Update
		$ribbonName = trim((string)($data['ribbonName'] ?? ''));
		$image      = trim((string)($data['image']      ?? ''));
		$awardCode  = trim((string)($data['awardCode']  ?? ''));
		$awardCount = max(0, (int)($data['awardCount']  ?? 0));
		$special    = max(0, min(2, (int)($data['special'] ?? 0)));

		if ($ribbonName === '') { $errors[] = "Ribbon #$rid: name is required.";          continue; }
		if ($image      === '') { $errors[] = "Ribbon #$rid: image is required.";         continue; }
		if ($awardCode  === '') { $errors[] = "Ribbon #$rid: trigger award is required."; continue; }

		$ok = $db->query(
			"UPDATE hlstats_Ribbons SET
				ribbonName='" . $db->escape($ribbonName) . "',
				image='"      . $db->escape($image)      . "',
				awardCode='"  . $db->escape($awardCode)  . "',
				awardCount='$awardCount',
				special='$special'
			 WHERE ribbonId='$rid'
			   AND game='" . $db->escape($gamecode) . "'",
			false
		);
		if (!$ok) $errors[] = "Ribbon #$rid: database error.";
	}

	// Insert new ribbon
	$n         = $_POST['new'] ?? [];
	$newName   = trim((string)($n['ribbonName'] ?? ''));
	$newImage  = trim((string)($n['image']      ?? ''));
	$newCode   = trim((string)($n['awardCode']  ?? ''));
	$newCount  = max(0, (int)($n['awardCount']  ?? 0));
	$newSpec   = max(0, min(2, (int)($n['special'] ?? 0)));

	if ($newName !== '' || $newImage !== '' || $newCode !== '') {
		$ne = [];
		if ($newName  === '') $ne[] = "New ribbon: name is required.";
		if ($newImage === '') $ne[] = "New ribbon: image is required.";
		if ($newCode  === '') $ne[] = "New ribbon: trigger award is required.";

		if ($ne) {
			$errors = array_merge($errors, $ne);
		} else {
			$ok = $db->query(
				"INSERT INTO hlstats_Ribbons
					(game, ribbonName, image, awardCode, awardCount, special)
				 VALUES (
					'" . $db->escape($gamecode) . "',
					'" . $db->escape($newName)  . "',
					'" . $db->escape($newImage) . "',
					'" . $db->escape($newCode)  . "',
					'$newCount',
					'$newSpec'
				 )",
				false
			);
			if (!$ok) $errors[] = "Could not add ribbon (duplicate award combination?).";
		}
	}

	if ($errors) {
		message("warning", implode("<br>", $errors));
	} else {
		message("success", "Operation successful.");
	}
}

// Load ribbons
$ribbons = [];
$result = $db->query(
	"SELECT ribbonId, ribbonName, image, awardCode, awardCount, special
	 FROM   hlstats_Ribbons
	 WHERE  game='" . $db->escape($gamecode) . "'
	 ORDER  BY awardCount, awardCode
	 LIMIT 30 OFFSET $start"

);
while ($row = $db->fetch_array($result)) {
	$ribbons[] = $row;
}

// ── Award select helper ───────────────────────────────────────────────────────
function rib_award_select(string $name, string $selected, array $awards, bool $allowEmpty): string
{
	if (empty($awards)) {
		return '<input type="text" class="textbox" name="' . htmlspecialchars($name)
			 . '" value="' . htmlspecialchars($selected) . '" size="20" maxlength="50">';
	}
	$out  = '<select name="' . htmlspecialchars($name) . '">';
	if ($allowEmpty) $out .= '<option value=""></option>';
	foreach ($awards as $code => $label) {
		$sel  = ($code === $selected) ? ' selected' : '';
		$out .= '<option value="' . htmlspecialchars($code) . '"' . $sel . '>'
			  . htmlspecialchars($label) . '</option>';
	}
	if ($selected !== '' && !isset($awards[$selected])) {
		$out .= '<option value="' . htmlspecialchars($selected) . '" selected>'
			  . htmlspecialchars($selected) . ' (current)</option>';
	}
	$out .= '</select>';
	return $out;
}

$formAction = htmlspecialchars(
	$g_options['scripturl'] . '?mode=admin&task=ribbons&game=' . urlencode($gamecode)
);
?>
<form method="post" action="<?= $formAction ?>">
<div class="panel">

<div class="hlstats-admin-note">
<p><strong>Special Logic:</strong></p>
<ul>
  <li>0 = standard ribbon (weapon award triggered)</li>
  <li>1 = CSS Only: HeadShot ribbon</li>
  <li>2 = Connection Time ribbon (awards needed = hours; award code is ignored)</li>
</ul>
</div>

<div class="responsive-table">
<table class="responsive-task">
<thead>
<tr>
  <th class="left">Ribbon Name</th>
  <th class="left">Image file</th>
  <th class="left">Trigger Award</th>
  <th class="left">Awards Needed</th>
  <th class="left">Special</th>
  <th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($ribbons as $r):
	$rid   = (int)$r['ribbonId'];
	$base  = 'ribbon[' . $rid . ']';
	$rName = htmlspecialchars($r['ribbonName']);
	$rImg  = htmlspecialchars($r['image']);
	$rCode = $r['awardCode'];
	$rCnt  = (int)$r['awardCount'];
	$rSpec = (int)$r['special'];
?>
<tr>
  <td data-label="Ribbon Name">
    <input type="text" class="textbox"
           name="<?= $base ?>[ribbonName]"
           value="<?= $rName ?>" size="25" maxlength="50">
  </td>
  <td data-label="Image file">
    <input type="text" class="textbox"
           name="<?= $base ?>[image]"
           value="<?= $rImg ?>" size="20" maxlength="50">
  </td>
  <td data-label="Trigger Award">
    <?= rib_award_select($base . '[awardCode]', $rCode, $awards, false) ?>
  </td>
  <td data-label="Awards Needed">
    <input type="text" class="textbox"
           name="<?= $base ?>[awardCount]"
           value="<?= $rCnt ?>" size="6">
  </td>
  <td data-label="Special">
    <input type="text" class="textbox"
           name="<?= $base ?>[special]"
           value="<?= $rSpec ?>" size="3">
  </td>
  <td data-label="Delete" style="text-align:center">
    <input type="checkbox"
           name="<?= $base ?>[delete]"
           value="1">
  </td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
  <td data-label="Ribbon Name">
    <input type="text" class="textbox"
           name="new[ribbonName]"
           value="" placeholder="New ribbon name" size="25" maxlength="50">
  </td>
  <td data-label="Image file">
    <input type="text" class="textbox"
           name="new[image]"
           value="" placeholder="filename.png" size="20" maxlength="50">
  </td>
  <td data-label="Trigger Award">
    <?= rib_award_select('new[awardCode]', '', $awards, true) ?>
  </td>
  <td data-label="Awards Needed">
    <input type="text" class="textbox"
           name="new[awardCount]"
           value="0" size="6">
  </td>
  <td data-label="Special">
    <input type="text" class="textbox"
           name="new[special]"
           value="0" size="3">
  </td>
  <td></td>
</tr>
</tfoot>
</table>
</div>
<?php
if ($total > 30) 
echo Pagination($total, $_GET['page'] ?? 1, 30, 'page', false, 'actions')
 ?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>

</div>
</form>
