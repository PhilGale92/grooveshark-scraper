<?php
/** 
 * This file programatically builds path constants as required
 */
	define ("ABS_PATH",  substr(str_replace('\\', DIRECTORY_SEPARATOR, dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR, 0));
	define ("BASE_PATH",  dirname($_SERVER['PHP_SELF']));	
	
