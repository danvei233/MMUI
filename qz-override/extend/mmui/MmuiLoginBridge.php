<?php
namespace mmui;

use think\facade\View;

class MmuiLoginBridge
{
    public static function tryHandle($app)
    {
        $config = config('web');
        $loginEnabled = (string)($config['mmui_login_enable'] ?? '0') === '1';
        $loginBundle = $app->getRootPath() . 'view/index/index/mmui_bundle.html';

        if (!$loginEnabled || !is_file($loginBundle)) {
            return null;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        return View::fetch('mmui', [
            'data' => $config,
            'mmuiLoginConfig' => [
                'title' => trim((string)($config['mmui_site_title'] ?? ($config['title'] ?? '轻舟云（QZSYSTEM）'))),
                'logo' => trim((string)($config['mmui_login_logo'] ?? '')),
                'assetBase' => '/static/component/auroraboat/login/',
            ],
        ]);
    }
}
