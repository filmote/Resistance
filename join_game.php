<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$gameId = $_POST['game_id'] ?? null;
$name   = trim($_POST['name'] ?? '');

if (!$gameId || $name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing game_id or name']);
    exit;
}

try {
    // find the player record by exact name + game
    $stmt = $db->prepare("SELECT id, role, claimed FROM players WHERE game_id = ? AND name = ?");
    $stmt->execute([$gameId, $name]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Incorrect game or name (exact match required)']);
        exit;
    }

    if ((int)$player['claimed'] === 1) {
        echo json_encode(['success'=>false,'error'=>'This name has already been claimed from another browser/device']);
        exit;
    }

    // mark claimed
    $u = $db->prepare("UPDATE players SET claimed = 1 WHERE id = ?");
    $u->execute([$player['id']]);

    // if all players claimed, set game active
    $c = $db->prepare("SELECT COUNT(*) FROM players WHERE game_id = ? AND claimed = 1");
    $c->execute([$gameId]);
    $claimedCount = (int)$c->fetchColumn();
    if ($claimedCount >= 5) {
        $db->prepare("UPDATE games SET status = 'active', update_at = datetime('now') WHERE id = ?")->execute([$gameId]);
    }

    // reveal spies if player is spy
    $spies = [];
    if ($player['role'] === 'spy') {
        $s = $db->prepare("SELECT name FROM players WHERE game_id = ? AND role = 'spy' AND name != ?");
        $s->execute([$gameId, $name]);
        $spies = $s->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode([
        'success' => true,
        'role'    => $player['role'],
        'name'    => $name,
        'game_id' => $gameId,
        'spies'   => $spies,
        'claimed_count' => $claimedCount,
        'redirect_to_game' => $claimedCount >= 5
    ]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>