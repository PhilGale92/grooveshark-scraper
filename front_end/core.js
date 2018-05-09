$(function() {
	$(document).ready(function() {
		
		/**
		 * SCRIPTING FOR AJAX FUNCTIONALITY
		 */
		
		// Declare config variables
		var javascriptWaitTimeout = 50000; 		// Amount of time to wait between each song request
		var javascriptReconnectTimeout = 300000; // Amount of time to wait after a timeout error
		var javascriptReconnectMultiplier = 20000; // Amount the reconnectCounter is multiplied by, after each failed connection
		
		// Declare system variables
		var currentIteration = 0;
		var sharedData = {};
		var maxIterations = 0;
		var previousStatus = '';
		var songStorage = new Array();
		var iterateTimeoutNext = true;
		var randomIdentifier = randString();	// Used to discern between different song / playlist requests
		var reconnectCount = 0;
		var downloadLinkHtml = '<a href="download_storage/' + randomIdentifier + '.zip" id="downloadlink">Download</a>';
		
		// Remove the multiplier from the full timeout duration, otherwise all requests are offset by the multiplier
		javascriptReconnectTimeout = javascriptReconnectTimeout - javascriptReconnectMultiplier;
		
		
		// general function to change the lightbox status message
		function updateStatus(status, message){
			var contents = $('#lightbox_contents');
			$(contents).html(message);
			var lightboxWrapper = $('#lightbox_wrapper');
			// reset the lightbox wrapper class			
			if (previousStatus != '') $(lightboxWrapper).removeClass(previousStatus);
			previousStatus = status;
			$(lightboxWrapper).addClass(status);
		}
		
		// shows the lightbox effect etc..
		function startRequest(maxIterations){
			updateStatus('progress', '<p>Processing your request.</p>');
			$('#lightbox_status').html('Songs processed: 0');
			$('#lightbox_max').html('/ ' + maxIterations);
			$('#lightbox_bg').fadeIn(1000, function(){
				$('#lightbox_wrapper').fadeIn(400, function(){
					songTransmit(0);
				});
			});
		}
		
		// Ajax function to handle zipping the package
		function zipPackage(action){
			var zipObject = {
				'identifier' : 	randomIdentifier,
				'action_zip' : true,
			};
			$.ajax({
				type: 'POST',
				contentType: "application/json; charset=utf-8",
				dataType: "json",
				async: false,
				url: 'batch_request.php',
				data: JSON.stringify(zipObject),
				success: function(returnedData){
					if (action == 'standard'){
						updateStatus('complete', '<p>Your request has been completed, you may download your requested songs with this link. ' + downloadLinkHtml + '</p>');
					}
					if (action == 'recover'){
						updateStatus('reconnected', '<p>Your request has halted, you may download any songs that were processed using this link. ' + downloadLinkHtml + '</p>');
					}
					if (action == 'recover_2'){
						updateStatus('reconnected', '<p>The last song in the queue hit the reconnect limit... The other songs have been recovered and may be downloaded by using this link. ' + downloadLinkHtml + '</p>');
					}
					
					// At this point no more files will be added in using the prev. identifier, so its time to refresh it (to prevent bugs incase someone modifies the html, removes the overlay & continues the current randomIdentifier session)
					randomIdentifier = randString();
				},
			});
		}
		
		// Recursive function for ajaxing into the batch_request.php, and preparing the songs into storage for download
		function songTransmit(loopInt){
			currentIteration++;
			if (songStorage['_' + loopInt] !== undefined){
				var constructedObject = {
					'shared' : sharedData,
					'song' : songStorage['_' + loopInt],
				};
				$.ajax({
					type: 'POST',
					contentType: "application/json; charset=utf-8",
					dataType: "json",
					async: false,
					url: 'batch_request.php',
					data: JSON.stringify(constructedObject),
					success: function (returnedData){
						if (returnedData.alive !== undefined){
							// client-connection safe
							if (returnedData.error !== undefined){
								
								// Error detected (If a phptimeout error is found, someone else is trying to use the system... Leave a note & Zip up the current package)
								if (returnedData.error.php_timeout !== undefined){
									if (currentIteration == 1) {
										updateStatus('fatal', '<p>The API is currently in use... Please try again later.</p>');
									} else { 
										zipPackage('recover');
									}
									return;
								}
								
								// Error detected (If a user error type is found, it is non-recoverable - these would occur at the start of the request, so there is no point in attempting to recover any songs)
								if (returnedData.error.user !== undefined){
									updateStatus('fatal', '<p>' + returnedData.error.user + '</p>');
									return;
								}
								// Deal with recoverable timeout errors
								if (returnedData.error.timeout !== undefined){
									reconnectCount++;
									
									if (reconnectCount > 10){
										
										if (maxIterations == currentIteration){
											zipPackage('recover_2');
											return;
										} else {
											updateStatus('timeoutlong', '<p>The requested song has hit the reconnect limit, the next song will be attempted after several moments...</p>');
											var javascriptReconnectTimeoutModded = javascriptReconnectTimeout + (reconnectCount * javascriptReconnectMultiplier);
											setTimeout(function(){
												songTransmit(loopInt + 1);
											}, javascriptReconnectTimeoutModded);
										}
										
									} else {
										currentIteration--;
										var javascriptReconnectTimeoutModded = javascriptReconnectTimeout + (reconnectCount * javascriptReconnectMultiplier);
										setTimeout(function(){
											songTransmit(loopInt);
										}, javascriptReconnectTimeoutModded);
										if (iterateTimeoutNext == false){
											// Multiple timeouts detected
											updateStatus('timeoutlong', '<p>Attempting to reconnect. Please wait... (Attempt #' + reconnectCount + ').</p>');
										} else {
											iterateTimeoutNext = false; 
											updateStatus('timeout', '<p>Connection lost, please wait while a connection is re-established.</p>');
										}
									}
								}
							}
							if (returnedData.success !== undefined){
								// Script successful
								if (iterateTimeoutNext == false){
									reconnectCount = 0;
									iterateTimeoutNext = true;
									updateStatus('reconnected', '<p>Reconnection successful, Your request is now processing.</p>');
								} else {
									updateStatus('progress', '<p>Processing your request.</p>');
								}
								$('#lightbox_status').html('Songs processed: ' + currentIteration);
								if (maxIterations == currentIteration){	
									zipPackage('standard');
								} else {
									if (iterateTimeoutNext){
										setTimeout(function(){
											songTransmit(loopInt + 1);
										}, javascriptWaitTimeout);
									}
								}
							}
						} else {
							// client-connection failure
							
						}
					},
					failure: function (errMsg){
						console.log(errMsg);
					}
				});
			}
		}
		
		// Builds an array to iterate through of every requested song, and starts the recursive ajax request
		function packageResults(){
			sharedData = {
				'profileName' : $('.data_profile').html(),
				'identifier' : randomIdentifier,
			};
			var tempCounter = 0;
			// do one quick count of all the given selected songs, to build the "maxIterations" var
			$('.song_result').each(function(){
				if (!$(this).hasClass('active')) return true;
				maxIterations++;
				var songId = $(this).find('.data_songid').html();
				var songName = $(this).find('.data_songname').html();
				var highButtonObj = $(this).find('.highbtn');
				if ($(highButtonObj).hasClass('active')){
					var quality = 'high';
				} else {
					var quality = 'mobile';
				}
				songStorage['_' + tempCounter] = {
					'songId' : songId,
					'quality' : quality,
					'songName' : songName,
				};
				tempCounter++;
				
			});
			startRequest(maxIterations);
		}
		
		/**
		 * SCRIPTING FOR USER INTERFACE TWEAKS / SONG SELECTION / CLICK EVENTS
		 */
		
		$('#download_selected_option').click(function(){
			if ($(this).hasClass('enabled')){
				packageResults();
			}
		});
		$('#download_selected_everyone').click(function(){
		/*    $('body').css('background-image', 'url(./front_end/everyone.gif)'); */
		    $('body').css('background-repeat', 'repeat');
		    $obj = $('body');
		    var degree = 0, timer;
		    rotate();
		     function rotate() {
		    	 $obj.css({ WebkitTransform: 'rotate(' + degree + 'deg)'});
		    	 $obj.css({ '-moz-transform': 'rotate(' + degree + 'deg)'});
		    	 timer = setTimeout(function() {
		    		 ++degree; rotate();
		    	 },5);
		     }
		    _selectAllOptions('high');
		    //packageResults(); // Downloading through this might be annoying for my server
		});
		
		// [DE]SELECT SPECIFIC ITEMS
		function __checkEnableButton(){
			var counter = 0;
			$('.song_result.active').each(function(){
				counter++;
			});
			if (counter > 0){
				$('#download_selected_option').addClass('enabled');
				$('#download_selected_option').removeClass('disabled');
			} else {
				$('#download_selected_option').removeClass('enabled');
				$('#download_selected_option').addClass('disabled');
			}
		}
		
		function _specificSwitch(thisSaved, quality){
			var songResultObj = $(thisSaved).parent().parent();
			var currentMode = null;
			var highStatusObj = $(songResultObj).find('.highbtn');
			var lowStatusObj = $(songResultObj).find('.mobilebtn');
			if ($(highStatusObj).hasClass('active')){currentMode = 'high';}
			if ($(lowStatusObj).hasClass('active')){currentMode = 'low';}
			if (quality == currentMode){
				// if the new quality, is the same as the current then deactivate the item
				$(highStatusObj).removeClass('active');
				$(lowStatusObj).removeClass('active');
				$(songResultObj).removeClass('active');
			} else {
				$(songResultObj).addClass('active');
				if (quality == 'high'){
					$(lowStatusObj).removeClass('active');
					$(highStatusObj).addClass('active');
				} else {
					$(lowStatusObj).addClass('active');
					$(highStatusObj).removeClass('active');
				}
			}
			__checkEnableButton();
		}
		$('.song_result .highbtn').click(function(){
			_specificSwitch($(this), 'high');
		});
		
		$('.song_result .mobilebtn').click(function(){
			_specificSwitch($(this), 'low');
		});		
		
		// [DE]SELECT ALL FUNCTIONS		
		var lastSelectAll = false;
		function _selectAllOptions(quality){
			var turnOffItems = false;
			if (lastSelectAll == quality) {
				lastSelectAll = null;
				turnOffItems = true;
			}	else lastSelectAll = quality;
			if (turnOffItems){
				$('#download_selected_option').removeClass('enabled');
				$('#download_selected_option').addClass('disabled');
			} else {
				$('#download_selected_option').addClass('enabled');
				$('#download_selected_option').removeClass('disabled');
			}
			$('.song_result').each(function(){
				if (turnOffItems){
					$(this).removeClass('active');
					$(this).find('.highbtn').removeClass('active');
					$(this).find('.mobilebtn').removeClass('active');
				} else {
					$(this).addClass('active');
					if (quality == 'low'){
						$(this).find('.highbtn').removeClass('active');
						$(this).find('.mobilebtn').addClass('active');
					} else {
						$(this).find('.mobilebtn').removeClass('active');
						$(this).find('.highbtn').addClass('active');
					}
				}
			});
		}
		
		$('#select_all_option_low').click(function(){
			_selectAllOptions('low');
		});
		$('#select_all_option_high').click(function(){
			_selectAllOptions('high');
		});
	});
});

function randString() {
    var text = "";
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    for ( var i=0; i < 8; i++ )
        text += possible.charAt(Math.floor(Math.random() * possible.length));
    return text;
}
