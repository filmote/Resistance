<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$gameId = $_GET['game_id'] ?? null;

// if (!$gameId) {
//     echo json_encode(['success'=>false,'error'=>'Missing game_id']);
//     exit;
// }

try {

    if (!$gameId) {
        $stmt = $db->prepare("SELECT id,name,status FROM games WHERE id = (select max(id) from games)");
        $stmt->execute();
    }
    else {
        $stmt = $db->prepare("SELECT id,name,status FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
    }

    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$game) {
        echo json_encode(['success'=>false,'error'=>'Game not found']);
        exit;
    }

    $stmt = $db->prepare("SELECT id,name,claimed FROM players WHERE game_id = ? ORDER BY id");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'game'=>$game,'players'=>$players]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>