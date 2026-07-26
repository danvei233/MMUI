<?php
namespace mmui;

class MmuiHealth
{
    public static function check($app)
    {
        $root = rtrim($app->getRootPath(), "\\/") . DIRECTORY_SEPARATOR;
        $version = class_exists('\\mmui\\MmuiQzVersion')
            ? MmuiQzVersion::detect($root)
            : ['version' => '', 'status' => 'unknown', 'label' => '版本解析器缺失'];
        $items = [
            self::versionItem($version),
            self::fileItem($root, 'extend/mmui/MmuiEcsBridge.php', 'ECS Bridge'),
            self::fileItem($root, 'extend/mmui/MmuiEcsPayload.php', 'ECS Payload 构造器'),
            self::fileItem($root, 'extend/mmui/MmuiLoginBridge.php', 'Login Bridge'),
            self::fileItem($root, 'extend/mmui/MmuiPatch.php', 'Hook 修复模块'),
            self::fileItem($root, 'extend/mmui/MmuiQzVersion.php', '轻舟版本解析器'),
            self::fileItem($root, 'extend/mmui/MmuiInstallState.php', '安装状态模块'),
            self::containsItem($root, 'app/control/controller/Ecs.php', 'MmuiEcsBridge::tryHandle', 'ECS 控制器 Hook'),
            self::containsItem($root, 'app/index/controller/Index.php', 'MmuiLoginBridge::tryHandle', '登录页 Hook'),
            self::containsItem($root, 'app/admin/controller/Index.php', '/mmui/index', '后台菜单入口'),
            self::containsItem($root, 'app/common/service/Ecs.php', 'addForwardDomain', '域名白名单 Service'),
            self::deleteContractItem($root),
            self::fileItem($root, 'app/admin/controller/Mmui.php', 'MMUI 设置控制器'),
            self::fileItem($root, 'view/admin/mmui/index.html', 'MMUI 设置页面'),
            self::fileItem($root, 'view/control/ecs/mmui.html', 'ECS MMUI 模板'),
            self::fileItem($root, 'view/control/ecs/mmui_bundle.html', 'ECS 构建模板'),
            self::fileItem($root, 'view/control/ecs/mmui_dev.html', 'ECS Dev 模板'),
            self::fileItem($root, 'view/control/ecs/mmui_analytics.html', 'ECS 统计模板'),
            self::fileItem($root, 'view/index/index/mmui.html', 'LoginUI 模板'),
            self::fileItem($root, 'view/index/index/mmui_bundle.html', 'LoginUI 构建模板'),
            self::fileItem($root, 'public/static/component/auroraboat/login/index.html', 'LoginUI 静态资源'),
            self::dirItem($root, 'public/src/static/mmui', 'ECS 静态资源目录'),
            self::dirWritableItem($root, 'public/src/static/mmui/uploads', '图标上传目录'),
        ];

        $missing = 0;
        foreach ($items as $item) {
            if (!$item['ok']) {
                $missing++;
            }
        }

        return [
            'ok' => $missing === 0,
            'total' => count($items),
            'missing' => $missing,
            'label' => $missing === 0 ? 'MMUI Hook 与资源完整' : '发现 ' . $missing . ' 个兼容项异常',
            'version' => $version,
            'items' => $items,
        ];
    }

    private static function versionItem($version)
    {
        $ok = ($version['status'] ?? '') === 'supported';
        return [
            'label' => '轻舟版本',
            'path' => 'cloud_new_version',
            'ok' => $ok,
            'class' => $ok ? '' : 'is-bad',
            'message' => $version['label'] ?? '未识别',
        ];
    }

    private static function deleteContractItem($root)
    {
        $path = 'app/common/service/Ecs.php';
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $source = is_file($file) ? (string)file_get_contents($file) : '';
        $ok = strpos($source, 'batDeleteDomainHost') !== false
            && strpos($source, "'dip'=>\$host->ip") !== false
            && strpos($source, "'ip'=>\$host->ip") !== false;
        return [
            'label' => '删除协议 dip/ip',
            'path' => $path,
            'ok' => $ok,
            'class' => $ok ? '' : 'is-bad',
            'message' => $ok ? '已兼容' : '需要修复',
        ];
    }

    private static function fileItem($root, $path, $label)
    {
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $ok = is_file($file);
        return [
            'label' => $label,
            'path' => $path,
            'ok' => $ok,
            'class' => $ok ? '' : 'is-bad',
            'message' => $ok ? '正常' : '文件缺失',
        ];
    }

    private static function dirItem($root, $path, $label)
    {
        $dir = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $ok = is_dir($dir);
        return [
            'label' => $label,
            'path' => $path,
            'ok' => $ok,
            'class' => $ok ? '' : 'is-bad',
            'message' => $ok ? '正常' : '目录缺失',
        ];
    }

    private static function dirWritableItem($root, $path, $label)
    {
        $dir = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ok = is_dir($dir) && is_writable($dir);
        return [
            'label' => $label,
            'path' => $path,
            'ok' => $ok,
            'class' => $ok ? '' : 'is-bad',
            'message' => $ok ? '可写' : '不可写',
        ];
    }

    private static function containsItem($root, $path, $needle, $label)
    {
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $ok = is_file($file) && strpos((string)@file_get_contents($file), $needle) !== false;

        return [
            'label' => $label,
            'path' => $path,
            'ok' => $ok,
            'class' => $ok ? '' : 'is-bad',
            'message' => $ok ? '正常' : 'Hook 缺失',
        ];
    }
}
