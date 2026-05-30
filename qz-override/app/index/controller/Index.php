<?php
declare (strict_types = 1);

namespace app\index\controller;

class Index extends \app\BaseController
{
    /**
     * 首页
     */
    public function index()
    {
        if (class_exists('\mmui\MmuiLoginBridge')) {
            $mmuiResponse = \mmui\MmuiLoginBridge::tryHandle($this->app);
            if ($mmuiResponse !== null) {
                return $mmuiResponse;
            }
        }

        return $this->fetch();
    }
}
