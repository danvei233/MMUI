<?php
namespace qzcloud;

use GuzzleHttp\Client;
use think\App;
use think\Log;
use think\Request;
use think\Response;

set_time_limit(0);
class Kvm{
    public $apikey ="";
    public $node_url="";
    public $port ='8000';

    public $forward_url='';
    public $forward_port='8000';

    //创建一个云主机

    /**
     * @param array $lineInfo 产品信息 线路信息
     * @param array $nodeInfo 节点信息 自生产有值
     * @param array $param
     * @return array
     */
    public function openHost($lineInfo=[],$nodeInfo=[],$param=[],$network=[],$taskid=null ){
        //        ip gateway netmask dns1 dns2 band_width mac switch_name switch_name
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;

        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['password'] = $param['os_password'];
        $post_data['template_path'] = $nodeInfo['template_path'];
        $post_data['data_path'] = $nodeInfo['data_path'];
        $post_data['os_path'] = $nodeInfo['os_path'];
        $post_data['cpu'] = $param['cpu'];
        //$post_data['cpu_model'] = $nodeInfo['cpu_model'];
        //$post_data['cpu_model'] = $param['cpu_model']; 默认host
        $post_data['cpu_limit'] =$param['cpu_limit'];
        $post_data['memory'] = $param['memory']*1024;
        $post_data['os_type'] = $param['os_type'];
        $post_data['os_name'] = $param['file_name'];
        $post_data['host_data'] = (int)$param['hard_disks'];

        $post_data['os_iops'] = (int)$nodeInfo['os_iops_max'];
        $post_data['os_read'] = (int)$param['os_read'];
        $post_data['os_write'] = (int)$param['os_write'];

        $post_data['switch_name1'] =$param['is_nat']==1?(isset($nodeInfo['switch3'])&&!empty($nodeInfo['switch3'])?$nodeInfo['switch3']:"vmbr2"):(isset($nodeInfo['switch1'])&&!empty($nodeInfo['switch1'])?$nodeInfo['switch1']:"vmbr0");
        $post_data['switch_name2'] = isset($nodeInfo['switch2'])&&!empty($nodeInfo['switch2'])?$nodeInfo['switch2']:"vmbr1";

        $post_data['data_iops'] = (int)$nodeInfo['data_iops_max'];
        $post_data['data_read'] = (int)$param['data_read'];
        $post_data['data_write'] = (int)$param['data_write'];

        $post_data['open_gpu'] =isset($param['open_gpu'])?$param['open_gpu']:0;
        $post_data['mdevid'] =0;
        if ($post_data['open_gpu']==1) {
            $post_data['mdevid'] = isset($nodeInfo['mdevid'])?$nodeInfo['mdevid']:0;
        }
        if ($post_data['open_gpu']!=0&&$post_data['mdevid']==0){
            return ['code'=>0,'msg'=>"显卡云，显卡序号为空"];
        }
        $post_data['vir_extensions'] = isset($param['metal'])&&$param['metal']==2?1:0;

        //网络
       // $param['network'] 二维数组 0为公网主ip 1为私网ip   ip gateway netmask dns1 dns2 band_width mac switch_name switch_name
        $post_data['ip'] =$network['eth1']['ip'];
        $post_data['gateway'] =$network['eth1']['gateway'];
        $post_data['netmask'] =$network['eth1']['netmask'];
        $post_data['dns1'] =$nodeInfo['dns1'];
        $post_data['dns2'] =$nodeInfo['dns2'];
        $post_data['mac'] =$network['eth1']['mac'];
        $post_data['bandwidth_in'] = !empty($param['bandwidth_in'])?(int)$param['bandwidth_in']:100;
        $post_data['bandwidth_out'] = (int)$param['bandwidth'];

        $post_data['ip1'] =$network['eth2']['ip'];
        $post_data['gateway1'] ='';
        $post_data['netmask1'] =$network['eth2']['netmask'];
        $post_data['mac1'] =$network['eth2']['mac'];
        $post_data['otherip'] = $network['otherip'];

        if(isset($param['re_create'])){
            $post_data['re_create'] = (int)$param['re_create'];
        }

        $post_data['is_vnc'] = isset($param['is_vnc'])?(int)$param['is_vnc']:(isset($nodeInfo['is_vnc'])?(int)$nodeInfo['is_vnc']:1);

       /* foreach ($post_data as $k=>$v){
            if(is_int($v)||is_float($v)){
                $post_data[$k]=(string)$v;
            }
        }*/

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'create_vps','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //更新一个云主机
    public function updateHost(array $nodeInfo=[],array $param=[],$taskid=0){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['data_path'] = $nodeInfo['data_path'];
        $post_data['cpu'] = (int)$param['cpu'];
        $post_data['cpu_limit'] =(int)$param['cpu_limit'];
       // $post_data['cpu_model'] = $nodeInfo['cpu_model'];
        $post_data['memory'] = $param['memory']*1024;
        $post_data['host_data'] = (int)$param['hard_disks'];
        $post_data['os_read'] = (int)$param['os_read'];
        $post_data['os_write'] = (int)$param['os_write'];
        $post_data['data_read'] = (int)$param['data_read'];
        $post_data['data_write'] = (int)$param['data_write'];

        $post_data['os_iops'] =isset($param['os_disk_maxiops'])?(int)($param['os_disk_maxiops']):(int)$nodeInfo['os_iops_max'];
        $post_data['data_iops'] = isset($param['data_disk_maxiops'])?(int)($param['data_disk_maxiops']):(int)$nodeInfo['data_iops_max'];

        //网络
        $post_data['bandwidth_in'] = !empty($param['bandwidth_in'])?(int)$param['bandwidth_in']:100;
        $post_data['bandwidth_out'] = (int)$param['bandwidth'];

        //多余ip
        if(isset($param['add_ip'])&&strlen($param['add_ip'])>6){
            $post_data['otherip'] = $param['ip'].','.$param['add_ip'];
        }
        if(isset($param['is_vnc'])){
            $post_data['is_vnc'] = (int)$param['is_vnc'];
        }
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'update_vps','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }

    }

    //删除一个云主机
    public function deleteHost(array $nodeInfo=[],array $param=[],$taskid){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['backup_path'] = $nodeInfo['backup_path'];
        $post_data['data_path'] =  $nodeInfo['data_path'];
        $post_data['os_path'] = $nodeInfo['os_path'];
        if(isset($nodeInfo['del_type'])&&$nodeInfo['del_type'] ==1){
            $post_data['move'] = 1;
        }
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'remove_vps','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }

    }

    //重新安装操作系统
    public function reinstallHost(array $nodeInfo=[],array $param=[],$network=[],$taskid){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['password'] = $param['os_password'];
        $post_data['os_name'] = $param['file_name'];

        $post_data['template_path'] = $nodeInfo['template_path'];
        $post_data['data_path'] = $nodeInfo['data_path'];
        $post_data['os_path'] = $nodeInfo['os_path'];
        $post_data['os_type'] = $param['os_type'];

        $post_data['ip'] =$network['eth1']['ip'];
        $post_data['gateway'] =$network['eth1']['gateway'];
        $post_data['netmask'] =$network['eth1']['netmask'];
        $post_data['dns1'] =$nodeInfo['dns1'];
        $post_data['dns2'] =$nodeInfo['dns2'];
        $post_data['mac'] =$network['eth1']['mac'];

        $post_data['ip1'] =$network['eth2']['ip'];
        $post_data['gateway1'] ='';
        $post_data['netmask1'] =$network['eth2']['netmask'];
        $post_data['mac1'] =$network['eth2']['mac'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'reinstall','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //批量设置ip
    public function batsetip(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['vm_name'];
        $post_data['ip'] = implode(',',$param['ip']);
        // $post_data['otherip'] = $param['add_ip'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/updateIpsetVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //云主机监控
    public function monitorHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['network_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/realMonitorVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 5
            ]);
            // return new { code = 200, data = new { StorageStats = 0, NetworkStats = NetworkStats, CpuStats = CpuStats, MemoryStats = MemoryStats, Traffic = new { TrafficOut = trafficOut, TrafficIn = trafficIn } } };
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    $netflow =($result['data']['netflow']);
                    $NetworkStats=[];
                    foreach ($netflow as $k=>$v){
                       $up =ceil($v[2]/1024);
                       $down =ceil($v[1]/1024);
                       $time=date('Y-m-d H:i',$v[0]);
                       $NetworkStats[$v[0]]=[$time,$up,$down];

                    }
                    ksort($NetworkStats);

                    $net =[];
                    foreach ($NetworkStats as $k=>$v){
                        array_push($net,$v);
                    }

                    $returndata =[
                        'CpuStats'=>$result['data']['cpu'],
                        'MemoryStats'=>$result['data']['mem'],
                        'NetworkStats'=>$net,
                        //'IO'=>isset($result['data']['io'])?$result['data']['io']:[]
                    ];
                    return ['code'=>200,'msg'=>'success','data'=>$returndata];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //云主机运行图片
    public function thumbnailHost(array $nodeInfo=[],array $param=[]){
      return "";
    }

    //云主机开机
    public function startHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/start",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //关机
    public function closeHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/shutdown",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //断开电源
    public function powerHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/powerOff",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //重启
    public function restartHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;

        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/restart",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //获取云主机状态
    public function getStateHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/getStateVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    if(strtolower($result['data']['status'])=="running"){
                        $state ='Running';
                    }else{
                        $state ='Stop';
                    }
                    return ['code'=>200,'msg'=>'success','data'=>['state'=>$state]];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //获取服务器目录iso文件
    public function getisoHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$nodeInfo['node_ip'];
        $post_data = [];
        $post_data['iso_path'] = $nodeInfo['iso_path'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/get_isolist_kvm",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //挂载或者卸载iso文件
    public function isoHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$nodeInfo['node_ip'];
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['iso_path'] = $param['iso']==''?'':$nodeInfo['iso_path'].'/'.$param['iso'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/update_iso_kvm",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //设置Bios
    public function setBiosHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$nodeInfo['node_ip'];
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['boot'] = strtoupper($param['bios'])=='CD'?'iso':'idc';
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/boot_order",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //创建快照
    public function createSnapshotHost(array $nodeInfo=[],array $param=[],$taskid=0){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'create_snapshot','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //还原快照
    public function restoreSnapshotHost(array $nodeInfo=[],array $param=[],$taskid){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];


        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'restore_snapshot','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除快照
    public function removeSnapshotHost(array $nodeInfo=[],array $param=[],$taskid=0){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];


        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'remove_snapshot','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //创建备份
    public function createBackupHost(array $nodeInfo=[],array $param=[],$taskid=0){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];
        $post_data['backup_path'] = $nodeInfo['backup_path'];


        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'create_backup','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //还原备份
    public function restoreBackupHost(array $nodeInfo=[],array $param=[],$taskid=0){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];
        $post_data['backup_path'] = $nodeInfo['backup_path'];


        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'restore_backup','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除备份
    public function removeBackupHost(array $nodeInfo=[],array $param=[],$taskid=0){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];
        $post_data['backup_path'] = $nodeInfo['backup_path'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/createTask",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => ['data'=>json_encode($post_data),'action'=>'remove_backup','callback_param'=>(string)$taskid,'state'=>1,'callback_api'=>\think\facade\Request::domain()],
                'timeout' => 1200
            ]);

            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'创建命令已发送'];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //添加策略
    function addFirewallHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];
        $post_data['action'] = $param['method'];
        $post_data['type'] =  trim($param['direction']);
        if($param['direction']=="in"){
            $post_data['source'] = $param['start_ip'];
            $post_data['dport'] = $param['start_port'];
        }

        if($param['direction']=="out"){
            $post_data['dest'] = $param['start_ip'];
            $post_data['sport'] = $param['start_port'];
        }
        //$post_data['source'] = $param['start_ip'];
       //$post_data['dest'] = $param['start_ip'];
       // $post_data['dport'] = $param['start_port'];

        $post_data['enable'] = 1;
        $post_data['proto'] = $param['protocol']=="ANY"?"":$param['protocol'];
        //$post_data['pos'] = (int)$param['priority'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/addNWVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }
    //删除策略
    function removeFirewallHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        $post_data['name'] = $param['name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/removeNWVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除策略
    function groupFirewallApply(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$nodeInfo['node_ip'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/add_group_firewall_kvm",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $param,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //修改系统密码
    public function updatePasswordHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['passwd'] = $param['password'];
        $post_data['host_name'] = $param['host_name'];
        $post_data['username'] = $param['os_type']=='windows'?'administrator':'root';
        $post_data['os_type'] = $param['os_type'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/updatePawsswdVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //月初清除流量
    public function clearNetworkFlowHost(array $nodeInfo=[],array $param=[]){
        return ;
    }

    //月流量
    public function CountNetworkFlowHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }


        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/count_flow_kvm",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 5
            ]);
            // return new { code = 200, data = new { StorageStats = 0, NetworkStats = NetworkStats, CpuStats = CpuStats, MemoryStats = MemoryStats, Traffic = new { TrafficOut = trafficOut, TrafficIn = trafficIn } } };
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>''];
                }else{
                    $returndata =[
                        'TrafficIn'=>round($result['data']['rx']/1024,2),
                        'TrafficOut'=>round($result['data']['tx']/1024,2),
                    ];
                    return ['code'=>200,'msg'=>'success','data'=>['Traffic'=>$returndata]];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    public function GetVMFlow(array $nodeInfo=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
             return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data["datatye"] ="month";
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/flowVirtualNet",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 5
            ]);
            // return new { code = 200, data = new { StorageStats = 0, NetworkStats = NetworkStats, CpuStats = CpuStats, MemoryStats = MemoryStats, Traffic = new { TrafficOut = trafficOut, TrafficIn = trafficIn } } };
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>''];
                }else{
                    $data = $result['data'];
                    return ['code'=>200,'msg'=>'success','data'=>$data];
                }
            }
        }catch (\GuzzleHttp\Exception\RequestException $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
        return ['code'=>200,'msg'=>'success','data'=>['Traffic'=>[]]];
    }

    //断网或者恢复网络 state 1打开2关闭
    public function setNetworkStateHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        $post_data['host_name'] = $param['host_name'];

        try {
            $action ="/api/v1/vhost/closeNetwork";
            if ($param['state']==1){
                $action ="/api/v1/vhost/openNetwork";
            }
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port.$action,[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }
    //批量删除域名
    public function batDeleteDomainHost(array $nodeInfo=[],array $param=[]){
        return ['code'=>200,'msg'=>'success','data'=>[]];
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $forward_url = $this->forward_url?$this->forward_url:$nodeInfo['forward_url'];
        $post_data = [];
        $post_data['domain'] = $param['domain'];
        $post_data['vm_name'] = $param['host_name'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$forward_url.':'.$this->forward_port."/api/Forward/delDomainBat",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //挂机宝端口映射
    //添加端口
    public function addForwardPort(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $forward_url = $this->forward_url?$this->forward_url:$node_ip;
        $post_data = [];
        $post_data['dport'] =  strval($param['dport']); //小鸡内网端口
        $post_data['sport'] = strval($param['sport']); //映射服务器公网端口
        $post_data['dip'] = $param['dip'];
       // $post_data['vm_name'] = $param['host_name'];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$forward_url.':'.$this->port."/api/v1/vhost/addPort",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除端口
    public function removeForwardPort(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $forward_url = $this->forward_url?$this->forward_url:$node_ip;
        $post_data = [];
        //$post_data['dport'] = $param['dport']; //小鸡内网端口
        $post_data['sport'] = $param['sport']; //映射服务器公网端口
        $post_data['dip'] = $param['dip'];
        //$post_data['vm_name'] = $param['host_name'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$forward_url.':'.$this->port."/api/v1/vhost/delPort",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //添加域名白名单
    public function addForwardDomain(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $forward_url = $this->forward_url?$this->forward_url:$node_ip;
        $post_data = [];
        $post_data['domain'] = $param['domain'];
        $post_data['dip'] = $param['dip'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$forward_url.':'.$this->port."/api/v1/vhost/addDomain",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //删除域名白名单
    public function removeForwardDomain(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $forward_url = $this->forward_url?$this->forward_url:$node_ip;
        $post_data = [];
        $post_data['domain'] = $param['domain'];
        $post_data['dip'] = $param['dip'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$forward_url.':'.$this->port."/api/v1/vhost/delDomain",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 10
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //批量删除端口
    public function batDeletePortHost(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $forward_url = $this->forward_url?$this->forward_url:$node_ip;
        $post_data = [];
        // $post_data['sport'] = $param['sport'];
        //$post_data['dport'] = $param['dport'];
        $dip = isset($param['dip']) ? $param['dip'] : (isset($param['ip']) ? $param['ip'] : '');
        if($dip===''){
            return ['code'=>0,'msg'=>'批量删除端口缺少目标IP'];
        }
        $post_data['dip'] = $dip;
        //$post_data['type_'] = $param['port_type'];
        //$post_data['vm_name'] =$param['host_name'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$forward_url.':'.$this->port."/api/v1/vhost/delPortBat",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //返回vnc可用信息
    public function guidHost(array $nodeInfo=[],array $param=[]){
        $domain = \think\facade\Request::domain();
        return [
            'code'=>200,
            'data'=>[
                'host'=>$nodeInfo['vnc_url']?$nodeInfo['vnc_url']:$nodeInfo['node_ip'],
                'host_name'=>$param['host_name'],
                'vnc_password'=>$param['vnc_password'],
                'url'=>$domain.'/vnc?hostid='.$param['id'],
            ],

        ];
    }

    public function resetNetwork(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }


        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;


        $post_data['host_name'] = $param['vm_name'];
        $net1 = $param['network'][0];
        $post_data['ip'] =$net1['ip'];
        $post_data['gateway'] =$net1['gateway'];
        $post_data['netmask'] =$net1['netmask'];
        $post_data['dns1'] =$net1['dns1'];
        $post_data['dns2'] =$net1['dns2'];
        $post_data['mac'] =$net1['mac'];

        $net2 = $param['network'][1];
        $post_data['ip1'] =$net2['ip'];
        $post_data['gateway1'] ='';
        $post_data['netmask1'] =$net2['netmask'];
        $post_data['mac1'] =$net2['mac'];

        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/resetIPVirtual",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    //监控母鸡信息
    public function monitorCompany(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }
        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = [];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/getCompanyInfo",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

    public function getVncInfo(array $nodeInfo=[],array $param=[]){
        if($this->apikey==""&&$nodeInfo['apikey']==""){
            return ['code'=>0,'msg'=>'通讯密钥错误'];
        }
        if($this->node_url==""&&$nodeInfo['node_ip']==""){
            return ['code'=>0,'msg'=>'节点通讯地址为空'];
        }

        $node_ip = $nodeInfo['node_ip'];
        $node_ip_arr = explode(":",$nodeInfo['node_ip']);
        if (count($node_ip_arr)>1){
            $this->port = $node_ip_arr[1];
            $node_ip = $node_ip_arr[0];
        }

        $apikey = $this->apikey?$this->apikey:$nodeInfo['apikey'];
        $node_url = $this->node_url?$this->node_url:$node_ip;
        $post_data = ['host_name'=>$param['host_name']];
        try {
            $Client = new Client();
            $res = $Client->post('http://'.$node_url.':'.$this->port."/api/v1/vhost/getVncPasswd",[
                'headers' => ['Content-Type' => 'application/json','apikey'=>$apikey],
                'http_errors' => false,
                'json'    => $post_data,
                'timeout' => 20
            ]);
            if($res->getStatusCode()!=200){
                return ['code'=>0,'msg'=>$res->getBody()->getContents()];
            }else{
                $body = $res->getBody();
                $result = \GuzzleHttp\json_decode($body,true);
                if($result['code']!=200){
                    return ['code'=>0,'msg'=>$result['msg']];
                }else{
                    return ['code'=>200,'msg'=>'success','data'=>$result['data']];
                }
            }
        }catch (\Exception $e){
            return ['code'=>0,'msg'=>$e->getMessage()];
        }
    }

}
?>









