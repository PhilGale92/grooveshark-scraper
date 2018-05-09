<?php 
/**
 * @desc This file is for use by core.js AJAX calls
 */
	set_time_limit(0);
	define("root", dirname(__FILE__));
	date_default_timezone_set("UTC");
	
	$requestData = json_decode(file_get_contents('php://input'));
	
	# load the API
	require_once('classes/Log.class.php');
	require_once('classes/File.class.php');
	require_once('classes/Keyring.class.php');
	require_once('classes/API.class.php');
	require_once('classes/Cache.class.php');
	require_once('classes/Grooveshark.class.php');
	require_once('classes/Limiter.class.php');
	
	if (isset($requestData->action_zip)){
		if (!isset($requestData->identifier)) API::response('error', 'user', 'System error: Invalid request');
		
		Cache::set('randomIdentifier', $requestData->identifier);
		File::packageDir();
		
	} else {
	
		if (!isset($requestData->shared->identifier) || !isset($requestData->shared->profileName)
				|| !isset($requestData->song->songId) || !isset($requestData->song->songName) || !isset($requestData->song->quality)
		) API::response('error', 'user', 'System error: Invalid request');
		
		Cache::set('randomIdentifier', $requestData->shared->identifier);
		$profileName = $requestData->shared->profileName;
		$songId = $requestData->song->songId;
		$songName = $requestData->song->songName;
		$quality = $requestData->song->quality;
		
		Grooveshark::retrieveSong($profileName, $songId, $songName, $quality);
		
	}
	
	API::response('success', 'success', 'no_err');
?>