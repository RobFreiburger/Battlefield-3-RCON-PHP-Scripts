<?php

// Configurable variables
$jsonURL = 'servers.json';
$whitelistURL = 'http://example.com/whitelist';
$forceKickVerification = 'gKnVsgV7zHt2RbDt'; // random string that must be passed in order to bypass lastrun timer
$secondsBeforeKick = 5; // time between kick announcement and kick commands
$maxPlayersToKick = 3; // maximum # of non-whitelisted players to kick per server
$cooldownSeconds = 180; // interval between successful kicks
// $cooldownSeconds is to prevent script spamming
// $cooldownSeconds will be checked on every server

// Get passed arguments and see if valid
if (empty($_GET)) {
	exit("No arguments passed to script.\n");
}

require('whitelist.funcs.php');

$servers = array();
foreach ($_GET as $key => $value) {
	if (mb_strpos($key, 'server') !== False) {
		$testValue = getServerInfo($jsonURL, $value);
		
		if ($testValue !== False) {
			array_push($servers, $testValue);
		}
	}
}

if (empty($servers)) {
	exit("Invalid argument(s) passed to script.\n");
}

// Set FORCE variable to true if valid string is passed to script
$FORCEKICK = False;
if (strcmp($_GET["force"], $forceKickVerification) === 0) {
	$FORCEKICK = True;
}

unset($_GET);

// Get whitelist and loop through all validated servers
$whitelist = getWhitelist($whitelistURL);
$totalPlayersKicked = getNumPlayersKicked();

require('rcon.funcs.php');

foreach ($servers as $server) {
	if ($server->enabled === False) {
		echo "$server->name: disabled. Skipped.\n";
		continue;
	}
	
	$clientSequenceNr = 0;
	
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_connect($socket, $server->ip, $server->port);
	socket_set_nonblock($socket);
	
	if ($socket === False) {
		echo "$server->name: could not open socket. Skipped.\n";
		continue;
	}
	
	if ($socket !== False) {
		
		// Get player list, skip if server's empty
		// listPlayers does not require login
		$response = rconCommand($socket, 'listPlayers all');
		
		if ($response[0] != 'OK') {
			echo "$server->name: on listPlayers: server error. Skipped.\n";
			socket_close($socket);
			continue;
		}
		
		$players = array();
		for ($i = 10; $i < count($response); $i += 7) { 
			array_push($players, $response[$i]);
		}
		
		if (empty($players)) {
			echo "$server->name: Server is empty. Skipped.\n";
			socket_close($socket);
			continue;
		}
		
		// All other needed commands require login
		$response = rconCommand($socket, 'login.hashed');
		
		if ($response[0] != 'OK') {
			echo "$server->name: could not login. Skipped.\n";
			socket_close($socket);
			continue;
		}
		
		$response = rconCommand($socket, 'login.hashed ' . generatePasswordHash($response[1], $server->password));
		
		switch ($response[0]) {
			case 'OK':
				break;
				
			case 'PasswordNotSet':
				echo "$server->name: password not set on server. Skipped.\n";
				socket_close($socket);
				continue 2;
				break;
				
			case 'InvalidArguments':
				echo "$server->name: on login: InvalidArguments. Skipped.\n";
				socket_close($socket);
				continue 2;
				break;
			
			default:
				echo "$server->name: on login: unexpected output. Skipped.\n";
				socket_close($socket);
				continue 2;
				break;
		}
		
		// Get max players of server, skip if not filled
		$response = rconCommand($socket, 'vars.maxPlayers');
		
		if ($response[0] != 'OK') {
			echo "$server->name: on maxPlayers: server error. Skipped.\n";
			socket_close($socket);
			continue;
		}
		
		if (count($players) < (intval($response[1]) - 1)) {
			echo "$server->name: " . count($players) . "/$response[1] players. Skipped.\n";
			socket_close($socket);
			continue;
		}
		
		// Get non-whitelisted players, skip if none are found
		$nonWhitelistedPlayers = array_values(array_udiff($players, $whitelist, 'strcasecmp')); // case insensitive
		
		if (count($nonWhitelistedPlayers) == 0) {
			echo "$server->name: all whitelisted players.\n";
			socket_close($socket);
			continue;
		}
		
		if ($FORCEKICK || whitelistKick_init($server->abbv, $cooldownSeconds)) {
			
			if (count($nonWhitelistedPlayers) > $maxPlayersToKick) {
				$tempArray = array();
				for ($i = 0; $i < $maxPlayersToKick; $i++) {
					array_push($tempArray, $nonWhitelistedPlayers[array_rand($nonWhitelistedPlayers)]);
				}
				$nonWhitelistedPlayers = $tempArray;
				unset($tempArray);
			}
			
			$response = rconCommand($socket, "admin.say 'Non-whitelisted player kick in $secondsBeforeKick seconds.' all");
			
			if ($response[0] != 'OK') {
				echo "$server->name: on whitelistKick: trouble announcing whitelistKick. Skipped.\n";
				socket_close($socket);
				continue;
			}
			
			sleep($secondsBeforeKick);
			
			foreach ($nonWhitelistedPlayers as $player) {
				
				$response = rconCommand($socket, "admin.kickPlayer '$player' Not on the whitelist.");
				
				if ($response[0] != 'OK') {
					echo "$server->name: trouble kicking $player: $response[1]\n";
					continue;
				} else
					echo "$server->name: $player kicked.\n";
				
				$totalPlayersKicked++;
				
				rconCommand($socket, "admin.say '$player was kicked.' all");
				
			}
			
		} else {
			echo "$server->name: cooldown timer still running. Skipped.\n";
		}
		
		rconCommand($socket, 'logout');
	}
	
	socket_close($socket);
}

setNumPlayersKicked($totalPlayersKicked);

?>