<?php
	ob_start();
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Grooveshark scraper</title>
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:400,600' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" type="text/css" href="front_end/gsv2.css" />
	<script type="text/javascript" src="front_end/jquery.js"></script>
	<script type="text/javascript" src="front_end/core.js"></script>
</head>
<body>
	<div id="lightbox_bg" class="hidden"></div>
	<div id="lightbox_wrapper" class="hidden">
		<div id="lightbox">
			<div id="lightbox_contents"></div>
			<div id="lightbox_status"></div>
			<div id="lightbox_max"></div>
		</div>
	</div>
	<div id="page">
		<div id="page_inner"><h1>Grooveshark Scraper</h1>
<?php 
	$template['header'] = ob_get_clean();
	ob_start();
?></div></div>
</body>
</html>
<?php $template['footer'] = ob_get_clean(); 
	ob_start();	
?><form action="" method="post">
	<fieldset>
	<label for="grooveshark_user">Profile name to scrape<br/></label><input type="text" name="grooveshark_user" /><br/>
	<label for="grooveshark_playlist">Playlist name<br/></label><input type="text" name="grooveshark_playlist" /><br/>
	<input type="submit" value="Scrape" />
	</fieldset>
</form>
<form action="" method="post" style="margin-top:30px;">
	<fieldset>
		<label for="grooveshark_url">Request song URL</label><br/><input type="text" name="grooveshark_url" /><br/>
		<input type="submit" value="Request" />
	</fieldset>
</form>

<form action="" method="post" style="margin-top:30px;">
	<fieldset>
		<label for="grooveshark_artist">Artist URL</label><br/><input type="text" name="grooveshark_artist" /><br/>
		<input type="submit" value="Request" />
	</fieldset>
</form>

<form action="?update=cache" method="post" style="margin-top:40px; padding-left:15px;">
	<label for="grooveshark_api_update">Update the API keys (using official grooveshark data)</label><br/><input type="submit" value="Update API" name="grooveshark_api_update" />
</form>
<?php $template['basic_contents'] = ob_get_clean(); ?>