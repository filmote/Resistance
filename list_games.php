<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

require 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {



    // Old games cleanup ..

    $countStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM games WHERE update_at < datetime('now', '-1 hour')");
    $countStmt->execute(); // <-- you were missing this
    $count = (int)$countStmt->fetchColumn();

    if ($count > 0) {
        // Delete them
        $deleteStmt = $db->prepare("DELETE FROM notes WHERE game_id in (select id from games where update_at < datetime('now', '-1 hour'))");
        $deleteStmt->execute();

        $deleteStmt = $db->prepare("DELETE FROM players WHERE game_id in (select id from games where update_at < datetime('now', '-1 hour'))");
        $deleteStmt->execute();

        $deleteStmt = $db->prepare("DELETE FROM games WHERE update_at < datetime('now', '-1 hour') ");
        $deleteStmt->execute();

        //("Deleted ${count} records.");

    } 
    else {
        //error_log("No old records found.");
    }


    $stmt = $db->query("SELECT g.id, g.name, g.status,
        (SELECT COUNT(*) FROM players p WHERE p.game_id = g.id and p.claimed = 1) AS player_count
        FROM games g
        WHERE g.status IN ('waiting','active')
        ORDER BY g.created_at DESC");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'games'=>$games]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>