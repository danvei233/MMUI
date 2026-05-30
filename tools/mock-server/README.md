# QZ Mock Node

This folder contains a local mock controlled node for qzsystem/MMUI development.
It is a development helper only and is not included in release override packages.

## Start

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\mock-server\start-qz-mock-node.ps1 -Restart
```

Default endpoint:

- API URL: `http://127.0.0.1:18080`
- VNC WebSocket: `ws://127.0.0.1:6080/websockify`
- API key: `mmui-mock-key`

The qzsystem demo node should point to:

```sql
UPDATE cloud_servers_node
SET node_ip = '127.0.0.1:18080',
    forward_url = '127.0.0.1:18080',
    vnc_url = '127.0.0.1',
    apikey = 'mmui-mock-key'
WHERE id = 9001;
```

`vnc_url` must not include the mock API port because qzsystem's KVM VNC template appends `:6080` itself.

## Behavior

- start, shutdown, poweroff, restart: about `1s`
- password, boot order: about `1s`
- reinstall: about `5s`
- snapshot/backup/task operations: about `1s`
- monitoring data is generated as a stable time series with small random jitter

Supported route families:

- qzkvm-style routes such as `/api/v1/reinstall_kvm`, `/api/v1/monitor_kvm`, `/api/v1/get_status_kvm`
- current qzsystem `Kvm.php` vhost routes such as `/api/v1/vhost/start`, `/api/v1/vhost/createTask`, `/api/v1/vhost/realMonitorVirtual`
- minimal noVNC/RFB WebSocket on port `6080`
