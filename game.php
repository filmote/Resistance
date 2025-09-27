<?php
    error_reporting(E_ALL & ~E_DEPRECATED);
    require 'db.php';

    $gameId = $_GET['game_id'] ?? null;
    $playerName = $_GET['player'] ?? null;

    if (!$gameId || !$playerName) {
        header('Location: index.html');
        exit;
    }

    // Verify player exists and is claimed
    $stmt = $db->prepare("SELECT id, role FROM players WHERE game_id = ? AND name = ? AND claimed = 1");
    $stmt->execute([$gameId, $playerName]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        header('Location: index.html');
        exit;
    }

    // Get game info
    $stmt = $db->prepare("SELECT name, status FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>The Resistance - Game</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background-color: #dfdfdf; font-family: system-ui, -apple-system, Roboto, Arial; padding: 1em; max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1em; }
                
        .players { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 1em 0; }
        .player { padding: 10px; border: 2px solid #aaa; border-radius: 8px; height:22px; background: #f2f2f2; }
        .player.leader { border-color: #FF9800; background: #FFF3E0; height:22px;  }
        .player.selected { border-color: #2196F3; background: #E3F2FD; height:22px;  }
        .player.on-mission { border-color: #4CAF50; background: #E8F5E8; height:22px;  }

        .gameover { padding: 10px; border: 2px solid #aaa; border-radius: 8px; height:22px; background: #f2f2f2; width: 120px; text-align: center; }

        .game-phase { margin: 1em 0; padding: 0.2em 0.5em 0.3em 0.5em; background: #f5f5f5; border-radius: 8px; }
        .actions { margin: 1em 0; }
        .actions button { padding: 8px 16px; margin: 4px; font-size: 1em; cursor: pointer; }
        .actions button:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .log { height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: white; }
        .role-info { padding: 10px; margin: 10px 0; border-radius: 8px; }
        .role-resistance { background: #E8F5E8; border: 1px solid #4CAF50; }
        .role-spy { background: #FFEBEE; border: 1px solid #f44336; }
        
        .hidden { display: none; }
        .vote-buttons { display: flex; gap: 10px; margin: 10px 0; }
        .vote-buttons button { padding: 10px 20px; }
        .approve { background: #4CAF50; color: white; border: none; border-radius: 6px; min-width: 130px; height:45px; }
        .reject { background: #f44336; color: white; border: none; border-radius: 6px; min-width: 130px; height:45px; }


        hr {
            color: #cfcfcf; 
        }

		.btn-propose {
			border: none;
			border-radius: 6px;
			color: #fff;
			padding: 0.5em 0.9em;
			min-width: 90px;
			text-align: center;
		}

		.btn-propose {
			background: #2196F3;
		}

		.btn-propose:hover {
			background: #1976d2;
		}

        ul#history {
            font-size: 14px;        
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .history_background {
            border-radius: 8px;
            display: block;
            height: auto;
            background: #f2f2f2;
            padding: 5px 5px 5px 5px;
        } 

        .mission.failure { border-color: #f44336; background: #FFEBEE; }
        .mission.current { border-color: #2196F3; background: #E3F2FD; }
        .mission.success { border-color: #4CAF50; background: #E8F5E8; }

        .mission-track {
            display: flex;
            flex-wrap: wrap;
            justify-content: center; 
            gap: 8px;              
            max-width: 700px;
            margin: 1em auto;
        }

        .mission {
            flex: 1 1 calc(20% - 8px); 
            padding: 8px; border: 2px solid #ddd; border-radius: 6px; min-width: 60px; text-align: center;
            min-width: 60px;
            max-width: 80px;
            background: #eee;
        }

        /* On smaller screens → 3 per row */
        @media (max-width: 700px) {
            .mission {
                flex: 1 1 calc(33.333% - 10px);
            }
        }

        .alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            font-family: sans-serif;
            font-size: 14px;
            margin: 10px 0;
            color: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .alert-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .alert-message {
            flex-grow: 1;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            color: inherit;
        }

        /* Variants */
        .alert-success {
            background-color: #28a745;
        }
        .alert-error {
            background-color: #dc3545;
        }
        .alert-warning {
            background-color: #ffc107;
            color: #333;
        }
        .alert-info {
            background-color: #17a2b8;
        }


    </style>
</head>
<body>
        
    <!-- <h2>The Resistance: <?php echo htmlspecialchars($game['name']); ?></h2>
    <div>Playing as: <strong><?php echo htmlspecialchars($playerName); ?></strong></div> -->

    <div id="game-alert" class="hidden">
        <div class="alert-icon">⚠️</div>
        <div class="alert-message" id="alert-message">
            <strong>Error:</strong> Something went wrong while restarting the game.
        </div>
        <button class="alert-close" onclick="window.location.href='index.html'">&times;</button>
    </div>

    <div id="role" class="role-info role-<?php echo $player['role']; ?>">
        <span>Name: <strong><?php echo htmlspecialchars($playerName); ?></strong>&nbsp;&nbsp;&nbsp;&nbsp;Role: <strong><?php echo strtoupper($player['role']); ?></strong></span>
        <span id="spyInfo" class="hidden"></span>
    </div>

    <div class="mission-track">
        <div class="mission current" id="mission1">Mission 1<br><small>2 players</small></div>
        <div class="mission" id="mission2">Mission 2<br><small>3 players</small></div>
        <div class="mission" id="mission3">Mission 3<br><small>2 players</small></div>
        <div class="mission" id="mission4">Mission 4<br><small>3 players</small></div>
        <div class="mission" id="mission5">Mission 5<br><small>3 players</small></div>
    </div>

    <div class="game-phase" id="gamePhase">
        <h3 id="phaseTitle">Loading...</h3>
        <div id="phaseDescription"></div>
    </div>

    <div class="players" id="players"></div>

    <div class="actions" id="actions"></div>

    <div id="votingSection" class="vote-section hidden">
        <h3>Vote on Team Proposal</h3>
        <div id="proposedTeam"></div>
        <div class="vote-buttons">
            <button class="approve" onclick="submitVote(true)">👍 APPROVE</button>
            <button class="reject" onclick="submitVote(false)">👎 REJECT</button>
        </div>
    </div>

    <div id="missionSection" class="mission-section hidden">
        <h3>Mission Action</h3>
        <p>Choose your action for this mission:</p>
        <div class="vote-buttons">
            <button class="approve" onclick="submitMissionAction('success')">✅ SUCCESS</button>
            <button class="reject" onclick="submitMissionAction('fail')" id="failButton">❌ FAIL</button>
        </div>
    </div>

    <br/>
    <h3>History</h3>
    <div class="history_background">
        <ul id="history"></ul>
    </div>

    <br/>
    <a href="index.html">Back</a>

    <script>
        gameId = <?php echo $gameId; ?>;
        playerName = <?php echo json_encode($playerName); ?>;
        playerRole = <?php echo json_encode($player['role']); ?>;
        
        let gameState = {};
        let selectedPlayers = [];
        let votedTeam = false;
        let votedMission = false;

        // Mission sizes for each round
        const missionSizes = [2, 3, 2, 3, 3];

        function showAlertMsg(type, msg) {
            const alertBox = document.getElementById('game-alert');
            const msgBox = document.getElementById('alert-message');
            alertBox.className = `alert alert-${type}`;
            msgBox.textContent = msg;
            alertBox.classList.remove('hidden');
        }

        function hideAlert() {
            document.getElementById('game-alert').classList.add('hidden');
        }

        function restartGame() {

            fetch('restart_game.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: 'id=' + gameId 
            })
            .then(r => {
                if (!r.ok) { // HTTP-level error
                    throw new Error(`Server error ${r.status}: ${r.statusText}`);
                }
                return r.json(); // try parse JSON
            })
            .then(d => {
                if (d.success) {
                    updateGameState();
                } else {
                    if (d.error == "Game not found") {
                        showAlertMsg("error", 'The current game has been ended.');
                    }
                    else {
                        showAlertMsg("error", 'Fetch failed: ' + err.message);
                    }                    
                }
            })
            .catch(err => {
                showAlertMsg("error", 'Fetch failed: ' + err.message);
            });

        }

        function updateGameState() {

            fetch(`get_game_state.php?id=${gameId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        gameState = data.game_state;
                        updateUI();
                    }
                })
                .catch(err => console.error(err));
        }

        function updateUI() {

            updateMissionTrack();
            updatePlayers();
            updatePhase();
            updateActions();
            loadNotes();
            
            // Show spy info if player is spy

            const roleDiv = document.getElementById('role');
            const spyInfo = document.getElementById('spyInfo');

            roleDiv.classList.remove('role-resistance');
            roleDiv.classList.remove('role-spy');

            gameState.players.forEach(player => {
                if (player.name === playerName) {
                    //alert(player.role);
                    playerRole = player.role;
                }
            });

            // if (playerRole === 'spy' && gameState.spies) {
            if (playerRole === 'spy') {
                roleDiv.classList.add('role-spy');
                spyInfo.innerHTML = '&nbsp;&nbsp;&nbsp;Partner: <strong>' + gameState.spies.filter(s => s !== playerName).join(', ') + '</strong>';
                spyInfo.classList.remove('hidden');
            }
            else {
                roleDiv.classList.add('role-resistance');
                spyInfo.classList.add('hidden');
            }

        }

		function loadNotes() {

			fetch('list_notes.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + gameId })
				.then(r => r.json())
				.then(d => {
					if (!d.success) { alert('Error loading notes: ' + (d.error || 'unknown')); return; }
					const list = document.getElementById("history");
					list.innerHTML = '';
					if (d.notes.length === 0) {
						list.innerHTML = '<li>No notes available</li>';
						return;
					}
					const ul = document.getElementById("history");
					ul.innerHTML = "";
					d.notes.forEach(note => {
                        const li = document.createElement("li");
                        li.innerHTML = note.notes;
						ul.appendChild(li);//note.note);
					});
				}).catch(err => { console.error(err); alert('Failed to load notes: ' + err); });
		}

        function updateMissionTrack() {

            for (let i = 1; i <= 5; i++) {
                const mission = document.getElementById(`mission${i}`);
                mission.className = 'mission';
                
                if (i < gameState.current_mission) {
                    mission.classList.add(gameState.mission_results[i-1] ? 'success' : 'failure');
                } else if (i === gameState.current_mission) {
                    mission.classList.add('current');
                }

            }

        }

        function updatePlayers() {

            const container = document.getElementById('players');
            container.innerHTML = '';

            gameState.players.forEach(player => {
                const div = document.createElement('div');
                div.className = 'player';
                div.onclick = () => togglePlayerSelection(player.name);
                
                if (player.name === gameState.current_leader) {
                    div.classList.add('leader');
                }
                if (gameState.current_team && gameState.current_team.includes(player.name)) {
                    div.classList.add('on-mission');
                }
                if (selectedPlayers.includes(player.name)) {
                    div.classList.add('selected');
                }
                
                div.innerHTML = `
                    <strong>${player.name}</strong>
                    ${player.name === gameState.current_leader ? '🎖️' : ''}
                    ${gameState.current_team && gameState.current_team.includes(player.name) ? '🪖' : ''}
                    
                `;
                container.appendChild(div);

            });

        }

        function updatePhase() {

            const title = document.getElementById('phaseTitle');
            const desc = document.getElementById('phaseDescription');
            
            switch (gameState.phase) {
                case 'team_building':
                    votedTeam = false;
                    title.textContent = `Mission ${gameState.current_mission} - Team Building`;
                    if (playerName === gameState.current_leader) {
                        desc.textContent = `You are the leader! Select ${missionSizes[gameState.current_mission-1]} players for the mission.`;
                    } else {
                        desc.textContent = `${gameState.current_leader} is selecting the team...`;
                    }
                    break;

                case 'voting':
                    votedMission = false;
                    title.textContent = `Mission ${gameState.current_mission} - Team Vote`;
                    desc.textContent = 'Vote to approve or reject the proposed team.';
                    break;

                case 'mission':
                    votedTeam = false;
                    title.textContent = `Mission ${gameState.current_mission} - Mission Phase`;
                    desc.textContent = 'Team members: choose your action!';
                    break;

                case 'game_over':
                    title.innerHTML = '<h1>🏆 Game Over!</h1>';
                    content = `<h2>${gameState.winner} wins</h2>`;

                    gameState.players.forEach(player => {
                        content = content + player.wins + ` wins for ` + player.name + `.<br/>`;
                    });

                    content = content + "<br/><div class='gameover' onClick='restartGame();'>New Game</div>";

                    desc.innerHTML = content;

                    break;

                default:
                    title.textContent = 'Waiting...';
                    desc.textContent = '';
            }

        }

        function updateActions() {

            const actions = document.getElementById('actions');
            const voting = document.getElementById('votingSection');
            const mission = document.getElementById('missionSection');
            
            // Hide all sections first
            voting.classList.add('hidden');
            mission.classList.add('hidden');
            actions.innerHTML = '';

            switch (gameState.phase) {
                case 'team_building':
                    if (playerName === gameState.current_leader) {
                        const button = document.createElement('button');
                        button.classList.add('btn-propose');
                        button.textContent = 'Propose Team';
                        button.onclick = proposeTeam;
                        button.disabled = selectedPlayers.length !== missionSizes[gameState.current_mission-1];
                        actions.appendChild(button);
                    }
                    break;

                case 'voting':
                    //alert(gameState.$gameData['votes'][$player]);
                    if (!votedTeam) {
                        voting.classList.remove('hidden');
                    }
                    const teamDiv = document.getElementById('proposedTeam');
                    teamDiv.innerHTML = 'Proposed Team: ' + (gameState.current_team || []).join(', ');
                    break;

                case 'mission':
                    if (gameState.current_team && gameState.current_team.includes(playerName)) {
                        if (!votedMission) {
                            mission.classList.remove('hidden');
                        }
                        // Spies can fail, resistance can only succeed
                        document.getElementById('failButton').style.display = playerRole === 'spy' ? 'block' : 'none';
                    }
                    break;

            }
        }

        function togglePlayerSelection(name) {

            if (gameState.phase !== 'team_building' || playerName !== gameState.current_leader) return;
            
            const index = selectedPlayers.indexOf(name);
            if (index === -1) {
                if (selectedPlayers.length < missionSizes[gameState.current_mission-1]) {
                    selectedPlayers.push(name);
                }
            } else {
                selectedPlayers.splice(index, 1);
            }
            updatePlayers();
            updateActions();

        }

        function proposeTeam() {

            if (selectedPlayers.length !== missionSizes[gameState.current_mission-1]) return;
            
            fetch('game_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `game_id=${gameId}&player=${encodeURIComponent(playerName)}&action=propose_team&team=${encodeURIComponent(JSON.stringify(selectedPlayers))}`
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    selectedPlayers = [];
                    updateGameState();
                } else {
                    alert('Error: ' + (data.error || 'unknown'));
                }
            });

        }

        function submitVote(approve) {
            fetch('game_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `game_id=${gameId}&player=${encodeURIComponent(playerName)}&action=vote&vote=${approve ? 1 : 0}`
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    votedTeam = true;
                    document.getElementById('votingSection').classList.add('hidden');
                } 
                else {
                    alert('Error: ' + (data.error || 'unknown'));
                }
            });
        }

        function submitMissionAction(action) {
            fetch('game_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `game_id=${gameId}&player=${encodeURIComponent(playerName)}&action=mission&mission_action=${action}`
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    votedMission = true;
                    document.getElementById('missionSection').classList.add('hidden');
                } 
                else {
                    alert('Error: ' + (data.error || 'unknown'));
                }
            });
        }

        // Poll for updates
        setInterval(updateGameState, 4000);
        updateGameState();

    </script>

    <script>
        const tabs = document.querySelectorAll('input[name="tab-control"]');
        const contents = document.querySelectorAll('.content');

        tabs.forEach(tab => {
        tab.addEventListener('change', (e) => {
            // hide all
            contents.forEach(c => c.classList.remove('active'));
            // show matching
            if (e.target.id === "tab1") document.getElementById("content1").classList.add("active");
            if (e.target.id === "tab2") document.getElementById("content2").classList.add("active");
            if (e.target.id === "tab3") document.getElementById("content3").classList.add("active");
        });
        });
    </script>
</body>
</html>