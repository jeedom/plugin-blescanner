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

/* * ***************************Includes********************************* */
class blescanner extends eqLogic {
	/*     * ***********************Methode static*************************** */
	public static $_widgetPossibility = array('custom' => true);
	public static function createFromDef($_def) {
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'blescanner',
			'message' => __('Nouveau module detecté ' . $_def['type'], __FILE__),
		));
		if (!isset($_def['id']) || !isset($_def['type'])) {
			log::add('blescanner', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_def, true));
			event::add('jeedom::alert', array(
				'level' => 'danger',
				'page' => 'blescanner',
				'message' => __('Information manquante pour ajouter l\'équipement. Inclusion impossible', __FILE__),
			));
			return false;
		}
		$device = self::devicesParameters($_def['type']);
		$blescanner = blescanner::byLogicalId($_def['id'], 'blescanner');
		if (!is_object($blescanner)) {
			$eqLogic = new blescanner();
			$eqLogic->setName('BLE ' . $_def['name'] . ' ' . $_def['id']);
		}
		$eqLogic->setLogicalId($_def['id']);
		$eqLogic->setEqType_name('blescanner');
		$eqLogic->setIsEnable(1);
		$eqLogic->setIsVisible(1);
		$eqLogic->setConfiguration('device', $_def['type']);
		$eqLogic->setConfiguration('antenna', 'local');
		$eqLogic->setConfiguration('antennareceive','local');
		$eqLogic->setConfiguration('canbelocked',0);
		$eqLogic->setConfiguration('islocked',0);
		$eqLogic->setConfiguration('cancontrol',0);
		$eqLogic->setConfiguration('resetRssis',1);
		$eqLogic->setConfiguration('name','0');
		$eqLogic->setConfiguration('refreshlist',array());
		$eqLogic->setConfiguration('specificclass',0);
		$eqLogic->setConfiguration('needsrefresh',0);
		$eqLogic->setConfiguration('specificwidgets',0);
		$model = $eqLogic->getModelListParam();
		if (count($model) > 0) {
			$eqLogic->setConfiguration('iconModel', array_keys($model[0])[0]);
			if ($_def['type'] == 'niu') {
				$eqLogic->setConfiguration('iconModel', 'niu/niu_' . strtolower($_def['color']));
			}
		}
		$eqLogic->save();

		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'blescanner',
			'message' => __('Module inclu avec succès ' .$_def['name'].' ' . $_def['id'], __FILE__),
		));
		return $eqLogic;
	}

	public static function devicesParameters($_device = '') {
		$return = array();
		foreach (ls(dirname(__FILE__) . '/../config/devices', '*') as $dir) {
			$path = dirname(__FILE__) . '/../config/devices/' . $dir;
			if (!is_dir($path)) {
				continue;
			}
			$files = ls($path, '*.json', false, array('files', 'quiet'));
			foreach ($files as $file) {
				try {
					$content = file_get_contents($path . '/' . $file);
					if (is_json($content)) {
						$return += json_decode($content, true);
					}
				} catch (Exception $e) {

				}
			}
		}
		if (isset($_device) && $_device != '') {
			if (isset($return[$_device])) {
				return $return[$_device];
			}
			return array();
		}
		return $return;
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = 'blescanner';
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder('blescanner') . '/deamon.pid';
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec('sudo rm -rf ' . $pid_file . ' 2>&1 > /dev/null;rm -rf ' . $pid_file . ' 2>&1 > /dev/null;');
			}
		}
		$return['launchable'] = 'ok';
		$port = jeedom::getBluetoothMapping(config::byKey('port', 'blescanner'));
		if ($port == '') {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __('Le port n\'est pas configuré', __FILE__);
		}
		return $return;
	}

	public static function dependancy_info() {
		#must find a way to detect git instllation of bluepy on multiple platform
		$return = array();
		$return['log'] = 'blescanner_update';
		$return['progress_file'] = jeedom::getTmpFolder('blescanner') . '/dependance';
		$return['state'] = 'ok';
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('blescanner') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
	public static function deamon_start() {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$port = jeedom::getBluetoothMapping(config::byKey('port', 'blescanner'));
		$blescanner_path = realpath(dirname(__FILE__) . '/../../resources/blescannerd');
		$cmd = 'sudo /usr/bin/python ' . $blescanner_path . '/blescannerd.py';
		$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('blescanner'));
		$cmd .= ' --device ' . $port;
		$cmd .= ' --socketport ' . config::byKey('socketport', 'blescanner');
		$cmd .= ' --sockethost 127.0.0.1';
		$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/blescanner/core/php/jeeblescanner.php';
		$cmd .= ' --apikey ' . jeedom::getApiKey('blescanner');
		$cmd .= ' --daemonname local';
		$cmd .= ' --pid ' . jeedom::getTmpFolder('blescanner') . '/deamon.pid';
		log::add('blescanner', 'info', 'Lancement démon blescanner : ' . $cmd);
		$result = exec($cmd . ' >> ' . log::getPathToLog('blescanner_local') . ' 2>&1 &');
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('blescanner', 'error', __('Impossible de lancer le démon blescanner, vérifiez la log',__FILE__), 'unableStartDeamon');
			return false;
		}
		message::removeAll('blescanner', 'unableStartDeamon');
		config::save('include_mode', 0, 'blescanner');
		return true;
	}

	public static function sendIdToDeamon() {
		foreach (self::byType('blescanner') as $eqLogic) {
			$eqLogic->allowDevice();
			usleep(500);
		}
	}
	
	public static function socket_connection($_value) {
		if (config::byKey('port', 'blescanner', 'none') != 'none') {
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'blescanner'));
			socket_write($socket, $_value, strlen($_value));
			socket_close($socket);
		}
	}
	
	public static function changeLogLive($_level) {
		$value = array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => $_level);
		$value = json_encode($value);
		self::socket_connection($value,True);
	}

	public static function deamon_stop() {
		$pid_file = '/tmp/blescannerd.pid';
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		system::kill('blescannerd.py');
		system::fuserk(config::byKey('socketport', 'blescanner'));
		sleep(1);
	}

	public static function changeIncludeState($_state, $_mode) {
		if ($_mode == 1) {
			if ($_state == 1) {
				$allowAll = config::byKey('allowAllinclusion', 'blescanner');
				$value = json_encode(array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => 'learnin', 'allowAll' => $allowAll));
				self::socket_connection($value,True);
			} else {
				$value = json_encode(array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => 'learnout'));
				self::socket_connection($value,True);
			}
		}
	}
	public static function getTintedColor($hex, $lum) {
		$initColor = $hex;
		$hex = str_replace('#','',$hex);
		$lum = -((100-$lum)/100);
		if ($lum==0){
			return $initColor;
		}
		log::add('blescanner','debug',$hex . ' ' . $lum);
		$rgb = "#";
		foreach (range(0,2) as $i) {
			$c = intval(substr($hex,$i*2,2), 16);
			$c = strval(round(min(max(0, $c + ($c * $lum)), 255)));
			
			$rgb = $rgb . str_pad(dechex($c),2,'0',STR_PAD_LEFT);
		}
			return $rgb;
	}

/*     * *********************Methode d'instance************************* */
	public function getModelListParam($_conf = '') {
		if ($_conf == '') {
			$_conf = $this->getConfiguration('device');
		}
		$modelList = array();
		$param = false;
		$files = array();
		foreach (ls(dirname(__FILE__) . '/../config/devices', '*') as $dir) {
			if (!is_dir(dirname(__FILE__) . '/../config/devices/' . $dir)) {
				continue;
			}
			$files[$dir] = ls(dirname(__FILE__) . '/../config/devices/' . $dir, $_conf . '_*.jpg', false, array('files', 'quiet'));
			if (file_exists(dirname(__FILE__) . '/../config/devices/' . $dir . $_conf . '.jpg')) {
				$selected = 0;
				if ($dir . $_conf == $this->getConfiguration('iconModel')) {
					$selected = 1;
				}
				$modelList[$dir . $_conf] = array(
					'value' => __('Défaut', __FILE__),
					'selected' => $selected,
				);
			}
			if (count($files[$dir]) == 0) {
				unset($files[$dir]);
			}
		}
		$replace = array(
			$_conf => '',
			'.jpg' => '',
			'_' => ' ',
		);
		foreach ($files as $dir => $images) {
			foreach ($images as $imgname) {
				$selected = 0;
				if ($dir . str_replace('.jpg', '', $imgname) == $this->getConfiguration('iconModel')) {
					$selected = 1;
				}
				$modelList[$dir . str_replace('.jpg', '', $imgname)] = array(
					'value' => ucfirst(trim(str_replace(array_keys($replace), $replace, $imgname))),
					'selected' => $selected,
				);
			}
		}
		$needsrefresh = false;
		if ($this->getConfiguration('needsrefresh',0) != 0) {
			$needsrefresh = true;
		}
		$remark = false;
		$json = self::devicesParameters($_conf);
		if (isset($json['compatibility'])) {
			foreach ($json['compatibility'] as $compatibility){
				if ($compatibility['imglink'] == explode('/',$this->getConfiguration('iconModel'))[1]){
					$remark = $compatibility['remark'] . ' | ' . $compatibility['inclusion'];
					break;
				}
			}
		}
		$specificmodal = false;
		if ($this->getConfiguration('specificmodal',0) != 0) {
			$specificmodal = 'blescanner.' . $this->getConfiguration('device');
		}
		$cancontrol = false;
		if ($this->getConfiguration('cancontrol',0) != 0) {
			$cancontrol = true;
		}
		$canbelocked = false;
		if ($this->getConfiguration('canbelocked',0) != 0) {
			$canbelocked = true;
		}
		return [$modelList, $needsrefresh,$remark,$specificmodal,$cancontrol,$canbelocked];
	}

	public function postSave() {
		if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
			$this->applyModuleConfiguration();
		} else {
			$this->allowDevice();
			if ($this->getConfiguration('specificclass',0) == 1) {
				$device= $this->getConfiguration('device');
				require_once dirname(__FILE__) . '/../config/devices/'.$device.'/class/'.$device.'.class.php';
				$class= $device.'blescanner';
				$childrenclass = new $class();
				$childrenclass->postSaveChild($this);
			}
		}
	}

	public function preRemove() {
		$this->disallowDevice();
	}
	
	public function closestAntenna() {
		$closest = 'local';
		$rssicompare = -200;
		foreach ($this->getCmd() as $cmd){
			if (substr($cmd->getLogicalId(),0,4) == 'rssi'){
				$rssi = $cmd->execCmd();
				if ($rssi > $rssicompare) {
					$rssicompare = $rssi;
					$closest = substr($cmd->getLogicalId(),4);
				}
			}
		}
		return $closest;
	}

	public function allowDevice() {
		$value = array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => 'add');
		$islocked =0;
		$emitter = 'local';
		if ($this->getConfiguration('islocked',0)==1){
			if ($this->getConfiguration('antenna','local') == 'all'){
				$islocked = 0;
				$emitter = 'all';
			} else if ($this->getConfiguration('antenna','local') == 'local'){
				$islocked = 1;
				$emitter = 'local';
			} else {
				$islocked = 1;
				$emitter = $this->getConfiguration('antenna','local');
			}
		} else {
			if ($this->getConfiguration('antenna','local') == 'all'){
				$emitter = 'all';
			} else if ($this->getConfiguration('antenna','local') == 'local'){
				$emitter = 'local';
			} else {
				$emitter = $this->getConfiguration('antenna','local');
			}
		}
		if ($this->getConfiguration('antennareceive','local') == 'local' || $this->getConfiguration('antennareceive','local') == 'all'){
			$refresher = $this->getConfiguration('antennareceive','local');
		} else {
			$refresher = $this->getConfiguration('antennareceive','local');
		}
		if ($this->getLogicalId() != '') {
			$value['device'] = array(
				'id' => $this->getLogicalId(),
				'delay' => $this->getConfiguration('delay',0),
				'needsrefresh' => $this->getConfiguration('needsrefresh',0),
				'name' => $this->getConfiguration('name','0'),
				'refreshlist' => $this->getConfiguration('refreshlist',array()),
				'islocked' => $islocked,
				'emitterallowed' => $emitter,
				'refresherallowed' => $refresher,
			);
			$value = json_encode($value);
			self::socket_connection($value,True);
		}
	}

	public function disallowDevice() {
		if ($this->getLogicalId() == '') {
			return;
		}
		$value = json_encode(array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => 'remove', 'device' => array('id' => $this->getLogicalId())));
		self::socket_connection($value,True);
	}

	public function applyModuleConfiguration() {
		$this->setConfiguration('canbelocked',0);
		$this->setConfiguration('cancontrol',0);
		$this->setConfiguration('islocked',0);
		$this->setConfiguration('name','0');
		$this->setConfiguration('refreshlist',array());
		$this->setConfiguration('specificmodal',0);
		$this->setConfiguration('specificclass',0);
		$this->setConfiguration('needsrefresh',0);
		$this->setConfiguration('resetRssis',1);
		$this->setConfiguration('specificwidgets',0);
		$this->setConfiguration('applyDevice', $this->getConfiguration('device'));
		$this->save();
		if ($this->getConfiguration('device') == '') {
			return true;
		}
		$device = self::devicesParameters($this->getConfiguration('device'));
		if (!is_array($device)) {
			return true;
		}
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'blescanner',
			'message' => __('Périphérique reconnu, intégration en cours', __FILE__),
		));
		$this->setConfiguration('needsrefresh', 0);
		$this->setConfiguration('name', '');
		$this->setConfiguration('hasspecificmodal', '');
		if (isset($device['configuration'])) {
			foreach ($device['configuration'] as $key => $value) {
				$this->setConfiguration($key, $value);
			}
		}
		if (isset($device['category'])) {
			foreach ($device['category'] as $key => $value) {
				$this->setCategory($key, $value);
			}
		}
		$cmd_order = 0;
		$link_cmds = array();
		$link_actions = array();
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'blescanner',
			'message' => __('Création des commandes', __FILE__),
		));

		$ids = array();
		$arrayToRemove = [];
		if (isset($device['commands'])) {
			foreach ($this->getCmd() as $eqLogic_cmd) {
				$exists = 0;
				foreach ($device['commands'] as $command) {
					if ($command['logicalId'] == $eqLogic_cmd->getLogicalId()) {
						$exists++;
					}
				}
				if ($exists < 1) {
					$arrayToRemove[] = $eqLogic_cmd;
				}
			}
			foreach ($arrayToRemove as $cmdToRemove) {
				try {
					$cmdToRemove->remove();
				} catch (Exception $e) {

				}
			}
			foreach ($device['commands'] as $command) {
				$cmd = null;
				foreach ($this->getCmd() as $liste_cmd) {
					if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
						|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
						$cmd = $liste_cmd;
						break;
					}
				}
				try {
					if ($cmd == null || !is_object($cmd)) {
						$cmd = new blescannerCmd();
						$cmd->setOrder($cmd_order);
						$cmd->setEqLogic_id($this->getId());
					} else {
						$command['name'] = $cmd->getName();
						if (isset($command['display'])) {
							unset($command['display']);
						}
					}
					utils::a2o($cmd, $command);
					$cmd->setConfiguration('logicalId', $cmd->getLogicalId());
					$cmd->save();
					if (isset($command['value'])) {
						$link_cmds[$cmd->getId()] = $command['value'];
					}
					if (isset($command['configuration']) && isset($command['configuration']['updateCmdId'])) {
						$link_actions[$cmd->getId()] = $command['configuration']['updateCmdId'];
					}
					$cmd_order++;
				} catch (Exception $exc) {

				}
			}
		}

		if (count($link_cmds) > 0) {
			foreach ($this->getCmd() as $eqLogic_cmd) {
				foreach ($link_cmds as $cmd_id => $link_cmd) {
					if ($link_cmd == $eqLogic_cmd->getName()) {
						$cmd = cmd::byId($cmd_id);
						if (is_object($cmd)) {
							$cmd->setValue($eqLogic_cmd->getId());
							$cmd->save();
						}
					}
				}
			}
		}
		if (count($link_actions) > 0) {
			foreach ($this->getCmd() as $eqLogic_cmd) {
				foreach ($link_actions as $cmd_id => $link_action) {
					if ($link_action == $eqLogic_cmd->getName()) {
						$cmd = cmd::byId($cmd_id);
						if (is_object($cmd)) {
							$cmd->setConfiguration('updateCmdId', $eqLogic_cmd->getId());
							$cmd->save();
						}
					}
				}
			}
		}
		$this->save();
		if (isset($device['afterInclusionSend']) && $device['afterInclusionSend'] != '') {
			event::add('jeedom::alert', array(
				'level' => 'warning',
				'page' => 'blescanner',
				'message' => __('Envoi des commandes post-inclusion', __FILE__),
			));
			sleep(5);
			$sends = explode('&&', $device['afterInclusionSend']);
			foreach ($sends as $send) {
				foreach ($this->getCmd('action') as $cmd) {
					if (strtolower($cmd->getName()) == strtolower(trim($send))) {
						$cmd->execute();
					}
				}
				sleep(1);
			}

		}
		sleep(2);
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'blescanner',
			'message' => '',
		));
	}
	
	public function toHtml($_version = 'dashboard') {
		if ($this->getConfiguration('specificwidgets',0) == 1) {
			if ($this->getConfiguration('specificclass',0) == 1) {
				$device= $this->getConfiguration('device');
				require_once dirname(__FILE__) . '/../config/devices/'.$device.'/class/'.$device.'.class.php';
				$class= $device.'blescanner';
				$childrenclass = new $class();
				return $childrenclass->convertHtml($this,$_version);
			} else {
				$replace = $this->preToHtml($_version);
				if (!is_array($replace)) {
					return $replace;
				}
				$version = jeedom::versionAlias($_version);
				foreach ($this->getCmd() as $cmd) {
					if ($cmd->getType() == 'info') {
						$replace['#' . $cmd->getLogicalId() . '_history#'] = '';
						$replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
						$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
						$replace['#' . $cmd->getLogicalId() . '_collectDate#'] = $cmd->getCollectDate();
						if ($cmd->getIsHistorized() == 1) {
							$replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
						}
					} else {
						$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
					}
				}
				return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $this->getConfiguration('device'), 'blescanner')));
			}
		} else {
			return parent::toHtml($_version);
		}
	}

}

class blescannerCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function execute($_options = null) {
		if ($this->getType() != 'action') {
			return;
		}
		$eqLogic = $this->getEqLogic();
		if ($eqLogic->getConfiguration('specificclass',0) != 0) {
			$device= $eqLogic->getConfiguration('device');
			require_once dirname(__FILE__) . '/../config/devices/'.$device.'/class/'.$device.'.class.php';
			$class= $device.'blescanner';
			$childrenclass = new $class();
		}
		$values = explode(',', $this->getLogicalId());
		foreach ($values as $value) {
			$value = explode(':', $value);
			if (count($value) == 2) {
				switch ($this->getSubType()) {
					case 'slider':
						$data[trim($value[0])] = trim(str_replace('#slider#', $_options['slider'], $value[1]));
						break;
					case 'color':
						$data[trim($value[0])] = str_replace('#','',trim(str_replace('#color#', $_options['color'], $value[1])));
						break;
					case 'select':
						$data[trim($value[0])] = trim(str_replace('#listValue#', $_options['select'], $value[1]));
						break;
					case 'message':
						$data[trim($value[0])] = trim(str_replace('#message#', $_options['message'], $value[1]));
						$data[trim($value[0])] = trim(str_replace('#title#', $_options['title'], $data[trim($value[0])]));
						break;
					default:
						$data[trim($value[0])] = trim($value[1]);
				}
			}
		}
		if (isset($data['secondary'])){
			$data['secondary'] = $eqLogic->getCmd('info',$data['secondary'])->execCmd();
		}
		if (isset($data['classlogical'])){
			$data = $childrenclass->calculateOutputValue($eqLogic,$data,$_options);
		}
		$data['device'] = array(
				'id' => $eqLogic->getLogicalId(),
				'delay' => $eqLogic->getConfiguration('delay',0),
				'needsrefresh' => $eqLogic->getConfiguration('needsrefresh',0),
				'name' => $eqLogic->getConfiguration('name','0'),
		);
		if (count($data) == 0) {
			return;
		}
		if ($this->getLogicalId() == 'refresh' || $this->getLogicalId() == 'helper' || $this->getLogicalId() == 'helperrandom'){
			$data['name'] = $eqLogic->getConfiguration('name','0');
			$value = json_encode(array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => $this->getLogicalId(), 'device' => array('id' => $eqLogic->getLogicalId()), 'command' => $data));
		} else {
			$value = json_encode(array('apikey' => jeedom::getApiKey('blescanner'), 'cmd' => 'action', 'device' => array('id' => $eqLogic->getLogicalId()), 'command' => $data));
		}
		$sender = $eqLogic->getConfiguration('antenna','local');
		log::add('blescanner','info','Envoi depuis local');
	}
}