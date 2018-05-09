<?php 
/**
 * @desc Library of top-level methods acting as the unoffical grooveshark API
 * @author Phil Gale
 *
 */
	class Grooveshark {
		
		/**
		 * @desc Fetches HTML from grooveshark which is used to scrape out any usefull variables that can be placed into the Cache (short term storage)
		 * @desc This method also uses the newly aquired cache to build both a secret key & a communicationToken for later use
		 * @param string $url
		 */
		public static function scrapeData($url){
			# V2
			# The preloader returns a vanilla js array, so convert it to json and convert it to a PHP array!
			$preloaderHtml = API::scrape('http://grooveshark.com/preload.php?getCommunicationToken=1&hash=&' . time());
			preg_match("/tokenData = {(.*)};/is", $preloaderHtml, $matches);
			$scrapeResult = json_decode( '{' . $matches[1] . '}', true);
			$getCommunicationToken  = $scrapeResult['getCommunicationToken']; 
			$sessionIdGS = $scrapeResult['getGSConfig']['sessionID']; 
			
			$country = (object) $scrapeResult['getGSConfig']['country'];
			Cache::set('country', $country);
			Cache::set('session', $sessionIdGS);
			Cache::set('uuid', $scrapeResult['getGSConfig']['uuid']);
			Cache::set('privacy', $scrapeResult['getGSConfig']['user']['Privacy']);
			
			API::buildSecretKey($sessionIdGS);
			API::buildCommunicationToken();
			
			/** OLD 
			* 
			* 	$rawHtml = API::scrape($url);
			* 	
			* 	preg_match("/gsConfig = (.*?);/", $rawHtml, $matches);
			* 	
			* 	$scrapeResult = json_decode($matches[1], true);
			* 	
			* 	$country = (object) $scrapeResult['country'];
			* 	Cache::set('country', $country);
			* 	Cache::set('session', $scrapeResult['sessionID']);
			* 	Cache::set('uuid', $scrapeResult['uuid']);
			* 	Cache::set('privacy', $scrapeResult['user']['Privacy']);
			* 	
			* 	API::buildSecretKey($scrapeResult['sessionID']);
			* 	API::buildCommunicationToken();
			*/
		}
		
		/**
		 * @desc Used to find the given profiles "profileId", which is then placed inside the Cache storage
		 * @param string $profile
		 */
		public static function getUserData($profile){
			$data = API::buildCurlHeader();
			$data->method = 'getItemByPageName';
			$data->parameters->name = $profile;
			$userData = json_decode(API::connect($data));
				
			Cache::set('profileId', $userData->result->user->UserID);
		}
		
		
		/**
		 * @desc Used to find all current playlists for a given grooveshark username
		 * @param string $profileName
		 * @return boolean OR array
		 */
		public static function listPlaylists($profileName){
			$validatedProfile = preg_match('/^[a-zA-Z0-9_.]+$/', $profileName);
			if ($validatedProfile ==! false){
				Limiter::invoke('playlist');
				Grooveshark::scrapeData('http://grooveshark.com/!#/' . $profileName);
				Grooveshark::getUserData($profileName);
				
				$data = API::buildCurlHeader();
				$data->parameters->userID = Cache::get('profileId');
				$data->parameters->limit = 1000;
				$data->method = 'userGetPlaylists';
				
				$playlistData = json_decode(API::connect($data));
				
				if (!is_array($playlistData->result->Playlists)) API::response('error', 'user', 'Profile not found');
				
				new Log('Listing request for ' . $profileName);
				
				return $playlistData->result->Playlists;
			} else return false;
		}
		
		/**
		 * @desc Scrapes data from grooveshark, and builds a list of recently played music
		 * @param string $profileName (Validated agaisnt "a-zA-Z0-9_.")
		 * @param string $playlistName
		 * @return boolean OR array
		 */
		public static function getPlaylist($profileName, $playlistName){
			$validatedProfile = preg_match('/^[a-zA-Z0-9_.]+$/', $profileName);
			if ($validatedProfile ==! false){
				Limiter::invoke('playlist');
				Grooveshark::scrapeData('http://grooveshark.com/#!/' . $profileName);
				Grooveshark::getUserData($profileName);
				
				$data = API::buildCurlHeader();
				$data->method = 'userGetPlaylists';
				$data->parameters->userID = Cache::get('profileId');
				$data->parameters->limit = 1000;
				
				$playlistDataRaw = API::connect($data);
				$decodedData = json_decode($playlistDataRaw);
				
				if (!is_array($decodedData->result->Playlists)) API::response('error', 'user', 'Profile not found');
				
				new Log('Playlist requested ' . $playlistName . ' (' . $profileName . ')');
				
				$selectedSongs = Grooveshark::parsePlaylists($decodedData, $playlistName);
				if ($selectedSongs == null) API::response('error', 'user', 'Playlist not found');
				return $selectedSongs;
			} else return false;
		}
		
		/**
		 * @desc Formats the full set of playlists, and finds the selected list
		 * @param array $playListData
		 * @param string $playlistName
		 * @return array OR null
		 */
		public static function parsePlaylists($playListData, $playlistName){
			foreach ($playListData->result->Playlists as $playlist){
				if ($playlist->Name == $playlistName){
					# Now to get the songs from the list
					$data = API::buildCurlHeader();
					$data->method = 'playlistGetSongs';
					$data->parameters->playlistID = $playlist->PlaylistID;
					return json_decode(API::connect($data))->result->Songs;
				}
			}
			return null;
		}
		
		/**
		 * @desc Gets the streamKey & ip address that a given song can be found on
		 * @param integer $songId
		 * @return array 
		 */
		public static function getStreamSongKey($songId, $quality = "mobile"){
			$data = API::buildCurlHeader();
			$data->parameters->songID = $songId;
			if ($quality == 'mobile') $data->parameters->mobile = true; else $data->parameters->mobile = false;
			$data->parameters->prefetch = false;
			$data->parameters->country = Cache::get('country');
			$data->method = 'getStreamKeyFromSongIDEx';
			$data->header->client = 'jsqueue';
			
			
			$debug = false;
			return json_decode(API::connect($data, true, $debug, true));
		}
		
		/**
		 * @desc Provides a wrapper for retrieving a song file, and storing it within the /download_storage/"randomIdentifier"/ dir for later use (within the batch_request.php file).
		 * @param string $profileName
		 * @param integer $songId
		 * @param string $songName
		 * @param string $quality
		 * @param string $randomIdentifier
		 * @return TRUE or FALSE
		 */
		public static function retrieveSong($profileName = '', $songId, $songName, $quality){
			Limiter::invoke('download');
			
			if ($profileName != '') $profileName = '#!/' . $profileName;
			
			Grooveshark::scrapeData('http://grooveshark.com/' . $profileName);
			$songStreamData = Grooveshark::getStreamSongKey($songId, $quality);
			
			if (!isset($songStreamData->result)) API::response('error', 'user', 'No song result found');
			
			new Log('Requested song ' . $songName);
			
			$song = new File();
			$song->url = 'http://' . $songStreamData->result->ip . "/stream.php?streamKey=" . $songStreamData->result->streamKey;
			$song->name = $songName;
			$song->identifier = Cache::get('randomIdentifier');
			$response = $song->retrieve();
			return $response;
		}
		
		/**
		 * @desc Provides a way to request a single song, by passing in the full grooveshark url. If successful, the file is downloaded directly.
		 * @param string $fullUrl
		 * @return string or FALSE
		 * 
		 */
		public static function getSongFromUrl($fullUrl){
			if (strpos($fullUrl, 'grooveshark') === false) return;
			Limiter::invoke('download');
			
			# First split the token out from the URL
			$urlParts = explode("/", $fullUrl);
			$urlParts2 = explode("?", $urlParts[count($urlParts) - 1]);
			$token = $urlParts2[0];
			
			# Prepare the cache
			Grooveshark::scrapeData($fullUrl);
			Cache::set('randomIdentifier', 'token_' . API::randomString());
			
			# Convert the url token into a songId
			$data = API::buildCurlHeader();
			$data->method = 'getSongFromToken';
			$data->parameters->token = $token;
			$data->parameters->country = $data->header->country;
			$songData = json_decode(API::connect($data));
			
			# Use the songId to get streaming data
			$songStreamData = Grooveshark::getStreamSongKey($songData->result->SongID, 'high');
			if (!isset($songStreamData->result)) API::response('error', 'user', 'No song result found');
			
			# Download the file into local storage
			$song = new File();
			$song->url = 'http://' . $songStreamData->result->ip . "/stream.php?streamKey=" . $songStreamData->result->streamKey;
			$song->name = $songData->result->Name;
			$song->identifier = Cache::get('randomIdentifier');
			$song->retrieve();
			
			new Log('Requested song with token ' . $token);
			
			# Zip up the song folder
			File::packageDir();
			
			# Send the http download headers
			File::download();
		}
		
		/**
		 * @desc Provides a way to list songs by an artist, by passing in the full grooveshark url. If successful, a list of available songs is displayed.
		 * @param string $artistUrl
		 * @return Array
		 *
		 */
		public static function getArtistSongs($artistUrl){
			if (strpos($artistUrl, 'grooveshark') === false) return;
			# Get the artistId
			$urlParts = explode("/", $artistUrl);
			$artistId = $urlParts[count($urlParts) - 1];
			
			if (!is_numeric($artistId)) return false;
			Limiter::invoke('playlist');
			Grooveshark::scrapeData($artistUrl);
			
			# Get song array
			$data = API::buildCurlHeader();
			$data->method = 'artistGetArtistSongs';
			$data->parameters->artistID = $artistId;
			$songData = json_decode(API::connect($data, true))->result;
			
			new Log('Requested artist playlist with url ' . $artistUrl);
			
			return $songData;
		}
	}
	