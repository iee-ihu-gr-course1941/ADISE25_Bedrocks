var me = {};
var game_status = {};
var board = {};
var dice = [];

$(function () {
    $('#plakoto_login').click(login);
    $('#roll_dice').click(roll_dice);
    $('#do_move').click(do_move);
    $('#refresh_board').click(update_state);
    $('#plakoto_reset').click(reset_game);

    $('.board-slot').click(click_on_point);

    $('#move_div').hide();
    update_state();
});

/* ---------- LOGIN ---------- */

function login() {
    $.post('game.php?action=login', {
        username: $('#username').val(),
        color: $('#pcolor').val()
    }, handle_state, 'json');
}

/* --------- STATE ---------- */

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
    });
}

function update_info() {
    if (game_status.status === 'initialized') {
        $('#game_info').html("Περιμένουμε αντίπαλο...");
        return;
    }

    if (game_status.status === 'started') {
        let t = (game_status.turn === 'W') ? 'Λευκά' : 'Μαύρα';
        $('#game_info').html("Σειρά: <b>" + t + "</b>");
    }

    if (game_status.status === 'aborted') {
        $('#game_info').html("Το παιχνίδι εγκαταλείφθηκε");
    }
}

/* ---------- RESET ---------- */

function reset_game() {
    $.post('game.php?action=reset', {}, update_state, 'json');
}
