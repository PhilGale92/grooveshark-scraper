<?php 
/**
 * @author Phil Gale
 *  
 * Requirements
 * 	PHP V5.3
 * 
 *  Extensions
 *  	cURL
 */
	date_default_timezone_set("UTC");
	set_time_limit(0);
	define("root", dirname(__FILE__));
	
	# get utils
	require_once('utilities/enviroment.inc.php');
	require_once('front_end/html_template.php');

	# Load the classes
	require_once('classes/Log.class.php');
	require_once('classes/File.class.php');
	require_once('classes/Keyring.class.php');
	require_once('classes/API.class.php');
	require_once('classes/Cache.class.php');
	require_once('classes/Grooveshark.class.php');
	require_once('classes/Limiter.class.php');
	
	# Additional functionality

	# Global administrator functions
	if (isset($_GET['update'])){
		if ($_GET['update'] == 'cache'){
			Keyring::update();
		}
	}
	
	$adminKey = 'root';
	
	if (isset($_GET['file'])){
		if ($_GET['file'] == $adminKey){
			File::purge();
		}
	}
	
	if (isset($_GET['log'])){
		if ($_GET['log'] == $adminKey){
			if (isset($_GET['action'])){
				if ($_GET['action'] == 'wipe'){
					Log::wipe();
				}
			} else {
				echo $template['header'];
					echo '<div id="returned_content">';
						echo '<h1>System logs</h1>';
						echo '<a href="' . BASE_PATH . '" id="back_link">&lt;&lt; BACK</a>';
						echo '<hr/>';
						echo Log::dump();
					echo '</div>';
				echo $template['footer'];
				die;
			}
		}
	}
	
	$showBasicContents = true;
	ob_start();
	
	if (isset($_POST['grooveshark_url'])){
		if ($_POST['grooveshark_url'] != ''){
			$fullSongUrl = $_POST['grooveshark_url'];
			Grooveshark::getSongFromUrl($fullSongUrl);
		}
	}
	
	if (isset($_POST['grooveshark_user'])){
		if ($_POST['grooveshark_playlist'] == ''){
			if ($_POST['grooveshark_user'] != ''){
				$profileName = $_POST['grooveshark_user'];
				$playlistList = Grooveshark::listPlaylists($profileName);
				echo $template['header'];
				echo '<div id="returned_content">';
					echo '<h1>Copy one of these lines into the "playlist name" field on the main page</h1>';
					echo '<a href="" id="back_link">&lt;&lt; BACK</a>';
					echo '<hr/>';
					if (is_array($playlistList)){
						foreach ($playlistList as $playlist){
							echo $playlist->Name . '<hr/>';
						}
					} else echo 'Profile not found';
				echo '</div>';
				echo $template['footer'];
				die;
			}
		}
	}
	
	if (isset($_POST['grooveshark_artist'])){
		if ($_POST['grooveshark_artist'] != ''){
			$artistUrl = $_POST['grooveshark_artist'];
			$artistSongs = Grooveshark::getArtistSongs($artistUrl);
			if (is_array($artistSongs)){
				# Need to now grab the artist Name from the URL, to be compatable with core.js
				$urlParts = explode("/", $artistUrl);
				$artistName = str_replace("+", " ", $urlParts[count($urlParts) - 2]);
				
				echo '<div id="list_data"><div class="data_profile">' . $artistName . '</div></div>';
				
				echo $template['header'];
				echo '<h2>' . htmlentities($artistUrl) . '</h2>';
				echo '<div id="returned_content">';
					echo '<div id="download_selected_option" class="disabled">Download Selected</div>';
					foreach ($artistSongs as $song){
						echo '<div class="song_result"><div class="song_result_inner">';
						echo '<div class="song_data"><div class="data_songid">' . $song->SongID . '</div><div class="data_songname">' . $song->Name . '</div></div>';
						echo '<p>' . $song->Name . ' - ' . $song->AlbumName . '<br/>' . $song->ArtistName . '</p>';
						echo '<div class="highbtn">High</div>';
						echo '<div class="mobilebtn">Low</div>';
						echo '</div></div>';
					}
				echo '</div>';
				echo $template['footer'];
				die;
			}
		}
	}
	
	if (isset($_POST['grooveshark_user']) && isset($_POST['grooveshark_playlist'])){
		$profileName = $_POST['grooveshark_user'];
		$playlist = $_POST['grooveshark_playlist'];
		
		if ($profileName == '' && $playlist == '') die();
		
		$playlistData = Grooveshark::getPlaylist($profileName, $playlist);
		if (!is_array($playlistData)) API::response('error', 'user', 'No songs found, have you entered the profile &amp; playlist names correctly?');
		echo '<h2>' . $profileName . ' - ' . $playlist . '</h2>';
		echo '<p class="download_description">To download you\'re songs, select the quality of the songs you want, and click the "Download Selected" option<span id="download_selected_everyone">.</span></p>';
		echo '<div id="list_data"><div class="data_profile">' . $profileName . '</div></div>';
		
		echo '<h3>Select all as</h3>';
		echo '<div style="margin-bottom:10px;">';
			echo '<div id="select_all_option_high">High quality</div>';
			echo '<div id="select_all_option_low">Low quality</div>';
			echo '<div class="clear"></div>';
		echo '</div>';
		

		echo '<div id="download_selected_option" class="disabled">Download Selected</div>';
		foreach ($playlistData as $song){
			echo '<div class="song_result"><div class="song_result_inner">';
				echo '<div class="song_data"><div class="data_songid">' . $song->SongID . '</div><div class="data_songname">' . $song->Name . '</div></div>';
				echo '<p>' . $song->Name . ' - ' . $song->AlbumName . '<br/>' . $song->ArtistName . '</p>';			
				echo '<div class="highbtn">High</div>';
				echo '<div class="mobilebtn">Low</div>';
			echo '</div></div>';
		}
		$showBasicContents = false;
	}
	$returnedContent = ob_get_clean();
	
	echo $template['header'];
	echo '<div id="returned_content">';
		echo $returnedContent;
	echo '</div>';
	if ($showBasicContents){
		echo $template['basic_contents'];
	}
	echo $template['footer'];
?>
