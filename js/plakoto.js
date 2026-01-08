var me = {};
var game_status = {};
var board = {};

$(function() {
    draw_empty_board('W');
    fill_board();

    $('#plakoto_reset').click(reset_board);
    $('#plakoto_login').click(login_to_game);
    $('#do_move').click(do_move);
    $('#refresh_board').click(fill_board);

    $('#move_div').hide();
    
    $('#the_move_src').change(update_moves_selector);
});

function do_move() {
    var from_pos = $('#the_move_src').val();
    var to_pos = $('#the_move_dest').val();

    if(!from_pos || !to_pos) {
        alert('Πρέπει να επιλέξετε αφετηρία και προορισμό');
        return;
    }

    $.ajax({
        url: "portes.php/board/position/" + from_pos, 
        method: 'PUT',
        dataType: "json",
        contentType: 'application/json',
        headers: {"App-Token": me.token},
        data: JSON.stringify({to_pos: to_pos}),
        success: move_result,
        error: login_error
    });
}

function move_result(data) {
    fill_board_by_data(data);
    $('#the_move_src').val('');
    $('#the_move_dest').html('');
}

function draw_empty_board(p) {
    
    var t = '<div id="plakoto_table" class="container">';
    
    
    t += '<div class="row board_row">';
    for(var j=13; j<=24; j++) {
        t += '<div class="plakoto_point col" id="pos_' + j + '"><small>' + j + '</small><div class="stack"></div></div>';
    }
    t += '</div>';

    
    t += '<div class="row board_row mt-4">';
    for(var j=12; j>=1; j--) {
        t += '<div class="plakoto_point col" id="pos_' + j + '"><small>' + j + '</small><div class="stack"></div></div>';
    }
    t += '</div>';
    
    t += '</div>';
    $('#backgammon_board').html(t);
    $('.plakoto_point').click(click_on_point);
}

function fill_board() {
    $.ajax({    
        method: "get",
        url: "portes.php/board/", 
        headers: {"App-Token": me.token},
        success: fill_board_by_data 
    });
}

function fill_board_by_data(data) {
    board = data;
    
    $('.stack').html('');

    for(var i=0; i<data.length; i++) {
        var o = data[i];
        var id = '#pos_' + o.pos + ' .stack';
        var color_class = (o.piece_color == 'W') ? 'white_piece' : 'black_piece';
        
        $(id).append('<div class="piece ' + color_class + '"></div>');
    }

    if(me.piece_color != null && game_status.p_turn == me.piece_color) {
        $('#move_div').show(500);
    } else {
        $('#move_div').hide(500);
    }
}

function login_to_game() {
    var user = $('#username').val();
    if(user == '') {
        alert('Δώστε Username');
        return;
    }
    var p_color = $('#pcolor').val();
    
    $.ajax({
        url: "portes.php/player/" + p_color, 
        method: 'PUT',
        dataType: "json",
        contentType: 'application/json',
        data: JSON.stringify({username: user, piece_color: p_color}),
        success: login_result,
        error: login_error
    });
}

function login_result(data) {
    me = data[0];
    $('#game_initializer').hide();
    update_info();
    game_status_update();
}

function game_status_update() {
    $.ajax({
        url: "portes.php/status/", 
        headers: {"App-Token": me.token},
        success: update_status
    });
}

function update_status(data) {
    var last_turn = game_status.p_turn;
    game_status = data[0];
    update_info();
    
    if(last_turn != game_status.p_turn) {
        fill_board();
    }

    if(game_status.p_turn == me.piece_color && me.piece_color != null) {
        $('#move_div').show(500);
        setTimeout(game_status_update, 10000);
    } else {
        $('#move_div').hide(500);
        setTimeout(game_status_update, 3000);
    }
}

function update_info(){
    var turn_text = (game_status.p_turn == 'W') ? "Λευκού" : "Μαύρου";
    $('#game_info').html("Είστε ο παίκτης: <b>" + me.piece_color + "</b> (" + me.username + ") | Σειρά: <b>" + turn_text + "</b>");
}

function click_on_point(e) {
    var id = $(this).attr('id'); 
    var pos = id.split('_')[1];
    $('#the_move_src').val(pos);
    update_moves_selector();
}

function update_moves_selector() {
    var src = $('#the_move_src').val();
    $('#the_move_dest').html('<option value="">---</option>');
    
    for(var i=1; i<=24; i++) {
        if(i != src) {
            $('#the_move_dest').append('<option value="' + i + '">' + i + '</option>');
        }
    }
}

function reset_board() {
    $.ajax({    
        method: 'POST',
        url: "portes.php/board/", 
        headers: {"App-Token": me.token},           
        success: function(data) {
            fill_board_by_data(data);
            location.reload();
        }
    });
}

function login_error(data) {
    var x = data.responseJSON;
    alert(x ? x.errormesg : "Παρουσιάστηκε σφάλμα στη σύνδεση.");
}