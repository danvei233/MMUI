<?php
namespace mmui;

class MmuiPatch
{
    private static $backupRoot = '';

    public static function repair($app)
    {
        return self::repairRoot($app->getRootPath());
    }

    public static function repairRoot($root)
    {
        $root = rtrim((string)$root, "\\/") . DIRECTORY_SEPARATOR;
        self::$backupRoot = $root . 'runtime' . DIRECTORY_SEPARATOR . 'mmui-backup' . DIRECTORY_SEPARATOR . date('Ymd-His');
        $version = class_exists('\\mmui\\MmuiQzVersion')
            ? MmuiQzVersion::detect($root)
            : ['version' => '', 'status' => 'unknown', 'label' => '版本解析器缺失'];

        $versionSupported = ($version['status'] ?? '') === 'supported';
        $results = [[
            'path' => 'cloud_new_version',
            'ok' => true,
            'warning' => !$versionSupported,
            'message' => $version['label'] ?? '未识别轻舟版本',
        ]];
        $results = array_merge($results, self::restoreLegacyOverrides($root, $version));
        $results = array_merge($results, [
            self::repairLoginHook($root),
            self::repairEcsControllerHook($root),
            self::repairAdminMenuHook($root),
            self::repairDeleteContract($root),
            self::repairKvmNodeAddress($root),
            self::repairDriverContract($root, 'extend/qzcloud/Kvm.php', 'KVM'),
            self::repairDriverContract($root, 'extend/qzcloud/HyperV.php', 'Hyper-V'),
        ]);
        $failed = array_values(array_filter($results, function ($item) {
            return empty($item['ok']);
        }));
        $warnings = array_values(array_filter($results, function ($item) {
            return !empty($item['warning']);
        }));

        return [
            'ok' => count($failed) === 0,
            'compatible' => $versionSupported,
            'version' => $version,
            'results' => $results,
            'warnings' => $warnings,
            'backup' => is_dir(self::$backupRoot) ? self::$backupRoot : '',
            'message' => count($failed) > 0
                ? '部分兼容补丁失败'
                : (count($warnings) > 0 ? 'MMUI 补丁已就绪，存在版本兼容提示' : 'MMUI 兼容补丁已就绪'),
        ];
    }

    private static function restoreLegacyOverrides($root, $version)
    {
        $number = (int)($version['number'] ?? 0);
        if ($number < 2025111901) {
            return [];
        }

        $results = [];
        $servicePath = 'app/common/service/Ecs.php';
        $serviceFile = self::file($root, $servicePath);
        $serviceCandidate = self::officialCandidate($root, $number, $servicePath);
        if (is_file($serviceFile)) {
            $source = (string)file_get_contents($serviceFile);
            $knownHashes = [
                '7c10468b2f5d282f1a2773bf0cde3b36d2cba40da6c7653266d01e8fb0da64d9',
                'a179001be15db785718b37528a4f01d78187cf0638ad5ab3aa1c4b6f0eb64844',
            ];
            $legacyFingerprint = strpos($source, "order('weight desc')->select()") !== false
                && strpos($source, 'public static function migrateHost') === false;
            if (in_array(hash('sha256', $source), $knownHashes, true) || $legacyFingerprint) {
                if ($serviceCandidate === '') {
                    $results[] = self::result($servicePath, false, '检测到旧 MMUI Service，但本机缺少官方更新基线');
                } else {
                    $official = (string)file_get_contents($serviceCandidate);
                    $results[] = self::write($root, $servicePath, $source, $official, '已恢复当前轻舟官方 Service 基线');
                }
            }
        }

        $controllerPath = 'app/control/controller/Ecs.php';
        $controllerFile = self::file($root, $controllerPath);
        $controllerCandidate = self::officialCandidate($root, $number, $controllerPath);
        if (is_file($controllerFile)) {
            $source = (string)file_get_contents($controllerFile);
            $legacyFingerprint = strpos($source, 'MmuiEcsBridge::tryHandle') !== false
                && strpos($source, 'private function getEcsRootUrl') !== false
                && strpos($source, 'public function history_memory') === false;
            if ($legacyFingerprint) {
                if ($controllerCandidate === '') {
                    $results[] = self::result($controllerPath, false, '检测到旧 MMUI Controller，但本机缺少官方更新基线');
                } else {
                    $official = (string)file_get_contents($controllerCandidate);
                    $results[] = self::write($root, $controllerPath, $source, $official, '已恢复当前轻舟官方 Controller 基线');
                }
            }
        }
        return $results;
    }

    private static function officialCandidate($root, $version, $path)
    {
        $dir = $root . str_replace('/', DIRECTORY_SEPARATOR, 'update/unzip');
        if (!is_dir($dir)) {
            return '';
        }
        $versions = [];
        foreach ((array)scandir($dir) as $name) {
            if (preg_match('/^20\d{8}$/', $name) && (int)$name <= $version) {
                $versions[] = (int)$name;
            }
        }
        rsort($versions, SORT_NUMERIC);
        foreach ($versions as $candidateVersion) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $candidateVersion . DIRECTORY_SEPARATOR . 'code'
                . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    private static function repairLoginHook($root)
    {
        $path = 'app/index/controller/Index.php';
        return self::patchFile($root, $path, 'MmuiLoginBridge::tryHandle', function ($source) {
            $pattern = '/(public\s+function\s+index\s*\(\s*\)\s*\{)/';
            if (!preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            $match = $matches[1][0];
            $offset = $matches[1][1];
            $hook = $match . "\r\n        if (class_exists('\\\\mmui\\\\MmuiLoginBridge')) {\r\n"
                . "            \$mmuiResponse = \\mmui\\MmuiLoginBridge::tryHandle(\$this->app);\r\n"
                . "            if (\$mmuiResponse !== null) {\r\n                return \$mmuiResponse;\r\n            }\r\n        }\r\n";
            return substr($source, 0, $offset) . $hook . substr($source, $offset + strlen($match));
        });
    }

    private static function repairEcsControllerHook($root)
    {
        $path = 'app/control/controller/Ecs.php';
        return self::patchFile($root, $path, 'MmuiEcsBridge::tryHandle', function ($source) {
            $pattern = '/^(\s*)return\s+\$this->fetch\(\s*[\'\"]{2}\s*,\s*\$data\s*\);/m';
            if (!preg_match($pattern, $source, $matches)) {
                return null;
            }
            $indent = $matches[1];
            $hook = $indent . "\$mmuiBaseUrl = \$this->request->baseUrl();\r\n"
                . $indent . "\$mmuiBaseParts = explode('ecs', \$mmuiBaseUrl);\r\n"
                . $indent . "\$data['rootUrl'] = \$this->request->domain() . \$mmuiBaseParts[0] . 'ecs/';\r\n"
                . $indent . "if (class_exists('\\\\mmui\\\\MmuiEcsBridge')) {\r\n"
                . $indent . "    \$mmuiResponse = \\mmui\\MmuiEcsBridge::tryHandle(\$this->app, \$this->request, \$data);\r\n"
                . $indent . "    if (\$mmuiResponse !== null) {\r\n"
                . $indent . "        return \$mmuiResponse;\r\n"
                . $indent . "    }\r\n"
                . $indent . "}\r\n"
                . $matches[0];
            $patched = preg_replace($pattern, self::escapeReplacement($hook), $source, 1);
            $isoPattern = '/^(\s*)\$iso_list\s*=\s*\$ecsService->getisoHost\((.*?)\);/m';
            $patched = preg_replace_callback($isoPattern, function ($isoMatches) {
                $indent = $isoMatches[1];
                return $indent . "\$iso_list = ['code' => 0, 'data' => [], 'msg' => ''];\r\n"
                    . $indent . "if (!class_exists('\\\\mmui\\\\MmuiEcsBridge') || !\\mmui\\MmuiEcsBridge::shouldSkipIndexIso(\$this->request)) {\r\n"
                    . $indent . "    try {\r\n"
                    . $indent . "        \$iso_list = \$ecsService->getisoHost(" . $isoMatches[2] . ");\r\n"
                    . $indent . "    } catch (\\Throwable \$e) {\r\n"
                    . $indent . "        \$iso_list = ['code' => 0, 'data' => [], 'msg' => \$e->getMessage()];\r\n"
                    . $indent . "    }\r\n"
                    . $indent . "}";
            }, $patched, 1);
            $patched = str_replace("\$portlist['3389']", "(\$portlist['3389'] ?? 3389)", $patched);
            $patched = str_replace("\$portlist['22']", "(\$portlist['22'] ?? 22)", $patched);
            return $patched;
        });
    }

    private static function repairAdminMenuHook($root)
    {
        $path = 'app/admin/controller/Index.php';
        return self::patchFile($root, $path, "'/mmui/index'", function ($source) {
            $needle = "        \$menu = Session::get('admin.menu');";
            if (strpos($source, $needle) === false) {
                $direct = "        return json(get_tree(Session::get('admin.menu')));";
                if (strpos($source, $direct) === false) {
                    return null;
                }
                $source = str_replace($direct, $needle . "\r\n        return json(get_tree(\$menu));", $source);
            }
            $hook = $needle . "\r\n        if (Session::get('admin.id') == 1) {\r\n"
                . "            \$menu[] = [\r\n                'id' => -9001,\r\n                'pid' => 22,\r\n"
                . "                'title' => 'MMUI配置设置',\r\n"
                . "                'href' => \\think\\facade\\Request::root() . '/mmui/index',\r\n"
                . "                'icon' => 'layui-icon layui-icon-theme',\r\n"
                . "                'sort' => 3,\r\n                'type' => 1,\r\n                'status' => 1,\r\n            ];\r\n        }";
            return str_replace($needle, $hook, $source);
        });
    }

    private static function repairDeleteContract($root)
    {
        $path = 'app/common/service/Ecs.php';
        $file = self::file($root, $path);
        if (!is_file($file)) {
            return self::result($path, false, '文件不存在');
        }
        $source = (string)file_get_contents($file);
        $patched = self::addHostIpKeys($source, 'batDeleteDomainHost');
        $patched = self::addHostIpKeys($patched, 'batDeletePortHost');
        if ($patched === $source) {
            $ok = self::deleteContractReady($source);
            return self::result($path, $ok, $ok ? '已兼容 dip/ip' : '未找到删除调用锚点');
        }
        return self::write($root, $path, $source, $patched, '已注入 dip/ip 兼容参数');
    }

    private static function repairDriverContract($root, $path, $label)
    {
        $file = self::file($root, $path);
        if (!is_file($file)) {
            return self::result($path, true, $label . ' 驱动不存在，已跳过');
        }
        $source = (string)file_get_contents($file);
        $patched = self::patchDriverMethod($source, 'batDeletePortHost');
        $patched = self::patchDriverMethod($patched, 'batDeleteDomainHost');
        if ($patched === $source) {
            return self::result($path, true, $label . ' 删除参数无需修改');
        }
        return self::write($root, $path, $source, $patched, $label . ' 已兼容 dip/ip');
    }

    private static function repairKvmNodeAddress($root)
    {
        $path = 'extend/qzcloud/Kvm.php';
        $file = self::file($root, $path);
        if (!is_file($file)) {
            return self::result($path, true, 'KVM 驱动不存在，已跳过节点地址兼容');
        }
        $source = (string)file_get_contents($file);
        if (strpos($source, 'MMUI_NODE_ADDRESS_COMPAT') !== false) {
            return self::result($path, true, 'KVM 节点地址已兼容 host:port');
        }
        $pattern = '/^(\s*)\$node_url\s*=\s*\$this->node_url\s*\?\s*\$this->node_url\s*:\s*\$nodeInfo\[[\'\"]node_ip[\'\"]\]\s*;/m';
        $patched = preg_replace_callback($pattern, function ($matches) {
            $indent = $matches[1];
            return $matches[0] . "\r\n"
                . $indent . "// MMUI_NODE_ADDRESS_COMPAT\r\n"
                . $indent . "\$mmuiNodeUrl = parse_url(strpos(\$node_url, '://') === false ? 'http://' . \$node_url : \$node_url);\r\n"
                . $indent . "if (!empty(\$mmuiNodeUrl['host'])) {\r\n"
                . $indent . "    \$node_url = \$mmuiNodeUrl['host'];\r\n"
                . $indent . "}\r\n"
                . $indent . "if (!empty(\$mmuiNodeUrl['port'])) {\r\n"
                . $indent . "    \$this->port = (int)\$mmuiNodeUrl['port'];\r\n"
                . $indent . "}";
        }, $source);
        if (!is_string($patched) || $patched === $source) {
            return self::result($path, false, '未找到 KVM 节点地址锚点');
        }
        return self::write($root, $path, $source, $patched, 'KVM 节点地址已兼容 host:port');
    }

    private static function patchFile($root, $path, $marker, $callback)
    {
        $file = self::file($root, $path);
        if (!is_file($file)) {
            return self::result($path, false, '文件不存在');
        }
        $source = (string)file_get_contents($file);
        if (strpos($source, $marker) !== false) {
            return self::result($path, true, '已存在');
        }
        $patched = call_user_func($callback, $source);
        if (!is_string($patched) || $patched === $source) {
            return self::result($path, false, '未找到兼容锚点');
        }
        return self::write($root, $path, $source, $patched, '已注入');
    }

    private static function addHostIpKeys($source, $method)
    {
        $pattern = '/(' . preg_quote($method, '/') . '\s*\(\s*\$node->toArray\(\)\s*,\s*\[)(.*?)(\]\s*\)\s*;)/s';
        return preg_replace_callback($pattern, function ($matches) {
            $body = $matches[2];
            $suffix = '';
            if (strpos($body, "'dip'=>") === false && strpos($body, "'dip' =>") === false) {
                $suffix .= "\r\n                        'dip'=>\$host->ip,";
            }
            if (strpos($body, "'ip'=>") === false && strpos($body, "'ip' =>") === false) {
                $suffix .= "\r\n                        'ip'=>\$host->ip,";
            }
            $body = rtrim($body);
            if ($suffix !== '' && substr($body, -1) !== ',') {
                $body .= ',';
            }
            return $matches[1] . $body . $suffix . "\r\n                    " . $matches[3];
        }, $source);
    }

    private static function deleteContractReady($source)
    {
        foreach (['batDeleteDomainHost', 'batDeletePortHost'] as $method) {
            $pattern = '/' . preg_quote($method, '/') . '\s*\(\s*\$node->toArray\(\)\s*,\s*\[(.*?)\]\s*\)\s*;/s';
            if (!preg_match_all($pattern, $source, $matches) || count($matches[1]) < 1) {
                return false;
            }
            foreach ($matches[1] as $body) {
                if (strpos($body, "'dip'") === false || strpos($body, "'ip'") === false) {
                    return false;
                }
            }
        }
        return true;
    }

    private static function patchDriverMethod($source, $method)
    {
        $start = strpos($source, 'public function ' . $method);
        if ($start === false) {
            return $source;
        }
        $end = strpos($source, 'public function ', $start + 20);
        if ($end === false) {
            $end = strlen($source);
        }
        $body = substr($source, $start, $end - $start);
        $fallback = "isset(\$param['dip']) ? \$param['dip'] : (isset(\$param['ip']) ? \$param['ip'] : '')";
        if (strpos($body, $fallback) !== false) {
            return $source;
        }
        $patched = preg_replace("/\\\$param\[['\"](?:dip|ip)['\"]\]/", $fallback, $body);
        if ($patched === $body) {
            return $source;
        }
        return substr($source, 0, $start) . $patched . substr($source, $end);
    }

    private static function write($root, $path, $source, $patched, $message)
    {
        $backup = self::$backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_dir(dirname($backup))) {
            @mkdir(dirname($backup), 0755, true);
        }
        if (!is_file($backup)) {
            @file_put_contents($backup, $source);
        }
        $ok = file_put_contents(self::file($root, $path), $patched) !== false;
        return self::result($path, $ok, $ok ? $message : '写入失败');
    }

    private static function file($root, $path)
    {
        return $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private static function escapeReplacement($value)
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }

    private static function result($path, $ok, $message)
    {
        return ['path' => $path, 'ok' => $ok, 'message' => $message];
    }
}
