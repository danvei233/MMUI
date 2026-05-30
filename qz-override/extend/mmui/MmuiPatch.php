<?php
namespace mmui;

class MmuiPatch
{
    public static function repair($app)
    {
        $root = rtrim($app->getRootPath(), "\\/") . DIRECTORY_SEPARATOR;
        $results = [];
        $results[] = self::repairLoginHook($root);
        $results[] = self::repairEcsHook($root);
        $results[] = self::repairAdminMenuHook($root);

        $failed = array_values(array_filter($results, function ($item) {
            return empty($item['ok']);
        }));

        return [
            'ok' => count($failed) === 0,
            'results' => $results,
            'message' => count($failed) === 0 ? 'MMUI Hook 修复完成' : '部分 Hook 修复失败',
        ];
    }

    private static function repairLoginHook($root)
    {
        $path = 'app/index/controller/Index.php';
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($file)) {
            return self::result($path, false, '文件不存在');
        }

        $source = (string)file_get_contents($file);
        if (strpos($source, 'MmuiLoginBridge::tryHandle') !== false) {
            return self::result($path, true, '已存在');
        }

        $needle = "    public function index()\r\n    {\r\n";
        if (strpos($source, $needle) === false) {
            $needle = "    public function index()\n    {\n";
        }

        if (strpos($source, $needle) === false) {
            return self::result($path, false, '未找到 index 方法入口');
        }

        $hook = $needle
            . "        if (class_exists('\\\\mmui\\\\MmuiLoginBridge')) {\r\n"
            . "            \$mmuiResponse = \\mmui\\MmuiLoginBridge::tryHandle(\$this->app);\r\n"
            . "            if (\$mmuiResponse !== null) {\r\n"
            . "                return \$mmuiResponse;\r\n"
            . "            }\r\n"
            . "        }\r\n\r\n";

        $source = str_replace($needle, $hook, $source);
        file_put_contents($file, $source);
        return self::result($path, true, '已修复');
    }

    private static function repairEcsHook($root)
    {
        $path = 'app/control/controller/Ecs.php';
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($file)) {
            return self::result($path, false, '文件不存在');
        }

        $source = (string)file_get_contents($file);
        if (strpos($source, 'MmuiEcsBridge::tryHandle') !== false) {
            return self::result($path, true, '已存在');
        }

        $needle = "        \$data['rootUrl'] = \$this->getEcsRootUrl();";
        if (strpos($source, $needle) === false) {
            return self::result($path, false, '未找到 ECS 渲染锚点');
        }

        $hook = $needle . "\r\n"
            . "        if (class_exists('\\\\mmui\\\\MmuiEcsBridge')) {\r\n"
            . "            \$mmuiResponse = \\mmui\\MmuiEcsBridge::tryHandle(\$this->app, \$this->request, \$data);\r\n"
            . "            if (\$mmuiResponse !== null) {\r\n"
            . "                return \$mmuiResponse;\r\n"
            . "            }\r\n"
            . "        }";

        $source = str_replace($needle, $hook, $source);
        file_put_contents($file, $source);
        return self::result($path, true, '已修复');
    }

    private static function repairAdminMenuHook($root)
    {
        $path = 'app/admin/controller/Index.php';
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($file)) {
            return self::result($path, false, '文件不存在');
        }

        $source = (string)file_get_contents($file);
        if (strpos($source, '/mmui/index') !== false) {
            return self::result($path, true, '已存在');
        }

        $needle = "        \$menu = Session::get('admin.menu');";
        if (strpos($source, $needle) === false) {
            return self::result($path, false, '未找到菜单锚点');
        }

        $hook = $needle . "\r\n"
            . "        if (Session::get('admin.id') == 1) {\r\n"
            . "            \$menu[] = [\r\n"
            . "                'id' => -9001,\r\n"
            . "                'pid' => 22,\r\n"
            . "                'title' => 'MMUI配置设置',\r\n"
            . "                'href' => Request::root() . '/mmui/index',\r\n"
            . "                'icon' => 'layui-icon layui-icon-theme',\r\n"
            . "                'sort' => 3,\r\n"
            . "                'type' => 1,\r\n"
            . "                'status' => 1,\r\n"
            . "            ];\r\n"
            . "        }";

        $source = str_replace($needle, $hook, $source);
        file_put_contents($file, $source);
        return self::result($path, true, '已修复');
    }

    private static function result($path, $ok, $message)
    {
        return [
            'path' => $path,
            'ok' => $ok,
            'message' => $message,
        ];
    }
}
