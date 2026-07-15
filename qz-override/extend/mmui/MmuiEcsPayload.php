<?php
namespace mmui;

use app\common\model\BackupVps;
use app\common\model\FirewallVps;
use app\common\model\ForwardDomainVps;
use app\common\model\ForwardPortVps;
use app\common\model\SnapshotVps;
use app\common\util\BaseConst;

class MmuiEcsPayload
{
    private $request;
    private $rootUrl;

    public function __construct($request, $rootUrl)
    {
        $this->request = $request;
        $this->rootUrl = $rootUrl;
    }

    public function filter($payload)
    {
        $fieldText = trim((string)$this->request->param('mmui_vue_fields', ''));
        if ($fieldText !== '') {
            return $this->pickPayloadFields($payload, explode(',', $fieldText));
        }

        $scope = strtolower(trim((string)$this->request->param('mmui_vue_scope', 'full')));
        $scopeMap = [
            'summary' => ['host', 'quotas', 'quickLinks', 'portableActions', 'network', 'pages.network', 'pages.vnc', 'actions'],
            'system' => ['host', 'pages.power', 'pages.reinstall', 'pages.iso', 'actions.power', 'actions.reinstall', 'actions.iso', 'actions.password'],
            'remote' => ['host', 'pages.vnc', 'actions.vnc'],
            'vnc' => ['host', 'pages.vnc', 'actions.vnc'],
            'network' => ['host', 'network', 'pages.network'],
            'monitor' => ['host', 'actions.monitor', 'actions.thumbnail', 'actions.state'],
            'port' => ['quotas.portMapping', 'quickLinks', 'pages.port', 'actions.port'],
            'snapshot' => ['quotas.snapshot', 'quickLinks', 'pages.snapshot', 'actions.snapshot'],
            'backup' => ['quotas.backup', 'quickLinks', 'pages.backup', 'actions.backup'],
            'strategy' => ['quotas.firewall', 'quickLinks', 'pages.strategy', 'actions.firewall'],
            'firewall' => ['quotas.firewall', 'quickLinks', 'pages.strategy', 'actions.firewall'],
            'site' => ['quotas.domain', 'pages.site', 'actions.domain'],
            'domain' => ['quotas.domain', 'pages.site', 'actions.domain'],
        ];

        if (!isset($scopeMap[$scope])) {
            unset($payload['monitors']);
            return $payload;
        }

        $result = $this->pickPayloadFields($payload, $scopeMap[$scope]);
        $result['scope'] = $scope;
        return $result;
    }

    public function build(array $data)
    {
        $host = $this->modelToArray($data['host'] ?? []);
        $line = $this->modelToArray($data['line_info'] ?? []);
        $area = $this->modelToArray($data['area'] ?? []);
        $imageInfo = $this->modelToArray($data['image_info'] ?? []);
        $networkFlow = $this->modelToArray($data['network_flow'] ?? []);
        $network = $data['network'] ?? [];
        $hostid = $host['id'] ?? ($data['hostid'] ?? 0);
        $rootUrl = $this->rootUrl;
        $primaryIp = $this->firstNetworkIp($network, 'eth1');
        $privateIps = $this->listToArray($network['eth2'] ?? []);
        $publicRows = $this->listToArray($network['eth1'] ?? []);
        $portRows = $this->rowsByHost(new ForwardPortVps(), $hostid);
        $snapshotRows = $this->rowsByHost(new SnapshotVps(), $hostid);
        $backupRows = $this->rowsByHost(new BackupVps(), $hostid);
        $firewallRows = $this->rowsByHost(new FirewallVps(), $hostid);
        $domainRows = $this->rowsByHost(new ForwardDomainVps(), $hostid);
        $osType = BaseConst::OS_TYPE[$imageInfo['os_type'] ?? 0] ?? '';
        $state = (int)($host['state'] ?? 0);
        $status = BaseConst::HOST_STATE[$state] ?? '未知';
        $remoteIp = ((int)($host['is_nat'] ?? 0) === 1)
            ? ($primaryIp['public_ip'] ?? $primaryIp['ip'] ?? $host['ip'] ?? '')
            : ($primaryIp['ip'] ?? $host['ip'] ?? '');
        $remotePort = (int)($host['is_nat'] ?? 0) === 1 && !empty($data['port']) ? ':' . $data['port'] : '';
        $webConfig = config('web');
        $remotePlugins = $webConfig['mmui_remote_methods'] ?? [];
        if (!is_array($remotePlugins)) {
            $remotePlugins = [];
        }
        $portTotal = (int)($host['port_num'] ?? $line['port_num'] ?? 0);
        $snapshotTotal = (int)($host['snapshot_num'] ?? $line['snapshot_num'] ?? 0);
        $backupTotal = (int)($host['backup_num'] ?? $line['backup_num'] ?? 0);
        $domainTotal = (int)($host['domain_num'] ?? $line['domain_num'] ?? 0);
        $imageTypeMap = [
            1 => ['title' => 'Windows', 'glyph' => 'W', 'subtitle' => 'Microsoft'],
            2 => ['title' => 'CentOS', 'glyph' => 'C', 'subtitle' => 'Linux'],
            3 => ['title' => 'Ubuntu', 'glyph' => 'U', 'subtitle' => 'Linux'],
            4 => ['title' => 'Debian', 'glyph' => 'D', 'subtitle' => 'Linux'],
        ];
        $reinstallCards = [];
        foreach (($data['image_list'] ?? []) as $type => $images) {
            $options = [];
            $optionIds = [];
            foreach ($images as $imageRow) {
                $imageRow = $this->modelToArray($imageRow);
                if (!empty($imageRow['os_name'])) {
                    $options[] = $imageRow['os_name'];
                    $optionIds[$imageRow['os_name']] = $imageRow['id'] ?? $imageRow['config_id'] ?? $imageRow['template_id'] ?? $imageRow['os_name'];
                }
            }

            if (!$options) {
                continue;
            }

            $meta = $imageTypeMap[(int)$type] ?? ['title' => 'Other', 'glyph' => 'O', 'subtitle' => 'Image'];
            $reinstallCards[] = array_merge($meta, [
                'description' => '选择该系统族中的镜像并设置新密码后开始重装。',
                'options' => $options,
                'optionIds' => $optionIds,
            ]);
        }
        $networkTables = [
            [
                'title' => '分配 IP 列表',
                'columns' => ['primary', 'secondary', 'mask', 'gateway'],
                'rows' => array_map(function ($row, $index) use ($host) {
                    $publicIp = (int)($host['is_nat'] ?? 0) === 1 ? ($row['public_ip'] ?? '') : '';
                    return [
                        'primary' => $publicIp ? '外部 IP：' . $publicIp : 'IP：' . ($row['ip'] ?? ''),
                        'secondary' => ((int)($host['is_nat'] ?? 0) === 1 ? 'NAT IP：' : '附加信息：') . ($row['ip'] ?? '') . ($index === 0 ? ' (主 IP)' : ''),
                        'mask' => '子网掩码：' . ($row['netmask'] ?? ''),
                        'gateway' => '网关：' . ($row['gateway'] ?? ''),
                    ];
                }, $publicRows, array_keys($publicRows)),
            ],
            [
                'title' => '线路信息',
                'columns' => ['primary', 'secondary'],
                'rows' => [
                    ['primary' => '区域：' . ($area['area_name'] ?? ''), 'secondary' => '线路：' . ($line['line_name'] ?? '')],
                    ['primary' => '带宽：' . ($host['bandwidth'] ?? 0) . ' Mbps', 'secondary' => '虚拟化：' . strtoupper($host['virtual_type'] ?? '')],
                ],
            ],
            [
                'title' => '流量使用情况',
                'columns' => ['primary', 'secondary'],
                'rows' => [
                    ['primary' => '上行流量：', 'secondary' => '月使用：' . ($networkFlow['network_out'] ?? 0) . ' MB'],
                    ['primary' => '下行流量：', 'secondary' => '月使用：' . ($networkFlow['network_in'] ?? 0) . ' MB'],
                ],
            ],
        ];

        return [
            'brand' => [
                'siteTitle' => $webConfig['mmui_site_title'] ?? ($webConfig['title'] ?? '轻舟云（QZSYSTEM）'),
                'loginLogo' => $webConfig['mmui_login_logo'] ?? '',
                'consoleTitle' => $webConfig['mmui_console_title'] ?? '云管理系统',
                'consoleLogo' => $webConfig['mmui_console_logo'] ?? '',
            ],
            'host' => [
                'id' => $hostid,
                'name' => $host['host_name'] ?? '',
                'status' => $status,
                'powerState' => $state === 2 ? 'running' : ($state === 3 ? 'stopped' : 'pending'),
                'state' => $state,
                'areaName' => $area['area_name'] ?? ($host['area_name'] ?? ''),
                'lineName' => $line['line_name'] ?? ($host['line_name'] ?? ''),
                'osName' => $host['os_name'] ?? ($imageInfo['os_name'] ?? ''),
                'osType' => $osType,
                'virtualType' => strtoupper($host['virtual_type'] ?? ''),
                'cpu' => (int)($host['cpu'] ?? 0),
                'memory' => (float)($host['memory'] ?? 0),
                'bandwidth' => (int)($host['bandwidth'] ?? 0),
                'traffic' => (string)($host['traffic'] ?? ''),
                'disk' => (int)($host['hard_disks'] ?? 0),
                'buyDate' => $this->formatDateOnly($host['buy_time'] ?? ''),
                'expireDate' => $this->formatDateOnly($host['end_time'] ?? ''),
                'panelPassword' => $host['panel_password'] ?? '',
                'systemPassword' => $host['os_password'] ?? '',
                'remoteAddress' => $remoteIp . $remotePort,
                'primaryIp' => $host['ip'] ?? '',
                'isNat' => (int)($host['is_nat'] ?? 0) === 1,
                'bios' => $host['bios'] ?? 'IDE',
                'nowIso' => $host['now_iso'] ?? '',
            ],
            'network' => [
                'public' => $publicRows,
                'private' => $privateIps,
                'primary' => $primaryIp,
                'remoteIp' => $remoteIp,
                'remotePort' => $data['port'] ?? '',
                'flow' => [
                    'in' => $networkFlow['network_in'] ?? 0,
                    'out' => $networkFlow['network_out'] ?? 0,
                    'month' => $networkFlow['month'] ?? '',
                ],
            ],
            'quotas' => [
                'portMapping' => ['used' => count($portRows), 'total' => $portTotal],
                'snapshot' => ['used' => count($snapshotRows), 'total' => $snapshotTotal],
                'backup' => ['used' => count($backupRows), 'total' => $backupTotal],
                'firewall' => ['used' => count($firewallRows), 'total' => max(count($firewallRows), 20)],
                'domain' => ['used' => count($domainRows), 'total' => $domainTotal],
            ],
            'monitors' => [
                'cpu' => [],
                'network' => [],
                'memory' => [],
                'labels' => [],
            ],
            'quickLinks' => [
                ['key' => 'port', 'title' => '端口映射', 'subtitle' => '数目：' . count($portRows) . '/' . $portTotal, 'icon' => 'BranchesOutlined', 'action' => '查看详情'],
                ['key' => 'snapshot', 'title' => '快照', 'subtitle' => '数目：' . count($snapshotRows) . '/' . $snapshotTotal, 'icon' => 'CameraOutlined', 'action' => '查看详情'],
                ['key' => 'backup', 'title' => '备份', 'subtitle' => '数目：' . count($backupRows) . '/' . $backupTotal, 'icon' => 'CopyOutlined', 'action' => '查看详情'],
                ['key' => 'firewall', 'title' => '安全策略', 'subtitle' => '数目：' . count($firewallRows), 'icon' => 'SafetyCertificateOutlined', 'action' => '查看详情'],
            ],
            'portableActions' => [
                ['key' => 'vnc', 'label' => 'VNC', 'icon' => 'DesktopOutlined'],
                ['key' => 'rescue', 'label' => '快速救援', 'icon' => 'ToolOutlined'],
                ['key' => 'download', 'label' => '一键远程', 'icon' => 'ArrowRightOutlined'],
                ['key' => 'share', 'label' => '一键分享', 'icon' => 'ShareAltOutlined'],
            ],
            'pages' => [
                'power' => ['cards' => []],
                'reinstall' => [
                    'quota' => (int)($host['reinstall_num'] ?? 0) . '/' . (int)($host['max_reinstall_num'] ?? 0),
                    'cards' => $reinstallCards,
                ],
                'snapshot' => ['rows' => $snapshotRows, 'total' => $snapshotTotal, 'quotaLabel' => '创建快照数：' . count($snapshotRows) . '/' . $snapshotTotal],
                'backup' => ['rows' => $backupRows, 'total' => $backupTotal, 'quotaLabel' => '创建备份数：' . count($backupRows) . '/' . $backupTotal],
                'strategy' => ['rows' => $firewallRows, 'count' => count($firewallRows)],
                'port' => ['rows' => $portRows, 'total' => $portTotal, 'quotaLabel' => '创建端口数：' . count($portRows) . '/' . $portTotal],
                'site' => ['rows' => $domainRows, 'used' => count($domainRows), 'total' => $domainTotal],
                'iso' => [
                    'isoOptions' => array_values($data['iso_list'] ?? []),
                    'bootOptions' => ['IDE', 'CD'],
                    'currentBoot' => $host['bios'] ?? 'IDE',
                    'currentIso' => $host['now_iso'] ?? '',
                ],
                'network' => ['trafficLimit' => (string)($host['traffic'] ?? ''), 'tables' => $networkTables],
                'vnc' => [
                    'infoNote' => '轻舟默认提供本地 RDP 与 Web VNC 两种远程方式；插件可通过公开配置追加第三方远程方式。',
                    'links' => ['rdp' => '', 'rdp_file' => '', 'vnc' => ''],
                    'primaryMethods' => [
                        [
                            'key' => 'rdp',
                            'name' => '本地 RDP',
                            'icon' => 'rdp',
                            'description' => '使用系统自带远程桌面客户端连接远程地址，连接时填入系统用户和系统密码。',
                            'actionLabel' => '复制地址',
                            'secondaryActionLabel' => '复制密码',
                            'primary' => true,
                            'enabled' => $remoteIp !== '',
                            'stateText' => '默认',
                        ],
                        [
                            'key' => 'vnc',
                            'name' => 'Web VNC',
                            'icon' => 'vnc',
                            'description' => '打开轻舟 Web VNC 控制台，适用于无法通过系统远程桌面登录时的救援和安装场景。',
                            'actionLabel' => '打开 VNC',
                            'primary' => false,
                            'enabled' => true,
                            'stateText' => '控制台',
                        ],
                    ],
                    'otherMethods' => array_values($remotePlugins),
                ],
            ],
            'actions' => [
                'json' => $this->request->url(true) . (strpos($this->request->url(true), '?') === false ? '?' : '&') . 'mmui_vue_json=1',
                'state' => $rootUrl . 'state_host?hostid=' . $hostid,
                'monitor' => $rootUrl . 'monitor_host?hostid=' . $hostid,
                'thumbnail' => $rootUrl . 'thumbnail_host?hostid=' . $hostid,
                'power' => [
                    'start' => $rootUrl . 'start_host',
                    'close' => $rootUrl . 'close_host',
                    'power' => $rootUrl . 'power_host',
                    'restart' => $rootUrl . 'restart_host',
                    'syncTime' => $rootUrl . 'synctime_host',
                ],
                'vnc' => $rootUrl . 'vnc_host',
                'reinstall' => $rootUrl . 'reinstall_host',
                'iso' => [
                    'list' => preg_replace('#ecs/$#', 'mmui/', $rootUrl) . 'iso_list_host?hostid=' . $hostid,
                    'mount' => $rootUrl . 'mountiso_host',
                    'unmount' => $rootUrl . 'unmountiso_host',
                    'bios' => $rootUrl . 'set_bios',
                ],
                'password' => [
                    'system' => $rootUrl . 'updata_systempass_host',
                    'panel' => $rootUrl . 'update_panel_password',
                ],
                'snapshot' => [
                    'list' => $rootUrl . 'snapshot?hostid=' . $hostid,
                    'create' => $rootUrl . 'create_snapshot_host',
                    'restore' => $rootUrl . 'restore_snapshot_host',
                    'remove' => $rootUrl . 'remove_snapshot_host',
                ],
                'backup' => [
                    'list' => $rootUrl . 'backup?hostid=' . $hostid,
                    'create' => $rootUrl . 'create_backup_host',
                    'restore' => $rootUrl . 'restore_backup_host',
                    'remove' => $rootUrl . 'remove_backup_host',
                ],
                'firewall' => [
                    'list' => $rootUrl . 'firewall_host?hostid=' . $hostid,
                    'add' => $rootUrl . 'add_firewall_host',
                    'remove' => $rootUrl . 'remove_firewall_host',
                ],
                'port' => [
                    'list' => $rootUrl . 'port_host?hostid=' . $hostid,
                    'add' => $rootUrl . 'add_port_host',
                    'remove' => $rootUrl . 'remove_port_host',
                    'find' => $rootUrl . 'findport?hostid=' . $hostid,
                ],
                'domain' => [
                    'list' => $rootUrl . 'domain_host?hostid=' . $hostid,
                    'add' => $rootUrl . 'add_domain_host',
                    'remove' => $rootUrl . 'remove_domain_host',
                ],
            ],
        ];
    }

    private function pickPayloadFields($payload, $fields)
    {
        if (!is_array($payload)) {
            return [];
        }

        $result = [];
        foreach ($fields as $field) {
            $field = trim((string)$field);
            if ($field === '') {
                continue;
            }

            $value = $this->getArrayPath($payload, $field);
            if ($value !== null) {
                $this->setArrayPath($result, $field, $value);
            }
        }

        if (!isset($result['actions']) || !is_array($result['actions'])) {
            $result['actions'] = [];
        }

        if (isset($payload['actions']['json'])) {
            $result['actions']['json'] = $payload['actions']['json'];
        }

        return $result;
    }

    private function getArrayPath($data, $path)
    {
        $current = $data;
        foreach (explode('.', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function setArrayPath(&$target, $path, $value)
    {
        if (!is_array($target)) {
            $target = [];
        }

        $segments = array_values(array_filter(explode('.', $path), function ($segment) {
            return $segment !== '';
        }));
        if (!$segments) {
            return;
        }

        $cursor =& $target;

        foreach ($segments as $index => $segment) {
            if (!is_array($cursor)) {
                $cursor = [];
            }

            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor =& $cursor[$segment];
        }
    }

    private function modelToArray($value)
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    private function listToArray($value)
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return is_array($value) ? array_values($value) : [];
    }

    private function firstNetworkIp($network, $key)
    {
        if (!isset($network[$key][0])) {
            return [];
        }

        return $this->modelToArray($network[$key][0]);
    }

    private function formatDateOnly($value)
    {
        if (empty($value)) {
            return '';
        }

        return substr((string)$value, 0, 10);
    }

    private function rowsByHost($model, $hostid)
    {
        $rows = $model->where(['host_id' => $hostid])->order('id desc')->select();
        return $this->listToArray($rows);
    }
}
