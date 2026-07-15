<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this installer from CLI.');
}

$root = __DIR__;
require_once $root . '/extend/mmui/MmuiQzVersion.php';
require_once $root . '/extend/mmui/MmuiPatch.php';

$result = \mmui\MmuiPatch::repairRoot($root);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
