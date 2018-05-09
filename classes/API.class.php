<?php 
/**
 * @desc Library of functions required to interface with grooveshark, including the cURL functions & methods for building the secure tokens.
 * @author Phil Gale
 *
 */
	class API {
		
		/**
		 * @desc Gets data from a given url
		 * @param string $url
		 * @return curl result $htmlData
		 */
		public static function scrape($url){
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0" );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 2 );
			$htmlData = curl_exec( $ch );
			curl_close ( $ch );
			
			return $htmlData;
		}
		
		/**
		 * @desc Constructs the secretKey variable, and places it into the cache
		 * @param string $session
		 */
		public static function buildSecretKey($session){
			Cache::set('secretKey', md5($session));
		}
		
		/**
		 * @desc Constructs the communicationToken, by using the secretKey
		 */
		public static function buildCommunicationToken(){
			$data = API::buildCurlHeader();
			$data->method = 'getCommunicationToken';
			$data->parameters->secretKey = Cache::get('secretKey');
			$response = API::connect($data, false);
			
			$communicationToken = json_decode($response)->result;
			Cache::set('communicationToken', $communicationToken);
		}
		
		/**
		 * @desc Utility method to assist in building the secureToken
		 * @return string
		 * @access private
		 */
		private static function buildSecureRandomizer(){
			$rand = sprintf("%06x",mt_rand(0,0xffffff));
			$lastRandom = Cache::get('lastRandom');
			if ($rand !== $lastRandom){
				Cache::set('lastRandom', $rand);
				return $rand;
			} else {
				return API::createNewRandomizer();
			}
		}
		
		/**
		 * @desc Utility method to create a basic random string
		 * @return string
		 */
		public static function randomString($length = 8){
			return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
		}
		
		/**
		 * @desc Constructs the secureToken, by using a given method, the communicationToken from the cache, and the 'revToken' key. If jsQueue is FALSE the controllerKey is used, rather than revToken
		 * @param string $method
		 * @param boolean $jsQueue Defaults to FALSE
		 * @access private
		 * @return string $secureToken
		 */
		private static function buildSecureToken($method, $jsQueue = false){
			$randomString = API::buildSecureRandomizer();
			$communicationToken = Cache::get('communicationToken');
			if ($jsQueue == true){
				$token = Keyring::get('controllerKey');
			} else {
				$token = Keyring::get('revToken');
			}
			return $randomString . sha1($method . ':' . $communicationToken . ':' . $token . ':' . $randomString);
		}
		
		/**
		 * @desc Provides a uniform method of connecting to grooveshark via cURL. If $secure is TRUE, this method will attempt to build a secureToken, using variables from the Cache, Keyring & the $data->method variable.
		 * @desc If $debug is TRUE, the result will be returned as part of a hash array (index of 'result', and cURL will return the results of curl_getinfo() within the index of 'debug').
		 * @desc If $jsQueue is TRUE, the secureToken will be built using the 'controllerKey' rather than the 'revToken'
		 * @param stdClass $data
		 * @param boolean $secure optional defaults to TRUE
		 * @param boolean $debug optional defaults to FALSE
		 * @param boolean $jsQueue optional defaults to FALSE
		 * @return multitype:mixed |mixed
		 */
		public static function connect($data, $secure = true, $debug = false, $jsQueue = false){
			$url = 'https://grooveshark.com/more.php?' . $data->method;
			if ($secure == true){
				$data->header->token = API::buildSecureToken($data->method, $jsQueue);
			}
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0" );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, true);
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
				'Accept: application/json',
				'Content-Type: application/json',
			));
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 30);
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 2 );
			$result = curl_exec( $ch );
			$debugData = curl_getinfo( $ch );
			curl_close ( $ch );
			
			Cache::set('lastDebug', array('info' => $debugData, 'params' => $data, 'result' => json_decode($result)));
			
			if ($debug == true) {
				$rse =  array ( 'result' => $result, 'debug' => $debugData);
				var_dump($rse['debug'] ) ;
				return $rse['result'];
			}
			
			return $result;
		}
		
		
		/**
		 * @desc Provides a way of returning basic information to the gsv2.js core.
		 * @desc Formatted as (within console.log): Object { $type="$message"}
		 * @param string $type
		 * @param string $message
		 */
		public static function response($type, $subType, $data){
			$response = new stdClass();
			$response->$type = new stdClass();
			$response->$type->$subType = $data;
			$response->alive = true;
			echo json_encode($response);
			die;
		}
		
		/**
		 * @desc Builds a standard cURL header object, which is used in every grooveshark API request
		 * @return stdClass
		 */
		public static function buildCurlHeader(){
			$headerObj = new stdClass();
			$headerObj->parameters = new stdClass();
			$headerObj->header = new stdClass();
			$headerObj->header->client = Keyring::get('clientLibrary');
			$headerObj->header->clientRevision = Keyring::get('clientRevision');
			$headerObj->header->country = Cache::get('country');
			$headerObj->header->privacy = Cache::get('privacy');
			$headerObj->header->session = Cache::get('session');
			$headerObj->header->uuid = Cache::get('uuid');
				
			return $headerObj;
		}
	}