<?php

$jsonFile = 'servers.json';
$whitelistURL = 'http://example.com/whitelist';

require 'rcon.funcs.php';
require 'whitelist.funcs.php';

$jsonData = getServerJSON($jsonFile);
$whitelist = getWhitelist($whitelistURL);

foreach ($jsonData as $key => $server) {
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
			echo "$server->name: Empty.\n";
			socket_close($socket);
			continue;
		}
		
		$numPlayers = count($players);
		
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
		
		// Get max players of server
		$response = rconCommand($socket, 'vars.maxPlayers');
		
		if ($response[0] != 'OK') {
			echo "$server->name: on maxPlayers: server error. Skipped.\n";
			socket_close($socket);
			continue;
		}
		
		$maxPlayers = intval($response[1]);
		
		// Get non-whitelisted players
		$nonWhitelistedPlayers = array_values(array_udiff($players, $whitelist, 'strcasecmp')); // case insensitive
		
		if (empty($nonWhitelistedPlayers))
			$nonWhitelistedPlayers = 0;
		else 
			$nonWhitelistedPlayers = count($nonWhitelistedPlayers);
		
		echo "$server->name: $numPlayers/$maxPlayers ($nonWhitelistedPlayers non-whitelisted players).\n";
		
		rconCommand($socket, 'logout');
	}
	
	socket_close($socket);
}

?>