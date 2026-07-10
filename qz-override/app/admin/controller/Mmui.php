<?php
declare (strict_types = 1);

namespace app\admin\controller;

use think\facade\Request;

class Mmui extends Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    private function isAppDebugEnabled()
    {
        $value = config('app.app_debug', env('app_debug', false));
        return $value === true || $value === 1 || $value === '1' || strtolower((string)$value) === 'true';
    }

    private function textValue($key, $default = '', $maxLength = 80)
    {
        $value = trim((string)Request::post($key, $default));
        $value = strip_tags($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }
        return substr($value, 0, $maxLength);
    }

    private function assetValue($key, $default = '')
    {
        $value = trim((string)Request::post($key, $default));
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^/src/static/mmui/uploads/[a-zA-Z0-9._/-]+$#', $value)) {
            return $default;
        }

        if (strpos($value, '..') !== false || strpos($value, '\\') !== false) {
            return $default;
        }

        return $value;
    }

    public function index()
    {
        $config = config('web');
        if (empty($config['mmui_first_open_token'])) {
            $config['mmui_first_open_token'] = bin2hex(random_bytes(8));
            set_web($config);
        }

        if (Request::isPost()) {
            $data = $config;
            $postedEnable = Request::post('mmui_enable', '0', 'strip_tags');
            $postedLoginEnable = Request::post('mmui_login_enable', '0', 'strip_tags');
            $postedDevEnable = Request::post('mmui_dev_enable', '0', 'strip_tags');
            $postedAnalyticsEnable = Request::post('mmui_analytics_enable', '0', 'strip_tags');
            $data['mmui_enable'] = $postedEnable === '1' ? '1' : '0';
            $data['mmui_login_enable'] = $postedLoginEnable === '1' ? '1' : '0';
            $data['mmui_dev_enable'] = ($this->isAppDebugEnabled() && $postedDevEnable === '1') ? '1' : '0';
            $data['mmui_analytics_enable'] = $postedAnalyticsEnable === '0' ? '0' : '1';
            $data['mmui_site_title'] = $this->textValue('mmui_site_title', $data['mmui_site_title'] ?? ($data['title'] ?? '轻舟云（QZSYSTEM）'), 80);
            $data['mmui_login_logo'] = $this->assetValue('mmui_login_logo', $data['mmui_login_logo'] ?? '');
            $data['mmui_console_title'] = $this->textValue('mmui_console_title', $data['mmui_console_title'] ?? '云管理系统', 40);
            $data['mmui_console_logo'] = $this->assetValue('mmui_console_logo', $data['mmui_console_logo'] ?? '');
            $data['mmui_author'] = '丁薇';
            $data['mmui_version'] = 'V2X 2.0.1';
            $data['mmui_version_code'] = 'LightningBoatX';
            set_web($data);
            return $this->success('保存成功', Request::root() . '/mmui/index');
        }

        $enabled = (string)($config['mmui_enable'] ?? '0') === '1';
        $loginEnabled = (string)($config['mmui_login_enable'] ?? '0') === '1';
        $analyticsEnabled = (string)($config['mmui_analytics_enable'] ?? '1') !== '0';
        $debugEnabled = $this->isAppDebugEnabled();
        $devEnabled = $debugEnabled && (string)($config['mmui_dev_enable'] ?? '0') === '1';
        $assetBase = $config['mmui_asset_base'] ?? '/src/static/mmui/';

        $mmuiViewExists = is_file($this->app->getRootPath() . 'view/control/ecs/mmui_bundle.html');
        $mmuiAssetExists = is_file($this->app->getRootPath() . 'public' . $assetBase . 'index.html');
        $loginAssetExists = is_file($this->app->getRootPath() . 'public/static/component/auroraboat/login/index.html');
        $mmuiHealth = class_exists('\mmui\MmuiHealth') ? \mmui\MmuiHealth::check($this->app) : [
            'ok' => false,
            'label' => 'MMUI 自检模块缺失',
            'items' => [],
        ];

        return $this->fetch('', [
            'data' => $config,
            'mmui_health' => $mmuiHealth,
            'mmui_health_label' => $mmuiHealth['label'],
            'mmui_health_class' => $mmuiHealth['ok'] ? 'is-ok' : 'is-warn',
            'mmui_enabled_label' => $enabled ? 'MMUI已启用' : '原版界面',
            'mmui_enabled_checked' => $enabled ? 'checked' : '',
            'mmui_disabled_checked' => $enabled ? '' : 'checked',
            'mmui_login_label' => $loginEnabled ? 'MMUI 登录页已启用' : '原版登录页',
            'mmui_login_checked' => $loginEnabled ? 'checked' : '',
            'mmui_login_disabled_checked' => $loginEnabled ? '' : 'checked',
            'mmui_analytics_label' => $analyticsEnabled ? '统计已启用' : '统计已关闭',
            'mmui_analytics_checked' => $analyticsEnabled ? 'checked' : '',
            'mmui_dev_label' => $devEnabled ? 'Dev 模式已启用' : ($debugEnabled ? 'Dev 模式未启用' : 'APP_DEBUG 关闭，禁止启用 Dev 模式'),
            'mmui_dev_checked' => $devEnabled ? 'checked' : '',
            'mmui_dev_disabled' => $debugEnabled ? '' : 'disabled',
            'mmui_debug_label' => $debugEnabled ? 'APP_DEBUG=true' : 'APP_DEBUG=false',
            'mmui_index_exists' => $mmuiViewExists && $mmuiAssetExists,
            'mmui_index_label' => ($mmuiViewExists && $mmuiAssetExists)
                ? '已找到 ECS MMUI 模板与 /src/static/mmui/ 资源'
                : '未找到构建产物',
            'mmui_login_asset_label' => $loginAssetExists
                ? '已链接 AuroraBoat LoginUI 构建产物'
                : '未找到 AuroraBoat LoginUI 构建产物',
        ]);
    }

    public function uploadIcon()
    {
        $file = Request::file('file');
        if (!$file) {
            return json(['code' => 201, 'msg' => '未收到上传文件']);
        }

        $size = (int)$file->getSize();
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return json(['code' => 201, 'msg' => '文件大小必须在 2MB 以内']);
        }

        $ext = strtolower((string)$file->extension());
        $allowExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'];
        if (!in_array($ext, $allowExt, true)) {
            return json(['code' => 201, 'msg' => '仅支持 png/jpg/jpeg/gif/webp/ico']);
        }

        $tmpPath = $file->getRealPath();
        $isIco = $ext === 'ico';
        if (!$isIco && @getimagesize($tmpPath) === false) {
            return json(['code' => 201, 'msg' => '图片文件无效']);
        }

        if ($isIco) {
            $fh = @fopen($tmpPath, 'rb');
            $header = $fh ? fread($fh, 4) : '';
            if ($fh) {
                fclose($fh);
            }
            if ($header !== "\x00\x00\x01\x00") {
                return json(['code' => 201, 'msg' => 'ICO 文件无效']);
            }
        }

        $saveDir = $this->app->getRootPath() . 'public/src/static/mmui/uploads';
        if (!is_dir($saveDir)) {
            @mkdir($saveDir, 0755, true);
        }

        $filename = 'mmui-icon-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $saved = $file->move($saveDir, $filename);
        if (!$saved) {
            return json(['code' => 201, 'msg' => '上传失败']);
        }

        $url = '/src/static/mmui/uploads/' . $filename;
        return json(['code' => 0, 'msg' => '上传成功', 'data' => ['url' => $url, 'src' => $url]]);
    }

    public function repair()
    {
        if (!Request::isPost()) {
            return json(['code' => 201, 'msg' => '请使用 POST 请求']);
        }

        if (!class_exists('\mmui\MmuiPatch')) {
            return json(['code' => 201, 'msg' => 'MMUI 修复模块缺失']);
        }

        $result = \mmui\MmuiPatch::repair($this->app);
        return json([
            'code' => $result['ok'] ? 0 : 201,
            'msg' => $result['message'],
            'data' => $result,
        ]);
    }
}
