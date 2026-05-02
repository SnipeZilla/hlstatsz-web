<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Ranks admin — direct SQL, no EditList dependency.
*/

if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
}

if ($auth->userdata["acclevel"] < 80) {
	die("Access denied!");
}

// Count total ranks
$total = $db->fetch_row(
	$db->query("SELECT COUNT(*) FROM hlstats_Ranks WHERE game='" . $db->escape($gamecode) . "'")
)[0] ?? 0;

$start = (isset($_GET['page']) && $total > 30) ? ((int)$_GET['page'] - 1) * 30 : 0;

// ── Process POST ──────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$posted = $_POST['rank'] ?? [];

	foreach ($posted as $rawId => $data) {
		$rid = (int)$rawId;
		if ($rid <= 0) continue;

		// Delete
		if (!empty($data['delete'])) {
			$db->query(
				"DELETE FROM hlstats_Ranks
				 WHERE rankId='$rid'
				   AND game='" . $db->escape($gamecode) . "'",
				false
			);
			continue;
		}

		// Update
		$rankName = trim((string)($data['rankName'] ?? ''));
		$image    = trim((string)($data['image']    ?? ''));
		$minKills = max(0, (int)($data['minKills']  ?? 0));
		$maxKills = max(0, (int)($data['maxKills']  ?? 0));

		if ($rankName === '') { $errors[] = "Rank #$rid: name is required.";      continue; }
		if ($image    === '') { $errors[] = "Rank #$rid: image is required.";     continue; }
		if ($maxKills < $minKills) { $errors[] = "Rank #$rid: maxKills must be >= minKills."; continue; }

		$ok = $db->query(
			"UPDATE hlstats_Ranks SET
				rankName='" . $db->escape($rankName) . "',
				image='"    . $db->escape($image)    . "',
				minKills='$minKills',
				maxKills='$maxKills'
			 WHERE rankId='$rid'
			   AND game='" . $db->escape($gamecode) . "'",
			false
		);
		if (!$ok) $errors[] = "Rank #$rid: database error.";
	}

	// Insert new rank
	$n        = $_POST['new'] ?? [];
	$newName  = trim((string)($n['rankName'] ?? ''));
	$newImage = trim((string)($n['image']    ?? ''));
	$newMin   = max(0, (int)($n['minKills']  ?? 0));
	$newMax   = max(0, (int)($n['maxKills']  ?? 0));

	if ($newName !== '' || $newImage !== '') {
		$ne = [];
		if ($newName  === '') $ne[] = "New rank: name is required.";
		if ($newImage === '') $ne[] = "New rank: image is required.";
		if ($newMax < $newMin) $ne[] = "New rank: maxKills must be >= minKills.";

		if ($ne) {
			$errors = array_merge($errors, $ne);
		} else {
			$ok = $db->query(
				"INSERT INTO hlstats_Ranks
					(game, rankName, image, minKills, maxKills)
				 VALUES (
					'" . $db->escape($gamecode) . "',
					'" . $db->escape($newName)  . "',
					'" . $db->escape($newImage) . "',
					'$newMin',
					'$newMax'
				 )",
				false
			);
			if (!$ok) $errors[] = "Could not add rank (duplicate range?).";
		}
	}

	if ($errors) {
		message("warning", implode("<br>", $errors));
	} else {
		message("success", "Operation successful.");
	}
}

// ── Load ranks ────────────────────────────────────────────────────────────────
$ranks = [];
$result = $db->query(
	"SELECT rankId, rankName, image, minKills, maxKills
	 FROM   hlstats_Ranks
	 WHERE  game='" . $db->escape($gamecode) . "'
	 ORDER  BY minKills ASC
	 LIMIT 30 OFFSET $start"
);
while ($row = $db->fetch_array($result)) {
	$ranks[] = $row;
}

$formAction = htmlspecialchars(
	$g_options['scripturl'] . '?mode=admin&task=ranks&game=' . urlencode($gamecode)
);
?>
<form method="post" action="<?= $formAction ?>">
<div class="panel">

<div class="hlstats-admin-note">
<p>
  Set minKills/maxKills with no gaps between ranks.<br>
  Images must be given without the <code>.gif/.png</code> and <code>_small</code> extension.
</p>
</div>

<div class="responsive-table">
<table class="responsive-task">
<thead>
<tr>
  <th class="left">Rank Name</th>
  <th class="left">Image file</th>
  <th class="left">Min Kills</th>
  <th class="left">Max Kills</th>
  <th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($ranks as $r):
	$rid   = (int)$r['rankId'];
	$base  = 'rank[' . $rid . ']';
	$rName = htmlspecialchars($r['rankName']);
	$rImg  = htmlspecialchars($r['image']);
	$rMin  = (int)$r['minKills'];
	$rMax  = (int)$r['maxKills'];
?>
<tr>
  <td data-label="Rank Name">
    <input type="text" class="textbox"
           name="<?= $base ?>[rankName]"
           value="<?= $rName ?>" size="25" maxlength="64">
  </td>
  <td data-label="Image file">
    <input type="text" class="textbox"
           name="<?= $base ?>[image]"
           value="<?= $rImg ?>" size="20" maxlength="64">
  </td>
  <td data-label="Min Kills">
    <input type="text" class="textbox"
           name="<?= $base ?>[minKills]"
           value="<?= $rMin ?>" size="8">
  </td>
  <td data-label="Max Kills">
    <input type="text" class="textbox"
           name="<?= $base ?>[maxKills]"
           value="<?= $rMax ?>" size="8">
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
  <td data-label="Rank Name">
    <input type="text" class="textbox"
           name="new[rankName]"
           value="" placeholder="New rank name" size="25" maxlength="64">
  </td>
  <td data-label="Image file">
    <input type="text" class="textbox"
           name="new[image]"
           value="" placeholder="filename" size="20" maxlength="64">
  </td>
  <td data-label="Min Kills">
    <input type="text" class="textbox"
           name="new[minKills]"
           value="0" size="8">
  </td>
  <td data-label="Max Kills">
    <input type="text" class="textbox"
           name="new[maxKills]"
           value="0" size="8">
  </td>
  <td></td>
</tr>
</tfoot>
</table>
</div>
<?= Pagination($total, $_GET['page'] ?? 1, 30, 'page', false, 'actions') ?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>

</div>
</form>
