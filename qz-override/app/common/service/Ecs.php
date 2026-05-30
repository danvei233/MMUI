<?php
declare (strict_types = 1);

namespace app\common\service;

use app\admin\controller\servers\ImageConfig;
use app\common\model\BackupVps;
use app\common\model\CheckOverdueVps;
use app\common\model\FirewallVps;
use app\common\model\ForwardDomainVps;
use app\common\model\ForwardPortVps;
use app\common\model\HostVps;
use app\common\model\NetworkMonitorVps;
use app\common\model\ServersNode;
use app\common\model\SnapshotVps;
use app\common\model\Task;
use app\common\model\VncPort;
use app\common\util\BaseConst;
use app\common\util\QzcloudTool;
use qzcloud\HyperV;
use qzcloud\Kvm;
use think\db\Where;
use think\Exception;
use think\facade\Db;
use think\facade\Session;
use think\Log;

class Ecs
{
    //通过其他接口地方创建云主机
    public static function createVps($param)
    {
        try {
            $hostVpsModel = new \app\common\model\HostVps();
            $serverIpv4Model = new \app\common\model\ServersIpv4();
            $serverIpv4NatModel = new \app\common\model\ServersIpv4Nat();
            $serverIpv4PrivateModel = new \app\common\model\ServersIpv4Private();
            $serversNodeModel = new \app\common\model\ServersNode();

            $param['host_name'] = (isset($param['host_name'])&&!empty($param['host_name'])) ? $param['host_name'] : QzcloudTool::getUniqueStr('host_name', 6, 'ecs');
            //节点信息
            $node = $serversNodeModel->where(['id'=>$param['node_id']])->find();
            $count = $hostVpsModel->where(['host_name' => $param['host_name']])->count();
            if ($count) {
                throw new  Exception('创建vps 主机名已存在');
            }

            $ip_pool_id = $node['ip_pool_id'];
            //取ip
            $ip_other =[];
            $private_ip = $serverIpv4PrivateModel->where(['state' => 1,'ip_pool_id'=>$ip_pool_id])->find(); //私有ip
            if(empty($private_ip)){
                throw new  Exception('没有可用私有ip');
            }
            $serverIpv4PrivateModel->where(['ip' => $private_ip['ip'],'ip_pool_id'=>$ip_pool_id])->update(['state' => 2, 'v_name' => $param['host_name']]);
            if (isset($param['is_nat']) && $param['is_nat'] == 1) {//挂机宝
                $nat_ip = $serverIpv4NatModel->where(['state' => 1])->find(); //挂机宝ip
                if(empty($nat_ip)){
                    throw new  Exception('没有可用NAT ip');
                }
                $ip = $nat_ip['ip'];
                if ($ip == '') {
                    throw new  Exception('创建云主机没有可用的nat ip');
                }
                $serverIpv4NatModel->where(['ip' => $ip])->update(['state' => 2, 'v_name' => $param['host_name']]);
            } else {
                $where = ['state' => 1,'ip_pool_id'=>$ip_pool_id];
                if(isset($param['ip'])){
                    $where['ip'] = $param['ip'];
                }
                $ipnum =0;
                if(isset($param['ipnum'])&&$param['ipnum']>0){
                    $ipnum = $param['ipnum']-1;
                }

                $public_ip = $serverIpv4Model->where($where)->find(); //独立ip
                if(empty($public_ip)&&!empty($param['ip'])){
                    throw new  Exception('在指定节点的默认IP池中未找到可用的IP'.$param['ip'].'可能被使用或者不存在默认IP池');
                }

                if(empty($public_ip)){
                    throw new  Exception('创建云主机ip池中没有可用的public ip');
                }
                $ip = $public_ip['ip'];
                $serverIpv4Model->where(['ip' => $ip,'ip_pool_id'=>$ip_pool_id])->update(['state' => 2, 'v_name' => $param['host_name']]);

                if($ipnum>0){ //附加多余ip
                    $public_ip_add = $serverIpv4Model->limit(0,$ipnum)->where(['state' =>1,'ip_pool_id'=>$ip_pool_id])->select(); //独立ip
                    $ip_other = [];
                    if(!empty($public_ip_add)){
                        $public_ip_add = $public_ip_add->toArray();
                        $ip_other = array_column($public_ip_add,'ip');
                    }

                    if(count($ip_other)!=$ipnum){
                        throw new  Exception('创建云主机没有足够的 public ip');
                    }
                    $serverIpv4Model->whereIn('ip',$ip_other)->update(['state' => 2, 'v_name' => $param['host_name']]);
                }
            }

            if ($private_ip['ip'] == '') {
                throw new  Exception('创建云主机ip池中没有可用的private ip');
            }
            $param['ip'] = $ip;
            $param['add_ip'] = implode(',',$ip_other);
            $param['local_ip'] = $private_ip['ip'];
            $param['vnc_password'] = isset($param['vnc_password']) ? $param['vnc_password'] : QzcloudTool::generatePassword(8);
            $param['os_password'] = isset($param['os_password']) ? $param['os_password'] : QzcloudTool::generatePassword(10);
            $param['panel_password'] = isset($param['panel_password']) ? $param['panel_password'] :$param['os_password'];
            $param['is_vnc'] = isset($param['is_vnc'])?$param['is_vnc']:$node['is_vnc'];

            //如果是kvm 分配kvm端口和密码
         /*   if($node['virtual_type']=="kvm"){
               if(empty($node->vnc_url)){
                    throw new  Exception('管理员未设置vnc地址');
                }
                $vnc_port = QzcloudTool::getVncPort(60000,50001,$node->vnc_url);
                if(empty($vnc_port)){
                    throw new  Exception('没有可用的vnc端口');
                }
                $param['vnc_port'] = $vnc_port;
            }*/

            unset($param['ipnum']);
            $res = $hostVpsModel->save($param);
            $param['id']=$hostVpsModel->id;
            $param['host_id']=$hostVpsModel->id;

            if($node['virtual_type']=="kvm"){
                //端口保存到表cloud_vnc_port_vps
                /*$vncPort = new VncPort();
                $vncPort->save(
                    [
                        "host_id"=>$param['id'],
                        "host_name"=>$param['host_name'],
                        "port"=>0,
                        "vnc_url"=>$node->vnc_url,
                    ]
                );*/
            }
            if (!$res) {
                throw new  Exception('创建vps失败，保存数据错误');
            }

            $res = $serversNodeModel->where(['id' => $param['node_id']])->update(['vm_number'=>$node['vm_number']+1]);
            if (!$res) {
                throw new  Exception('创建云主机保存节点主机数失败');
            }

            //挂机宝映射端口
            $noport = $node->no_port;
            $noport = array_filter(explode(',', $noport));
            if (isset($param['is_nat']) && $param['is_nat'] == 1) {
                try {
                    $temp = [];
                    $port = QzcloudTool::getPort($node->max_port, $node->min_port, $node->forward_url, '', 2, $noport, false);
                    $ser = [['name' => 'ssh', 'port' => 22], ['name' => '远程桌面', 'port' => 3389]];
                    $ecsLogic = new Ecs();
                    foreach ($port as $k => $v) {
                        $temp['hostid'] = $hostVpsModel->id;
                        $temp['name'] = $ser[$k]['name'];
                        $temp['port_type'] = 'all';
                        $temp['sport'] = $v;
                        $temp['dport'] = $ser[$k]['port'];
                        $temp['sys'] = 2;
                        $temp['dip'] = $hostVpsModel->ip;
                        $res = $ecsLogic->add_forward_port($temp);
                        \think\facade\Log::record(var_export($temp,true));
                        if($res['code']==0){
                            throw new  Exception('请联系管理员，NAT云主机端口映射异常');
                        }
                    }
                } catch (Exception $e) {
                    throw new  Exception($e->getMessage());
                }
            }
            //创建任务
            //$task = new Task();
            //$task->save(['command'=>'create_vps', 'param'=>'id=' . $hostVpsModel->id, 'state'=>0,'start_time'=>date('Y-m-d H:i:s')]);
            //$taskid = $task->getLastInsID();
            $taskid =  self::addTask('create_vps', 'id=' .$hostVpsModel->id, []);
            //执行任务
            $cloudTask = new \app\common\service\ExecuteTask();
            $res = $cloudTask->CreateVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            return $param;
        } catch (Exception $e) {
            throw new  Exception('line:'.$e->getLine()."msg:".$e->getMessage());
        }
    }

    //更新云主机
    public static function updateVps($param,$infoOld)
    {
        try {
            if(isset($param['line_id'])&&isset($param['node_id'])){
                $line_id = $param['line_id'];
                $node_id = $param['node_id'];
                $line_info = explode('|',$line_id);
                $node_info = explode('|',$node_id);
                $param['line_id'] =$line_info[0];
                $param['line_name'] = isset($line_info[1])?$line_info[1]:'';
                $param['node_id'] = $node_info[0];
                $param['node_name'] =  isset($node_info[1])?$node_info[1]:'';
                $node_model = new ServersNode();
                $node_model->where(['id' =>$infoOld['node_id']])->dec('vm_number',1)->update();
                $node_model->where(['id' =>$node_info[0]])->inc('vm_number',1)->update();
            }
            \app\common\model\HostVps::update($param);

            $node_model = new ServersNode();
            $node_info=$node_model->where(['id' =>$infoOld['node_id']])->find();
            //判断一下是否需要创建任务
            $istask = 0;
            if(empty($infoOld)){ //创建任务
                $istask = 1;
            }else{
                if(isset($param['cpu'])&&$param['cpu']!=$infoOld['cpu']){
                    $istask =1;
                }
                if(isset($param['ip'])&&$param['ip']!=$infoOld['ip']){
                    $istask =1;
                }
                if(isset($param['cpu_limit'])&&$param['cpu_limit']!=$infoOld['cpu_limit']){
                    $istask =1;
                }
                if(isset($param['metal'])&&$param['metal']!=$infoOld['metal']){
                    $istask =1;
                }

                if(isset($param['memory'])&&$param['memory']!=$infoOld['memory']){
                    $istask =1;
                }
                if(isset($param['ram_start'])&&$param['ram_start']!=$infoOld['ram_start']){
                    $istask =1;
                }

                if(isset($param['bandwidth'])&&$param['bandwidth']!=$infoOld['bandwidth']){
                    $istask =1;
                }

                if(isset($param['bandwidth_in'])&&$param['bandwidth_in']!=$infoOld['bandwidth_in']){
                    $istask =1;
                }

                if(isset($param['hard_disks'])&&$param['hard_disks']!=$infoOld['hard_disks']){
                    $istask =1;
                }
                if(isset($param['os_disk_maxiops'])&&$param['os_disk_maxiops']!=$infoOld['os_disk_maxiops']){
                    $istask =1;
                }
                if(isset($param['data_disk_maxiops'])&&$param['data_disk_maxiops']!=$infoOld['data_disk_maxiops']){
                    $istask =1;
                }

                if(isset($param['resolution'])&&$param['resolution']!=$infoOld['resolution']){
                    $istask =1;
                }
                if(isset($param['gpu_capacity'])&&$param['gpu_capacity']!=$infoOld['gpu_capacity']){
                    $istask =1;
                }

                if(isset($param['os_read'])&&$param['os_read']!=$infoOld['os_read']&&$node_info['virtual_type']!='hyper-v'){
                    $istask =1;
                }

                if(isset($param['os_write'])&&$param['os_write']!=$infoOld['os_write']&&$node_info['virtual_type']!='hyper-v'){
                    $istask =1;
                }
                if(isset($param['data_read'])&&$param['data_read']!=$infoOld['data_read']&&$node_info['virtual_type']!='hyper-v'){
                    $istask =1;
                }
                if(isset($param['data_write'])&&$param['data_write']!=$infoOld['data_write']&&$node_info['virtual_type']!='hyper-v'){
                    $istask =1;
                }

                if(isset($param['cpu_model'])&&$param['cpu_model']!=$infoOld['cpu_model']&&$node_info['virtual_type']!='hyper-v'){
                    $istask =1;
                }

                if(isset($param['is_vnc'])&&$param['is_vnc']!=$infoOld['is_vnc']&&$node_info['virtual_type']!='hyper-v'){
                    $istask =1;
                }

            }

            if($istask==1){
                $taskid =  self::addTask('update_vps', 'id=' .$param['id'], []);
                $cloudTask = new \app\common\service\ExecuteTask();
                $res = $cloudTask->updateVps($taskid);
                if($res['code']!=200){
                    throw new  Exception($res['msg']);
                }
            }
            //参数对比创建任务
            return true;
        } catch (Exception $e) {
            throw new  Exception('line:'.$e->getLine()."msg:".$e->getMessage());
        }
    }

    //删除云主机
    public static function  delete_host(array $param){
        //节点虚拟机减去1
        //删除云主机记录
        //恢复ip 私网ip 公网ip  natip
        //删除流量表
        //删除端口映射表
        //删除域名白名单表
        //kvm 删除vnc端口
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(empty($host)){
                return ['code'=>200,'msg'=>''];
            }
            \think\facade\Log::info("admin remove host hostinfo=###".json_encode($host->toArray()).'##userinfo='.Session::get('admin.id'));
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $post_data = [];
            $post_data['host_name'] = $host->host_name;
            $post_data['back_dir'] = $node->backup_path;

            //删除私有ip
            $serverIpv4PrivateModel = new \app\common\model\ServersIpv4Private();//私有ip
            $serverIpv4PrivateModel->where(['v_name'=>$host->host_name])->update(['state'=>1,'v_name'=>'']);
            //删除public ip
            $serverIpv4Model = new \app\common\model\ServersIpv4();//独立ip
            $serverIpv4Model->where(['v_name'=>$host->host_name])->update(['state'=>1,'v_name'=>'']);
            //删除nat ip
            $serverIpv4NatModel = new \app\common\model\ServersIpv4Nat();//natip
            $serverIpv4NatModel->where(['v_name'=>$host->host_name])->update(['state'=>1,'v_name'=>'']);

            //删除流量
            $network_monitor_model =  new NetworkMonitorVps();
            $network_monitor_model->where(['host_name'=>$host->host_name])->delete();

            //删除端口映射表
            $port_vps_model = new  ForwardPortVps();
            $port = $port_vps_model->where(['host_name'=>$host->host_name])->select();
            $port_vps_model->where(['host_name'=>$host->host_name])->delete();

            //删除域名
            $domain_vps_model = new ForwardDomainVps();
            $domain = $domain_vps_model->where(['host_name'=>$host->host_name])->select();
            $domain_vps_model->where(['host_name'=>$host->host_name])->delete();

            //删除策略
            $firewall_vps_model = new FirewallVps();
            $firewall_vps_model->where(['host_name'=>$host->host_name])->delete();

            //删除vnc端口
            $vncPort = new VncPort();
            $vncPort->where(['host_name'=>$host->host_name])->delete();

            //删除受控端口和域名
            if($host->is_nat==1){ //挂机宝
                if($domain){
                    $domain = $domain->toArray();
                    $domain = array_column($domain,'domain');
                    $vm->batDeleteDomainHost($node->toArray(),[
                        'domain'=>implode(',',$domain),
                        'host_name'=>$host->host_name
                    ]);
                }
                if($port){
                    $port = $port->toArray();
                    $sport= array_column($port,'sport');
                    $dport= array_column($port,'dport');
                    $port_type= array_column($port,'port_type');
                    $vm->batDeletePortHost($node->toArray(),[
                        'domain'=>implode(',',$domain),
                        'host_name'=>$host->host_name,
                        'sport'=>implode(',',$sport),
                        'dport'=>implode(',',$dport),
                        'port_type'=>implode(',',$port_type),
                        'ip'=>$host->ip,
                    ]);
                }
            }
            //删除检查表
            $checkOverdueVps =  new CheckOverdueVps();
            $checkOverdueVps->where(['host_id'=>$host->id])->delete();

            $host_vps_model ->where(['id'=>$host->id])->update(['state'=>6]);

            $taskid =  self::addTask('remove_vps', 'id=' .$host->id, []);
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->removeVps($taskid);

            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            throw new  Exception('line:'.$e->getLine()."msg:".$e->getMessage());
        }
    }

    //删除云主机
    public static function  taskDeleteHost(array $param){
        //节点虚拟机减去1
        //删除云主机记录
        //恢复ip 私网ip 公网ip  natip
        //删除流量表
        //删除端口映射表
        //删除域名白名单表
        //kvm 删除vnc端口
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(empty($host)){
                return ['code'=>200,'msg'=>''];
            }

            $endtime = strtotime($host->end_time);
            $nowtime = strtotime(date('Y-m-d'));
            //到期以后断网
            if ($endtime>$nowtime){
                return ['code'=>200,'msg'=>''];
            }

            \think\facade\Log::info("admin remove host hostinfo=###".json_encode($host->toArray()).'##userinfo='.Session::get('admin.id')."##now_time=".$nowtime);
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $post_data = [];
            $post_data['host_name'] = $host->host_name;
            $post_data['back_dir'] = $node->backup_path;

            //删除私有ip
            $serverIpv4PrivateModel = new \app\common\model\ServersIpv4Private();//私有ip
            $serverIpv4PrivateModel->where(['v_name'=>$host->host_name])->update(['state'=>1,'v_name'=>'']);
            //删除public ip
            $serverIpv4Model = new \app\common\model\ServersIpv4();//独立ip
            $serverIpv4Model->where(['v_name'=>$host->host_name])->update(['state'=>1,'v_name'=>'']);
            //删除nat ip
            $serverIpv4NatModel = new \app\common\model\ServersIpv4Nat();//natip
            $serverIpv4NatModel->where(['v_name'=>$host->host_name])->update(['state'=>1,'v_name'=>'']);

            //删除流量
            $network_monitor_model =  new NetworkMonitorVps();
            $network_monitor_model->where(['host_name'=>$host->host_name])->delete();

            //删除端口映射表
            $port_vps_model = new  ForwardPortVps();
            $port = $port_vps_model->where(['host_name'=>$host->host_name])->select();
            $port_vps_model->where(['host_name'=>$host->host_name])->delete();

            //删除域名
            $domain_vps_model = new ForwardDomainVps();
            $domain = $domain_vps_model->where(['host_name'=>$host->host_name])->select();
            $domain_vps_model->where(['host_name'=>$host->host_name])->delete();

            //删除策略
            $firewall_vps_model = new FirewallVps();
            $firewall_vps_model->where(['host_name'=>$host->host_name])->delete();

            //删除vnc端口
            $vncPort = new VncPort();
            $vncPort->where(['host_name'=>$host->host_name])->delete();

            //删除受控端口和域名
            if($host->is_nat==1){ //挂机宝
                if($domain){
                    $domain = $domain->toArray();
                    $domain = array_column($domain,'domain');
                    $vm->batDeleteDomainHost($node->toArray(),[
                        'domain'=>implode(',',$domain),
                        'host_name'=>$host->host_name
                    ]);
                }
                if($port){
                    $port = $port->toArray();
                    $sport= array_column($port,'sport');
                    $dport= array_column($port,'dport');
                    $port_type= array_column($port,'port_type');
                    $vm->batDeletePortHost($node->toArray(),[
                        'domain'=>implode(',',$domain),
                        'host_name'=>$host->host_name,
                        'sport'=>implode(',',$sport),
                        'dport'=>implode(',',$dport),
                        'port_type'=>implode(',',$port_type),
                        'ip'=>$host->ip,
                    ]);
                }
            }
            //删除检查表
            $checkOverdueVps =  new CheckOverdueVps();
            $checkOverdueVps->where(['host_id'=>$host->id])->delete();

            $host_vps_model ->where(['id'=>$host->id])->update(['state'=>6]);

            $taskid =  self::addTask('remove_vps', 'id=' .$host->id, []);
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->removeVps($taskid);

            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            throw new  Exception('line:'.$e->getLine()."msg:".$e->getMessage());
        }
    }

    //云主机重建
    public static function recreateVps($param)
    {
        try {
            $hostVpsModel = new  \app\common\model\HostVps();
            $host = $hostVpsModel ->find($param['hostid']);

            $hostVpsModel ->where(['id'=>$host->id])->update(['state'=>1]);

            //创建任务
            //$task = new Task();
            //$task->save(['command'=>'recreate_vps', 'param'=>'id=' . $host->id, 'state'=>0,'start_time'=>date('Y-m-d H:i:s')]);
            //$taskid = $task->getLastInsID();
            $taskid =  self::addTask('recreate_vps', 'id=' .$host->id, []);
            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->recreateVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            return $param;
        } catch (Exception $e) {
            throw new  Exception('line:'.$e->getLine()."msg:".$e->getMessage());
        }
    }

    //云主机监控
    static function  monitorHost(array $param){
        $hostid = $param['hostid']; //HostVps
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(in_array($host->state,[1,4,5,11,10])){
            return ['code'=>0,'msg'=>'当前状态为'. BaseConst::HOST_STATE[$host->state].'请稍后刷新页面'];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        return  $vm->monitorHost($node->toArray(),$host->toArray());
    }

    //云主机运行图片
    function  thumbnailHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(in_array($host->state,[1,4,5,11,10])){
            return ['code'=>0,'msg'=>'状态为'.$host->state];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        return  $vm->thumbnailHost($node->toArray(),$host->toArray());
    }

    //云主机开机
    public  static  function startHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(strtotime($host['end_time'])<strtotime(date('Y-m-d'))){
            throw  new Exception('您的机器已到期');
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if(in_array($host->state,[1,4,5,6,11,10])){
            throw  new Exception('当前状态不允许操作');
        }
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res = $vm->startHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>2]);
        return ['code'=>200,'msg'=>''];
    }

    //设置BIOS
    public  static  function setBios(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(strtotime($host['end_time'])<strtotime(date('Y-m-d'))){
            throw  new Exception('您的机器已到期');
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if(in_array($host->state,[1,4,5,6,11,10])){
            throw  new Exception('当前状态不允许操作');
        }
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $host->bios = $param['bios'];
        $res = $vm->setBiosHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['bios'=>$param['bios']]);
        return ['code'=>200,'msg'=>''];
    }

    //云主机关机
    public  static function closeHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(in_array($host->state,[1,4,5,6,11,10])){
            return ['code'=>0,'msg'=>'当前状态不允许操作'];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res = $vm->closeHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            return ['code'=>0,'msg'=>$res['msg']];
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>3]);
        return ['code'=>200,'msg'=>''];
    }

    //云主机断开电源
    public  static function powerHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(in_array($host->state,[1,4,5,6,11,10])){
            throw  new Exception('当前状态不允许操作');
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res = $vm->powerHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>3]);
        return ['code'=>200,'msg'=>''];
    }

    //云主机重启
    public static function restartHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();

        if(strtotime($host['end_time'])<strtotime(date('Y-m-d'))){
            throw  new Exception('您的机器已到期');
        }

        if(in_array($host->state,[1,4,5,6,11,10])){
            throw  new Exception('当前状态不允许操作');
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res = $vm->restartHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>2]);
        return ['code'=>200,'msg'=>''];
    }

    //lock
    public static function lockHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(empty($host)){
            return ['code'=>200,'msg'=>''];
        }
        if($host->from_type!=1){
            throw  new Exception('非自生产产品不允许操作');
        }
        if(in_array($host->state,[10])){
            return ['code'=>200,'msg'=>''];
        }

        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res =   $vm->powerHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>10]);
        return ['code'=>200,'msg'=>''];
    }

    //lock
    public static function tasklockHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(empty($host)){
            return ['code'=>200,'msg'=>''];
        }
        if($host->from_type!=1){
            throw  new Exception('非自生产产品不允许操作');
        }
        if(in_array($host->state,[10])){
            return ['code'=>200,'msg'=>''];
        }

        $endtime = strtotime($host->end_time);
        $nowtime = strtotime(date('Y-m-d'));
        //到期以后断网
        if ($endtime>$nowtime){
            return ['code'=>200,'msg'=>''];
        }

        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res =   $vm->powerHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>10]);
        return ['code'=>200,'msg'=>''];
    }

    //解lock
    public static function unlockHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if($host->from_type!=1){
            throw  new Exception('非自生产产品不允许操作');
        }
        if(!in_array($host->state,[10])){
            return ['code'=>200,'msg'=>''];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res =  $vm->startHost($node->toArray(),$host->toArray());
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        $host_vps_model->where(['id'=>$hostid])->update(['state'=>2]);
        return ['code'=>200,'msg'=>''];
    }

    //批量设置IP
    public static function goBatSetip(array $param){
        $hostid = $param['hostid'];
        $host_model = new \app\common\model\HostVps();
        $ip_model =  new \app\common\model\ServersIpv4();
        $host = $host_model ->where(['id'=>$hostid])->find();

        $ips = $ip_model->where(['v_name'=>$host->host_name,'state'=>2])->select();
        $ip = array_column($ips->toArray(),'ip');

        if($host->from_type!=1){
            throw  new Exception('非自生产产品不允许操作');
        }
        if(in_array($host->state,[11,1])){
            throw  new Exception('当前状态不允许操作');
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $res =  $vm->batsetip($node->toArray(),['ip'=>$ip,'vm_name'=>$host->host_name]);
        if($res['code']!=200){
            throw  new Exception($res['msg']);
        }
        return ['code'=>200,'msg'=>''];
    }

    //Guid
    static function guidHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if(in_array($host->state,[1,4,5,6,11])){
            return ['code'=>0,'msg'=>'当前状态不允许操作'];
        }
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        return  $vm->guidHost($node->toArray(),$host->toArray());
    }

    //获取状态
    function getStateHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if(in_array($host->state,[1,4,5,6,11])){
            return ['code'=>0,'msg'=>'当前状态不允许操作'];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        return  $vm->getStateHost($node->toArray(),$host->toArray());
    }

    //重新安装操作系统 添加任务
    static function reinstallTask(array $param){
        $hostid = $param['hostid'];
        $os_id = $param['os_id'];
        $password = $param['password'];
        try{
            Db::startTrans();
            $host_vps_model =  new \app\common\model\HostVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            $image = new \app\common\model\ServersImageConfig();
            $imageinfo = $image->where(['id'=>$os_id])->find();
            if(in_array($host->state,[1,11,10])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            if($host->state==4){
                return ['code'=>0,'msg'=>'等待上一次安装操作系统完成'];
            }
            if(empty($host->reinstall_time)){
                $host->reinstall_time = date('Y-m-d');
            }
            if(date("Y-m-d",strtotime( $host->reinstall_time))==date("Y-m-d")){
                if($host->reinstall_num>=$host->max_reinstall_num){
                    return ['code'=>0,'msg'=>'每天最多允许重新安装操作系统'.$host->max_reinstall_num.'次'];
                }
                $host->reinstall_num= $host->reinstall_num+1;
            }else{
                $host->reinstall_num= 1;
            }

            //创建任务
            $taskid = self::addTask('reinstall','id='.$hostid);

            $host->state=4;
            $host->os_name=$imageinfo->os_name;
            $host->reinstall_time = date("Y-m-d");
            $host->os_password = $password;
            $host->save();

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->reinstallVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            Db::commit();
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            Db::rollback();
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //获取iso
    function getisoHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if(in_array($host->state,[1,4,5,11,10])){
            return ['code'=>0,'msg'=>'当前状态不允许操作'];
        }
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        return  $vm->getisoHost($node->toArray(),$host->toArray());
    }

    //挂载iso
    function mountisoHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if(in_array($host->state,[1,4,5,11,10])){
            return ['code'=>0,'msg'=>'当前状态不允许操作'];
        }
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }

        $result =  $vm->isoHost($node->toArray(),array_merge($host->toArray(),['iso'=>$param['iso']]));
        if($result['code']==200){
            $host->now_iso=$param['iso'];
            $host->save();
        }
        return $result;
    }

    //卸载iso
    function unmountisoHost(array $param){
        $hostid = $param['hostid'];
        $host_vps_model =  new \app\common\model\HostVps();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if($host->now_iso==''){
            return ['code'=>0,'msg'=>'当前没有挂载的文件'];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$host->node_id])->find();
        if(in_array($host->state,[1,4,5,11,10])){
            return ['code'=>0,'msg'=>'当前状态不允许操作'];
        }
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $result = $vm->isoHost($node->toArray(),array_merge($host->toArray(),['iso'=>'']));
        if($result['code']==200){
            $host->now_iso='';
            $host->save();
        }
        return $result;
    }

    //创建备份 添加任务
    static function  createBackupHost(array $param){
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $backup_vps_model = new BackupVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count = $backup_vps_model->where(['host_id'=>$hostid])->whereIn('state',[1,2,4])->count();
            if($host->backup_num<=$count){
                return ['code'=>0,'msg'=>'您最多可创建备份数量'.$host->backup_num.'份'];
            }
            $count1 = $backup_vps_model->where(['host_id'=>$hostid,'state'=>1])->count();
            if($count1>0){
                return ['code'=>0,'msg'=>'请等待上一个备份完成'];
            }

            $name = 'bak'.date('mdHis');
            $backup_id = 0;
            $taskid =  Db::transaction(function ()use ($host,$backup_vps_model,$name,&$backup_id) {
                $backup_vps_model->save(['name'=>$name,'host_name'=>$host->host_name,'host_id'=>$host->id,'state'=>1]);
                $id = $backup_vps_model->getLastInsID();
                $backup_id = $id;
                $taskid = self::addTask('create_backup','id='.$id);
                return $taskid;
            });

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->createBackupVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            return ['code'=>200,'msg'=>'','data'=>['name'=>$name,'id'=>$backup_id]];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //恢复备份  添加任务
    static function  restoreBackupHost(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $backup_vps_model = new BackupVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count1 = $backup_vps_model->where(['host_id'=>$hostid])->whereIn('state',[4,5])->count();
            if($count1>0){
                return ['code'=>0,'msg'=>'请等待上一个操作完成'];
            }


            $backup_vps_model->where(['id'=>$id])->save(['state'=>4]);
            $taskid = self::addTask('restore_backup','id='.$id);

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->restoreBackupVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }

            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除备份  添加任务
    static function  removeBackupHost(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $backup_vps_model = new BackupVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count1 = $backup_vps_model->where(['host_id'=>$hostid])->whereIn('state',[4,5])->count();
            if($count1>0){
                return ['code'=>0,'msg'=>'请等待上一个操作完成'];
            }

            $backup_vps_model->where(['id'=>$id])->save(['state'=>5]);
            $taskid = self::addTask('remove_backup','id='.$id);

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->removeBackupVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //创建快照 添加任务
    static  function  createSnapshotHost(array $param){
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $snapshot_vps_model = new SnapshotVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count = $snapshot_vps_model->where(['host_id'=>$hostid])->whereIn('state',[1,2,4])->count();
            if($host->snapshot_num<=$count){
                return ['code'=>0,'msg'=>'您最多可创建快照数量'.$host->snapshot_num.'份'];
            }
            $count1 = $snapshot_vps_model->where(['host_id'=>$hostid,'state'=>1])->count();
            if($count1>0){
                return ['code'=>0,'msg'=>'请等待上一个快照完成'];
            }
            $name = "hot".date('mdHis');
            $snapshot_id= 0 ;
            $taskid =  Db::transaction(function ()use ($host,$snapshot_vps_model,$name,&$snapshot_id) {
                $snapshot_vps_model->save(['name'=>$name,'host_name'=>$host->host_name,'host_id'=>$host->id,'state'=>1]);
                $id = $snapshot_vps_model->getLastInsID();
                $snapshot_id =  $id;
                $taskid = self::addTask('create_snapshot','id='.$id);
                return $taskid;
            });

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->createSnapshotVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }

            return ['code'=>200,'msg'=>'','data'=>['name'=>$name,'id'=>$snapshot_id]];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //恢复快照 添加任务
    static function  restoreSnapshotHost(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $snapshot_vps_model = new SnapshotVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count1 = $snapshot_vps_model->whereIn('state',[4,5])->where(['host_id'=>$hostid])->count();
            if($count1>0){
                return ['code'=>0,'msg'=>'请等待上一个操作完成'];
            }

            $snapshot_vps_model->where(['id'=>$id])->save(['state'=>4]);
            $taskid = self::addTask('restore_snapshot','id='.$id);

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->restoreSnapshotVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }

            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除快照 添加任务
    static function  removeSnapshotHost(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $snapshot_vps_model = new SnapshotVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count1 = $snapshot_vps_model->whereIn('state',[4,5])->where(['host_id'=>$hostid])->count();
            if($count1>0){
                return ['code'=>0,'msg'=>'请等待上一个操作完成'];
            }

            $snapshot_vps_model->where(['id'=>$id])->save(['state'=>5]);
            $taskid = self::addTask('remove_snapshot','id='.$id);

            //执行任务
            $cloudTask = new ExecuteTask();
            $res = $cloudTask->removeSnapshotVps($taskid);
            if($res['code']!=200){
                throw new  Exception($res['msg']);
            }

            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //添加策略 AddAclExtendedHyperV
    static function add_firewall_host(array $param){
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $firewall_vps_model = new FirewallVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();
            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $post_data = [];
            $post_data['host_name'] = $host->host_name;
            $post_data['host_id'] = $host->id;
            $post_data['name'] = date("mdHis");
            $post_data['direction'] = $param['direction'];
            $post_data['protocol'] = $param['protocol'];
            $post_data['method'] = $param['method'];
            $post_data['start_port'] = $param['port']==-1?'ANY':$param['port'];
            $post_data['start_ip'] = $param['ip']=='0.0.0.0'?'ANY':$param['ip'];
            $post_data['priority'] = isset($param['priority'])?$param['priority']:'';
            $firewall_vps_model->save($post_data);
            $data = $vm->addFirewallHost($node->toArray(),$post_data);
            if($data['code']!=200){
                return ['code'=>0,'msg'=>$data['msg']];
            }
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            $host_vps_model->rollback();
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除策略
    static function remove_firewall_host(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $firewall_vps_model = new FirewallVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();
            $firewall_info =$firewall_vps_model->where(['id'=>$id,'host_id'=>$hostid])->find();
            if(!$firewall_info){
                return ['code'=>0,'msg'=>'策略不存在'];
            }
            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $post_data = [];
            $post_data['host_name'] = $firewall_info->host_name;
            $post_data['name'] =  $firewall_info->name;
            $data = $vm->removeFirewallHost($node->toArray(),$post_data) ;
            if($data['code']!=200){
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $firewall_info->delete();
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            $host_vps_model->rollback();
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    static  function security_group_apply(array $param){
        try {
            $old = Db::table('cloud_bind_virtual_security_groups')->where(['virtuals_id'=>$param['hostid']])->find();
            $host = (new HostVps())->find($param['hostid']);
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();
            if($node['virtual_type']=='hyper-v'){
                if($old){
                    $oldlist = Db::table('cloud_security_group_acls')->where(['virtual_security_groups_id'=>$old['virtual_security_groups_id']])->select();
                    $firewall_vps_model = new FirewallVps();
                    foreach ($oldlist as $k=>$v){
                        $info = $firewall_vps_model->where(
                            [
                                'hostid'=>$param['hostid'],
                                'direction'=>$v['orientation']==1?'in':'out',
                                'protocol'=>strtoupper($v['type'])=='ALL'?'ANY':strtoupper($v['type']),
                                'method'=>$v['tactics']==1?'drop':'accept',
                                'port'=>$v['port_start'],
                                'ip'=>$v['ip_start'],
                                'priority'=>(string)$v['level'],
                            ]
                        )->find();
                        if(empty($info)){continue;}
                        self::remove_firewall_host(
                            [
                                'hostid'=>$param['hostid'],
                                'id'=>$info['id'],
                            ]
                        );
                    }
                }
                $newlist = Db::table('cloud_security_group_acls')->where(['virtual_security_groups_id'=>$param['security_groups_id']])->select();
                foreach ($newlist as $k=>$v){
                    $data =  [
                        'hostid'=>$param['hostid'],
                        'direction'=>$v['orientation']==1?'in':'out',
                        'protocol'=>strtoupper($v['type'])=='ALL'?'ANY':strtoupper($v['type']),
                        'method'=>$v['tactics']==1?'drop':'accept',
                        'port'=>$v['port_start'],
                        'ip'=>$v['ip_start'],
                        'priority'=>(string)$v['level'],
                    ];

                    $data = self::add_firewall_host($data);
                    if($data['code']!=200){
                        throw new Exception($data['msg']);
                    }
                }
            }else{
                $newlist = Db::table('cloud_security_group_acls')->where(['virtual_security_groups_id'=>$param['security_groups_id']])->select();
                $data = [];
                foreach ($newlist as $k=>$v){
                    $temp =  [
                        'host_name'=> $host->host_name,
                        'host_id'=>$host->id,
                        'name'=>uniqid(),

                        'direction'=>$v['orientation']==1?'in':'out',
                        'protocol'=>strtoupper($v['type'])=='ALL'?'ANY':strtoupper($v['type']),
                        'action'=>$v['tactics']==1?'drop':'accept',
                        'start_port'=>$v['port_start'],
                        'end_port'=>$v['port_end'],
                        'start_ip'=>$v['ip_start'],
                        'end_ip'=>$v['ip_end'],
                        'priority'=>(string)$v['level'],
                    ];
                    $data[] = $temp;
                }
                $vm = new Kvm();
                $re = $vm->groupFirewallApply($node->toArray(),$data);
                if ($re['code']!=200){
                    return ['code'=>0,'msg'=>$re['msg']];
                }
            }
            $info = Db::table('cloud_bind_virtual_security_groups')->where(['virtuals_id'=>$param['hostid']])->find();
            if($info){
                Db::table('cloud_bind_virtual_security_groups')->where(['virtuals_id'=>$param['hostid']])->update(['virtual_security_groups_id'=>$param['security_groups_id']]);
            }else{
                Db::table('cloud_bind_virtual_security_groups')->save(
                    [
                        'virtuals_id'=>$param['hostid'],
                        'virtual_security_groups_id'=>$param['security_groups_id'],
                        'created_at'=>date('Y-m-d H:i:s'),
                        'updated_at'=>date('Y-m-d H:i:s')]
                );
            }

            return ['code'=>200,'msg'=>""];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }

        Db::table('cloud_bind_virtual_security_groups')->where(['virtuals_id'=>$param['hostid']])->update(['virtual_security_groups_id'=>$param['security_groups_id']]);
        return ['code'=>200,'msg'=>''];
    }

    static function security_group_acl_add(array $param){
        $groups_id = $param['groups_id']; //users_id
        $groups= Db::table('cloud_security_groups')->where(['id'=>$groups_id])->find();
        $field = ['orientation','tactics','type','port_start','port_end','ip_start','ip_end','level','virtual_security_groups_id','desc'];
        $data = [];
        foreach($param as $k=>$v){
            if(in_array($k,$field)){
                $data[$k] = $v;
            }
        }
        $data['users_id'] = $groups['users_id'];
        $data['virtual_security_groups_id'] = $groups['id'];
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('cloud_security_group_acls')->save($data);
    }
    static function security_group_acl_del(array $param){
        Db::table('cloud_security_group_acls')->where(['id'=>$param['id']])->delete();
    }

    //查找可用端口
    public static  function find_port(array $param){
        $hostid = $param['hostid'];
        $keywords = $param['keywords'];
        $host_vps_model = new \app\common\model\HostVps();
        $host = $host_vps_model->where(['id' => $hostid])->find();
        if (!in_array($host->state, [2, 3])) {
            return ['code' => 0, 'msg' => '当前状态不允许操作'];
        }
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model->where(['id' => $host->node_id])->find();
        $noport= $node->no_port;
        $noport = array_filter(explode(',',$noport));
        $like = true;
        if(isset($param['like'])&&$param['like']!=1){
            $like = false;
        }
        $port = QzcloudTool::getPort($node->max_port,$node->min_port,$node->forward_url,$keywords,5,$noport,$like);
        return $port;
    }

    //添加端口
    static function add_forward_port(array $param){
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $port_vps_model = new ForwardPortVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[1,2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count = $port_vps_model->where(['host_id'=>$hostid,'sys'=>1])->count();
            $sys =1;
            if(isset($param['sys'])){
                $sys = $param['sys'];
            }
            if($host->port_num<=$count&&$sys!=2){
                return ['code'=>0,'msg'=>'您最多可添加端口数量'.$host->port_num.'个'];
            }

            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();

            $post_data = [];
            $post_data['host_name'] = $host->host_name;
            $post_data['host_id'] = $host->id;
            $post_data['name'] = $param['name'];
            $post_data['port_type'] = 'all';
            $post_data['sport'] = $param['sport'];
            $post_data['dport'] = $param['dport'];
            $post_data['sys'] = isset($param['sys'])?$param['sys']:1;
            $post_data['api_url'] = $node->forward_url;
            $post_data['dip'] = $host->ip;

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $data = $vm->addForwardPort($node->toArray(),$post_data);
            if($data['code']!=200){
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $port_vps_model->insert($post_data);
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除端口
    static function remove_forward_port(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $port_vps_model = new ForwardPortVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();
            $port_info =$port_vps_model->where(['id'=>$id,'host_id'=>$hostid])->find();
            if(!$port_info){
                return ['code'=>0,'msg'=>'要删除的端口不存在'];
            }
            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }

            $post_data = [];
            $post_data['host_name'] = $port_info->host_name;
            $post_data['sport'] =  $port_info->sport;
            $post_data['dport'] =  $port_info->dport;
            $post_data['dip'] =  $port_info->dip;

            $data = $vm->removeForwardPort($node->toArray(),$post_data) ;
            if($data['code']!=200){
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $port_info->delete();
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //添加域名
    function add_forward_domain(array $param){
        $hostid = $param['hostid'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $domain_vps_model = new ForwardDomainVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }

            $count = $domain_vps_model->where(['host_id'=>$hostid])->count();
            if($host->domain_num<=$count){
                return ['code'=>0,'msg'=>'您最多可添加域名数量'.$host->domain_num.'个'];
            }

            $domain_info =$domain_vps_model->where(['domain'=>$param['domain']])->find();
            if($domain_info){
                return ['code'=>0,'msg'=>'要添加的域名已存在'];
            }

            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();

            $post_data = [];
            $post_data['host_name'] = $host->host_name;
            $post_data['host_id'] = $host->id;
            $post_data['domain'] = $param['domain'];
            $post_data['api_url'] = $node->forward_url;
            $post_data['dip'] = $host->ip;

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }

            $data = $vm->addForwardDomain($node->toArray(),$post_data);
            if($data['code']!=200){
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $domain_vps_model->save($post_data);
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除域名
    function remove_forward_domain(array $param){
        $hostid = $param['hostid'];
        $id = $param['id'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $domain_vps_model = new ForwardDomainVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();
            $domain_info =$domain_vps_model->where(['id'=>$id,'host_id'=>$hostid])->find();
            if(!$domain_info){
                return ['code'=>0,'msg'=>'要删除的域名不存在'];
            }
            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }

            $post_data = [];
            $post_data['host_name'] = $domain_info->host_name;
            $post_data['domain'] =  $domain_info->domain;
            $post_data['dip'] =  $domain_info->ip;

            $data = $vm->removeForwardDomain($node->toArray(),$post_data) ;
            if($data['code']!=200){
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $domain_info->delete();
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除域名
    static  function update_password_host(array $param){
        $hostid = $param['hostid'];
        $password= $param['password'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $host_vps_model->startTrans();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $post_data = [];

            $image = new \app\common\model\ServersImageConfig();
            $imageinfo = $image->where(['os_name'=>$host['os_name']])->find();
            if(!$imageinfo){
                throw new Exception('镜像文件没有不存在 os_name=####'.$host['os_name']);
            }

            $post_data['os_type'] = \app\common\model\ServersImageConfig::$os_type[$imageinfo->os_type];//修改系统密码 传递是root还是administrator
            $post_data['host_name'] = $host->host_name;
            $post_data['password'] =  $password;
            $data = $vm->updatePasswordHost($node->toArray(),$post_data) ;
            if($data['code']!=200){
                $host_vps_model->rollback();
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $host->os_password=$param['password'];
            $host->save();
            $host_vps_model->commit();
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            $host_vps_model->rollback();
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //同步物理机器时间
    function synctime_host(array $param){
        $hostid = $param['hostid'];
        $sync_time= $param['sync_time'];
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $host_vps_model->startTrans();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if(!in_array($host->state,[2,3])){
                return ['code'=>0,'msg'=>'当前状态不允许操作'];
            }
            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $post_data = [];
            $post_data['host_name'] = $host->host_name;
            $post_data['state'] =  $sync_time;
            $data = $vm->syncTimeHost($node->toArray(),$post_data) ;
            if($data['code']!=200){
                $host_vps_model->rollback();
                return ['code'=>0,'msg'=>$data['msg']];
            }
            $host->sync_time=$sync_time;
            $host->save();
            $host_vps_model->commit();
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            $host_vps_model->rollback();
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //计算小鸡流量
    public  static  function network_flow_host(){
        try{
            $host_vps_model =  new \app\common\model\HostVps();
            $network_monitor_model =  new NetworkMonitorVps();
            $node_model = new \app\common\model\ServersNode();
            $nodeList = $node_model->order('flow_task_time asc')->limit(2)->select();

            foreach ($nodeList as $k=>$node){
                $node_ip = $node['node_ip'];
                $node_ip_arr = explode(":",$node_ip);
                if (!self::isNetworkConnected($node_ip_arr[0], isset($node_ip_arr[1])?(int)$node_ip_arr[1]:8000, 20)){
                    $node_model->where(['id'=>$node->id])->update(['flow_task_time'=>date('Y-m-d H:i:s')]);
                    continue;
                }

                $m =0;
                if($node['virtual_type']=='hyper-v'){
                    $vm = new HyperV();
                }else{ //kvm
                    $vm = new Kvm();
                }
                $data = $vm->GetVMFlow($node->toArray());

                if($data['code']!=200){
                    continue;
                }
                //ecs-OyJwoL
                $flow = $data['data'];
                foreach ($flow as $k=>$v){
                    $host = $host_vps_model->where(['host_name'=>$k])->find();
                    if(empty($host)){
                        continue;
                    }
                    $network_monitor_host = $network_monitor_model->where(['host_id'=>$host->id])->find();
                    if($host["virtual_type"]=="kvm"){
                        if (!is_array($v)){
                            continue;
                        }

                        foreach ($v as $k1=>$v2){
                            if ($v2["date"]==date("Y-m")){
                                    if($network_monitor_host){
                                        $network_monitor_host->network_in=isset($v2['rx'])?$v2['rx']:0;
                                        $network_monitor_host->network_out=isset($v2['tx'])?$v2['tx']:0;
                                        $network_monitor_host->month=date("m");
                                        $network_monitor_host->save();
                                    }else{
                                        $network_monitor_model->insert([
                                            'host_id'=>$host->id,
                                            'host_name'=>$host->host_name,
                                            'month'=>date("m"),
                                            'network_in'=>isset($v2['rx'])?$v2['rx']:0,
                                            'network_out'=>isset($v2['tx'])?$v2['tx']:0,
                                            'update_time'=>date('Y-m-d H:i:s'),
                                            'create_time'=>date('Y-m-d H:i:s')
                                        ]);

                                    }
                            }
                        }

                       continue;
                    }

                    $day = date("d",strtotime($host['buy_time']));
                    $nowMonthLastDay = date('t');
                    if ($day>$nowMonthLastDay){
                        $day = $nowMonthLastDay;
                    }

                    $month = date('m');
                    //变态的情况 11月份  当天没清理完毕，
                    if (substr($host['buy_time'],0,10)==substr($host['end_time'],0,10)){
                        if (!empty($network_monitor_host)&&date("d")>=date('d',strtotime($host['end_time']))&&$network_monitor_host['month']>0){
                            $month = 0;
                            $vm->clearNetworkFlowHost($node->toArray(),['host_name'=>$host->host_name]);
                        }
                    }else{
                        if(!empty($network_monitor_host)){
                            if (($day<date("d")&&($network_monitor_host['month']<=date('m')))
                                ||(date('m')-$network_monitor_host['month']==2)){ //或者说隔了2个月
                                $vm->clearNetworkFlowHost($node->toArray(),['host_name'=>$host->host_name]);
                                $month = $month+1;
                            }
                        }

                    }
                    if(isset($network_monitor_host['month'])&&$network_monitor_host['month']>$month){
                        $month = $network_monitor_host['month'];
                    }

                    if($network_monitor_host){
                        $network_monitor_host->network_in=$v[0];
                        $network_monitor_host->network_out=$v[1];
                        $network_monitor_host->month=$month;
                        $network_monitor_host->save();
                    }else{
                        $network_monitor_model->insert([
                            'host_id'=>$host->id,
                            'host_name'=>$host->host_name,
                            'month'=>$month,
                            'network_in'=>$v[0],
                            'network_out'=>$v[1],
                            'update_time'=>date('Y-m-d H:i:s'),
                            'create_time'=>date('Y-m-d H:i:s')
                        ]);

                    }
                }
                $node_model->where(['id'=>$node->id])->update(['flow_task_time'=>date('Y-m-d H:i:s')]);

            }
            return ['code'=>200,'msg'=>''];
        }catch (Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //根据流量设置网卡状态 前提是没有到期
    public  static  function set_network_state_host(){
        $host_vps_model =  new \app\common\model\HostVps();//close_network
        $sql='SELECT B.host_name,B.id hostid,B.node_id,B.close_network ,case when B.traffic*1024>A.network_in+A.network_out THEN 1 ELSE 2 END state from cloud_network_monitor_vps  A INNER JOIN  cloud_host_vps B 
              ON A.host_id=B.id where B.state in(2,3)  and B.traffic>0  and  B.from_type=1 and B.end_time>="'.date('Y-m-d').'"';
        $list = Db::query($sql);

        $node_model = new \app\common\model\ServersNode();
        foreach ($list as $k=>$info){
            $hostid = $info['hostid'];
            $node = $node_model ->where(['id'=>$info['node_id']])->find();
            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }
            $host = $host_vps_model->find($hostid);
            if($info['state']!=$info['close_network']){
                $netdata =[
                    'host_name'=>$info['host_name'],
                    'state'=>$info['state'],
                    // 'vlanid1'=>$host['vlanid1'],
                    "is_nat"=>$host->is_nat
                ];
                if(!empty($host['vlanid1'])){
                    $netdata['vlanid1'] = $host['vlanid1'];
                }
                $data = $vm->setNetworkStateHost($node->toArray(),$netdata);

                if($data['code']!=200){
                    continue;
                }
                $host_vps_model ->where(['id'=>$hostid])->update(['close_network'=>$info['state']]);
            }
            return ['code'=>200,'msg'=>''];
        }
        return ['code'=>200,'msg'=>''];
    }

    static function isNetworkConnected($host, $port = 80, $timeout = 20) {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);
            return true;
        } else {
            return false;
        }
    }


    //根据流量设置网卡状态
    public  static function set_network_state($param){
        $hostid = $param['hostid'];
        $state = $param['state'];
        $host_vps_model =  new \app\common\model\HostVps();
        $node_model = new \app\common\model\ServersNode();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if($state==$host['close_network']){
            return ;
        }
        $node = $node_model->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $data = $vm->setNetworkStateHost($node->toArray(),['host_name'=>$host->host_name,'state'=>$state,"is_nat"=>$host->is_nat]);
        if($data['code']==200)$host_vps_model ->where(['id'=>$hostid])->update(['close_network'=>$state]);
        return $data;
    }

    //检查过期3天内到期的插入这张表
    public  static function check_overdue(){
        $model =  new \app\common\model\HostVps();
        $checkOverdueVps =  new CheckOverdueVps();
        $list = $model->alias('a')
            ->join('cloud_servers_node b','a.node_id=b.id','left')
            ->join('cloud_check_overdue_vps c','c.host_id=a.id','left')
            ->where(['from_type'=>1])
            ->where('a.end_time','<',date('Y-m-d',strtotime('+3 days')))
            ->whereNull('c.end_time')
            ->field('a.*,b.host_delete_day')
            ->select();

        $data = [];
        foreach ($list as $k=>$v){
            $temp=[];
            $temp['host_name'] = $v->host_name;
            $temp['host_id'] = $v->id;

            $temp['buy_time'] = $v->buy_time;
            $temp['end_time'] = $v->end_time;
            $v->host_delete_day=empty($v->host_delete_day)?0:$v->host_delete_day;
            $temp['del_time'] = date('Y-m-d',strtotime('+ '.$v->host_delete_day.'days',strtotime($v->end_time)));
            $data[]=$temp;
        }
        $checkOverdueVps->saveAll($data);
    }

    //暂停  恢复 删除
    public  static function do_overdue($hostid=0){
        $hostModel =  new \app\common\model\HostVps();
        $serversNodeModel =  new \app\common\model\ServersNode();
        $checkOverdueVps =  new CheckOverdueVps;
        if($hostid&&$hostid>0){
            $list = $checkOverdueVps->where(['id'=>$hostid])->select();
        }else{
            $list = $checkOverdueVps->select();
        }

        $serversNode = $serversNodeModel->column('*','id');
        foreach ($list as $k=>$v){
            $host = $hostModel->where(['id'=>$v->host_id])->find();
            if(empty($host)){
                $v->delete();
                continue;
            }
            $endtime = strtotime($host['end_time']);
            $nowtime = strtotime(date('Y-m-d'));

            if(empty($endtime)||trim((string)$endtime,'')==''){
                continue;
            }
            //续期
            if ($endtime>=$nowtime){
                try {
                    self::unlockHost(['hostid'=>$host->id]);
                }catch (Exception $e ){
                    // continue;
                }
                $checkOverdueVps->where(['host_id'=>$host->id])->delete();
                continue;
            }

            //到期以后断网
            if ($endtime<$nowtime){
                try {
                    if($host['state']==10){
                        continue;
                    }
                    \think\facade\Log::info("task lock host hostinfo=###".json_encode($host->toArray())."###nowtime=".$nowtime."###endtime=".$endtime);
                    self::tasklockHost(['hostid'=>$host->id]);
                }catch (Exception $e ){
                    //continue;
                }

            }

            $line = $serversNode[$host->node_id];
            $host_delete_day = $line['host_delete_day']*86400;
            $deltime = $endtime+$host_delete_day;
            if($deltime<$nowtime&&($endtime<$nowtime)){
                \think\facade\Log::info("task remove host hostinfo=###".json_encode($host->toArray())."###nowtime=".$nowtime."###endtime=".$endtime);
                try {
                    $hostModel->startTrans();
                    self::taskDeleteHost(['hostid' => $host->id]);
                    $hostModel->commit();
                }catch (Exception $e){
                    $hostModel->rollback();
                }
                continue;
            }else{
                $v->delete();
                continue;
            }

        }


    }


    public static function setField($where,$data){
        try{
            $model =  new Task();
            $model->where($where)->data($data)->save();
        }catch (Exception $E){
            throw new Exception($E->getMessage());
        }
    }

    public static function addTask($command,$where,$other=[]){
        try{
            $model = new Task();
            $data = ['command'=>$command,'param'=>$where,'create_time'=>date('Y-m-d H:i:s')];
            if($other){
                $data =array_merge($data,$other);
            }
            $res = $model->insert($data,true);
            return $res;
        }catch (Exception $E){
            throw new Exception($E->getMessage());
        }
    }

    //监控母鸡
    public static function monitorCompany(array $param){
        $node_id= $param['node_id'];
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $data =  $vm->monitorCompany($node->toArray(),[]);
        if($data['code']==200){

            $data['data']['memUsage'] = round(($data['data']['memory']-$data['data']['freeMemory'])*100/$data['data']['memory'],2);
        }
        return $data;
    }

    public static function getMemoryAndDisk(array $param){
        $node_id= $param['node_id'];
        $node_model = new \app\common\model\ServersNode();
        $node = $node_model ->where(['id'=>$node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $data =  $vm->getMemoryAndDisk($node->toArray(),[]);
        if($data['code']==200){
            $data['data']['memUsed'] = bcsub((string)$data['data']['memory'],(string)$data['data']['freeMemory'],1);
        }
        $msg = isset($data['msg'])?$data['msg']:'null';
        $msg.='节点id='.$node_id;
        $data['msg'] = $msg;
        return $data;
    }

    public static function getNodeid($line_id){
        $nodeModel = new ServersNode();//物理节点信息
        $hostVpsModel = new HostVps();
        //and vm_number<max_vm_number
        $node_list = $nodeModel->where("line_id = '" . $line_id . "' and state=1 ")->order('weight desc')->select();
        $ct = $hostVpsModel->field("count(*) ct,node_id")->group('node_id')->where('state!=11')->select();
        $ct_=[];
        if(!empty($ct)){
            $ct =$ct->toArray();
            $ct_ =array_column($ct,'ct','node_id');
        }

        foreach ($node_list as $k=>$v){
            $memory_disk = [];
            if(!empty($v['max_vm_disk'])||!empty($v['max_vm_memory'])){
                $result = Ecs::getMemoryAndDisk(['node_id'=>$v['id']]);
                if($result['code']!=200){
                    throw  new Exception($result['msg']);
                }

                $data = $result['data'];
                $memory_disk =$data;
            }

            if(isset($ct_[$v['id']])&&$v['max_vm_number']<=$ct_[$v['id']]){
                continue;
            }

            if(!empty($v['max_vm_memory'])&&(float)$v['max_vm_memory']<=(float)$memory_disk['memUsed']){
                continue;
            }

            $os_disk = strtolower($v['os_path']);
            $data_disk = strtolower($v['data_path']);
            $disk =0 ;
            if(!empty($v['max_vm_data_disk'])){
                foreach ($memory_disk['space'] as $k=>$space){
                    $name = strtolower($space['name']);
                    if(strstr($data_disk,$name)!==false){
                        $useddisk = bcsub((string)$space['size'],(string)$space['freeSpace'],2);
                        if($v['max_vm_data_disk']<=(float)$useddisk){
                            $disk=1;
                            break;
                        }
                    }
                }
            }
            if(!empty($v['max_vm_os_disk'])){
                foreach ($memory_disk['space'] as $k=>$space){
                    $name = strtolower($space['name']);
                    if(strstr($os_disk,$name)!==false){
                        $useddisk = bcsub((string)$space['size'],(string)$space['freeSpace'],2);
                        if($v['max_vm_os_disk']<=(float)$useddisk){
                            $disk=1;
                            break;
                        }
                    }
                }
            }
            if( $disk==1){
                continue;
            }
            return $v;
        }
    }

    //重新设置主ip
    public static function resetNetwork($param)
    {
        try {
            $hostid = $param['hostid']; //HostVps
            $host_vps_model =  new \app\common\model\HostVps();
            $host = $host_vps_model ->where(['id'=>$hostid])->find();
            if($host['is_nat']==1){ //挂机宝 不允许
                return;
            }

            $ip_model =  new \app\common\model\ServersIpv4();
            $public_all_ip = $ip_model->where(['v_name'=>$host->host_name,'state'=>2])->select();
            $ip = array_column($public_all_ip->toArray(),'ip');

            $ip_model =  new \app\common\model\ServersIpv4Private();
            $private_ip = $ip_model->where(['v_name'=>$host->host_name,'state'=>2])->find();
            $netmask = '';
            $gateway = '';
            $otherip = '';
            $masterip = '';
            foreach ($public_all_ip as $k=>$v){
                if($host->ip == $v['ip']){
                    $netmask = $v['netmask'];
                    $gateway = $v['gateway'];
                    $masterip = $v;
                }else{
                    $otherip = $v['ip'].',';
                }
            }

            $node_model = new \app\common\model\ServersNode();
            $node = $node_model ->where(['id'=>$host->node_id])->find();


            $otherip = trim($otherip,',');
            $vmconfig = [];
            $network = [['ip'=>$host->ip,'netmask'=>$netmask,'gateway'=>$gateway,'mac'=>$masterip['mac'],'otherip'=>$otherip,'dns1'=>$node['dns1'],'dns2'=>$node['dns2']],['ip'=>$private_ip->ip,'netmask'=>$private_ip->netmask,'gateway'=>'','mac'=>$private_ip->mac,'otherip'=>'']];
            $vmconfig['vm_name'] = $host->host_name;
            $vmconfig['network'] = $network;

            if($node['virtual_type']=='hyper-v'){
                $vm = new HyperV();
            }else{ //kvm
                $vm = new Kvm();
            }

            $vm->resetNetwork($node->toArray(),$vmconfig);

        } catch (Exception $e) {
            throw new  Exception('line:'.$e->getLine()."msg:".$e->getMessage());
        }
    }

    public  function vnc($hostid){
        $host_vps_model =  new \app\common\model\HostVps();
        $node_model = new \app\common\model\ServersNode();
        $host = $host_vps_model ->where(['id'=>$hostid])->find();
        if (!$host){
            return [];
        }
        $node = $node_model->where(['id'=>$host->node_id])->find();
        if($node['virtual_type']=='hyper-v'){
            $vm = new HyperV();
        }else{ //kvm
            $vm = new Kvm();
        }
        $data = $vm->getVncInfo($node->toArray(),['host_name'=>$host->host_name]);
        if ($data['code']!=200){
            throw new Exception($data['msg']);
        }
        $data= $data['data'];
        $data['vncServer'] = $node['vnc_url'];
        $data['vncServerPort'] = "6080";
        $data['vncToken'] = $host['host_name'];
        $data['host'] = $host->toArray();
        $model = (new \app\common\model\ServersImageConfig());
        $imageInfo = $model->where(['os_name'=>$host->os_name])->find();
        if ($imageInfo['os_type']==1){
            $data['remoteUser']="administrator";
        }else{
            $data['remoteUser']="root";
        }
        return $data;
    }

}