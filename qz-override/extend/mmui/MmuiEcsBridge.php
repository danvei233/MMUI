<?php
namespace mmui;

use think\facade\View;

class MmuiEcsBridge
{
    public static function tryHandle($app, $request, array $data)
    {
        $builder = new MmuiEcsPayload($request, $data['rootUrl'] ?? self::getRootUrl($request));
        $payload = $builder->build($data);

        if (self::isJsonRequest($request)) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            return json($builder->filter($payload));
        }

        if (!self::isEnabled() || !is_file($app->getRootPath() . 'view/control/ecs/mmui_bundle.html')) {
            return null;
        }

        $data['mmuiVuePayload'] = $payload;
        $data['mmuiVueRequestKey'] = 'mmui_vue_json';
        $data['mmuiVueJsonUrl'] = $payload['actions']['json'] ?? '';

        if (self::isDevEnabled()) {
            $webConfig = config('web');
            $data['mmuiDevServer'] = rtrim($webConfig['mmui_dev_server'] ?? 'http://127.0.0.1:5173', '/');
            return View::fetch('mmui_dev', $data);
        }

        return View::fetch('mmui', $data);
    }

    public static function isEnabled()
    {
        $webConfig = config('web');
        return (string)($webConfig['mmui_enable'] ?? '0') === '1';
    }

    public static function isDevEnabled()
    {
        $debugValue = config('app.app_debug', env('app_debug', false));
        $debugEnabled = $debugValue === true
            || $debugValue === 1
            || $debugValue === '1'
            || strtolower((string)$debugValue) === 'true';

        if (!$debugEnabled) {
            return false;
        }

        $webConfig = config('web');
        return (string)($webConfig['mmui_dev_enable'] ?? '0') === '1';
    }

    public static function isJsonRequest($request)
    {
        return (string)$request->param('mmui_vue_json', '') === '1';
    }

    public static function shouldSkipIndexIso($request)
    {
        return self::isJsonRequest($request) || self::isEnabled();
    }

    private static function getRootUrl($request)
    {
        $baseUrl = $request->baseUrl();
        $baseUrlArr = explode('ecs', $baseUrl);
        return $request->domain() . $baseUrlArr[0] . 'ecs/';
    }
}
