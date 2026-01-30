let gameId = null;
let myToken = null;
let myColor = '';
let currentTurn = '';
let dice = [];
let selectedPos = null;
let boardState = [];
let pollingInterval = null;
let lastBoardJson = ''; // ΝΕΟ: Για να θυμόμαστε την προηγούμενη κατάσταση

// --- 1. SETUP MENU ---

async function createGame() {
    const res = await apiCall({ action: 'create_game' });
    if (res.status === 'success') {
        startGame(res.game_id, res.token, res.my_color);
        alert(`Το παιχνίδι φτιάχτηκε! ID: ${res.game_id}\n\nΔώσε το ID στον φίλο σου.`);
    }
}

async function joinGame() {
    const idInput = document.getElementById('join-id').value;
    if (!idInput) return alert("Βάλε ID!");
    
    const res = await apiCall({ action: 'join_game', game_id: idInput });
    if (res.status === 'success') {
        startGame(idInput, res.token, res.my_color);
    } else {
        alert(res.message);
    }
}

function startGame(id, token, color) {
    gameId = id;
    myToken = token;
    myColor = color;

    document.getElementById('setup-modal').style.display = 'none';
    document.getElementById('game-ui').style.display = 'flex';
    document.getElementById('game-ui').style.flexDirection = 'column';
    
    document.getElementById('display-game-id').innerText = gameId;
    document.getElementById('my-role-badge').innerText = (myColor === 'white') ? "Eimai: WHITE" : "Eimai: BLACK";
    document.getElementById('my-role-badge').style.background = (myColor === 'white') ? "#eee" : "#333";
    document.getElementById('my-role-badge').style.color = (myColor === 'white') ? "#000" : "#fff";

    updateBoard();
    pollingInterval = setInterval(updateBoard, 1500);
}

// --- 2. BOARD POLLING & UPDATE ---
async function updateBoard() {
    try {
        const t = new Date().getTime();
        const data = await fetch(`api.php?game_id=${gameId}&t=${t}`).then(r => r.json());

        const currentJson = JSON.stringify(data.board);
        const status = data.status;
        currentTurn = data.turn;
        boardState = data.board;

        // UI Updates (Κείμενα)
        const turnLabel = document.getElementById('turn-indicator');
        const msgArea = document.getElementById('message-area');
        const btnRoll = document.getElementById('btn-roll');

        if (status === 'waiting') {
            turnLabel.innerText = "Αναμονή 2ου παίκτη...";
            btnRoll.disabled = true;
        } else {
            turnLabel.innerText = `Σειρά: ${currentTurn.toUpperCase()}`;
            
            if (currentTurn === myColor) {
                turnLabel.style.color = "#2ecc71";
                if (dice.length === 0) {
                    btnRoll.disabled = false;
                    msgArea.innerText = "Ρίξε τα ζάρια!";
                } else {
                    btnRoll.disabled = true;
                    msgArea.innerText = "Παίξε...";
                }
            } else {
                turnLabel.style.color = "#e74c3c";
                btnRoll.disabled = true;
                msgArea.innerText = "Περίμενε τον αντίπαλο...";
                if (dice.length > 0) { dice = []; updateDiceUI(); }
            }
        }

        // --- Η ΜΕΓΑΛΗ ΑΛΛΑΓΗ ---
        // 1. Αν τα δεδομένα είναι ίδια με πριν, ΜΗΝ ξαναζωγραφίζεις το ταμπλό.
        if (currentJson === lastBoardJson) {
            return;
        }

        // 2. Αν είναι η σειρά μου ΚΑΙ έχω επιλέξει πούλι (το κρατάω), 
        // ΜΗΝ κάνεις update για να μην χαθεί η επιλογή μου.
        if (currentTurn === myColor && selectedPos !== null) {
            return; 
        }

        // Αν φτάσαμε εδώ, σημαίνει ότι κάτι άλλαξε πραγματικά, άρα ενημερώνουμε.
        lastBoardJson = currentJson; 
        renderBoard(data.board);

    } catch (e) { console.error(e); }
}

function renderBoard(pieces) {
    document.querySelectorAll('.point').forEach(p => {
        let num = p.id.split('-')[1];
        p.innerHTML = `<span class="point-number">${num}</span>`;
        p.onclick = null;
        p.classList.remove('highlight-move');
        p.style.cursor = 'default';
    });

    pieces.forEach(p => {
        const pointDiv = document.getElementById(`pos-${p.position}`);
        if (!pointDiv) return;

        const posInt = parseInt(p.position);
        const pCount = parseInt(p.piece_count);
        
        let overlapMargin = '0px';
        if (pCount > 5) {
            let percentage = -40 - (pCount * 1.5);
            if (percentage < -75) percentage = -75;
            overlapMargin = `${percentage}%`;
        } else if (pCount > 1) { overlapMargin = '-5%'; }

        for (let k = 0; k < pCount; k++) {
            let piece = document.createElement('div');
            piece.className = `piece ${p.piece_color}`;
            if (k > 0) piece.style.marginTop = overlapMargin;

            if (k === 0 && parseInt(p.pinned_count) > 0) {
                piece.style.opacity = '0.5'; piece.style.border = '2px dashed #000';
            }

            if (p.piece_color === myColor && currentTurn === myColor && k === pCount - 1) {
                piece.style.cursor = 'pointer';
                piece.onclick = (e) => { e.stopPropagation(); selectPiece(posInt); };
            }
            pointDiv.appendChild(piece);
        }
    });
}

// --- 3. ACTIONS ---

async function rollDice() {
    const res = await apiCall({ action: 'roll_dice', game_id: gameId, token: myToken });
    if(res.status === 'success') {
        dice = [parseInt(res.die1), parseInt(res.die2)];
        if (dice[0] === dice[1]) dice = [dice[0], dice[0], dice[0], dice[0]];
        updateDiceUI();
        // Force update local state to allow interaction immediately
        // Δεν περιμένουμε το polling
        updateBoard(); 
    } else {
        alert(res.message);
    }
}

function updateDiceUI() {
    document.getElementById('die1').innerText = dice.length > 0 ? dice[0] : '-';
    document.getElementById('die2').innerText = dice.length > 1 ? dice[1] : '';
}

function selectPiece(pos) {
    if (dice.length === 0) return;
    pos = parseInt(pos);
    selectedPos = pos;

    document.querySelectorAll('.highlight-move').forEach(el => el.classList.remove('highlight-move'));
    document.querySelectorAll('.selected-piece').forEach(el => el.classList.remove('selected-piece'));

    const point = document.getElementById(`pos-${pos}`);
    const pieces = point.getElementsByClassName('piece');
    if (pieces.length > 0) pieces[pieces.length - 1].classList.add('selected-piece');

    const direction = (myColor === 'white') ? 1 : -1;
    let uniqueDice = [...new Set(dice)]; 

    uniqueDice.forEach(die => {
        let target = pos + (die * direction);
        if (target >= 1 && target <= 24) {
            if (isValidMove(target, myColor)) {
                const targetDiv = document.getElementById(`pos-${target}`);
                targetDiv.classList.add('highlight-move');
                targetDiv.onclick = () => executeMove(pos, target, die);
            }
        }
    });
}

function isValidMove(targetPos, color) {
    const targetSpot = boardState.find(p => parseInt(p.position) === targetPos);
    if (!targetSpot) return true;
    if (targetSpot.piece_color === color) return true;
    if (parseInt(targetSpot.piece_count) === 1) return true; 
    return false;
}

async function executeMove(from, to, dieVal) {
    // Optimistic UI Update: Κρύβουμε τα highlight για να μην ξαναπατηθούν
    document.querySelectorAll('.highlight-move').forEach(el => el.classList.remove('highlight-move'));
    document.querySelectorAll('.selected-piece').forEach(el => el.classList.remove('selected-piece'));
    
    const res = await apiCall({ 
        action: 'move_piece', 
        game_id: gameId, 
        token: myToken,
        from: from, 
        to: to, 
        color: myColor 
    });

    if (res.status === 'success') {
        const index = dice.indexOf(dieVal);
        if (index > -1) dice.splice(index, 1);
        updateDiceUI();
        selectedPos = null;
        
        // Force reset του lastJson για να αναγκάσουμε το Polling να ξαναζωγραφίσει το ταμπλό με τα νέα δεδομένα
        lastBoardJson = ""; 
        
        if (dice.length === 0) {
            await apiCall({ action: 'end_turn', game_id: gameId, token: myToken });
        }
        
        // Καλούμε το update άμεσα
        const t = new Date().getTime();
        const data = await fetch(`api.php?game_id=${gameId}&t=${t}`).then(r => r.json());
        lastBoardJson = JSON.stringify(data.board);
        renderBoard(data.board);

    } else {
        alert("Λάθος: " + res.message);
        // Αν αποτύχει, επαναφορά
        selectedPos = null;
    }
}

async function apiCall(data) {
    return await fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json());
}