<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$gameId = $_POST['game_id'] ?? null;
$player = $_POST['player'] ?? null;
$action = $_POST['action'] ?? null;

if (!$gameId || !$player || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

try {
    $db->beginTransaction();

    // Get game
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        throw new Exception('Game not found');
    }

    // Verify player
    $stmt = $db->prepare("SELECT * FROM players WHERE game_id = ? AND name = ? AND claimed = 1");
    $stmt->execute([$gameId, $player]);
    $playerData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$playerData) {
        throw new Exception('Player not found or not claimed');
    }

    $gameData = json_decode($game['game_data'] ?? '{}', true);
    
    // Get all players for reference
    $stmt = $db->prepare("SELECT name FROM players WHERE game_id = ? ORDER BY id");
    $stmt->execute([$gameId]);
    $allPlayerNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $response = ['success' => true];

    switch ($action) {

        case 'propose_team':
            if ($gameData['phase'] !== 'team_building' || $gameData['current_leader'] !== $player) {
                throw new Exception('Not your turn to propose team');
            }
            
            $team = json_decode($_POST['team'] ?? '[]', true);
            $missionSizes = [2, 3, 2, 3, 3];
            $expectedSize = $missionSizes[$gameData['current_mission'] - 1];
            
            if (count($team) !== $expectedSize) {
                throw new Exception('Invalid team size');
            }
            
            $gameData['current_team'] = $team;
            $gameData['phase'] = 'voting';
            $gameData['votes'] = [];
            $gameData['vote_results'] = [];

            // Log: players vote.
            $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '<b>Mission " . $gameData['current_mission'] . "</b>: " . $player . " proposed players ";
            for ($i = 0; $i < $expectedSize; $i++) {
                $sql = $sql . $team[$i];
                if ($i < $expectedSize - 2) {
                    $sql = $sql . ", ";
                }
                if ($i < $expectedSize - 1) {
                    $sql = $sql . " and ";
                }
            }
            $sql = $sql . ".', 0," . $gameData['current_mission'] . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute();

            break;
            
        case 'vote':
            if ($gameData['phase'] !== 'voting') {
                throw new Exception('Not voting phase');
            }
            
            $vote = (int)($_POST['vote'] ?? 0);
            $gameData['votes'][$player] = $vote;


            // Log: players vote.
            $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;&nbsp;&nbsp;&nbsp;" . ($vote == 1 ? '👍' : '👎') . "&nbsp;&nbsp;" . $player . " " . ($vote == 1 ? 'approved' : 'rejected') . " the mission.', 1, " . $gameData['current_mission'] . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute();

            $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;&nbsp;&nbsp;&nbsp;" . $player . " has voted.', 2," . $gameData['current_mission'] . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute();

            // Check if all votes are in
            if (count($gameData['votes']) >= 5) {


                // Remove temporary messages and display players votes ..

                $sql = "delete from notes where hide = 2 and game_id = " . $gameId;
                $stmt = $db->prepare($sql);
                $stmt->execute();

                $sql = "update notes set hide = 0 where hide = 1 and game_id = " . $gameId;
                $stmt = $db->prepare($sql);
                $stmt->execute();

                $approvals = array_sum($gameData['votes']);
                $approved = $approvals > 2; // Need majority (3+ votes)
                
                $gameData['vote_results'][] = [
                    'team' => $gameData['current_team'],
                    'votes' => $gameData['votes'],
                    'approved' => $approved
                ];
                
                if ($approved) {

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '✅&nbsp;&nbspMission approved.', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '<b>Mission " . $gameData['current_mission'] . "</b>: started.', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    // Team approved, start mission
                    $gameData['phase'] = 'mission';
                    $gameData['mission_actions'] = [];
                    $gameData['vote_track'] = 0; // Reset vote track

                } else {

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '❌&nbsp;&nbspMission rejected.', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    // Team rejected
                    $gameData['vote_track']++;
                    
                    if ($gameData['vote_track'] >= 5) {
                        // 5 failed votes = spies win
                        $gameData['winner'] = 'Spies';
                        $gameData['phase'] = 'game_over';
                    } else {
                        // Next leader, new team building phase
                        $currentLeaderIndex = array_search($gameData['current_leader'], $allPlayerNames);
                        $nextLeaderIndex = ($currentLeaderIndex + 1) % 5;
                        $gameData['current_leader'] = $allPlayerNames[$nextLeaderIndex];
                        $gameData['phase'] = 'team_building';
                        $gameData['current_team'] = null;
                    }
                }
                $gameData['votes'] = [];
            }
            
            break;
            
        case 'mission':
            if ($gameData['phase'] !== 'mission') {
                throw new Exception('Not mission phase');
            }
            
            if (!in_array($player, $gameData['current_team'])) {
                throw new Exception('You are not on this mission');
            }
            
            $missionAction = $_POST['mission_action'] ?? null;
            if (!in_array($missionAction, ['success', 'fail'])) {
                throw new Exception('Invalid mission action');
            }
            
            // Resistance can only choose success
            if ($playerData['role'] === 'resistance' && $missionAction === 'fail') {
                throw new Exception('Resistance cannot fail missions');
            }
            
            $gameData['mission_actions'][$player] = $missionAction;

            $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;&nbsp;&nbsp;&nbsp;" . $player . " has completed the mission.', 4," . $gameData['current_mission'] . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute();

            // Check if all mission actions are in
            if (count($gameData['mission_actions']) >= count($gameData['current_team'])) {


                // Remove temporary messages and display players votes ..

                $sql = "delete from notes where hide = 4 and game_id = " . $gameId;
                $stmt = $db->prepare($sql);
                $stmt->execute();

                $failures = array_count_values($gameData['mission_actions'])['fail'] ?? 0;
                $missionSuccess = $failures === 0;
                
                if ($missionSuccess) {

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '✅&nbsp;&nbsp;Mission successful.', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $gameData['resistance_wins']++;

                } 
                else {

                    if ($failures > 0) {

                        $success = count($gameData['current_team']) - $failures;

                        $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;&nbsp;&nbsp;&nbsp;✅&nbsp;&nbsp;". $success . " successful votes.', 0," . $gameData['current_mission'] . ")";
                        $stmt = $db->prepare($sql);
                        $stmt->execute();

                        $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;&nbsp;&nbsp;&nbsp;❌&nbsp;&nbsp;". $failures . " failed votes.', 0," . $gameData['current_mission'] . ")";
                        $stmt = $db->prepare($sql);
                        $stmt->execute();
                    
                    }

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '❌&nbsp;&nbsp;Mission failed.', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $gameData['spy_wins']++;

                }
                
                $gameData['mission_results'][] = $missionSuccess;
                
                // Check for game over
                if ($gameData['resistance_wins'] >= 3 || $gameData['spy_wins'] >= 3) {

                    $gameData['current_mission']++;
                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '🏅&nbsp;&nbsp;Game over! The " . ($gameData['resistance_wins'] >= 3 ? 'Resistance' : 'Spies') . " wins!', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '&nbsp;', 0," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $gameData['winner'] = $gameData['resistance_wins'] >= 3 ? 'Resistance' : 'Spies'; //SJH 3
                    $gameData['phase'] = 'game_over';

                    if ($gameData['resistance_wins'] >= 3) {

                        $sql = "update players set wins = wins + 1 where role = 'resistance' and game_id = " . $gameId;
                        $stmt = $db->prepare($sql);
                        $stmt->execute();

                    }

                    if ($gameData['spy_wins'] >= 3) {

                        $sql = "update players set wins = wins + 1 where role = 'spy' and game_id = " . $gameId;
                        $stmt = $db->prepare($sql);
                        $stmt->execute();

                    }

                } 
                else {


                    // Next mission
                    $gameData['current_mission']++;
                    $gameData['phase'] = 'team_building';

                    $sql = "update notes set hide = 0 where hide = 5 and game_id = " . $gameId;
                    $stmt = $db->prepare($sql);
                    $stmt->execute();

                    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '<hr/><br/>', 5," . $gameData['current_mission'] . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
   

                    // Next leader
                  
                    $currentLeaderIndex = array_search($gameData['current_leader'], $allPlayerNames);
                    $nextLeaderIndex = ($currentLeaderIndex + 1) % 5;
                    $gameData['current_leader'] = $allPlayerNames[$nextLeaderIndex];
                    
                    $gameData['current_team'] = null;
                    $gameData['vote_track'] = 0;
                }
                
                $gameData['mission_actions'] = [];
            }
            
            break;
            
        default:
            throw new Exception('Unknown action');
    }

    // Save updated game data
    $stmt = $db->prepare("UPDATE games SET game_data = ?, update_at = datetime('now') WHERE id = ?");
    $stmt->execute([json_encode($gameData), $gameId]);

    $db->commit();
    echo json_encode($response);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>