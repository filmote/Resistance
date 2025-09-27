<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing game ID']);
    exit;
}

try {

    $stmt = $db->query("SELECT id, game_id, notes FROM notes 
        where game_id = " . $id . " and hide in (0, 2, 4, 6, 8, 10)
        ORDER BY mission DESC, id ASC");
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'notes'=>$notes]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>