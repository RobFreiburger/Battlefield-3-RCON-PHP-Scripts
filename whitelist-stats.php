<?php

$jsonFile = 'servers.json';
$whitelistURL = 'http://example.com/whitelist';

require 'whitelist.funcs.php';
require 'rcon.funcs.php';

// Get server counts
$jsonData = getServerJSON($jsonFile);
$totalServers = 0;
$totalPlayerSlots = 0;

foreach ($jsonData as $key => $server) {
	if ($server->enabled === False) {
		continue;
	}
	
	$totalServers++;
	
	$clientSequenceNr = 0;
	
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_connect($socket, $server->ip, $server->port);
	socket_set_nonblock($socket);
	
	if ($socket === False) {
		echo "$server->name: could not open socket. Skipped.\n";
		continue;
	}
	
	if ($socket !== False) {
		
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
		
		$totalPlayerSlots += intval($response[1]);
		
		rconCommand($socket, 'logout');
	}
	
	socket_close($socket);
}

// Whitelist count
$totalWhitelisted = count(getWhitelist($whitelistURL));

// Non-whitelisted players kicked count
$totalPlayersKicked = getNumPubbiesKicked();

echo "There are $totalWhitelisted whitelisted, $totalPlayerSlots slots on $totalServers servers, and $totalPlayersKicked non-whitelisted players kicked.\n";

?>