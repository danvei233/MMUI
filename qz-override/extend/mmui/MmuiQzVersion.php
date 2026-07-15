<?php
namespace mmui;

class MmuiQzVersion
{
    const MIN_TESTED = 2024083001;
    const CURRENT_TESTED = 2026070901;
    const HYPERV_DIP_VERSION = 2025111901;

    public static function detect($root)
    {
        $root = rtrim((string)$root, "\\/") . DIRECTORY_SEPARATOR;
        $version = self::fromDatabase();
        $source = $version > 0 ? 'database' : '';

        if ($version <= 0) {
            $version = self::fromUpdateDirectory($root);
            $source = $version > 0 ? 'update-directory' : '';
        }
        if ($version <= 0) {
            $version = self::fromInstallSql($root);
            $source = $version > 0 ? 'install-sql' : 'unknown';
        }

        $tested = $version >= self::MIN_TESTED && $version <= self::CURRENT_TESTED;
        if ($version <= 0) {
            $status = 'unknown';
            $label = '未识别轻舟版本';
        } elseif ($tested) {
            $status = 'supported';
            $label = '轻舟 ' . $version . '（已验证）';
        } elseif ($version > self::CURRENT_TESTED) {
            $status = 'future';
            $label = '轻舟 ' . $version . '（高于已验证版本）';
        } else {
            $status = 'legacy';
            $label = '轻舟 ' . $version . '（旧于已验证版本）';
        }

        return [
            'version' => $version > 0 ? (string)$version : '',
            'number' => $version,
            'source' => $source,
            'status' => $status,
            'tested' => $tested,
            'label' => $label,
            'profile' => $version >= self::HYPERV_DIP_VERSION ? 'hyperv-dip' : 'legacy-domain',
            'min_tested' => (string)self::MIN_TESTED,
            'max_tested' => (string)self::CURRENT_TESTED,
        ];
    }

    private static function fromDatabase()
    {
        if (!class_exists('\\app\\common\\model\\NewVersion')) {
            return 0;
        }
        try {
            $model = new \app\common\model\NewVersion();
            $row = $model->where(['id' => 1])->find();
            return $row ? self::normalize($row['version']) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function fromUpdateDirectory($root)
    {
        $versions = [];
        foreach (['update/unzip', 'update/zip'] as $relative) {
            $dir = $root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_dir($dir)) {
                continue;
            }
            foreach ((array)scandir($dir) as $name) {
                $version = self::normalize($name);
                if ($version > 0) {
                    $versions[] = $version;
                }
            }
        }
        return $versions ? max($versions) : 0;
    }

    private static function fromInstallSql($root)
    {
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, 'app/install/data/data.sql');
        if (!is_file($file)) {
            return 0;
        }
        $sql = (string)@file_get_contents($file);
        if (preg_match('/INSERT\s+INTO\s+`?cloud_new_version`?.*?[\(\'\"](20\d{8})/is', $sql, $matches)) {
            return self::normalize($matches[1]);
        }
        return 0;
    }

    private static function normalize($value)
    {
        if (preg_match('/(20\d{8})/', (string)$value, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
}
