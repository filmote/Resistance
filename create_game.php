<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$name  = trim($_POST['game_name'] ?? '');
$names = $_POST['names'] ?? null;

if (!$name || !is_array($names) || count($names) !== 5) {
    echo json_encode(['success' => false, 'error' => 'Need game name and exactly 5 player names']);
    exit;
}

$clean = array_map('trim', $names);
foreach ($clean as $n) {
    if ($n === '') {
        echo json_encode(['success'=>false,'error'=>'All 5 player names must be non-empty']);
        exit;
    }
}
if (count(array_unique($clean)) !== 5) {
    echo json_encode(['success'=>false,'error'=>'Player names must be unique']);
    exit;
}

try {
    $db->beginTransaction();

    // ensure unique game name among waiting/active
    $stmt = $db->prepare("SELECT id FROM games WHERE name = ? AND status IN ('waiting','active')");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        $db->rollBack();
        echo json_encode(['success'=>false,'error'=>'Game name already in use']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO games (name, status, game_data, update_at) VALUES (?, 'waiting', '{}', datetime('now'))");
    $stmt->execute([$name]);
    $gameId = $db->lastInsertId();

    $roles = ['spy','spy','resistance','resistance','resistance'];
    shuffle($roles);

    $ins = $db->prepare("INSERT INTO players (id, game_id, name, role) VALUES (?, ?, ?, ?)");
    for ($i=0;$i<5;$i++) {
        $ins->execute([$i, $gameId, $clean[$i], $roles[$i]]);
    }

    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '<hr/><br/>', 5, 1)";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $db->commit();
    echo json_encode(['success'=>true,'game_name'=>$name,'game_id'=>$gameId]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>