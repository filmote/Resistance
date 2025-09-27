<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$gameId = $_POST['id'] ?? null;
if (!$gameId) {
    echo json_encode(['success' => false, 'error' => 'Missing game ID']);
    exit;
}


try {



    // Get game
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        throw new Exception('Game not found');
    }


    $db->beginTransaction();

    // ensure unique game name among waiting/active
    // $stmt = $db->prepare("SELECT id FROM games WHERE name = ? AND status IN ('waiting','active')");
    // $stmt->execute([$name]);
    // if ($stmt->fetch()) {
    //     $db->rollBack();
    //     echo json_encode(['success'=>false,'error'=>'Game name already in use']);
    //     exit;
    // }

    $stmt = $db->prepare("update games set status = '', game_data = '{}', update_at = datetime('now') where id = ?");
    $stmt->execute([$gameId]);

    $stmt = $db->prepare("delete from notes where game_id = ?");
    $stmt->execute([$gameId]);
    
    $sql = "INSERT INTO notes (game_id, notes, hide, mission) VALUES (" . $gameId . ", '<hr/><br/>', 5, 1)";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $roles = ['spy','spy','resistance','resistance','resistance'];
    shuffle($roles);

    $ins = $db->prepare("update players set role = ? where id = ? and game_id = ?");
    for ($i=0;$i<5;$i++) {
        $ins->execute([$roles[$i], $i, $gameId]);
    }

    $db->commit();
    echo json_encode(['success'=>true,'game_id'=>$gameId]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>