var me = { token: null, piece_color: null };

$(function() {
    $('#plakoto_login').click(login);
    $('#plakoto_reset').click(reset_board);
    $('#do_move').click(do_move);
    
    // Ανανέωση κάθε 2 δευτερόλεπτα (Polling)
    setInterval(fetch_board, 2000);
});

function login() {
<<<<<<< HEAD
    $.post('game.php?action=login', {
        username: $('#username').val(),
        color: $('#pcolor').val()
    }, handle_state, 'json');
}

/* ---------- STATE ---------- */

function update_state() {
    $.get('game.php?action=state', handle_state, 'json');
}

function handle_state(data) {
    if (data.status !== 'OK') {
        alert(data.message);
        return;
    }

    game_status = data;
    board = data.board;
    dice = data.dice || [];

    render_board();
    update_info();

    if (game_status.turn === me.color && dice.length === 0) {
        $('#roll_dice').prop('disabled', false);
    } else {
        $('#roll_dice').prop('disabled', true);
    }

    setTimeout(update_state, 3000);
}

/* ---------- DICE ---------- */

function roll_dice() {
    $.post('game.php?action=roll', {}, handle_state, 'json');
}

/* ---------- MOVE ---------- */

function do_move() {
    $.post('game.php?action=move', {
        src: $('#the_move_src').val(),
        dest: $('#the_move_dest').val()
    }, handle_state, 'json');
}

/* ---------- UI ---------- */

function render_board() {
    $('.board-slot').html('');

    for (let pos in board) {
        board[pos].forEach(p => {
            $('#pos_' + pos).append(
                `<div class="piece ${p === 'W' ? 'white_piece' : 'green_piece'}"></div>`
            );
        });
    }

    update_moves_selector();
}

function click_on_point() {
    let pos = $(this).attr('id').split('_')[1];
    $('#the_move_src').val(pos);
    update_moves_selector();
}

function update_moves_selector() {
    let src = $('#the_move_src').val();
    $('#the_move_dest').html('<option value="">---</option>');

    if (!src) return;

    dice.forEach(d => {
        let dest = (me.color === 'W')
            ? parseInt(src) + d
            : parseInt(src) - d;

        if (dest >= 1 && dest <= 24) {
            $('#the_move_dest').append(`<option>${dest}</option>`);
        }
=======
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
>>>>>>> 48924139f2daeb781e2fd105b0eacc1dbadb398b
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