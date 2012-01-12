<?php

$numPlayersKickedURL = 'numPlayersKicked';

function getServerJSON($jsonURL)
{
	if (file_exists($jsonURL))
		return json_decode(file_get_contents($jsonURL));
	else die("$jsonURL missing");
}

function getServerInfo($jsonURL, $string)
{
	$jsonData = getServerJSON($jsonURL);
	
	foreach ($jsonData as $key => $object) {
		if ($object->abbv == $string) {
			return $object;
		}
	}
	
	return False;
}

function getWhitelist($whitelistURL)
{
 	$whitelistString = trim(file_get_contents($whitelistURL));
	return preg_split('/\s+/s', $whitelistString);
}

function getNumPlayersKicked()
{
	global $numPlayersKickedURL;
	
	if (!file_exists($numPlayersKickedURL)) {
		return 0;
	} else {
		return intval(file_get_contents($numPlayersKickedURL));
	}
}

function setNumPlayersKicked($number)
{
	global $numPlayersKickedURL;
	touch($numPlayersKickedURL);
	file_put_contents($numPlayersKickedURL, $number);
}

function whitelistKick_set_lastrun($serverAbbv)
{
	$filename = "whitelistKick_$serverAbbv";
	touch($filename);
	file_put_contents($filename, time());
}

function whitelistKick_get_lastrun($serverAbbv)
{
	$filename = "whitelistKick_$serverAbbv";
	
	if (!file_exists($filename)) {
		return False;
	} else return file_get_contents($filename);
}

function whitelistKick_init($serverAbbv, $cooldownSeconds)
{
	/* Sufficient time has passed, continue */
	$oldTime = intval(whitelistKick_get_lastrun($serverAbbv));
	if ((time() - $oldTime) > $cooldownSeconds)
	{
		whitelistKick_set_lastrun($serverAbbv);
		
		return True;
	}
	/* We're only allowed to run once in a while */
	else return False;
}

?>