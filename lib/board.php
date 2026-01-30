<?php

require_once "lib/game.php";

/* =========================
   ROUTER
   ========================= */
function handle_board($method, $input) {
    if ($method == 'GET') {
        show_board();
    } else if ($method == 'POST') {
        reset_board();
    } else {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['errormesg' => 'Invalid method']);
    }
}

/* =========================
   SHOW BOARD
   ========================= */
function show_board() {
    header('Content-type: application/json');
    echo json_encode(read_board(), JSON_PRETTY_PRINT);
}

/* =========================
   RESET BOARD
   ========================= */
function reset_board() {
    global $mysqli;

    // καθαρισμός πίνακα
    $mysqli->query("DELETE FROM board");

    // αρχικό setup Πλακωτού (απλοποιημένο)
    // 2 πούλια στον 1 για W, 2 πούλια στον 24 για B
    $st = $mysqli->prepare(
        "INSERT INTO board (pos, piece_color) VALUES (?,?)"
    );

    $pos = 1;  $color = 'W';
    $st->bind_param('is', $pos, $color);
    $st->execute();
    $st->execute();

    $pos = 24; $color = 'B';
    $st->bind_param('is', $pos, $color);
    $st->execute();
    $st->execute();

    // reset game_status
    $mysqli->query(
        "UPDATE game_status 
         SET status='initialized', p_turn=NULL, result=NULL"
    );

    show_board();
}

/* =========================
   READ BOARD
   ========================= */
function read_board() {
    global $mysqli;

    $sql = "SELECT pos, piece_color FROM board ORDER BY pos";
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();

    return $res->fetch_all(MYSQLI_ASSOC);
}
?>