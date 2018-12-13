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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'blescanner')) {
	echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action';
	die();
}

if (init('test') != '') {
	echo 'OK';
	die();
}
$result = json_decode(file_get_contents("php://input"), true);
if (!is_array($result)) {
	die();
}
if (isset($result['source'])){
	log::add('blescanner','debug','This is a message from antenna ' . $result['source']);
}
if (isset($result['learn_mode'])) {
	if ($result['learn_mode'] == 1) {
		config::save('include_mode', 1, 'blescanner');
		event::add('blescanner::includeState', array(
			'mode' => 'learn',
			'state' => 1)
		);
	} else {
		config::save('include_mode', 0, 'blescanner');
		event::add('blescanner::includeState', array(
			'mode' => 'learn',
			'state' => 0)
		);
	}
}

if (isset($result['started'])) {
	if ($result['started'] == 1) {
		log::add('blescanner','info','Antenna ' . $result['source'] . ' alive sending known devices');
		if ($result['source'] != 'local'){
			$remotes = blescanner_remote::all();
			foreach ($remotes as $remote){
				if ($remote->getRemoteName() == $result['source']){
					$remote->setConfiguration('lastupdate',date("Y-m-d H:i:s"));
					$remote->save();
					break;
				}
			}
		}
		usleep(500);
		blescanner::sendIdToDeamon();
	}
}
if (isset($result['heartbeat'])) {
	if ($result['heartbeat'] == 1) {
		log::add('blescanner','info','This is a heartbeat from antenna ' . $result['source']);
		if ($result['source'] != 'local'){
			$remotes = blescanner_remote::all();
			foreach ($remotes as $remote){
				if ($remote->getRemoteName() == $result['source']){
					$remote->setConfiguration('lastupdate',date("Y-m-d H:i:s"));
					$remote->save();
					break;
				}
			}
		}
	}
}

if (isset($result['devices'])) {
	
	foreach ($result['devices'] as $key => $datas) {
		if (!isset($datas['id'])) {
			continue;
		}
		$blescanner = blescanner::byLogicalId($datas['id'], 'blescanner');
		if (!is_object($blescanner)) {
			if ($datas['learn'] != 1) {
				continue;
			}
			log::add('blescanner','info','This is a learn from antenna ' . $datas['source']);
			$blescanner = blescanner::createFromDef($datas);
			if (!is_object($blescanner)) {
				log::add('blescanner', 'debug', __('Aucun équipement trouvé pour : ', __FILE__) . secureXSS($datas['id']));
				continue;
			}
			event::add('jeedom::alert', array(
				'level' => 'warning',
				'page' => 'blescanner',
				'message' => '',
			));
			event::add('blescanner::includeDevice', $blescanner->getId());
		}
		if (!$blescanner->getIsEnable()) {
			continue;
		}
		if (isset($datas['rssi'])) {
			$cmdremote = $blescanner->getCmd(null, 'rssi');
			if (!is_object($cmdremote)) {
				$cmdremote = new blescannerCmd();
				$cmdremote->setLogicalId('rssi');
				$cmdremote->setIsVisible(0);
				$cmdremote->setIsHistorized(1);
				$cmdremote->setName(__('Rssi ', __FILE__));
				$cmdremote->setType('info');
				$cmdremote->setSubType('numeric');
				$cmdremote->setUnite('dbm');
				$cmdremote->setEqLogic_id($blescanner->getId());
				$cmdremote->save();
			}
			$cmdraw = $blescanner->getCmd(null, 'rawdata');
			if (!is_object($cmdraw)) {
				$cmdraw = new blescannerCmd();
				$cmdraw->setLogicalId('rawdata');
				$cmdraw->setIsVisible(0);
				$cmdraw->setIsHistorized(0);
				$cmdraw->setName(__('Données brutes', __FILE__));
				$cmdraw->setType('info');
				$cmdraw->setSubType('string');
				$cmdraw->setEqLogic_id($blescanner->getId());
				$cmdraw->save();
			}
			if ($cmdremote->getConfiguration('repeatEventManagement') != "always"){
				$cmdremote->setConfiguration('repeatEventManagement',"always");
				$cmdremote->save();
			}
			$cmdremote->event($datas['rssi']);
			$cmdpresent = $blescanner->getCmd(null, 'present');
			if (!is_object($cmdpresent)) {
				$cmdpresent = new blescannerCmd();
				$cmdpresent->setLogicalId('present');
				$cmdpresent->setIsVisible(0);
				$cmdpresent->setIsHistorized(1);
				$cmdpresent->setName(__('Present', __FILE__));
				$cmdpresent->setType('info');
				$cmdpresent->setSubType('binary');
				$cmdpresent->setTemplate('dashboard','line');
				$cmdpresent->setTemplate('mobile','line');
				$cmdpresent->setEqLogic_id($blescanner->getId());
				$cmdpresent->save();
			}
			if (isset($datas['present']) && $datas['present']==0) {
				$cmdpresent->event(0);
			} else {
				$cmdpresent->event(1);
			}
		}
	}
}
