<?php
// Απενεργοποίηση Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// --- 1. ΔΗΜΙΟΥΡΓΙΑ ΠΑΙΧΝΙΔΙΟΥ (PLAYER 1) ---
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'create_game') {
    // Καθαρισμός παλιών (για το demo μας)
    $mysqli->query("DELETE FROM board"); 
    $mysqli->query("DELETE FROM game_state");
    $mysqli->query("ALTER TABLE board AUTO_INCREMENT = 1");
    $mysqli->query("ALTER TABLE game_state AUTO_INCREMENT = 1");

    $token = bin2hex(random_bytes(16)); // Δημιουργία μοναδικού κωδικού για τον P1

    $mysqli->query("INSERT INTO game_state (status, current_turn, p1_token) VALUES ('waiting', 'white', '$token')");
    $game_id = $mysqli->insert_id;

    // Στήσιμο
    $mysqli->query("INSERT INTO board (game_id, position, piece_count, piece_color, pinned_count) VALUES ($game_id, 1, 15, 'white', 0)");
    $mysqli->query("INSERT INTO board (game_id, position, piece_count, piece_color, pinned_count) VALUES ($game_id, 24, 15, 'black', 0)");

    echo json_encode(["status" => "success", "game_id" => $game_id, "token" => $token, "my_color" => "white"]);
    exit;
}

// --- 2. ΣΥΝΔΕΣΗ ΔΕΥΤΕΡΟΥ ΠΑΙΚΤΗ (PLAYER 2) ---
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'join_game') {
    $game_id = intval($input['game_id']);
    
    // Έλεγχος αν υπάρχει το παιχνίδι και αν περιμένει παίκτη
    $check = $mysqli->query("SELECT * FROM game_state WHERE id=$game_id AND status='waiting'");
    if ($check->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Το παιχνίδι δεν υπάρχει ή είναι γεμάτο!"]);
        exit;
    }

    $token = bin2hex(random_bytes(16)); // Δημιουργία μοναδικού κωδικού για τον P2
    
    // Ενημέρωση ότι μπήκε ο P2 και το παιχνίδι ξεκινάει
    $mysqli->query("UPDATE game_state SET status='active', p2_token='$token' WHERE id=$game_id");

    echo json_encode(["status" => "success", "token" => $token, "my_color" => "black"]);
    exit;
}

// --- 3. ΡΙΨΗ ΖΑΡΙΩΝ ---
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'roll_dice') {
    $game_id = $input['game_id'];
    $token = $input['token'];

    // Security Check: Είναι η σειρά μου;
    if (!verifyTurn($mysqli, $game_id, $token)) {
        echo json_encode(["status" => "error", "message" => "Δεν είναι η σειρά σου!"]);
        exit;
    }

    $d1 = rand(1, 6);
    $d2 = rand(1, 6);
    echo json_encode(["status" => "success", "die1" => $d1, "die2" => $d2]);
    exit;
}

// --- 4. ΚΙΝΗΣΗ ---
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'move_piece') {
    $game_id = $input['game_id'];
    $from = $input['from'];
    $to = $input['to'];
    $token = $input['token'];
    $color = $input['color'];

    if (!verifyTurn($mysqli, $game_id, $token)) {
        echo json_encode(["status" => "error", "message" => "Δεν είναι η σειρά σου!"]);
        exit;
    }

    // Λήψη θέσεων
    $resFrom = $mysqli->query("SELECT * FROM board WHERE game_id=$game_id AND position=$from");
    $rowFrom = $resFrom->fetch_assoc();
    $resTo = $mysqli->query("SELECT * FROM board WHERE game_id=$game_id AND position=$to");
    $rowTo = $resTo->fetch_assoc();

    if (!$rowFrom || $rowFrom['piece_count'] == 0 || $rowFrom['piece_color'] != $color) {
        echo json_encode(["status" => "error", "message" => "Δεν υπάρχει πούλι δικό σου εκεί!"]);
        exit;
    }

    // Logic Κίνησης
    $newCountFrom = $rowFrom['piece_count'] - 1;
    if ($newCountFrom == 0) {
        if ($rowFrom['pinned_count'] > 0) {
            $opponent = ($color == 'white') ? 'black' : 'white';
            $newPinned = $rowFrom['pinned_count'] - 1;
            $mysqli->query("UPDATE board SET piece_count=1, piece_color='$opponent', pinned_count=$newPinned WHERE id=" . $rowFrom['id']);
        } else {
            $mysqli->query("DELETE FROM board WHERE id=" . $rowFrom['id']);
        }
    } else {
        $mysqli->query("UPDATE board SET piece_count=$newCountFrom WHERE id=" . $rowFrom['id']);
    }

    if (!$rowTo) {
        $mysqli->query("INSERT INTO board (game_id, position, piece_count, piece_color) VALUES ($game_id, $to, 1, '$color')");
    } else {
        if ($rowTo['piece_color'] == $color) {
            $mysqli->query("UPDATE board SET piece_count = piece_count + 1 WHERE id=" . $rowTo['id']);
        } else {
            // ΠΛΑΚΩΜΑ
            if ($rowTo['piece_count'] == 1) {
                $newPinned = $rowTo['pinned_count'] + 1;
                $mysqli->query("UPDATE board SET piece_count=1, piece_color='$color', pinned_count=$newPinned WHERE id=" . $rowTo['id']);
            } else {
                echo json_encode(["status" => "error", "message" => "Πόρτα!"]);
                exit;
            }
        }
    }

    echo json_encode(["status" => "success"]);
    exit;
}

// --- 5. ΑΛΛΑΓΗ ΣΕΙΡΑΣ ---
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'end_turn') {
    $game_id = $input['game_id'];
    $token = $input['token'];

    if (!verifyTurn($mysqli, $game_id, $token)) {
        echo json_encode(["status" => "error", "message" => "Wait your turn"]);
        exit;
    }

    $r = $mysqli->query("SELECT current_turn FROM game_state WHERE id=$game_id");
    $curr = $r->fetch_assoc()['current_turn'];
    $next = ($curr == 'white') ? 'black' : 'white';
    
    $mysqli->query("UPDATE game_state SET current_turn='$next' WHERE id=$game_id");
    echo json_encode(["status" => "success"]);
    exit;
}

// --- 6. GET STATE (POLLING) ---
if ($method === 'GET' && isset($_GET['game_id'])) {
    $game_id = intval($_GET['game_id']);
    
    $boardRes = $mysqli->query("SELECT * FROM board WHERE game_id = $game_id");
    $board = [];
    while ($row = $boardRes->fetch_assoc()) $board[] = $row;

    $gameRes = $mysqli->query("SELECT status, current_turn FROM game_state WHERE id = $game_id");
    $gameState = $gameRes->fetch_assoc();

    echo json_encode([
        "board" => $board, 
        "turn" => $gameState['current_turn'], 
        "status" => $gameState['status']
    ]);
    exit;
}

// Helper: Verify Token
function verifyTurn($mysqli, $gameId, $token) {
    $res = $mysqli->query("SELECT current_turn, p1_token, p2_token FROM game_state WHERE id=$gameId");
    if($res->num_rows == 0) return false;
    $row = $res->fetch_assoc();
    
    // Αν είναι η σειρά του Λευκού, το token πρέπει να είναι το p1_token
    if ($row['current_turn'] === 'white' && $token === $row['p1_token']) return true;
    // Αν είναι η σειρά του Μαύρου, το token πρέπει να είναι το p2_token
    if ($row['current_turn'] === 'black' && $token === $row['p2_token']) return true;
    
    return false;
}
?>