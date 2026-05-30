# QZ Mock Node

This mock server emulates a KVM controlled node for local qzsystem/MMUI testing.
It is intentionally lightweight and uses only Python standard library modules.

## Start

```powershell
powershell -ExecutionPolicy Bypass -File .\MMUI-V2X\scripts\start-qz-mock-node.ps1 -Restart
```

Default endpoint:

- URL: `http://127.0.0.1:18080`
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

The current qzsystem KVM adapter calls node APIs synchronously through Guzzle.
So this mock intentionally blocks before responding:

- start, shutdown, poweroff, restart: about `1s`
- password, boot order: about `1s`
- reinstall: about `5s`
- snapshot/backup/task operations: about `1s`

Monitoring data is generated as a stable time series with small random jitter:

- CPU: realistic percent range
- memory: realistic percent range
- network: 21 sequential one-minute samples
- flow counters: monotonic rx/tx totals

## Supported Route Families

The server supports both route families:

- qzkvm-style routes such as `/api/v1/reinstall_kvm`, `/api/v1/monitor_kvm`, `/api/v1/get_status_kvm`.
- Current qzsystem `Kvm.php` vhost routes such as `/api/v1/vhost/start`, `/api/v1/vhost/createTask`, `/api/v1/vhost/realMonitorVirtual`.
- A minimal noVNC/RFB WebSocket on port `6080`; it returns a 1280x720 mock screen. By default it reads `%USERPROFILE%\Desktop\webwxgetmsgimg.gif` and scales the low-resolution GIF up to the VNC framebuffer.

All protected routes require the `apikey` header unless the server is started with an empty API key.
