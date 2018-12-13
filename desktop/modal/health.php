<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
$eqLogics = blescanner::byType('blescanner');
?>

<table class="table table-condensed tablesorter" id="table_healthopenenocean">
	<thead>
		<tr>
			<th>{{Module}}</th>
			<th>{{ID}}</th>
			<th>{{Mac}}</th>
			<th>{{Rssi}}</th>
			<th>{{Présent}}</th>
			<th>{{Dernière communication}}</th>
			<th>{{Date création}}</th>
		</tr>
	</thead>
	<tbody>
	 <?php
foreach ($eqLogics as $eqLogic) {
	
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getHumanName(true) . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getId() . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getLogicalId() . '</span></td>';
	$rssi ='';
	$rssicmd = $eqLogic->getCmd('info', 'rssilocal');
	if (is_object($rssicmd)) {
		$rssiantenna = $rssicmd->execCmd();
		$antennaname = 'local';
		$signalLevel = 'success';
			if ($rssiantenna <= -150) {
				$signalLevel = 'none';
			}elseif ($rssiantenna <= -92) {
				$signalLevel = 'danger';
			} elseif ($rssiantenna <= -86) {
				$signalLevel = 'warning';
			} elseif ($rssiantenna <= -81) {
				$signalLevel = 'yellow';
			}
			
		if ($signalLevel!='none' && $signalLevel!='yellow'){
			$rssi = $rssi . '<span class="label label-'.$signalLevel.'" style="font-size : 0.9em;cursor:default;padding:0px 5px;">' . $rssiantenna .'dBm (' . ucfirst($antennaname) .')</span>';
		} else if ($signalLevel=='yellow'){
			$rssi = $rssi . '<span class="label" style="font-size : 0.9em;cursor:default;padding:0px 5px;background-color:#cccc00">' . $rssiantenna .'dBm (' . ucfirst($antennaname) .')</span>';
		}
	}
	$present = 0;
	$presentcmd = $eqLogic->getCmd('info', 'present');
	if (is_object($presentcmd)) {
		$present = $presentcmd->execCmd();
	}
	if ($present == 1){
		$present = '<span class="label label-success" style="font-size : 1em;" title="{{Présent}}"><i class="fa fa-check"></i></span>';
	} else {
		$present = '<span class="label label-danger" style="font-size : 1em;" title="{{Absent}}"><i class="fa fa-times"></i></span>';
	}
	echo '<td>' . $rssi . '</td>';
	echo '<td>' . $present . '</td>';
	echo '<td><span class="label label-info" style="font-size : 1em;cursor:default;">' . $eqLogic->getStatus('lastCommunication') . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em;cursor:default;">' . $eqLogic->getConfiguration('createtime') . '</span></td></tr>';
}
?>
	</tbody>
</table>
