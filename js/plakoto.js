var me = {};
var game_status = {};
var board = {};

$(function() {
    //  draw_empty_board('W'); kolitsis
    fill_board();

    $('#plakoto_reset').click(reset_board);
    $('#plakoto_login').click(login_to_game);
    $('#do_move').click(do_move);
    $('#refresh_board').click(fill_board);
    
    $('.board-slot').click(click_on_point); 
    $('#roll_dice').click(roll_dice);
    
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
    
    // Καθαρισμός
    $('.board-slot').html('');

    for(var i=0; i<data.length; i++) {
        var o = data[i];
        var id = '#pos_' + o.pos; 
        
        // αν δεν είναι Λευκό ('W'), τότε είναι Πράσινο
        var color_class = (o.piece_color == 'W') ? 'white_piece' : 'green_piece';
        
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

    // ΕΛΕΓΧΟΣ ΣΕΙΡΑΣ
    if(game_status.p_turn == me.piece_color && me.piece_color != null) {
        // ΕΙΝΑΙ Η ΣΕΙΡΑ ΜΟΥ
        $('#move_div').show(500);
        
        // --- Ξεκλείδωσε το κουμπί ---
        $('#roll_dice').prop('disabled', false); 
        
        setTimeout(game_status_update, 10000);
    } else {
        // ΔΕΝ ΕΙΝΑΙ Η ΣΕΙΡΑ ΜΟΥ
        $('#move_div').hide(500);
        
        //Κλείδωσε το κουμπί ---
        $('#roll_dice').prop('disabled', true); 
        
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

function roll_dice() {
    if (!me.piece_color || game_status.p_turn != me.piece_color) {
        alert("Δεν είναι η σειρά σας να ρίξετε!");
        return; // Σταματάμε εδώ, δεν ρίχνει ζάρια
    }
    $('#dice1').html('');
    $('#dice2').html('');

    // Παράγουμε τυχαία νούμερα 1-6
    var d1 = Math.floor(Math.random() * 6) + 1;
    var d2 = Math.floor(Math.random() * 6) + 1;

    
    var img1 = '<img class="dice-img" src="imagesErgasia/zari' + d1 + '.png">';
    var img2 = '<img class="dice-img" src="imagesErgasia/zari' + d2 + '.png">';

    $('#dice1').html(img1);
    $('#dice2').html(img2);
}