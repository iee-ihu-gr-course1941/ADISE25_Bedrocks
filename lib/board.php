<?php

function handle_board($method) {
    if($method=='GET') { show_board(); } 
    else if($method=='POST') { reset_board(); }
}

function handle_piece($method, $pos, $input, $token) {
    if($method=='PUT') { move_piece($pos, $input['to_pos'], $token); }
}

function show_board() {
    global $mysqli;
    // ORDER BY id ASC: Σημαντικό για να μπουν με τη σωστή σειρά στο HTML (το τελευταίο πάνω)
    $sql = 'SELECT * FROM board ORDER BY pos, id ASC';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    header('Content-type: application/json');
    print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

function reset_board() {
    global $mysqli;
    $mysqli->query("DELETE FROM board");

    // ΑΡΧΙΚΟ ΣΤΗΣΙΜΟ: 15αρια
    for($i=0; $i<15; $i++) { $mysqli->query("INSERT INTO board (pos, piece_color) VALUES (1,'W')"); }
    for($i=0; $i<15; $i++) { $mysqli->query("INSERT INTO board (pos, piece_color) VALUES (24,'B')"); }

    $mysqli->query("UPDATE game_status SET status='initialized', p_turn='W', result=NULL");
    show_board();
}

function move_piece($from, $to, $token) {
    global $mysqli;
    
    // 1. Βρες Χρώμα Παίκτη
    $sql = "SELECT piece_color FROM players WHERE token=?";
    $st = $mysqli->prepare($sql);
    $st->bind_param('s',$token);
    $st->execute();
    $res = $st->get_result();
    if($row=$res->fetch_assoc()) {
        $color = $row['piece_color'];
    } else {
        header("HTTP/1.1 401 Unauthorized");
        print json_encode(['errormesg'=>"Token invalid"]); return;
    }

    // 2. Έλεγχος Σειράς
    $status = $mysqli->query("SELECT p_turn FROM game_status")->fetch_assoc()['p_turn'];
    if($status != $color) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"Δεν είναι η σειρά σου"]); return;
    }

    // 3. Βρες το ΔΙΚΟ ΣΟΥ πούλι που είναι ΠΑΝΩ-ΠΑΝΩ (μεγαλύτερο ID)
    // Στο Πλακωτό κουνιέται μόνο το πάνω πούλι της στήλης.
    $sql = "SELECT id, piece_color FROM board WHERE pos=? ORDER BY id DESC LIMIT 1";
    $st = $mysqli->prepare($sql);
    $st->bind_param('i',$from);
    $st->execute();
    $piece = $st->get_result()->fetch_assoc();

    if(!$piece || $piece['piece_color'] != $color) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"Δεν υπάρχει πούλι δικό σου στην κορυφή της θέσης $from"]); return;
    }

    // 4. Update θέσης (Μετακίνηση)
    // Στο Πλακωτό απλά αλλάζουμε το pos. Το ID μένει ίδιο (και αφού μετακινηθεί,
    // αν υπήρχε άλλο πούλι εκεί, αυτό θα έχει μεγαλύτερο ID άρα θα μπει από πάνω).
    
    $sql = "UPDATE board SET pos=? WHERE id=?";
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $to, $piece['id']);
    $st->execute();

    // 5. Αλλαγή σειράς (Προσωρινά αυτόματα για να παίξεις)
    $next = ($color=='W')?'B':'W';
    $mysqli->query("UPDATE game_status SET p_turn='$next'");

    show_board();
}
?>