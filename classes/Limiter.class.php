<?php 
/**
 * @desc Library of functions to prevent spamming grooveshark with multiple requests in a short time period.
 * @desc This library uses /utilities/limiter.inc.php to store unix timestamps of the most recent function requests
 * @author Phil Gale
 *
 */
	class Limiter {
		
		public static function invoke($requestName){
			$timeout = 30;
			$timeoutExpiry = Limiter::lastRuntime($requestName) + $timeout;
			if (time() >= $timeoutExpiry) {
				Limiter::updateTimestamp($requestName);
				return true;
			} else {
				API::response('error', 'php_timeout', 'This script has already ran within the last ' . $timeout . ' seconds, please wait to avoid spamming grooveshark with requests');
			}
		}
		
		private static function lastRuntime($requestName){
			$limiterDetails = explode(",", file_get_contents('utilities/limiter.inc.php'));
			if ($requestName == 'playlist'){
				return $limiterDetails[0];
			} else {
				return $limiterDetails[1];
			}
		}
		
		private static function updateTimestamp($requestName){
			$currentContent = explode(",", file_get_contents('utilities/limiter.inc.php'));
			$newContent = '';
			if ($requestName == 'playlist'){
				$newContent = time() . ',' . $currentContent[1];
			} else {
				$newContent = $currentContent[0] . ',' . time();
			}
			file_put_contents('utilities/limiter.inc.php', $newContent);
		}
		
	}