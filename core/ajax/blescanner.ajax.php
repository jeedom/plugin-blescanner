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

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception('401 Unauthorized');
	}

	ajax::init();

	if (init('action') == 'changeIncludeState') {
		blescanner::changeIncludeState(init('state'), init('mode'));
		ajax::success();
	}
	
	if (init('action') == 'getMobileGraph') {
		ajax::success(blescanner::getMobileGraph());
	}
	
	if (init('action') == 'getMobileHealth') {
		ajax::success(blescanner::getMobileHealth());
	}
	
	if (init('action') == 'saveAntennaPosition') {
		ajax::success(blescanner::saveAntennaPosition(init('antennas')));
	}
	
	if (init('action') == 'autoDetectModule') {
		$eqLogic = blescanner::byId(init('id'));
		if (!is_object($eqLogic)) {
			throw new Exception(__('blescanner eqLogic non trouvé : ', __FILE__) . init('id'));
		}
		foreach ($eqLogic->getCmd() as $cmd) {
			$cmd->remove();
		}
		$eqLogic->applyModuleConfiguration();
		ajax::success();
	}
	
	if (init('action') == 'getModelListParam') {
		$blescanner = blescanner::byId(init('id'));
		if (!is_object($blescanner)) {
			ajax::success(array());
		}
		ajax::success($blescanner->getModelListParam(init('conf')));
	}
	
	if (init('action') == 'save_blescannerRemote') {
		$blescannerRemoteSave = jeedom::fromHumanReadable(json_decode(init('blescanner_remote'), true));
		$blescanner_remote = blescanner_remote::byId($blescannerRemoteSave['id']);
		if (!is_object($blescanner_remote)) {
			$blescanner_remote = new blescanner_remote();
		}
		utils::a2o($blescanner_remote, $blescannerRemoteSave);
		$blescanner_remote->save();
		ajax::success(utils::o2a($blescanner_remote));
	}

	if (init('action') == 'get_blescannerRemote') {
		$blescanner_remote = blescanner_remote::byId(init('id'));
		if (!is_object($blescanner_remote)) {
			throw new Exception(__('Remote inconnu : ', __FILE__) . init('id'), 9999);
		}
		ajax::success(jeedom::toHumanReadable(utils::o2a($blescanner_remote)));
	}

	if (init('action') == 'remove_blescannerRemote') {
		$blescanner_remote = blescanner_remote::byId(init('id'));
		if (!is_object($blescanner_remote)) {
			throw new Exception(__('Remote inconnu : ', __FILE__) . init('id'), 9999);
		}
		$blescanner_remote->remove();
		ajax::success();
	}
	
	if (init('action') == 'sendRemoteFiles') {
        ajax::success(blescanner::sendRemoteFiles(init('remoteId')));
     }
	 
	 if (init('action') == 'getRemoteLog') {
        ajax::success(blescanner::getRemoteLog(init('remoteId')));
     }
	 
	 if (init('action') == 'getRemoteLogDependancy') {
        ajax::success(blescanner::getRemoteLog(init('remoteId'),'_dependancy'));
     }
	 
	 if (init('action') == 'launchremote') {
        ajax::success(blescanner::launchremote(init('remoteId')));
     }
	 
	 if (init('action') == 'stopremote') {
        ajax::success(blescanner::stopremote(init('remoteId')));
     }
	 
	 if (init('action') == 'remotelearn') {
        ajax::success(blescanner::remotelearn(init('remoteId'), init('state')));
     }
	 
	 if (init('action') == 'dependancyRemote') {
        ajax::success(blescanner::dependancyRemote(init('remoteId')));
     }
	 
	 if (init('action') == 'aliveremote') {
        ajax::success(blescanner::aliveremote(init('remoteId')));
     }
	
	if (init('action') == 'changeLogLive') {
		ajax::success(blescanner::changeLogLive(init('level')));
	}

	throw new Exception('Aucune methode correspondante');
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}
?>
