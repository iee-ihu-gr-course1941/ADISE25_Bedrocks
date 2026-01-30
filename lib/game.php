<?php

/* =========================
   GAME STATUS – PLAKOTO
   ========================= */

function show_status() {
    global $mysqli;

    $sql = 'SELECT * FROM game_status';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();

    header('Content-type: application/json');
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

/* -------------------------
   UPDATE GAME STATUS
   ------------------------- */
function update_game_status() {
    global $mysqli;

    // τρέχουσα κατάσταση
    $status = read_status();

    $new_status = null;
    $new_turn   = null;

    /* --------- ABORT LOGIC --------- */
    $st = $mysqli->prepare(
        'SELECT COUNT(*) AS aborted 
         FROM players 
         WHERE last_action < (NOW() - INTERVAL 20 MINUTE)'
    );
    $st->execute();
    $aborted = $st->get_result()->fetch_assoc()['aborted'];

    if ($aborted > 0) {
        $sql = "UPDATE players 
                SET username=NULL, token=NULL 
                WHERE last_action < (NOW() - INTERVAL 20 MINUTE)";
        $mysqli->prepare($sql)->execute();

        if ($status['status'] === 'started') {
            $new_status = 'aborted';
        }
    }

    /* --------- ACTIVE PLAYERS --------- */
    $st = $mysqli->prepare(
        'SELECT COUNT(*) AS c 
         FROM players 
         WHERE username IS NOT NULL'
    );
    $st->execute();
    $active_players = $st->get_result()->fetch_assoc()['c'];

    switch ($active_players) {
        case 0:
            $new_status = 'not active';
            $new_turn = NULL;
            break;

        case 1:
            $new_status = 'initialized';
            $new_turn = NULL;
            break;

        case 2:
            $new_status = 'started';

            // αν ξεκινάει τώρα το παιχνίδι
            if ($status['p_turn'] === NULL) {
                $new_turn = 'W'; // Λευκά ξεκινούν στο Πλακωτό
            }
            break;
    }

    /* --------- UPDATE DB --------- */
    $sql = 'UPDATE game_status SET status=?, p_turn=?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('ss', $new_status, $new_turn);
    $st->execute();
	if ($new_status !== null || $new_turn !== null) {
    $sql = 'UPDATE game_status SET status=COALESCE(?,status), p_turn=COALESCE(?,p_turn)';
    $st = $mysqli->prepare($sql);
    $st->bind_param('ss', $new_status, $new_turn);
    $st->execute();
}

}

/* -------------------------
   READ STATUS
   ------------------------- */
function read_status() {
    global $mysqli;

    $sql = 'SELECT * FROM game_status';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();

    return $res->fetch_assoc();
}
?>