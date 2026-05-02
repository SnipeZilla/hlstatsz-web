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

    ob_flush();
    flush();

    $asterisk = $g_options['DeleteDays'] ? ' *' : '';

    list($realgame,$realname) = getRealGame($game);
    $result = $db->query("
        SELECT
            hlstats_Weapons.code,
            hlstats_Weapons.name
        FROM
            hlstats_Weapons
        WHERE
            hlstats_Weapons.game = '$game'
    ");

    while ($rowdata = $db->fetch_row($result)) {
        $code = $rowdata[0];
        $fname[strToLower($code)] = htmlspecialchars($rowdata[1]);
    }

if (empty($_GET['ajax']) || $_GET['ajax'] == 'weapons') {


    $sortorder = $_GET['weap_sortorder'] ?? '';
    $sort      = $_GET['weap_sort'] ?? '';
    $sort2     = "kills";

    $col = array("weapon","modifier","kills","headshots","kpercent","hpercent","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "kills";
        $sortorder = "DESC";
    }

    if ($sort == "kills") {
        $sort2 = "headshots";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['weap_page']) ? ((int)$_GET['weap_page'] - 1) * 10 : 0;

    $result = $db->query("
                WITH frag_data AS (
                    SELECT
                        f.weapon,
                        IFNULL(w.modifier, 1.00) AS modifier,
                        COUNT(f.weapon) AS kills,
                        ROUND(COUNT(f.weapon) / $realkills * 100, 2) AS kpercent,
                        SUM(f.headshot = 1) AS headshots,
                        ROUND(SUM(f.headshot = 1) / IF(COUNT(f.weapon) = 0, 1, COUNT(f.weapon)), 2) AS hpk,
                        ROUND(SUM(f.headshot = 1) / $realheadshots * 100, 2) AS hpercent
                    FROM hlstats_Events_Frags f
                    LEFT JOIN hlstats_Weapons w
                        ON w.code = f.weapon
                    WHERE
                        f.killerId = $player
                        AND (w.game = '$game' OR w.weaponId IS NULL)
                    GROUP BY
                        f.weapon, w.modifier
                )
                SELECT
                    *,
                    COUNT(*) OVER() AS total_rows
                FROM frag_data
                ORDER BY
                    $sort $sortorder,
                    $sort2 $sortorder
                LIMIT 10 OFFSET $start;
    ");

    if ($db->num_rows($result)) {
        if (empty($_GET['ajax'])) {

        printSectionTitle('Weapon Usage'.$asterisk);

?>
<div id="weapons">
<?php
}
?>
<div class="responsive-table">
  <table class="weapons-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('weapon',$sort,$sortorder) ?>"><?= headerUrl('weapon', ['weap_sort','weap_sortorder'], 'weapons') ?>Weapons</a></th>
        <th class="hide-1<?= isSorted('modifier',$sort,$sortorder) ?>"><?= headerUrl('modifier', ['weap_sort','weap_sortorder'], 'weapons') ?>Modifier</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['weap_sort','weap_sortorder'], 'weapons') ?>Kills</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['weap_sort','weap_sortorder'], 'weapons') ?>Ratio</a></th>
        <th class="<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['weap_sort','weap_sortorder'], 'weapons') ?>Headshots</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['weap_sort','weap_sortorder'], 'weapons') ?>Ratio</a></th>
        <th class="hide<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['weap_sort','weap_sortorder'], 'weapons') ?>HS:K</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            $weapon = strtolower($res['weapon']);
            $image = getImage("/games/$realgame/weapons/" . $weapon);
            if ($image) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } elseif ($image = getImage("/games/$realgame/weapons/" . $weapon)) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } else {
                $weapimg = '<span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=weaponinfo&weapon='.$res['weapon'].'&game='.$game.'"><span class="hlstats-image">'.$weapimg.'</span></a></td>
                  <td class="nowrap hide-1">'.$res['modifier'].' times</td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['kpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['kpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['hpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['hpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['weap_page'] ?? 1, 10, 'weap_page', true, 'weapons');

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}

    ob_flush();
    flush();

if (empty($_GET['ajax']) || $_GET['ajax'] == 'statsme') {


    $sortorder = $_GET['sm_sortorder'] ?? '';
    $sort      = $_GET['sm_sort'] ?? '';
    $sort2     = "smweapon";

    $col = array("smweapon","smkills","smhits","smshots","smheadshots","smdeaths","smdamage","smdhr","smkdr","smaccuracy","smspk");
    if (!in_array($sort, $col)) {
        $sort      = "smkills";
        $sortorder = "DESC";
    }

    if ($sort == "smweapon") {
        $sort2 = "smkills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['sm_page']) ? ((int)$_GET['sm_page'] - 1) * 10 : 0;


    $result = $db->query("

    WITH sm_data AS (
        SELECT
            s.weapon AS smweapon,
            SUM(s.kills) AS smkills,
            SUM(s.hits) AS smhits,
            SUM(s.shots) AS smshots,
            SUM(s.headshots) AS smheadshots,
            SUM(s.deaths) AS smdeaths,
            SUM(s.damage) AS smdamage,
            ROUND(SUM(s.damage) / IF(SUM(s.hits) = 0, 1, SUM(s.hits)), 1) AS smdhr,
            SUM(s.kills) / IF(SUM(s.deaths) = 0, 1, SUM(s.deaths)) AS smkdr,
            ROUND(SUM(s.hits) / SUM(s.shots) * 100, 1) AS smaccuracy,
            ROUND(
                IF(SUM(s.kills) = 0, 0, SUM(s.shots)) /
                IF(SUM(s.kills) = 0, 1, SUM(s.kills)),
            1) AS smspk
        FROM hlstats_Events_Statsme s
        WHERE s.PlayerId = $player
        GROUP BY s.weapon
        HAVING SUM(s.shots) > 0
    )
    SELECT
        *,
        COUNT(*) OVER() AS total_rows
    FROM sm_data
    ORDER BY
        $sort $sortorder,
        $sort2 $sortorder
    LIMIT 10 OFFSET $start;
    ");


    if ($db->num_rows($result)) {
        if (empty($_GET['ajax'])) {
            printSectionTitle('Weapon Statistics'.$asterisk);


?>
<div id="statsme">
<?php
}
?>
<div class="responsive-table">
  <table class="statsme-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('smweapon',$sort,$sortorder) ?>"><?= headerUrl('smweapon', ['sm_sort','sm_sortorder'], 'statsme') ?>Weapon</a></th>
        <th class="<?= isSorted('smshots',$sort,$sortorder) ?>"><?= headerUrl('smshots', ['sm_sort','sm_sortorder'], 'statsme') ?>Shots</a></th>
        <th class="<?= isSorted('smhits',$sort,$sortorder) ?>"><?= headerUrl('smhits', ['sm_sort','sm_sortorder'], 'statsme') ?>Hits</a></th>
        <th class="hide-2<?= isSorted('smdamage',$sort,$sortorder) ?>"><?= headerUrl('smdamage', ['sm_sort','sm_sortorder'], 'statsme') ?>Damage</a></th>
        <th class="hide<?= isSorted('smheadshots',$sort,$sortorder) ?>"><?= headerUrl('smheadshots', ['sm_sort','sm_sortorder'], 'statsme') ?>Headshots</a></th>
        <th class="hide<?= isSorted('smkills',$sort,$sortorder) ?>"><?= headerUrl('smkills', ['sm_sort','sm_sortorder'], 'statsme') ?>Kills</a></th>
        <th class="hide-2<?= isSorted('smkdr',$sort,$sortorder) ?>"><?= headerUrl('smkdr', ['sm_sort','sm_sortorder'], 'statsme') ?>K:D</a></th>
        <th class="hide-1<?= isSorted('smaccuracy',$sort,$sortorder) ?>"><?= headerUrl('smaccuracy', ['sm_sort','sm_sortorder'], 'statsme') ?>Accuracy</a></th>
        <th class="hide-2<?= isSorted('smdhr',$sort,$sortorder) ?>"><?= headerUrl('smdhr', ['sm_sort','sm_sortorder'], 'statsme') ?>Damage:Hit</a></th>
        <th class="hide-3<?= isSorted('smspk',$sort,$sortorder) ?>"><?= headerUrl('smspk', ['sm_sort','sm_sortorder'], 'statsme') ?>Shots:Kills</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            $weapon = strtolower($res['smweapon']);
            $image = getImage("/games/$realgame/weapons/" . $weapon);
            if ($image) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } elseif ($image = getImage("/games/$realgame/weapons/" . $weapon)) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } else {
                $weapimg = '<span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=weaponinfo&weapon='.$weapon.'&game='.$game.'"><span class="hlstats-image">'.$weapimg.'</span></a></td>
                  <td class="nowrap">'.$res['smshots'].' times</td>
                  <td class="nowrap">'.$res['smhits'].'</td>
                  <td class="nowrap hide-2">'.$res['smdamage'].'</td>
                  <td class="nowrap hide">'.$res['smheadshots'].'</td>
                  <td class="nowrap hide">'.$res['smkills'].'</td>
                  <td class="nowrap hide-2">'.$res['smkdr'].'</td>
                  <td class="nowrap hide-1">'.$res['smaccuracy'].'</td>
                  <td class="nowrap hide-2">'.$res['smdhr'].'</td>
                  <td class="nowrap hide-3">'.$res['smspk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>

   <?php
       echo Pagination($total, $_GET['sm_page'] ?? 1, 10, 'sm_page', true, 'statsme');

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}
?>

</div>

<?php
    ob_flush();
    flush();

if (empty($_GET['ajax']) || $_GET['ajax'] == 'statsme2') {


    $sortorder = $_GET['sm2_sortorder'] ?? '';
    $sort      = $_GET['sm2_sort'] ?? '';
    $sort2     = "smweapon";

    $col = array("smweapon","smhits","smleft","smmiddle","smright");
    if (!in_array($sort, $col)) {
        $sort      = "smhits";
        $sortorder = "DESC";
    }

    if ($sort == "smweapon") {
        $sort2 = "smhits";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['sm2_page']) ? ((int)$_GET['sm2_page'] - 1) * 10 : 0;

    $query = "
        WITH sm2 AS (
            SELECT
                s.weapon AS smweapon,
                SUM(s.head)      AS smhead,
                SUM(s.chest)     AS smchest,
                SUM(s.stomach)   AS smstomach,
                SUM(s.leftarm)   AS smleftarm,
                SUM(s.rightarm)  AS smrightarm,
                SUM(s.leftleg)   AS smleftleg,
                SUM(s.rightleg)  AS smrightleg,
        
                -- total hits
                SUM(s.head)
                + SUM(s.chest)
                + SUM(s.stomach)
                + SUM(s.leftarm)
                + SUM(s.rightarm)
                + SUM(s.leftleg)
                + SUM(s.rightleg) AS smhits,
        
                -- left side %
                IFNULL(
                    ROUND(
                        (SUM(s.leftarm) + SUM(s.leftleg)) /
                        NULLIF(
                            SUM(s.head) + SUM(s.chest) + SUM(s.stomach)
                            + SUM(s.leftarm) + SUM(s.rightarm)
                            + SUM(s.leftleg) + SUM(s.rightleg),
                        0) * 100,
                    1),
                0.0) AS smleft,
        
                -- right side %
                IFNULL(
                    ROUND(
                        (SUM(s.rightarm) + SUM(s.rightleg)) /
                        NULLIF(
                            SUM(s.head) + SUM(s.chest) + SUM(s.stomach)
                            + SUM(s.leftarm) + SUM(s.rightarm)
                            + SUM(s.leftleg) + SUM(s.rightleg),
                        0) * 100,
                    1),
                0.0) AS smright,
        
                -- middle %
                IFNULL(
                    ROUND(
                        (SUM(s.head) + SUM(s.chest) + SUM(s.stomach)) /
                        NULLIF(
                            SUM(s.head) + SUM(s.chest) + SUM(s.stomach)
                            + SUM(s.leftarm) + SUM(s.rightarm)
                            + SUM(s.leftleg) + SUM(s.rightleg),
                        0) * 100,
                    1),
                0.0) AS smmiddle
            FROM hlstats_Events_Statsme2 s
            WHERE s.PlayerId = $player
            GROUP BY s.weapon
            HAVING smhits > 0
        )
        SELECT
            *,
            COUNT(*) OVER() AS total_rows
        FROM sm2
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        LIMIT 10 OFFSET $start;
    ";
    $result = $db->query($query);
    if ($db->num_rows($result)) {
        if (empty($_GET['ajax'])) {

        printSectionTitle('Weapon Targets'.$asterisk);
        


?>
<div class="hlstats-cards-grid">
<section>

<div id="statsme2">
<?php } ?>
<div class="responsive-table">
  <table class="statsme2-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('smweapon',$sort,$sortorder) ?>"><?= headerUrl('smweapon', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Weapon</a></th>
        <th class="<?= isSorted('smhits',$sort,$sortorder) ?>"><?= headerUrl('smhits', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Hits</a></th>
        <th class="<?= isSorted('smleft',$sort,$sortorder) ?>"><?= headerUrl('smleft', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Left</a></th>
        <th class="<?= isSorted('smmiddle',$sort,$sortorder) ?>"><?= headerUrl('smmiddle', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Middle</a></th>
        <th class="<?= isSorted('smright',$sort,$sortorder) ?>"><?= headerUrl('smright', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Right</a></th>
    </tr>
    <?php
        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            $weapon = strtolower($res['smweapon']);
            $image = getImage("/games/$realgame/weapons/" . $weapon);
            if ($image) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } elseif ($image = getImage("/games/$realgame/weapons/" . $weapon)) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } else {
                $weapimg = '<span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="javascript:switch_weapon(\''.$res['smweapon'].'\');" onclick="switch_weapon(\''.$res['smweapon'].'\');return false;"><span class="hlstats-image">'.$weapimg.'</span></a></td>
                  <td class="nowrap">'.$res['smhits'].' times</td>
                  <td class="nowrap">'.$res['smleft'].'</td>
                  <td class="nowrap">'.$res['smmiddle'].'</td>
                  <td class="nowrap">'.$res['smright'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>

   <?php
       echo Pagination($total, $_GET['sm2_page'] ?? 1, 10, 'sm2_page', true, 'statsme2');

  if (!empty($_GET['ajax'])) exit;
  ?>
  </div>
</section>
<section>

    <?php
    $result = $db->query($query);
    $weapon_data = array ();
    $weapon_data['game'] = $realgame;
    $cs2_models = array ('cs2_ct', 'cs2_ct2', 'cs2_ts', 'cs2_ts2');
    $cs2_ct_weapons = array ('awp', 'usp', 'tmp', 'm4a1', 'aug', 'famas', 'sig550','deagle');
    $cs2_ts_weapons = array ('awp', 'glock', 'elite', 'mac10', 'ak47', 'sg556', 'galil', 'g3sg1','deagle');
    $css_models = array ('ct', 'ct2', 'ct3', 'ct4', 'ts', 'ts2', 'ts3', 'ts4');
    $css_ct_weapons = array ('usp', 'tmp', 'm4a1', 'aug', 'famas', 'sig550');
    $css_ts_weapons = array ('glock', 'elite', 'mac10', 'ak47', 'sg552', 'galil', 'g3sg1');
    $css_random_weapons = array ('knife', 'deagle', 'p228', 'm3', 'xm1014', 'mp5navy', 'p90', 'scout', 'awp', 'm249', 'hegrenade', 'flashbang', 'ump45', 'smokegrenade_projectile');
    $dods_models = array ('allies', 'axis');
    $dods_allies_weapons = array ('thompson', 'colt', 'spring', 'garand', 'riflegren_us', 'm1carbine', 'bar', 'amerknife', '30cal', 'bazooka', 'frag_us', 'riflegren_us', 'smoke_us');
    $dods_axis_weapons = array ('spade', 'riflegren_ger', 'k98', 'mp40', 'p38', 'frag_ger', 'smoke_ger', 'mp44', 'k98_scoped', 'mg42', 'pschreck', 'c96');
    $l4d_models = array ('zombie1', 'zombie2', 'zombie3');
    $insmod_models = array ('insmod1', 'insmod2');
    $fof_models = array ('fof1', 'fof2');
    $ges_models = array ('ges-bond', 'ges-boris');
    $dinodday_models            = array('ddd_allies', 'ddd_axis');
    $dinodday_allies_weapons    = array('garand', 'greasegun', 'thompson', 'shotgun', 'sten', 'carbine', 'bar', 'mosin', 'p38', 'piat', 'nagant', 'flechette', 'pistol', 'trigger');
    $dinodday_axis_weapons        = array('mp40', 'k98', 'mp44', 'k98sniper', 'luger', 'stygimoloch', 'mg42', 'trex');
    while ($rowdata = $db->fetch_array())
    {
        $weapon_data['total']['head'] += $rowdata['smhead'];
        $weapon_data['total']['leftarm'] += $rowdata['smleftarm'];
        $weapon_data['total']['rightarm'] += $rowdata['smrightarm'];
        $weapon_data['total']['chest'] += $rowdata['smchest'];
        $weapon_data['total']['stomach'] += $rowdata['smstomach'];
        $weapon_data['total']['leftleg'] += $rowdata['smleftleg'];
        $weapon_data['total']['rightleg'] += $rowdata['smrightleg'];
        $weapon_data[$rowdata['smweapon']]['head'] = $rowdata['smhead'];
        $weapon_data[$rowdata['smweapon']]['leftarm'] = $rowdata['smleftarm'];
        $weapon_data[$rowdata['smweapon']]['rightarm'] = $rowdata['smrightarm'];
        $weapon_data[$rowdata['smweapon']]['chest'] = $rowdata['smchest'];
        $weapon_data[$rowdata['smweapon']]['stomach'] = $rowdata['smstomach'];
        $weapon_data[$rowdata['smweapon']]['leftleg'] = $rowdata['smleftleg'];
        $weapon_data[$rowdata['smweapon']]['rightleg'] = $rowdata['smrightleg'];
        switch ($realgame)
        {
            case 'cs2':
                $weapon_data[$rowdata['smweapon']]['model'] = 'cs2_ct';
                break;
            case 'dods':
                $weapon_data[$rowdata['smweapon']]['model'] = 'allies';
                break;
            case 'l4d':
                $weapon_data[$rowdata['smweapon']]['model'] = 'zombie1';
                break;
            case 'hl2mp':
                $weapon_data[$rowdata["smweapon"]]['model'] = 'alyx';
                break;
            case 'insmod':
                $weapon_data[$rowdata['smweapon']]['model'] = 'insmod1';
                break;
            case 'zps':
                $weapon_data[$rowdata["smweapon"]]['model'] = 'zps1';
                break;
            case 'ges':
                $weapon_data[$rowdata['smweapon']]['model'] = 'ges-bond';
                break;
            case 'tfc':
                $weapon_data[$rowdata["smweapon"]]['model'] = 'pyro';
                break;
            case 'fof':
                $weapon_data[$rowdata['smweapon']]['model'] = 'fof1';
                break;
            case 'dinodday':
                $weapon_data[$rowdata['smweapon']]['model'] = 'ddd_allies';
                break;
            default:
                $weapon_data[$rowdata['smweapon']]['model'] = 'ct';
        }
        if ($realgame == 'cs2')
        {
            if (in_array($rowdata['smweapon'], $css_random_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $cs2_models[array_rand($cs2_models)];
            }
            elseif (in_array($rowdata['smweapon'], $cs2_ct_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $cs2_models[rand(0, 2) + 3];
            }
            elseif (in_array($rowdata['smweapon'], $cs2_ts_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $cs2_models[rand(0, 2)];
            }
        }
        elseif ($realgame == 'css' || $realgame == 'cstrike')
        {
            if (in_array($rowdata['smweapon'], $css_random_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $css_models[array_rand($css_models)];
            }
            elseif (in_array($rowdata['smweapon'], $css_ct_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $css_models[rand(0, 2) + 3];
            }
            elseif (in_array($rowdata['smweapon'], $css_ts_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $css_models[rand(0, 2)];
            }
        }
        elseif ($realgame == 'dods')
        {
            if (in_array($rowdata['smweapon'], $dods_allies_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $dods_models[1];
            }
            elseif (in_array($rowdata['smweapon'], $dods_axis_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $dods_models[0];
            }
        }
        elseif ($realgame == 'dinodday')
        {
            if (in_array($rowdata['smweapon'], $dinodday_allies_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $dinodday_models[1];
            }
            elseif (in_array($rowdata['smweapon'], $dinodday_axis_weapons))
            {
                $weapon_data[$rowdata['smweapon']]['model'] = $dinodday_models[0];
            }
        }
    }
    switch ($realgame)
    {
        case 'cs2':
            $start_model = $cs2_models[array_rand($cs2_models)];
            break;
        case 'dods':
            $start_model = $dods_models[array_rand($dods_models)];
            break;
        case 'l4d':
            $start_model = $l4d_models[array_rand($l4d_models)];
            break;
        case 'hl2mp':
            $start_model = 'alyx';
            break;
        case 'insmod':
            $start_model = $insmod_models[array_rand($insmod_models)];
            break;
        case 'zps':
            $start_model = 'zps1';
            break;
        case 'ges':
            $start_model = $ges_models[array_rand($ges_models)];
            break;
        case 'tfc':
            $start_model = 'pyro';
            break;
        case 'fof':
            $start_model = $fof_models[array_rand($fof_models)];
            break;
        case 'dinodday':
            $start_model = $dinodday_models[array_rand($dinoday_models)];
            break;
        default:
            $start_model   = $css_models[array_rand($css_models)];
    }
    $weapon_data['total']['model'] = $start_model;

    echo '<script type="application/json" id="hitbox-data">' . json_encode($weapon_data) . '</script>';

?>

    <table>
    <tr>
       <th>Targets</th>
    </tr>
    <tr>
        <td style="width:100%;padding:0;margin:0;">
          <div id="hitbox-frame"></div>
        </td>
    </tr>
    <tr>
        <td>
        <a href="javascript:switch_weapon('total');">
          <span class="hldstats-name">Show All Weapons statistics</span>
        </a>
        </td>
    </tr>
    </table>
  </section>
</div>
<?php
  }
}
?>
