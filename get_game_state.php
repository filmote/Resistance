<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$gameId = $_GET['id'] ?? null;
if (!$gameId) {
    echo json_encode(['success' => false, 'error' => 'Missing game_id']);
    exit;
}

try {


    // Get game info
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }

    // Get players
    $stmt = $db->prepare("SELECT name, role, wins FROM players WHERE game_id = ? ORDER BY id");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get spies for spy players
    $spies = [];
    $stmt = $db->prepare("SELECT name FROM players WHERE game_id = ? AND role = 'spy'");
    $stmt->execute([$gameId]);
    $spies = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Parse game data

    $gameData = json_decode($game['game_data'] ?? '{}', true);
    // error_log($game['game_data']);
    // Initialize game data if needed
    if (empty($gameData)) {
        $r = rand(0, 4);
            // error_log("here ". $r);
            // error_log($players[$r]['name']);
        $gameData = [
            'current_mission' => 1,
            'phase' => 'team_building',
            'current_leader' => $players[$r]['name'], // Start with first player
            'mission_results' => [],
            'vote_track' => 0, // Track consecutive failed votes
            'resistance_wins' => 0,
            'spy_wins' => 0,
            'winner' => null
        ];
        
        // Save initialized data
        $updateStmt = $db->prepare("UPDATE games SET game_data = ?, update_at = datetime('now') WHERE id = ?");
        $updateStmt->execute([json_encode($gameData), $gameId]);
    }

    // Check for game over conditions
    if ($gameData['resistance_wins'] >= 3) {
        $gameData['winner'] = 'Resistance';
        $gameData['phase'] = 'game_over';
    } elseif ($gameData['spy_wins'] >= 3) {
        $gameData['winner'] = 'Spies';
        $gameData['phase'] = 'game_over';
    } elseif ($gameData['vote_track'] >= 5) {
        $gameData['winner'] = 'Spies';
        $gameData['phase'] = 'game_over';
    }

    $response = [
        'success' => true,
        'game_state' => [
            'game_id' => $gameId,
            'status' => $game['status'],
            'players' => $players,
            'spies' => $spies,
            'current_mission' => $gameData['current_mission'],
            'phase' => $gameData['phase'],
            'current_leader' => $gameData['current_leader'],
            'current_team' => $gameData['current_team'] ?? null,
            'mission_results' => $gameData['mission_results'],
            'vote_track' => $gameData['vote_track'],
            'resistance_wins' => $gameData['resistance_wins'],
            'spy_wins' => $gameData['spy_wins'],
            'winner' => $gameData['winner']
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>