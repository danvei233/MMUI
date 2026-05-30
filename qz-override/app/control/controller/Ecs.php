<?php

namespace app\control\controller;

use app\common\model\BackupVps;
use app\common\model\FirewallVps;
use app\common\model\ForwardDomainVps;
use app\common\model\ForwardPortVps;
use app\common\model\HostVps;
use app\common\model\NetworkMonitorVps;
use app\common\model\ServersArea;
use app\common\model\ServersImageConfig;
use app\common\model\ServersImageLine;
use app\common\model\ServersIpv4;
use app\common\model\ServersIpv4Nat;
use app\common\model\ServersIpv4Private;
use app\common\model\ServersLine;
use app\common\model\ServersNode;
use app\common\model\SnapshotVps;
use app\common\util\BaseConst;
use app\common\util\HttpRequest;
use qzcloud\HyperV;
use qzcloud\Kvm;
use think\App;
use think\Exception;
use think\facade\Session;
use think\Log;
use think\Request;

/**
 * 云主机管理
 *
 * @icon fa fa-dashboard
 * @remark  云主机管理
 */
class Ecs extends \app\BaseController
{
    protected $noNeedLogin = ['get_panel_password','login'];

    protected function initialize()
    {
        $rootUrl = $this->getEcsRootUrl();
        $this->assign('rootUrl',$rootUrl);
        $arr = $this->noNeedLogin;
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if ($arr) {
            $arr = array_map('strtolower', $arr);
            // 是否存在
            if (in_array(strtolower($this->request->action()), $arr) || in_array('*', $arr)) {
                return true;
            }
        }

        $admin = Session::get('admin');
        if ($admin) {
            return true;
        }

        //判断权限
        $hostid = $this->request->param('hostid');
        $host_id = $this->request->param('host_id');
        $host_id =  $hostid?$hostid:$host_id;
        if(empty($host_id)){
            $this->error('参数不正确');
        }

        $host_model = new HostVps();
        $host = $host_model->find($host_id);
        if(empty($host)){
            $this->error('云主机不存在');
        }

        $site = config('web');
        $localapikey = $site['apikey'];
        $token = md5($hostid.$localapikey);
        $hostinfo = session('host_info');
        if($this->request->action()=='vnc'&&empty($hostinfo)&&!empty($localapikey)){
            $posttoken = $this->request->param('token');
            if($posttoken==$token){
                return true;
            }
        }
        if(empty($hostinfo)){
            $this->error('未登陆或已退出登陆');
        }
        if($host->id!=$hostinfo->id){
            $this->error('权限不足');
        }

    }

    public function get_panel_password(){
        $host_vps_model =  model('HostVps');
        $host_name = $this->request->param('host_name');
        $apikey= $this->request->param('apikey');
        if(!$apikey){
            return json(['code'=>0,'data'=>'','msg'=>__('You have no permission')],0);
        }
        $user_model =  model('User');
        $user = $user_model->where(['api_key'=>$apikey])->find();
        $host = $host_vps_model->where(['host_name'=>$host_name,'user_id'=>$user->id])->find();
        if($host){
            return json(['code'=>200,'data'=>['panel_password'=>$host->panel_password],'msg'=>''],200);
        }else{
            return json(['code'=>0,'data'=>'','msg'=>__('You have no permission')],0);
        }
    }

    private function getEcsRootUrl()
    {
        $baseUrl = $this->request->baseUrl();
        $baseUrlArr = explode('ecs', $baseUrl);
        return $this->request->domain() . $baseUrlArr[0] . "ecs/";
    }

    //登录控制台
    public function login(){
        $host_name = $this->request->param('host_name','','trim');
        $panel_password = $this->request->param('panel_password','','trim');
        $host_vps_model =  new HostVps();
        $info = $host_vps_model->where(['host_name'=>$host_name,'panel_password'=>$panel_password])->find();
        $site = config('web');
        $domian = '';
        if(isset($site['apiurl'])){
            $domian = $site['apiurl'];
        }
        if($info){
            session('host_info',$info);
        }else{
            $this->error('登陆失败',$domian.url('control/index/index'));
        }
        $forward_url =url('control/ecs/index',['hostid'=>$info->id],false);
        if($this->request->isAjax()){
            $this->success('登陆成功',$forward_url);
        }else{
            //header('Location: '.$forward_url);
            return  redirect($forward_url) ;
        }
    }
    //控制台首页
    public function index()
    {
        $hostid = $this->request->param('hostid');
        $host_vps_model =  new HostVps();
        $line_model = new ServersLine();
        $image_model = new ServersImageConfig();
        $area_model = new ServersArea();
        //主机信息
        $host = $host_vps_model->where(['id'=>$hostid])->find();

        if(!$host){
            $this->error('查询云主机不存在');
        }

        //线路信息
        $line_info = $line_model->where(['id'=>$host->line_id])->find();

        //上级机器跳转
        if($host->from_type==2){
            //获取版面密码
            $api =$line_info->line_api;
            if(strstr($line_info->line_api,'http')===false){
                $api = 'http://'.$line_info->line_api;
            }
            $data = HttpRequest::post($api.'/control/ecs/get_panel_password',[
                'headers' => ['Content-Type' => 'application/json'],
                'http_errors' => false,
                'json'    => ['apikey'=>$line_info->line_apikey,'host_name'=>$host->host_name],
                'timeout' => 4
            ],true);
            if($data['code']!=200){
                $this->error(__('You have no permission'));
            }
            $panel_password = $data['data']['panel_password'];
            $forward_url =url('/control/ecs/login',['host_name'=>$host->host_name,'panel_password'=>$panel_password],false);
            header('Location: '.$api.$forward_url);die;
        }

        //操作系统信息
        $image_info = $image_model->where(['os_name'=>$host->os_name])->find();

        //地区信息
        $area = $area_model->find($line_info->area_id);

        //主机ip信息
        $network =[];//ServersIpv4Private
        $privateip_model = new ServersIpv4Private();
        $privateip = $privateip_model->where(['v_name'=>$host->host_name])->select();
        $network['eth2'] = $privateip;
        $node_model = new ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($host->is_nat==1){ //挂机宝
            $natip_model = new ServersIpv4Nat();
            $natip = $natip_model->where(['ip'=>$host->ip])->find();
            $natip ->public_ip = $node->forward_url;
            //$network
            $network['eth1'] = [$natip];
        }else{
            $ip_model = new ServersIpv4();
            $ip = $ip_model->where(['v_name'=>$host->host_name])->select();
            $ip = $ip->toArray();
            foreach ($ip as $k=>$v){
                if($v['ip']==$host['ip']){
                    unset($ip[$k]);
                    array_unshift($ip,$v);
                }
            }
            $network['eth1'] = $ip;
        }
        $image_config_model = new ServersImageConfig();
        //操作系统
        $image_config_line = new ServersImageLine();

        $image =   $image_config_line->where(['line_id'=>$host->line_id])->select();
        if(!empty($image)){
            $image = $image->toArray();
            $image = array_column($image,'config_id');
        }
        $image_list = $image_config_model->whereIn('id',$image)
            ->where("limit_cpu<={$host->cpu} and limit_memory <= {$host->memory}")
            ->select();

        $image = [1=>[],2=>[],3=>[],4=>[]];
        if($image_list){
            $image_list = $image_list->toArray();
            foreach ($image_list as $k=>$v){
                $v['config_id'] = $v['id'];
                $image[$v['os_type']][]=$v;
            }
        }

        //iso文件
        $skipIndexIso = class_exists('\mmui\MmuiEcsBridge') && \mmui\MmuiEcsBridge::shouldSkipIndexIso($this->request);
        $iso_list = ['code' => 0, 'data' => [], 'msg' => ''];
        if (!$skipIndexIso) {
            $ecsService = new \app\common\service\Ecs();
            try {
                $iso_list = $ecsService->getisoHost(['hostid'=>$host->id]);
            } catch (\Throwable $e) {
                $iso_list = ['code' => 0, 'data' => [], 'msg' => $e->getMessage()];
            }
        }
        $iso_list_ =[];
         if($iso_list['code']==200&&!empty($iso_list['data'])){
             $iso_list_ = $iso_list['data'];
         }
        //流量
        $network_monitor_model =  new NetworkMonitorVps();
        $network_flow = $network_monitor_model->where(['host_id'=>$hostid])->find();
        //端口
        $port = 0;
        if($host->is_nat==1){
            $port_vps_model = new ForwardPortVps();
            $portlist = $port_vps_model->where(['host_id'=>$hostid,'sys'=>2])->column('sport','dport');
            if($image_info->os_type==1){
                $port = $portlist['3389'] ?? 3389;
            }else{
                $port = $portlist['22'] ?? 22;
            }
        }

        $data['port']=$port;
        $data['network']=$network;
        $data['iso_list']=$iso_list_;
        $data['network_flow']=$network_flow;
        $data['host']=$host;
        $data['line_info']=$line_info;

        $data['image_info']=$image_info;
        $data['image_list']=$image;
        $data['hostid']=$hostid;
        $data['area'] =$area;
        $data['rootUrl'] = $this->getEcsRootUrl();
        if (class_exists('\mmui\MmuiEcsBridge')) {
            $mmuiResponse = \mmui\MmuiEcsBridge::tryHandle($this->app, $this->request, $data);
            if ($mmuiResponse !== null) {
                return $mmuiResponse;
            }
        }
        //$this->assignconfig('BACKUP_STATUS',BaseConst::BACKUP_STATUS);
       // $this->assignconfig('purl',str_replace('ecs/index','ecs',trim($this->request->baseUrl(),'/')));
        //$this->assignconfig('domain',$this->request->domain().'/'.str_replace('index.php','',$this->request->root()));
        return $this->fetch('',$data);
    }

    //资源监控
    public function monitor_host(){
         $hostid = $this->request->param('hostid');
         //减少开支限制频率
        $ip = $this->request->ip();
        if(!$ip){
            return;
        }
        /**
        $key = "ip:{$ip}:monitor_host";
        $limit = 10;
        try{
            $redis= new \Redis();
            $redis->connect(config('REDIS_HOST'),config('REDIS_PORT'));
            $redis->Expire = config('REDIS_EXPIRE');
        }catch (\Exception $e){
            Log::error($e->getMessage());
            exit('redis 连接错误');
        }

        $check = $redis->exists($key);
        if($check){
            $redis->incr($key);  //键值递增
            $count = $redis->get($key);
            if($count > $limit){
                exit('your have too many request');
            }
        }else{
            $redis->incr($key);
            //限制时间为60秒
            $redis->expire($key,60);
        }
       **/
         $logic = new \app\common\service\Ecs();
         $data = $logic->monitorHost(['hostid'=>$hostid]);
         if($data['code']==200){
             $this->success('success','',$data['data']);
         }else{
             $this->error($data['msg']);
         }
    }

    //云主机实时运行状态
    public function thumbnail_host(){
        $hostid = $this->request->param('hostid');
        $ip = $this->request->ip();
        if(!$ip){
            return;
        }
        /**
        $key = "ip:{$ip}:thumbnail_host";
        $limit = 5;
        try{
            $redis= new \Redis();
            $redis->connect(config('REDIS_HOST'),config('REDIS_PORT'));
            $redis->Expire = config('REDIS_EXPIRE');
        }catch (\Exception $e){
            Log::error($e->getMessage());
            exit('redis 连接错误');
        }
        $check = $redis->exists($key);
        if($check){
            $redis->incr($key);  //键值递增
            $count = $redis->get($key);
            if($count > $limit){
                $this->error('your have too many request');
            }
        }else{
            $redis->incr($key);
            //限制时间为60秒
            $redis->expire($key,60);
        }
         * **/
        $logic = new \app\common\service\Ecs();
        $data = $logic->thumbnailHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $this->success('success','',$data['data']);
        }else{
            $this->error($data['msg']);
        }
    }

    //开机
    public function start_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->startHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $logic->setField(['id'=>$hostid],['state'=>2]);
            $this->success('启动命令执行成功','',$data['msg']);
        }else{
            $this->error($data['msg']);
        }
    }

    //设置bios
    public function set_bios(){
        $hostid = $this->request->param('hostid');
        $bios= $this->request->param('bios');
        $logic = new \app\common\service\Ecs();
        $data = $logic->setBios(['hostid'=>$hostid,'bios'=>$bios]);
        if($data['code']==200){
            $logic->setField(['id'=>$hostid],['state'=>2]);
            $this->success('命令执行成功','',$data['msg']);
        }else{
            $this->error($data['msg']);
        }
    }
    //关机
    public function close_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->closeHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $logic->setField(['id'=>$hostid],['state'=>3]);
            $this->success('关机命令执行成功','',$data['msg']);
        }else{
            $this->error($data['msg']);
        }
    }

    //断开电源
    public function power_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->powerHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $logic->setField(['id'=>$hostid],['state'=>3]);
            $this->success('断开电源命令执行成功','',$data['msg']);
        }else{
            $this->error($data['msg']);
        }
    }

    //重启
    public function restart_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->restartHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $this->success('重启命令执行成功','',$data['msg']);
        }else{
            $this->error($data['msg']);
        }
    }

    //vnc GetVmGuidHyperV
    public function vnc_host(){
        $hostid = $this->request->param('hostid');
        $host_vps_model =  new  HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if ($host){
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model->where(['id'=>$host->node_id])->find();
            if($node['virtual_type']=='kvm'){
                return json(['url'=>"/control/ecs/connect?hostid=".$hostid,'code'=>1],200);
            }
        }
        $data = [];
        $site = config('web');
        $localapikey = $site['apikey'];
        $token = md5($hostid.$localapikey);
        $url = url('/control/ecs/vnc',['hostid'=>$hostid,'token'=>$token],false);
       // echo $url;die;
        $data['data']['url'] =$url;
        $data['data']['code']=1;

       return json(['url'=>(string)$url,'code'=>1],200);
    }

    //云主机状态
    public function state_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $host_vps_model =  new  HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(!in_array($host->state,[2,3])){
            $state=[$host->state,BaseConst::HOST_STATE[$host->state]];
        }else{
            $data = $logic->getStateHost(['hostid'=>$hostid]);
            if($data['code']==200){
                if(strtolower($data['data']['state'])=='running'){
                    $state=[2,BaseConst::HOST_STATE[2]];
                }else{
                    $state=[3,BaseConst::HOST_STATE[3]];
                }

            }else{
                $this->error('采集状态失败');
            }
        }
        $this->success('success','',$state);
    }

   //重新安装操作系统
    public function reinstall_host(){
        $hostid = $this->request->param('hostid');
        $osid = $this->request->param('template_id');
        $password = $this->request->param('password');
        if(!$osid){
            $this->error('请选择操作系统');
        }
        $logic = new \app\common\service\Ecs();
        $data = $logic->reinstallTask(['hostid'=>$hostid,'os_id'=>$osid,'password'=>$password]);
        if($data['code']==200){
            $this->success('执行重装系统命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //挂载iso
    public function iso_list_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        try {
            $data = $logic->getisoHost(['hostid'=>$hostid]);
        } catch (\Throwable $e) {
            $data = ['code' => 0, 'data' => [], 'msg' => $e->getMessage()];
        }

        if($data['code']==200){
            $this->success('success','',$data['data'] ?? []);
        }else{
            $this->error($data['msg'] ?? '获取 ISO 列表失败');
        }
    }

    //挂载iso
    public function mountiso_host(){
        $hostid = $this->request->param('hostid');
        $iso_path = $this->request->param('iso_path');
        $logic = new \app\common\service\Ecs();
        $data = $logic->mountisoHost(['hostid'=>$hostid,'iso'=>$iso_path]);
        if($data['code']==200){
            $this->success('挂载命令执行成功','',$data['data']);
        }else{
            $this->error($data['msg']);
        }
    }

    //卸载iso
    public function unmountiso_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->unmountisoHost(['hostid'=>$hostid,'iso'=>'']);
        if($data['code']==200){
            $this->success('卸载命令执行成功','',$data['data']);
        }else{
            $this->error($data['msg']);
        }
    }

    //备份列表
    public function backup(){
        $hostid = $this->request->param('hostid');
        $model = new BackupVps();
        $list = $model ->where(['host_id'=>$hostid])->order('id desc')->select();
        return json(['code'=>0,'data'=>$list,'msg'=>'']);
    }

    //快照列表
    public function snapshot(){
        $hostid = $this->request->param('hostid');
        $model = new SnapshotVps();
        $list = $model ->where(['host_id'=>$hostid])->order('id desc')->select();
        return json(['code'=>0,'data'=>$list,'msg'=>'']);
    }

    //创建备份
    public function create_backup_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->createBackupHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $this->success('执行创建备份命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //恢复备份
    public function restore_backup_host(){
        $hostid = $this->request->param('hostid');
        $id = $this->request->param('id');
        $logic = new \app\common\service\Ecs();
        $data = $logic->restoreBackupHost(['hostid'=>$hostid,'id'=>$id]);
        if($data['code']==200){
            $this->success('执行恢复备份命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //删除备份
    public function remove_backup_host(){
        $hostid = $this->request->param('hostid');
        $id = $this->request->param('id');
        $logic = new \app\common\service\Ecs();
        $data = $logic->removeBackupHost(['hostid'=>$hostid,'id'=>$id]);
        if($data['code']==200){
            $this->success('执行删除备份命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //创建快照
    public function create_snapshot_host(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->createSnapshotHost(['hostid'=>$hostid]);
        if($data['code']==200){
            $this->success('执行创建快照命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //恢复快照
    public function restore_snapshot_host(){
        $hostid = $this->request->param('hostid');
        $id = $this->request->param('id');
        $logic = new \app\common\service\Ecs();
        $data = $logic->restoreSnapshotHost(['hostid'=>$hostid,'id'=>$id]);
        if($data['code']==200){
            $this->success('执行恢复快照命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //删除快照
    public function remove_snapshot_host(){
        $hostid = $this->request->param('hostid');
        $id = $this->request->param('id');
        $logic = new \app\common\service\Ecs();
        $data = $logic->removeSnapshotHost(['hostid'=>$hostid,'id'=>$id]);
        if($data['code']==200){
            $this->success('执行删除快照命令成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //防火墙策略列表
    public function firewall_host(){
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            //FirewallVps
            $model = new FirewallVps();
            $where =[];
            $hostid = $this->request->param('hostid');
            $page = $this->request->param('page');
            $limit = $this->request->param('limit');
            $type= $this->request->param('type');
            $direction= $this->request->param('direction');
            $method= $this->request->param('method');
            $where['host_id']=$hostid;
            if($type){
                $where['type']=$type;
            }
            if($direction){
                $where['direction']=$direction;
            }
            if($method){
                $where['method']=$method;
            }
            $total =$model
                ->where($where)
                ->count();
            $list = $model
                ->where($where)
                ->order('id','desc')
                ->limit(($page-1)*$limit, $limit)
                ->select();
          //  echo $model->getLastSql();
            $list = $list->toArray();
            $result = array("code"=>0,"count" => $total, "data" => $list);
            return json($result);
        }

    }

    //添加策略
    public function add_firewall_host(){
        $logic = new \app\common\service\Ecs();
        $data = $logic->add_firewall_host($this->request->param());
        if($data['code']==200){
            $this->success('添加策略成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //删除策略
    public function remove_firewall_host(){
        $logic = new \app\common\service\Ecs();
        $data = $logic->remove_firewall_host($this->request->param());
        if($data['code']==200){
            $this->success('添加策略成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //端口列表
    public function port_host(){
        $hostid = $this->request->param('hostid');
        $model =new ForwardPortVps();
        $list = $model ->where(['host_id'=>$hostid])->order('id desc')->select();
        return json(['code'=>0,'data'=>$list,'msg'=>'']);
    }

    //添加一个端口
    public function add_port_host(){
        $logic = new \app\common\service\Ecs();
        $param = $this->request->param();
        $info = $logic->find_port(['keywords'=>$param['sport'],'hostid'=>$param['hostid'],'like'=>2]);
        if(!$info){
            $this->error('抱歉您添加的公网端口被占用');
        }
        $data = $logic->add_forward_port($param);
        if($data['code']==200){
            $this->success('添加端口成功');
        }else{
            $this->error($data['msg']);
        }
    }
   //删除端口
    public function remove_port_host(){
        $logic = new \app\common\service\Ecs();
        $param = $this->request->param();
        $data = $logic->remove_forward_port($param);
        if($data['code']==200){
            $this->success('删除端口成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //域名列表
    public function domain_host(){
        $hostid = $this->request->param('hostid');
        $model = new ForwardDomainVps();
        $list = $model ->where(['host_id'=>$hostid])->order('id desc')->select();
        return json(['code'=>0,'data'=>$list,'msg'=>'']);
    }

    //添加一个端口
    public function add_domain_host(){
        $logic = new \app\common\service\Ecs();
        $param = $this->request->param();
        $data = $logic->add_forward_domain($param);
        if($data['code']==200){
            $this->success('添加域名成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //删除域名
    public function remove_domain_host(){
        $logic = new \app\common\service\Ecs();
        $param = $this->request->param();
        $data = $logic->remove_forward_domain($param);
        if($data['code']==200){
            $this->success('删除域名成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //修改系统密码
    public function updata_systempass_host(){
        $logic = new \app\common\service\Ecs();
        $param = $this->request->param();
        $data = $logic->update_password_host($param);
        if($data['code']==200){
            $this->success('修改密码成功');
        }else{
            $this->error($data['msg']);
        }
    }

    //同步物理机器时间
    public function synctime_host(){
        $logic = new \app\common\service\Ecs();
        $param = $this->request->param();
        $data = $logic->synctime_host($param);
        if($data['code']==200){
            $this->success('操作成功');
        }else{
            $this->error($data['msg']);
        }
    }

    public function findport(){
        $keywords = $this->request->param('keywords');
        $hostid = $this->request->param('hostid');
        $data = \app\common\service\Ecs::find_port(['keywords'=>$keywords,'hostid'=>$hostid]);
        //查找端口
        return json(['code'=>0,'content'=>$data,'type'=>'success']);
    }

    public function update_panel_password(){
        $param = $this->request->param();
        $panel_password = $param['panel_password'];
        if(empty($panel_password)){
            $this->error('新的版面密码不能为空');
        }
        $host_mode = new HostVps();
        $host_mode->where(['id'=>$param['hostid']])->save(['panel_password'=>$panel_password]);
        $this->success('修改密码成功');
    }

    public function vnc(){
        $hostid = $this->request->param('hostid');
        $logic = new \app\common\service\Ecs();
        $data = $logic->guidHost(['hostid'=>$hostid]);

        if(empty($data['data'])){
            $this->error('vnc error');
        }
        if($this->request->isAjax()){
            return  $this->json('',200,$data['data']);
        }
        return $this->fetch('',['url'=>$data['data']['url']]);
    }

    public function connect(){
        try {
            $vmid = $this->request->param('hostid');
            $logic = new \app\common\service\Ecs();
            $data = $logic->vnc($vmid);
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        return $this->fetch('kvm',['data'=>$data]);
    }
}
?>
