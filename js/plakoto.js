var me = { token: null, piece_color: null };

$(function() {
    $('#plakoto_login').click(login);
    $('#plakoto_reset').click(reset_board);
    $('#do_move').click(do_move);
    
    // Ανανέωση κάθε 2 δευτερόλεπτα (Polling)
    setInterval(fetch_board, 2000);
});

function login() {
    var user = $('#username').val();
    var color = $('#pcolor').val();
    $.ajax({
        url: "portes.php/player/" + color,
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({username: user, piece_color: color}),
        success: function(data) {
            me = data[0];
            alert("Επιτυχής Σύνδεση! Token: " + me.token);
            fetch_board();
        },
        error: function() { alert("Αποτυχία σύνδεσης."); }
    });
}

function reset_board() {
    $.post("portes.php/board/", function() { fetch_board(); });
}

function fetch_board() {
    $.get("portes.php/board/", function(data) {
        // Καθαρισμός
        $('.board-slot').html('');
        
        // Ζωγράφισμα: Η PHP τα στέλνει με τη σειρά (ORDER BY id)
        // άρα το τελευταίο που μπαίνει (append) θα φαίνεται πάνω-πάνω
        data.forEach(function(piece) {
            var colorClass = (piece.piece_color == 'W') ? 'white_piece' : 'green_piece';
            
            // Τοποθέτηση στο σωστό div
            $('#pos_' + piece.pos).append('<div class="piece '+colorClass+'"></div>');
        });
    });
}

function do_move() {
    var s = $('#src').val();
    var d = $('#dest').val();
    
    if(!s || !d) { alert("Δώσε αφετηρία και προορισμό"); return; }
    
    $.ajax({
        url: "portes.php/board/piece/" + s,
        method: 'PUT',
        headers: {"App-Token": me.token},
        contentType: 'application/json',
        data: JSON.stringify({to_pos: d}),
        success: function() {
            console.log("Move OK");
            fetch_board();
            $('#src').val('');
            $('#dest').val('');
        },
        error: function(e) {
            alert("Error: " + e.responseJSON.errormesg);
        }
    });
}