<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors to browser

$path = __DIR__ . '/resistance.db';
$dsn = "sqlite:$path";
try {
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //  $db->exec("CREATE TABLE IF NOT EXISTS games (
    //     id INTEGER PRIMARY KEY AUTOINCREMENT,
    //     name TEXT UNIQUE,
    //     game_data TEXT,
    //     status TEXT DEFAULT 'waiting',
    //     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    //     update_at DATETIME CURRENT_TIMESTAMP
    // )");
        
    // $db->exec("CREATE TABLE IF NOT EXISTS players (
    //     id INTEGER,
    //     game_id INTEGER,
    //     name TEXT,
    //     role TEXT,
    //     claimed INTEGER DEFAULT 0,
    //     wins INTEGER DEFAULT 0,
    //     FOREIGN KEY (game_id) REFERENCES games(id)
    // )");

    // $db->exec("CREATE TABLE IF NOT EXISTS notes (
    //         id INTEGER PRIMARY KEY AUTOINCREMENT,
    //         game_id	INTEGER,
    //         notes	TEXT,
    //         hide INTEGER,
    //         mission INTEGER,
    //         FOREIGN KEY (game_id) REFERENCES games(id)
    // )");

    //error_log("DB Success");
    
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error'=>'DB Error: '.$e->getMessage()]);
    exit;
}
?>