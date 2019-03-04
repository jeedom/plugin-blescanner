# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import subprocess
import os,re
import logging
import sys
import argparse
import time
import datetime
import signal
import json
import traceback
from bluepy.btle import Scanner, DefaultDelegate
import globals
from threading import Timer
import thread
try:
	from jeedom.jeedom import *
except ImportError:
	print "Error: importing module from jeedom folder"
	sys.exit(1)
	
try:
    import queue
except ImportError:
    import Queue as queue

class ScanDelegate(DefaultDelegate):
	import globals
	def __init__(self):
		DefaultDelegate.__init__(self)

	def handleDiscovery(self, dev, isNewDev, isNewData):
		if isNewDev or isNewData:
			mac = dev.addr
			rssi = dev.rssi
			connectable = dev.connectable
			addrType = dev.addrType
			name = ''
			data =''
			manuf =''
			action = {}
			onlypresent = False
			if mac not in globals.IGNORE:
				logging.debug('SCANNER------'+str(dev.getScanData()) +' '+str(connectable) +' '+ str(addrType) +' '+ str(mac))
			findDevice=False
			for (adtype, desc, value) in dev.getScanData():
				if desc == 'Complete Local Name':
					name = value.strip()
				elif 'Service Data' in desc:
					data = value.strip()
				elif desc == 'Manufacturer':
					manuf = value.strip()
			action['id'] = mac.upper()
			action['type'] = 'default'
			action['name'] = name
			action['rssi'] = rssi
			action['source'] = globals.daemonname
			action['rawdata'] = str(dev.getScanData())
			if mac in globals.IGNORE:
				return
			globals.IGNORE.append(mac)
			if mac.upper() not in globals.KNOWN_DEVICES:
				if not globals.LEARN_MODE:
					logging.debug('SCANNER------It\'s an unknown packet but not sent because this device is not Included and I\'am not in learn mode ' +str(mac))
					return
				else:
					logging.debug('SCANNER------It\'s a unknown packet and I don\'t known this device so I learn ' +str(mac))
					action['learn'] = 1
					logging.debug(action)
					globals.JEEDOM_COM.add_changes('devices::'+action['id'],action)
			else:
				if len(action) > 2:
					if globals.LEARN_MODE:
						logging.debug('SCANNER------It\'s an unknown packet i know this device but i\'m in learn mode ignoring ' +str(mac))
						return
					logging.debug('SCANNER------It\'s a unknown packet and I known this device so I send ' +str(mac))
					logging.debug(action)
					if action['id'] not in globals.SEEN_DEVICES:
						globals.SEEN_DEVICES[action['id']] = {}
					if 'present' not in globals.SEEN_DEVICES[action['id']]:
						globals.JEEDOM_COM.add_changes('devices::'+action['id'],action)
						globals.SEEN_DEVICES[action['id']]['lastseen'] = int(time.time())
						globals.SEEN_DEVICES[action['id']]['present'] = 1
						logging.info('First Time SEEEEEEEEEN------' + str(action['id']) + ' || ' +str(globals.SEEN_DEVICES))
					elif globals.SEEN_DEVICES[action['id']]['present'] == 0:
						globals.JEEDOM_COM.add_changes('devices::'+action['id'],action)
						globals.SEEN_DEVICES[action['id']]['lastseen'] = int(time.time())
						globals.SEEN_DEVICES[action['id']]['present'] = 1
						logging.info('RE SEEEEEEEEEN------' + str(action['id']) + ' || ' +str(globals.SEEN_DEVICES))
					elif globals.SEEN_DEVICES[action['id']]['present'] == 1:
						globals.SEEN_DEVICES[action['id']]['lastseen'] = int(time.time())
						

def listen():
	global scanner
	globals.PENDING_ACTION=False
	jeedom_socket.open()
	logging.info("GLOBAL------Start listening...")
	globals.SCANNER = Scanner(globals.IFACE_DEVICE).withDelegate(ScanDelegate())
	logging.info("GLOBAL------Preparing Scanner...")
	globals.JEEDOM_COM.send_change_immediate({'learn_mode' : 0,'source' : globals.daemonname});
	thread.start_new_thread( read_socket, ('socket',))
	thread.start_new_thread( seen_handler, ('seen',))
	globals.JEEDOM_COM.send_change_immediate({'started' : 1,'source' : globals.daemonname});
	try:
		while 1:
			try:
				if globals.LEARN_MODE or (globals.LAST_CLEAR + 2.5)  < int(time.time()):
					globals.SCANNER.clear()
					globals.IGNORE[:] = []
					globals.LAST_CLEAR = int(time.time())
				globals.SCANNER.start()
				if globals.LEARN_MODE:
					globals.SCANNER.process(2)
				else:
					globals.SCANNER.process(1)
				globals.SCANNER.stop()
				if globals.SCAN_ERRORS > 0:
					logging.info("GLOBAL------Attempt to recover successful, reseting counter")
					globals.SCAN_ERRORS = 0
			except Exception, e:
				if not globals.PENDING_ACTION and not globals.LEARN_MODE: 
					if globals.SCAN_ERRORS < 5:
						logging.warning("GLOBAL------Exception on scanner (trying to resolve by myself " + str(globals.SCAN_ERRORS) + "): %s" % str(e))
						globals.SCAN_ERRORS = globals.SCAN_ERRORS+1
						globals.SCANNER = Scanner(globals.IFACE_DEVICE).withDelegate(ScanDelegate())
					elif globals.SCAN_ERRORS >=5 and globals.SCAN_ERRORS< 8:
						globals.SCAN_ERRORS = globals.SCAN_ERRORS+1
						logging.warning("GLOBAL------Exception on scanner (trying to resolve by myself " + str(globals.SCAN_ERRORS) + "): %s" % str(e))
						os.system('hciconfig ' + globals.device + ' down')
						os.system('hciconfig ' + globals.device + ' up')
						globals.SCANNER = Scanner(globals.IFACE_DEVICE).withDelegate(ScanDelegate())
					else:
						logging.error("GLOBAL------Exception on scanner (didn't resolve there is an issue with bluetooth) : %s" % str(e))
						logging.info("GLOBAL------Shutting down due to errors")
						globals.JEEDOM_COM.send_change_immediate({'learn_mode' : 0,'source' : globals.daemonname});
						time.sleep(2)
						shutdown()
			time.sleep(0.02)
	except KeyboardInterrupt:
		logging.error("GLOBAL------KeyboardInterrupt, shutdown")
		shutdown()

def seen_handler(name):
	while 1:
		for device in globals.SEEN_DEVICES:
			if 'present' in globals.SEEN_DEVICES[device] and globals.SEEN_DEVICES[device]['present'] == 1:
				if (globals.SEEN_DEVICES[device]['lastseen'] + 9) < int(time.time()):
					logging.info('Not SEEEEEEEEEN------ since 9s '+ str(device))
					globals.SEEN_DEVICES[device]['present'] = 0
					action = {}
					action['present']=0
					action['id']=device
					action['rssi'] = -200
					globals.JEEDOM_COM.add_changes('devices::'+device,action)
		time.sleep(1)
	

def read_socket(name):
	while 1:
		try:
			global JEEDOM_SOCKET_MESSAGE
			if not JEEDOM_SOCKET_MESSAGE.empty():
				logging.debug("SOCKET-READ------Message received in socket JEEDOM_SOCKET_MESSAGE")
				message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
				if message['apikey'] != globals.apikey:
					logging.error("SOCKET-READ------Invalid apikey from socket : " + str(message))
					return
				logging.debug('SOCKET-READ------Received command from jeedom : '+str(message['cmd']))
				if message['cmd'] == 'add':
					logging.debug('SOCKET-READ------Add device : '+str(message['device']))
					if 'id' in message['device']:
						globals.KNOWN_DEVICES[message['device']['id']] = message['device']
				elif message['cmd'] == 'remove':
					logging.debug('SOCKET-READ------Remove device : '+str(message['device']))
					if 'id' in message['device']:
						del globals.KNOWN_DEVICES[message['device']['id']]
						if message['device']['id'] in globals.KEEPED_CONNECTION:
							logging.debug("SOCKET-READ------This antenna should not keep a connection with this device, disconnecting " + str(message['device']['id']))
							try:
								globals.KEEPED_CONNECTION[message['device']['id']].disconnect()
							except Exception, e:
								logging.debug(str(e))
							if message['device']['id'] in globals.KEEPED_CONNECTION:
								del globals.KEEPED_CONNECTION[message['device']['id']]
							logging.debug("SOCKET-READ------Removed from keep connection list " + str(message['device']['id']))
				elif message['cmd'] == 'learnin':
					logging.debug('SOCKET-READ------Enter in learn mode')
					globals.LEARN_MODE_ALL = 0
					if message['allowAll'] == '1' :
						globals.LEARN_MODE_ALL = 1
					globals.LEARN_MODE = True
					globals.LEARN_BEGIN = int(time.time())
					globals.JEEDOM_COM.send_change_immediate({'learn_mode' : 1,'source' : globals.daemonname});
				elif message['cmd'] == 'learnout':
					logging.debug('SOCKET-READ------Leave learn mode')
					globals.LEARN_MODE = False
					globals.JEEDOM_COM.send_change_immediate({'learn_mode' : 0,'source' : globals.daemonname});
				elif message['cmd'] in ['action','refresh','helper','helperrandom']:
					logging.debug('SOCKET-READ------Attempt an action on a device')
					thread.start_new_thread( action_handler, (message,))
					logging.debug('SOCKET-READ------Action Thread Launched')
				elif message['cmd'] == 'stop':
					logging.info('SOCKET-READ------Arret du demon sur demande socket')
					globals.JEEDOM_COM.send_change_immediate({'learn_mode' : 0,'source' : globals.daemonname});
					time.sleep(2)
					shutdown()
		except Exception,e:
			logging.error("SOCKET-READ------Exception on socket : %s" % str(e))
		time.sleep(0.3)

def handler(signum=None, frame=None):
	logging.debug("GLOBAL------Signal %i caught, exiting..." % int(signum))
	shutdown()
	
def shutdown():
	logging.debug("GLOBAL------Shutdown")
	logging.debug("GLOBAL------Removing PID file " + str(globals.pidfile))
	logging.debug("GLOBAL------Closing all potential bluetooth connection")
	for device in list(globals.KEEPED_CONNECTION):
		logging.debug("GLOBAL------This antenna should not keep a connection with this device, disconnecting " + str(device))
		try:
			globals.KEEPED_CONNECTION[device].disconnect(True)
			logging.debug("Connection closed for " + str(device))
		except Exception, e:
			logging.debug(str(e))
	try:
		os.remove(globals.pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

parser = argparse.ArgumentParser(description='Blead Daemon for Jeedom plugin')
parser.add_argument("--device", help="Device", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--pidfile", help="Value to write", type=str)
parser.add_argument("--callback", help="Value to write", type=str)
parser.add_argument("--apikey", help="Value to write", type=str)
parser.add_argument("--socketport", help="Socket Port", type=str)
parser.add_argument("--sockethost", help="Socket Host", type=str)
parser.add_argument("--daemonname", help="Daemon Name", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
args = parser.parse_args()

if args.device:
	globals.device = args.device
if args.loglevel:
	globals.log_level = args.loglevel
if args.pidfile:
	globals.pidfile = args.pidfile
if args.callback:
	globals.callback = args.callback
if args.apikey:
	globals.apikey = args.apikey
if args.cycle:
	globals.cycle = float(args.cycle)
if args.socketport:
	globals.socketport = args.socketport
if args.sockethost:
	globals.sockethost = args.sockethost
if args.daemonname:
	globals.daemonname = args.daemonname

globals.socketport = int(globals.socketport)
globals.cycle = float(globals.cycle)

jeedom_utils.set_log_level(globals.log_level)
logging.info('GLOBAL------Start blead')
logging.info('GLOBAL------Log level : '+str(globals.log_level))
logging.info('GLOBAL------Socket port : '+str(globals.socketport))
logging.info('GLOBAL------Socket host : '+str(globals.sockethost))
logging.info('GLOBAL------Device : '+str(globals.device))
logging.info('GLOBAL------PID file : '+str(globals.pidfile))
logging.info('GLOBAL------Apikey : '+str(globals.apikey))
logging.info('GLOBAL------Callback : '+str(globals.callback))
logging.info('GLOBAL------Cycle : '+str(globals.cycle))
import devices
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)
globals.IFACE_DEVICE = int(globals.device[-1:])
try:
	jeedom_utils.write_pid(str(globals.pidfile))
	globals.JEEDOM_COM = jeedom_com(apikey = globals.apikey,url = globals.callback,cycle=globals.cycle)
	if not globals.JEEDOM_COM.test():
		logging.error('GLOBAL------Network communication issues. Please fix your Jeedom network configuration.')
		shutdown()
	jeedom_socket = jeedom_socket(port=globals.socketport,address=globals.sockethost)
	listen()
except Exception,e:
	logging.error('GLOBAL------Fatal error : '+str(e))
	logging.debug(traceback.format_exc())
	shutdown()
