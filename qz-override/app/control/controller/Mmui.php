<?php
declare (strict_types = 1);

namespace app\control\controller;

class Mmui extends Ecs
{
    public function iso_list_host()
    {
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        try {
            $data = $logic->getisoHost(['hostid' => $hostid]);
        } catch (\Throwable $e) {
            $data = ['code' => 0, 'data' => [], 'msg' => $e->getMessage()];
        }

        if (($data['code'] ?? 0) == 200) {
            $this->success('success', '', $data['data'] ?? []);
        }
        $this->error($data['msg'] ?? '获取 ISO 列表失败');
    }
}
