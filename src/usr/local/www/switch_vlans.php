<?php
/*
 * switch_vlans.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-switch-vlans
##|*NAME=Switch: VLANs
##|*DESCR=Allow access to the 'Switch: VLANs' page.
##|*MATCH=switch_vlans.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"));
$shortcut_section = "vlans";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), false, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), true, "switch_vlans.php");
display_top_tabs($tab_array);

// Probably uFW specific.
function find_pfSense_vlan($vlantag) {
	global $config;

	if (!isset($config["vlans"]["vlan"]) || !is_array($config["vlans"]["vlan"])) {
		return (NULL);
	}

	for ($i = 0; $i < count($config["vlans"]["vlan"]); $i++) {
		$vlan = $config["vlans"]["vlan"][$i];
		if ($vlan["tag"] != $vlantag) {
			continue;
		}

		return (array("id" => $i, "vlan" => $vlan));
	}

	return (NULL);
}

// Build an array with which to populate the switch device selector
function get_switches($devicelist) {

	$switches = array();

	foreach ($devicelist as $swdev) {

		$swinfo = pfSense_etherswitch_getinfo($swdev);
		if ($swinfo == NULL) {
			continue;
		}
		if ($swdevice == NULL)
			$swdevice = $swdev;

		$switches[$swdev] = $swinfo['name'];
	}

	return($switches);
}

// Delete a VLAN
if ($_GET['act'] == "del") {
	$vid = $_GET['vid'];
	$device = $_GET['swdevice'];

	print("Deleting VID: " . $vid . " from device: " . $device . "<br />");

	// ToDo: Add some code to delete the VLAN
}

// List the available switches
$swdevices = switch_get_devices();
$vlans_system = switch_get_system_vlans(false);
$swtitle = switch_get_title();

// If there is more than one switch, draw a selector to allow the user to choose which one to look at
if (count($swdevices) > 1) {
	$form = new Form(false);

	$section = new Form_Section('Select switch');

	$section->addInput(new Form_Select(
		'swdevice',
		'Switch',
		$_POST['swdevice'],
		get_switches($swdevices)
	));

	$form->add($section);

	print($form);

}

if ($_GET['swdevice']) {
	$_POST['swdevice'] = $_GET['swdevice'];
}

// If the selector was changed, the selected value becomes the default
if($_POST['swdevice']) {
	$swdevice = $_POST['swdevice'];
} else {
	$swdevice = $swdevices[0];
}


$swinfo = pfSense_etherswitch_getinfo($swdevice);
if ($swinfo == NULL) {
	$input_errors[] = "Cannot get switch device information\n";
}

if ($input_errors) {
	print_input_errors($input_errors);
} else {
	// Don't draw the table if there were hardware errors
?>
<div class="panel panel-default">
<?
	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "PORT") {
?>
	<div class="panel-heading"><h2 class="panel-title"><?= gettext($swtitle) ." ". gettext('Switch Port based VLANs')?></h2></div>
<?
	} else {
?>
	<div class="panel-heading"><h2 class="panel-title"><?= gettext($swtitle) ." ". gettext('Switch 802.1Q VLANs')?></h2></div>
<?
	}
?>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("VLAN group"); ?></th>
<?
	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
?>
						<th><?=gettext("VLAN ID"); ?></th>
<?
	} else {
?>
						<th><?=gettext("Port"); ?></th>
<?
	}
?>
						<th><?=gettext("Members"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Action"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php

for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
	$vgroup = pfSense_etherswitch_getvlangroup($swdevice, $i);
	if ($vgroup == NULL) {
		continue;
	}
?>
					<tr>
						<td>
							<?= htmlspecialchars($vgroup['vlangroup']); ?>
						</td>
						<td>
							<?= htmlspecialchars($vgroup['vid']); ?>
						</td>
						<td>
<?
	$comma = false;

	foreach ($vgroup['members'] as $member => $val) {
		if ($comma) {
			echo ",";
		}

		echo "$member";
		$comma = true;
	}
?>
						</td>
						<td>
<?
	$sys = false;
	foreach ($vlans_system as $svlan) {
		if ($svlan['vid'] != $vgroup['vid']) {
			continue;
		}

		echo "Default System VLAN";
		$sys = true;

		break;
	}
	if (!$sys) {
		$vlan = find_pfSense_vlan($vgroup['vid']);
		if ($vlan != NULL && is_array($vlan)) {
			echo htmlspecialchars($vlan['vlan']['descr']);
		}
	}
?>
						</td>
						<td>
<?php
		if (!$sys) {
			if (isset($vlan) && $vlan != NULL && is_array($vlan)) {
?>
							<a class="fa fa-pencil" title="<?=gettext("Edit"); ?>" href="interfaces_vlan_edit.php?id=<?=htmlspecialchars($vlan['id'])?>"></a>
							<a class="fa fa-trash no-confirm"       title="<?=gettext('Delete VLAN')?>"     role="button" id="del-<?=$vlan['id']?>"></a>
<?php
			}
		}
?>
						</td>
					</tr>
<?
	}

?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php

/*
 * Not implemented yet.
 *
 * <nav class="action-buttons">
 *	<a href="switch_vlans_edit.php?swdevice=<?=$swdevice?>&amp;act=new&amp;nports=<?=$swinfo['nports']?>" role="button" class="btn btn-success btn-sm">
 *		<i class="fa fa-plus icon-embed-btn"></i>
 *		<?=gettext("Add");?>
 *	</a>
 * </nav>
 */

} // e-o-if($input_errors) else . .

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Automatically submit the form when the selector is changed
	$('#swdevice').on('change', function () {
		$('form').submit();
	});
});
//]]>
</script>

<?php
        $delmsg = gettext("Are you sure you want to delete this VLAN?");
?>

<form name="vlan_edit_form" action="interfaces_vlan.php" method="post">
        <input id="act" type="hidden" name="act" value="" />
        <input id="id" type="hidden" name="id" value="" />
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Select 'delete button' clicks, extract the id, set the hidden input values and submit
	$('[id^=del-]').click(function(event) {
		if (confirm("<?=$delmsg?>")) {
			$('#act').val('del');
			$('#id').val(this.id.replace("del-", ""));
			$('form[name="vlan_edit_form"]').submit();
		}
	});

});
//]]>
</script>

<?php
include("foot.inc");
