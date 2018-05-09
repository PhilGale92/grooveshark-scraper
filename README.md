# Grooveshark-scraper
An old defunct grooveshark scraper I found in my documents. Put on github so I have a record, but it no longer works. 
*Grooveshark is offline.*

## Disclaimer
This code is pretty old - before i started using OOP properly, but has some nice gems inside.

## Intro
So Grooveshark was a spotify competitor that closed down a couple years ago. 
I noticed it was leaving some very interesting tokens in javascript scope so I reverse engineered the application.

## API 
So it turns out the web interface ran on an internal grooveshark-API, which used various security tokens to authorise the requests.
Which a mixture of single-use tokens, and an embedded security key that I found by decompiling
 a .swf file. 

## Internal Classes

* API - Handles the cURL connection and token retrieval from the website 
* Cache - Misc variable storage (urgh)
* File - File manipulation - saving the downloaded `.mp3` and archiving them into `.zip` for download
* Grooveshark - Exposed Grooveshark API, I've listed some of the more interesting calls.
    * ::getArtistSongs()
    * ::getSongFromUrl()
    * ::retrieveSong()
    * ::getPlaylist()
    * ::listPlaylists()
* Keyring - Security key storage for the current user
* Limiter - Prevents the current user from making excessive server requests.
* Log - Log usage, and provides debugging information



## Keys

Grooveshark used 4 different security keys within the API, my script populated the first 3 by 
scraping or loading from an internal .js file.

	clientRevision
	clientLibrary
	revToken


The final key however was within the .swf file, so i used a program called `ActionscriptExtractor` to decompile the `.swf`
and update the `utilities/keys.inc.php` file and modify the `controllerKey`.
	
	controllerKey (used to download files)
	
	To update, use the ActionscriptExtractor.zip (as found within /assets/), and run http://grooveshark.com/JSQueue.swf. 
	The required variable can be found in "(com -> grooveshark -> jsQueue -> Controller -> ~ Line 71"`
	
