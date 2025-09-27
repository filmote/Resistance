<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing game ID']);
    exit;
}

try {
    $stmt = $db->prepare("DELETE FROM players WHERE game_id = ?");
    $stmt->execute([$id]);
    $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>