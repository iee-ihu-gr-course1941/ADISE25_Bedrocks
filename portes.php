<?php
require_once "lib/dbconnect.php"; 
require_once "lib/board.php";
require_once "lib/game.php";
require_once "lib/users.php";

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'],'/'));
$input = json_decode(file_get_contents('php://input'),true);

// Ανάκτηση Token από headers
$token = null;
if (isset($_SERVER['HTTP_APP_TOKEN'])) {
    $token = $_SERVER['HTTP_APP_TOKEN'];
}

switch ($r=array_shift($request)) {
    case 'board' : 
        switch ($b=array_shift($request)) {
            case '':      handle_board($method); break;       // GET /board/
            case 'piece': handle_piece($method, $request[0], $input, $token); break; // PUT /board/piece/{pos}
        }
        break;
    case 'status': 
        handle_status($method);
        break;
    case 'player': 
        handle_player($method, $request[0], $input);
        break;
    default: 	
        header("HTTP/1.1 404 Not Found");
        exit;
}
?>