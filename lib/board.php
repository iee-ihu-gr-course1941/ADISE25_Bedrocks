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

function handle_position($method, $pos, $input) {
    if($method=='GET') {
        show_board(); // Ή show_position αν θες συγκεκριμένο
    } else if ($method=='PUT') {
        move_piece($pos, $input['to_pos'], $input['token']);
    }    
}

// 
function move_piece($x, $y, $token) {
    global $mysqli;
    
    // 1. Βρες ποιος παίκτης κάνει την κίνηση
    $color = current_color($token);
    if($color == null ) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"You are not a player."]);
        exit;
    }

    // 2. Έλεγχος Σειράς (Turn)
    $status = read_status();
    if($status['status']!='started' || $status['p_turn']!=$color) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"It is not your turn."]);
        exit;
    }

    // 3. Έλεγχος Αφετηρίας: Έχω πούλι εκεί;
    // Μετράμε πόσα πούλια έχω στη θέση $x
    $stmt = $mysqli->prepare("SELECT count(*) as c FROM board WHERE pos=? AND piece_color=?");
    $stmt->bind_param("is", $x, $color);
    $stmt->execute();
    $count_source = $stmt->get_result()->fetch_assoc()['c'];

    if($count_source == 0) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"You don't have a piece at position $x"]);
        exit;
    }

    // 4. Έλεγχος Προορισμού (PLAKOTO RULES)
    // Βλέπουμε τι υπάρχει στο $y
    $stmt = $mysqli->prepare("SELECT piece_color, count(*) as c FROM board WHERE pos=? GROUP BY piece_color");
    $stmt->bind_param("i", $y);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $dest_count = 0;
    $dest_color = null;
    
    // Αν υπάρχει κάτι, παίρνουμε τα στοιχεία
    if(count($res) > 0) {
        // Στο Πλακωτό, αν υπάρχουν και τα δύο χρώματα (πλακωμένο), μας νοιάζει το "πάνω" πούλι.
        // Αλλά για τον έλεγχο "πόρτας", μας νοιάζει αν υπάρχει αντίπαλος.
        foreach($res as $r) {
            if($r['piece_color'] != $color) {
                // Υπάρχει αντίπαλος
                $dest_count = $r['c'];
                $dest_color = $r['piece_color'];
            }
        }
    }

    // ΚΑΝΟΝΑΣ: Αν υπάρχει πάνω από 1 πούλι αντιπάλου -> ΠΟΡΤΑ (απαγορεύεται)
    if($dest_color != null && $dest_color != $color && $dest_count > 1) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"Position $y is blocked (Porta)."]);
        exit;
    }

    // 5. ΕΚΤΕΛΕΣΗ ΚΙΝΗΣΗΣ (Database Transaction)
    
    // Αφαιρούμε ΕΝΑ πούλι από την αφετηρία (LIMIT 1)
    $stmt = $mysqli->prepare("DELETE FROM board WHERE pos=? AND piece_color=? LIMIT 1");
    $stmt->bind_param("is", $x, $color);
    $stmt->execute();

    // Προσθέτουμε το πούλι στον προορισμό
    // Στο Πλακωτό απλά βάζουμε το πούλι μας από πάνω. 
    // Η SQL επιτρέπει να υπάρχουν rows με διαφορετικά χρώματα στο ίδιο pos.
    $stmt = $mysqli->prepare("INSERT INTO board (pos, piece_color) VALUES (?,?)");
    $stmt->bind_param("is", $y, $color);
    $stmt->execute();

    // 6. Ενημέρωση Σειράς (Ποιος παίζει μετά;)
    // Εδώ χρειάζεται λογική για το αν έριξες ζαριές κλπ, 
    // αλλά για αρχή ας το κάνουμε να αλλάζει σειρά αυτόματα για να τεστάρεις.
    
    // ΠΡΟΣΟΧΗ: Αυτό θα αλλάζει σειρά σε κάθε κίνηση (λάθος για τάβλι, αλλά σωστό για debug τώρα)
    // Κανονικά το JS πρέπει να ελέγχει πότε τελείωσαν οι κινήσεις.
    // $next_turn = ($color=='W') ? 'B' : 'W';
    // $mysqli->query("UPDATE game_status SET p_turn='$next_turn'");

    // Επιστροφή νέου ταμπλό
    show_board();
}

// Χρειάζεται και στο board.php ή να είναι global
function read_status() {
    global $mysqli;
    $sql = 'SELECT * FROM game_status';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    return $res->fetch_assoc();
}

function current_color($token) {
    global $mysqli;
    if($token==null) {return(null);}
    $sql = 'select * from players where token=?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('s',$token);
    $st->execute();
    $res = $st->get_result();
    if($row=$res->fetch_assoc()) {
        return($row['piece_color']);
    }
    return(null);

// 


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