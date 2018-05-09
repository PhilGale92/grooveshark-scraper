<?php 
/**
 * @desc Wrapper for temporary variables, that are only required for a single session
 * @author Phil Gale
 *
 */
	class Cache {
		
		private static $cache = array();
		
		/**
		 * @desc Provides a method to update a given cache variable
		 * @param string $key
		 * @param multitype $value
		 */
		public static function set($key, $value){
			self::$cache[$key] = $value;
		}
		
		/**
		 * @desc Provides a method to fetch a given cache variable
		 * @param string $key
		 */
		public static function get($key){
			if (isset(self::$cache[$key])){
				return self::$cache[$key];
			} else return null;
		}
		
		/**
		 * @desc Returns a dump of all cache variables for logging purposes
		 * @return array self::$cache:
		 */
		public static function dump(){
			return self::$cache;
		}
	}
