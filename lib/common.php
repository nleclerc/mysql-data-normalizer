<?php

mb_internal_encoding( 'UTF-8' );

function __autoload($class_name) {
    require "classes/$class_name.php";
}

function formatNb($value, $minlength) {
	$result = "$value";
	
	while (strlen($result) < $minlength)
		$result = "0$result";
	
	return $result;
}

function getQueryParameter($parmName){
	$value = null;
	
	if (isset($_POST[$parmName]))
		$value = $_POST[$parmName];
	else if (isset($_GET[$parmName]))
		$value = $_GET[$parmName];
	
	return $value;
}

function getCookie($name) {
	if (isset($_COOKIE[$name]))
		return $_COOKIE[$name];
	
	return null;
}

function findConfigurationFiles($folderPath) {
	$result = array();
	$listedFiles = scandir($folderPath);
	
	foreach ($listedFiles as $currentfile)
		if (preg_match('/.json$/i', $currentfile))
			array_push($result, $currentfile);
	
	return $result;
}

function put($msg='') {
	echo $msg;
}

function puts($msg='') {
	put("$msg\n");
}

function processDatabases() {
	$confPath = __DIR__.'/../conf';
	$confs = findConfigurationFiles($confPath);
	
	foreach ($confs as $confFile) {
		$conf = json_decode(file_get_contents($confPath.'/'.$confFile));
		if (!$conf)
			puts("Not a valid json file:\n".file_get_contents($confFile));
		else {
			$filter = new DataFilter($conf);
			$filter->processDatabase();
		}
	}
}
