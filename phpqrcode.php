<?php
// generate_qr.php - debug-friendly local phpqrcode output
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// adjust path to where you placed phpqrcode.php
$lib = __DIR__ . '/libs/phpqrcode.php';
if (!file_exists($lib)) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "ERROR: phpqrcode.php not found at: $lib\n";
    exit;
}
require $lib;

$baseUrl = 'http://bloggingadeadhorse.com/resistance/index.html';
$gameId = isset($_GET['game_id']) ? $_GET['game_id'] : '123';
$name = isset($_GET['name']) ? $_GET['name'] : '';
$url = $baseUrl . '?game_id=' . urlencode($gameId). '&name=' . urlencode($name);

// Clear any previous output so image is pure binary
while (ob_get_level()) ob_end_clean();

// Send correct header
header('Content-Type: image/png');

// Good mobile settings
$size = 6;    // each QR module pixel size (increase to make image larger)
$margin = 4;  // quiet zone
// Use high error correction (if available)
QRcode::png($url, false, QR_ECLEVEL_H, $size, $margin, false);
exit;

?>