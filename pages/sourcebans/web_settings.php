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

// Settings stored in sb_settings (shared with the original SourceBans panel)
$groups = array();
foreach (SB_SETTINGS as $key => $setting) {
	$groups[$setting['group']][$key] = $setting;
}

printSectionTitle('Settings');
?>
<div class="sba">
<form class="hlstats-section hlstats-card sba-card" data-sba-action="settings.save" novalidate>
<?php foreach ($groups as $group => $settings): ?>
	<fieldset class="sba-settings">
		<legend><?= htmlspecialchars($group) ?></legend>
<?php foreach ($settings as $key => $setting):
	$field = str_replace('.', '_', $key);
	$value = sbSetting($key);
	if ($setting['type'] === 'bool'): ?>
		<label class="sba-switch"><input type="checkbox" name="<?= $field ?>" value="1"<?= $value === '1' ? ' checked' : '' ?>><span class="sba-switch-ui"></span>
			<span><b><?= htmlspecialchars($setting['label']) ?></b><small><?= htmlspecialchars($setting['help']) ?></small></span></label>
<?php elseif ($setting['type'] === 'int'): ?>
		<label class="sba-field"><span class="sba-label"><?= htmlspecialchars($setting['label']) ?></span>
			<input type="number" class="sba-narrow" name="<?= $field ?>" min="<?= $setting['min'] ?>" max="<?= $setting['max'] ?>" value="<?= htmlspecialchars($value) ?>">
			<small class="sba-help"><?= htmlspecialchars($setting['help']) ?></small><span class="sba-error" data-error-for="<?= $field ?>"></span></label>
<?php elseif ($setting['type'] === 'lines'): ?>
		<label class="sba-field"><span class="sba-label"><?= htmlspecialchars($setting['label']) ?> <small><?= htmlspecialchars($setting['help']) ?></small></span>
			<textarea name="<?= $field ?>" rows="5"><?= htmlspecialchars(implode("\n", sbCustomReasons())) ?></textarea></label>
<?php else: ?>
		<label class="sba-field"><span class="sba-label"><?= htmlspecialchars($setting['label']) ?></span><input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($value) ?>"></label>
<?php endif; endforeach; ?>
	</fieldset>
<?php endforeach; ?>
	<div class="sba-form-foot">
		<span class="sba-form-error" role="alert"></span>
		<button type="submit" class="sba-btn is-primary">Save settings</button>
	</div>
</form>
</div>
