<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\model\CheckOverdueVps;
use app\common\model\HostVps;
use app\common\model\NetworkMonitorVps;
use think\facade\Session;
use think\facade\Request;
use app\common\util\Upload as Up;
use app\common\model\AdminPhoto as P;
use app\common\service\AdminAdmin as S;

class Index extends Base
{
    protected $middleware = ['AdminCheck'];
    
    // 首页
    public function index(){
        return $this->fetch('',[
            'nickname'  => get_field('admin_admin',Session::get('admin.id'),'nickname')
        ]);
    }

    // 清除缓存
    public function cache(){
        Session::clear();
         return $this->getJson(rm());
        }

    // 菜单
    public function menu(){
        $menu = Session::get('admin.menu');
        if (Session::get('admin.id') == 1) {
            $menu[] = [
                'id' => -9001,
                'pid' => 22,
                'title' => 'MMUI配置设置',
                'href' => Request::root() . '/mmui/index',
                'icon' => 'layui-icon layui-icon-theme',
                'sort' => 3,
                'type' => 1,
                'status' => 1,
            ];
        }
        return json(get_tree($menu));
    }

    // 欢迎页
    public function home(){
        $host_model = new HostVps();
        $host_day_num = $host_model->where("create_time>='".date('Y-m-d')."' and state!=1 and state!=12")->field('count(*)ct ')->find();
        $host_count = $host_model->where("state!=1 and state!=12")->field('count(*)ct')->find();

        $third_host_count = $host_model->where("state!=1 and state!=12 and (end_time>='".date('Y-m-d',strtotime('-3days'))."' and end_time<'".date('Y-m-d')."')")->field('count(*)ct')->find();

        $check_overdue_vps_model = new CheckOverdueVps();
        $del_host_count= $check_overdue_vps_model->where("state!=1 and state!=12 and del_time<='".date('Y-m-d',strtotime('-1days'))."'")->field('count(*)ct')->find();

        $network_monitor_vps_model = new NetworkMonitorVps();
        $flowlist = $network_monitor_vps_model->field('network_out+network_in count_,network_out,network_in,host_name')->order('count_ desc')->limit(10)->select();
        $data = $this->getSystem();
        $data['host_day_num'] = $host_day_num['ct'];
        $data['host_count_num'] = $host_count['ct'];
        $data['host_3day_num'] = $third_host_count['ct'];
        $data['host_del_num'] = $del_host_count['ct'];
        $data['flowlist'] = $flowlist;

        return $this->fetch('',$data);
    }

    // 修改密码
    public function pass(){
        if (Request::isAjax()){
            $this->getJson(S::goPass());
        }
        return $this->fetch();
    }

    // 通用上传
    public function upload(){
        return $this->getJson(Up::putFile(Request::file(),Request::post('path')));
    }

    // 图库选择
    public function optPhoto(){
        if (Request::isAjax()) {
            return $this->getJson(P::getAll());
        }
        return $this->fetch('',P::getPath());
    }

}
