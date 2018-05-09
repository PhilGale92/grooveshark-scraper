<?php 
/**
 * @desc Library of functions to save basic logging information within the 'access_log.inc.php' file
 * @desc This library uses /utilities/access_log.inc.php to store logging data for requests
 * @author Phil Gale
 *
 */
	class Log  {
		
		
		
		public function __construct($message = ''){			
			$timestamp = time();
			
			$pathToLogs = root . '/utilities/access_log.inc.php';
			
			$userData = array (
				'unixTime' => $timestamp,
				'timestamp' => date('F j, Y H:i:s', $timestamp),
				'ip' => $_SERVER['REMOTE_ADDR'],
			);
			$this->user = (object) $userData;
			$this->message = $message;
			if (Cache::get('lastDebug') == null) {
				$this->debug = (object) Cache::dump();
			} else {
				$this->debug = Cache::get('lastDebug');
			}
			
			$fileHandle = fopen($pathToLogs, 'a+');
			fwrite($fileHandle, json_encode($this) . PHP_EOL);
			fclose($fileHandle);
		}
		
		public static function dump(){
			$pathToLogs = root . '/utilities/access_log.inc.php';
			$loggedDataLines = array_reverse(explode(PHP_EOL, file_get_contents($pathToLogs)));
			ob_start();
			foreach ($loggedDataLines as $loggedData){
				if ($loggedData == '') continue;
				krumo(json_decode($loggedData));
			}
			return ob_get_clean();
		}
		
		public static function wipe(){
			$pathToLogs = root . '/utilities/access_log.inc.php';
			file_put_contents($pathToLogs, '');
			API::response('success', 'success', 'Logs wiped');
		}
		
	}