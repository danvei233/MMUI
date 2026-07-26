<?php
namespace mmui;

class MmuiInstallState
{
    public static function read($root)
    {
        $file = self::file($root);
        $data = [];
        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        return [
            'installed' => !empty($data['installed']),
            'file' => $file,
            'data' => $data,
        ];
    }

    public static function complete($root, array $patchResult, $mmuiVersion)
    {
        $file = self::file($root);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        $payload = [
            'installed' => true,
            'installed_at' => date('c'),
            'mmui_version' => (string)$mmuiVersion,
            'qz_version' => (string)($patchResult['version']['version'] ?? ''),
            'qz_status' => (string)($patchResult['version']['status'] ?? 'unknown'),
            'compatible' => !empty($patchResult['compatible']),
            'backup' => (string)($patchResult['backup'] ?? ''),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            return false;
        }
        $temp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temp, $json, LOCK_EX) === false) {
            return false;
        }
        if (!@rename($temp, $file)) {
            @unlink($temp);
            return false;
        }
        return true;
    }

    private static function file($root)
    {
        return rtrim((string)$root, "\\/") . DIRECTORY_SEPARATOR
            . 'runtime' . DIRECTORY_SEPARATOR . 'mmui' . DIRECTORY_SEPARATOR . 'installation.json';
    }
}
