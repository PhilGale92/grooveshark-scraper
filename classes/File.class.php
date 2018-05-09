<?php 
/**
 * @desc Provides a library of file manipulation, and setting HTTP headers (for use when downloading the songs after a link has been constructed, by the other classes)
 * @author Phil Gale
 *
 */
	class File {
		
		/**
		 * @desc Basic function that removes all illigal charecters from a given string, so file IO won't fail on filenames
		 * @param string $string
		 * @return clean string
		 */
		public static function fixFileName($string){
			$illigalCharArray = array (
				':', ';', '%', '/', '?', '.', '<', '>', '\\', 
				'\'', '"', '`','�', '�', '�', '$','*', '=', '+',
				'@', '{', '}', '#', 
			);
			return str_replace($illigalCharArray, '', $string);
		}
		
		/**
		 * @desc Recursively removes a given folder, and all of its contents
		 * @param string $dir
		 * @return boolean
		 */
		private static function rrmdir($dir){
				if (substr($dir, strlen($dir)-1, 1) != '/') $dir .= '/';
				if ($handle = opendir($dir)) {
					while ($obj = readdir($handle)) {
						if ($obj != '.' && $obj != '..') {
							if (is_dir($dir.$obj)) {
								if (!deleteDir($dir.$obj)) return false;
							} elseif (is_file($dir.$obj)) {
								if (!unlink($dir.$obj))	return false;
							}
						}
					}
					closedir($handle);
					if (!@rmdir($dir))
						return false;
					return true;
				}
				return false;
			}
		
		/**
		 * @desc Packs a given /download_storage/$randomIdentifier/ directory into a .zip file, and removes the original folder when complete. Returns the new archive filename
		 * @param string $randomIdentifier
		 * @return string $randomIdentifier.zip
		 */
		public static function packageDir(){
			$randomIdentifier = Cache::get('randomIdentifier');
			$path = root . '/download_storage/';
			$folderDir = $path . $randomIdentifier . '/';
			$archive = new ZipArchive();
			if ($archive->open($path . $randomIdentifier . '.zip', ZIPARCHIVE::CREATE) !== TRUE){
				die ("Could not open archive");
			}
			
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderDir));
			foreach ($iterator as $key => $value){
				$baseValue = basename($key);
				if ($baseValue == '.' || $baseValue == '..') continue;
				$archive->addFile(realpath($key), $baseValue) or die ("ERROR: Could not add file $key");
			}
			
			$archive->close();
			File::rrmdir($folderDir);
			return $randomIdentifier . '.zip';
		}
		
		
		/**
		 * @desc Gets the song contents based off $this->url, and named using $this->name. Before placing it within a .mp3 file based within /download_storage/. 
		 * @param string $this->url
		 * @param string $this->name
		 * @return boolean
		 */
		public function retrieve(){
			if (!file_exists(root . '/download_storage/' . $this->identifier)){ mkdir(root . '/download_storage/' . $this->identifier); }
			$localFileName = File::fixFileName($this->name) . '.mp3';
			$localFilePath = root . '/download_storage/' . $this->identifier . '/' . $localFileName;
			
			$songContents = API::scrape($this->url);
			
			if ($songContents === false || empty($songContents) || strlen($songContents) == 0) {
				# Debugging to go here, to find the cause for timeouts when connecting to grooveshark..
				new Log('File download failed');
				API::response('error', 'timeout', 'failed');
			}
			$fileIO = @file_put_contents($localFilePath, $songContents);
			if ($fileIO === false) API::response('error', 'user', 'System error: File I/O failed');
			
			if ($fileIO !== false || !file_exists($localFilePath)) {
				return true;
			}
			return false;
		}
		
		/**
		 * @desc Purges the /download_storage/ directory of downloads
		 */
		public static function purge(){
			$files = glob('download_storage/*');
			foreach ($files as $file){
				if (is_file($file)){
					unlink($file);
				}
			}
		}
		
		/**
		 * @desc Downloads a (local) zip package directly by changing the http headers
		 * @param Cache::get('randomIdentifier');
		 */
		public static function download(){
			$randomIdentifier = Cache::get('randomIdentifier');
			$filePath = root . '/download_storage/' . $randomIdentifier . '.zip';
			
			if (!file_exists($filePath)) API::response('error', 'user', 'System error: File I/O failed');
			
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header('Last-Modified: '.gmdate ('D, d M Y H:i:s', filemtime ($filePath)).' GMT');
			header("Cache-Control: private", false);
			header("Content-type: octet/stream");
			header("Content-disposition: attachment; filename=\"" . $randomIdentifier . ".zip\";"); 
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: " . filesize($filePath));
			header("Connection: close");
			
			readfile($filePath);
			die();
		}
	}