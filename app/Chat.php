<?php

namespace App;

use Amp\Http\Server\Websocket\Websocket;
use Amp\Http\Server\Websocket\Message;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

class Chat extends Websocket {
	protected $clients = [];
	protected $next_conn_id = 0;
	
	private $possible_names = [
		"Fred",
		"Barney",
		"Wilma",
		"Pebbles",
		"Bamm-Bamm",
		"Betty",
		"Dino",
	];
	private $possible_colors = [
		"Red" => "d00000",
		"Orange" => "e08000",
		"Green" => "00d000",
		"Blue" => "0000d0",
		"Purple" => "c000c0",
	];
	
	public function __construct() {
		parent::__construct();
	}
	
	public function onHandshake(Request $request, Response $response): Response {
		if (!\in_array($request->getHeader('origin'), ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://[::1]:8000'], true)) {
			$response->setStatus(403);
		}

		return $response;
	}

	public function onConnection(Client $client, Request $request): \Generator {
		while ($message = yield $client->receive()) {
			\assert($message instanceof Message);
			$this->broadcast(\sprintf('%d: %s', $client->getId(), yield $message->buffer()));
		}
	}


	public function onOpen(int $client_id, Request $request) {
		$name = "Connection #$client_id";
		$color_value = "000000";
		
		$info = [
			"id" => $client_id,
			"auth" => false,
			"name" => $name,
			"color" => $color_value,
		];
		$this->clients[$client_id] = $info;
		
        $num_clients = count($this->clients);
		$color_name = array_keys($this->possible_colors)[($num_clients % 5)];
		$color_value = $this->possible_colors[$color_name];
		$name = "$color_name " . $this->possible_names[($num_clients % 7)];
		$this->setupAuth($client_id, $name, $color_value, $info);
		
		echo "WS CONNECTED #$client_id: $name ($color_value)\n";
	}
	
	public function onData(int $client_id, Message $message) {
		try {
			$payload = yield $message->buffer();
			$data = json_decode($payload);
			
			switch ($data->type) {
				case "chat":
					$this->recvMessage($client_id, $data);
				break;
				
				default:
					print_r($data);
					throw new \Exception("Unknown message type received: $data->type");
				break;
			}
		} catch (\Exception $e) {
			$this->sendError($client_id, $e->getMessage());
		}
	}
	
	public function onClose(int $client_id, int $code, string $reason) {
		$info = $this->clients[$client_id];
		unset($this->clients[$client_id]);
		
		echo "WS DISCONNECTED: {$info['name']} - {$code} / {$reason}\n";
	}
	
	private function setupAuth(int $client_id, string $name, string $color, array $info) {
		printf(" + AUTH (%d): %s - %s\n", $client_id, $name, $color);
		
		if ($info === null) {
			$info = $this->clients[$client_id];
		}
		
		$this->send(json_encode([
			"type" => "auth",
			"valid" => true,
			"name" => $name,
			"color" => $color,
		]), $client_id);
		
		$info['auth'] = true;
		$info['name'] = $name;
		$info['color'] = $color;
		
		$this->clients[$client_id] = $info;
	}
	private function recvMessage(int $client_id, \StdClass $data) {
		$info = $this->clients[$client_id];
		printf(" + CHAT (%d / %s): %s\n", $info['id'], $info['name'], $data->message);
		if ($info['auth'] === true) {
			$this->broadcast(json_encode([
				"type" => "chat",
				"from" => $info['name'],
				"color" => $info['color'],
				"message" => $data->message,
			]));
		}
	}
	private function sendError(int $client_id, $msg) {
		$this->send(json_encode([
			"type" => "error",
			"error" => $msg,
		]), $client_id);
	}
}

