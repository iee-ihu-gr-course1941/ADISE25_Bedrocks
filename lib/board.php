<?php

function handle_board($method, $input) {
    if ($method == 'GET') {
        show_board();
    } else if ($method == 'POST') {
        reset_board();
    }
}

function handle_position($method, $pos, $input) {
    if ($method == 'PUT') {
        move_piece($pos, $input['to_pos'], $input['token']);
    }
}

function show_board() {
    global $mysqli;
    // Επιστρέφουμε όλα τα πούλια
    $sql = "SELECT pos, piece_color FROM board";
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    header('Content-type: application/json');
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

function reset_board() {
    global $mysqli;
    $mysqli->query("DELETE FROM board");

    // --- ΑΡΧΙΚΟ ΣΤΗΣΙΜΟ ΠΛΑΚΩΤΟΥ ---
    // 15 Λευκά στη θέση 1
    for ($i=0; $i<15; $i++) {
        $mysqli->query("INSERT INTO board (pos, piece_color) VALUES (1, 'W')");
    }
    // 15 Μαύρα στη θέση 24
    for ($i=0; $i<15; $i++) {
        $mysqli->query("INSERT INTO board (pos, piece_color) VALUES (24, 'B')");
    }

    // Reset status
    $mysqli->query("UPDATE game_status SET status='initialized', p_turn='W', result=NULL");
    show_board();
}

function move_piece($from, $to, $token) {
    global $mysqli;

    // 1. Έλεγχος Παίκτη
    $color = current_color($token);
    if (!$color) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['errormesg' => "Δεν είστε συνδεδεμένος."]);
        exit;
    }

    // 2. Έλεγχος Σειράς
    $sql = "SELECT * FROM game_status";
    $status = $mysqli->query($sql)->fetch_assoc();
    if ($status['p_turn'] != $color) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['errormesg' => "Δεν είναι η σειρά σας."]);
        exit;
    }

    // 3. Έλεγχος: Έχω πούλι στην αφετηρία ($from);
    $sql = "SELECT count(*) as c FROM board WHERE pos=? AND piece_color=?";
    $st = $mysqli->prepare($sql);
    $st->bind_param('is', $from, $color);
    $st->execute();
    $count = $st->get_result()->fetch_assoc()['c'];
    
    if ($count == 0) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['errormesg' => "Δεν υπάρχει πούλι δικό σας στη θέση $from."]);
        exit;
    }

    // 4. Έλεγχος Προορισμού (Κανόνες Πλακωτού)
    // Βλέπουμε τι υπάρχει στο $to
    $sql = "SELECT piece_color, count(*) as c FROM board WHERE pos=? GROUP BY piece_color";
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $to);
    $st->execute();
    $dest_data = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    // Αν υπάρχει πούλι αντιπάλου και είναι > 1, τότε είναι ΠΟΡΤΑ (απαγορεύεται)
    foreach ($dest_data as $row) {
        if ($row['piece_color'] != $color && $row['c'] > 1) {
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['errormesg' => "Η θέση $to είναι πιασμένη (Πόρτα)."]);
            exit;
        }
    }

    // 5. ΕΚΤΕΛΕΣΗ ΚΙΝΗΣΗΣ
    // Σβήνουμε ΕΝΑ πούλι από την αφετηρία
    $sql = "DELETE FROM board WHERE pos=? AND piece_color=? LIMIT 1";
    $st = $mysqli->prepare($sql);
    $st->bind_param('is', $from, $color);
    $st->execute();

    // Βάζουμε ΕΝΑ πούλι στον προορισμό
    $sql = "INSERT INTO board (pos, piece_color) VALUES (?, ?)";
    $st = $mysqli->prepare($sql);
    $st->bind_param('is', $to, $color);
    $st->execute();

    // 6. Αλλαγή Σειράς (Προσωρινά αυτόματη)
    $next = ($color == 'W') ? 'B' : 'W';
    $mysqli->query("UPDATE game_status SET p_turn='$next'");

    show_board();
}
?>