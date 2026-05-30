#!/usr/bin/env python3
"""
Local mock node for QZ KVM debugging.

It emulates the qzkvm JSON envelope and the vhost endpoints that the current
qzsystem/extend/qzcloud/Kvm.php actually calls.
"""

from __future__ import annotations

import argparse
import base64
import ctypes
import hashlib
import json
import random
import socket
import struct
import subprocess
import sys
import threading
import time
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

try:
    import pymysql  # type: ignore
except ImportError:  # pragma: no cover - optional local qzsystem completion helper.
    pymysql = None

try:
    from PIL import Image, ImageSequence  # type: ignore
except ImportError:  # pragma: no cover - optional VNC mock visual dependency.
    Image = None
    ImageSequence = None


SUCCESS_CODE = 200
ERROR_CODE = 0

POWER_DELAY_SECONDS = 1.0
REINSTALL_DELAY_SECONDS = 5.0
TASK_DELAY_SECONDS = 1.0
MOCK_VNC_WIDTH = 1280
MOCK_VNC_HEIGHT = 720
DEFAULT_VNC_GIF_PATH = Path.home() / "Desktop" / "webwxgetmsgimg.gif"
MOCK_VNC_MAX_GIF_FRAMES = 24
WEBSOCKET_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"

try:
    import psutil  # type: ignore
except ImportError:  # pragma: no cover - optional local telemetry dependency.
    psutil = None


ONE_PIXEL_PNG = base64.b64encode(
    base64.b64decode(
        "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII="
    )
).decode("ascii")


class LocalMachineMetrics:
    def __init__(self) -> None:
        self.cache: dict[str, Any] = {}
        self.cache_at = 0.0
        self.last_net: tuple[float, int, int] | None = None
        if psutil:
            psutil.cpu_percent(interval=None)

    def sample(self) -> dict[str, float]:
        now = time.time()
        if now - self.cache_at < 1.5 and self.cache:
            return self.cache

        cpu = self.read_cpu_percent()
        memory = self.read_memory_percent()
        rx_total, tx_total = self.read_network_totals()
        rx_rate = 0.0
        tx_rate = 0.0

        if self.last_net:
            last_at, last_rx, last_tx = self.last_net
            elapsed = max(0.1, now - last_at)
            rx_rate = max(0.0, (rx_total - last_rx) / elapsed)
            tx_rate = max(0.0, (tx_total - last_tx) / elapsed)

        self.last_net = (now, rx_total, tx_total)
        self.cache = {
            "cpu": clamp(cpu, 0, 100),
            "memory": clamp(memory, 0, 100),
            "rx_rate": rx_rate,
            "tx_rate": tx_rate,
        }
        self.cache_at = now
        return self.cache

    def read_cpu_percent(self) -> float:
        if psutil:
            value = psutil.cpu_percent(interval=None)
            return float(value)

        for command in (
            ["wmic", "cpu", "get", "loadpercentage", "/value"],
            [
                "powershell",
                "-NoProfile",
                "-Command",
                "(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average",
            ],
        ):
            try:
                output = subprocess.check_output(command, text=True, timeout=1.2, stderr=subprocess.DEVNULL)
                for line in output.splitlines():
                    numbers = [part for part in line.replace("=", " ").split() if part.replace(".", "", 1).isdigit()]
                    if numbers:
                        return float(numbers[-1])
            except Exception:
                continue

        return 0.0

    def read_memory_percent(self) -> float:
        if psutil:
            return float(psutil.virtual_memory().percent)

        if sys.platform.startswith("win"):
            class MemoryStatusEx(ctypes.Structure):
                _fields_ = [
                    ("dwLength", ctypes.c_ulong),
                    ("dwMemoryLoad", ctypes.c_ulong),
                    ("ullTotalPhys", ctypes.c_ulonglong),
                    ("ullAvailPhys", ctypes.c_ulonglong),
                    ("ullTotalPageFile", ctypes.c_ulonglong),
                    ("ullAvailPageFile", ctypes.c_ulonglong),
                    ("ullTotalVirtual", ctypes.c_ulonglong),
                    ("ullAvailVirtual", ctypes.c_ulonglong),
                    ("ullAvailExtendedVirtual", ctypes.c_ulonglong),
                ]

            status = MemoryStatusEx()
            status.dwLength = ctypes.sizeof(MemoryStatusEx)
            if ctypes.windll.kernel32.GlobalMemoryStatusEx(ctypes.byref(status)):
                return float(status.dwMemoryLoad)

        return 0.0

    def read_network_totals(self) -> tuple[int, int]:
        if psutil:
            counters = psutil.net_io_counters()
            return int(counters.bytes_recv), int(counters.bytes_sent)

        try:
            output = subprocess.check_output(["netstat", "-e"], text=True, timeout=1.2, stderr=subprocess.DEVNULL)
            for line in output.splitlines():
                if "Bytes" not in line and "字节" not in line:
                    continue

                values = []
                for part in line.replace(",", "").split():
                    if part.isdigit():
                        values.append(int(part))
                if len(values) >= 2:
                    return values[0], values[1]
        except Exception:
            pass

        return 0, 0


LOCAL_METRICS = LocalMachineMetrics()


class HostState:
    def __init__(self, name: str) -> None:
        now = time.time()
        seed = sum(ord(ch) for ch in name)
        self.name = name
        self.status = "running"
        self.password = "Sys-Mock-2026!"
        self.panel_password = "panel-Mock2026"
        self.vnc_password = f"vnc{seed % 1000000:06d}"[:12]
        self.iso_path = ""
        self.boot = "idc"
        self.network_enabled = True
        self.created_at = now
        self.updated_at = now
        self.cpu_base = 18 + (seed % 18)
        self.mem_base = 35 + (seed % 22)
        self.rx_total = 128 * 1024 * 1024 + seed * 8192
        self.tx_total = 64 * 1024 * 1024 + seed * 4096
        self.netflow_history: list[list[Any]] = []
        self.operation = ""
        self.operation_ends_at = 0.0

    def begin_operation(self, operation: str, seconds: float, final_status: str) -> None:
        self.operation = operation
        self.operation_ends_at = time.time() + seconds
        if operation in {"shutdown", "poweroff"}:
            self.status = "stopping"
        elif operation in {"start", "restart", "reinstall"}:
            self.status = "starting"
        self._final_status = final_status

    def refresh(self) -> None:
        if self.operation and time.time() >= self.operation_ends_at:
            self.status = getattr(self, "_final_status", "running")
            self.operation = ""
            self.operation_ends_at = 0.0
            self.updated_at = time.time()

    def status_for_qz(self) -> str:
        self.refresh()
        if self.status in {"running", "starting"}:
            return "running"
        return "shutoff"

    def monitor(self) -> dict[str, Any]:
        self.refresh()
        now = int(time.time())
        local = LOCAL_METRICS.sample()
        busy_boost = 30 if self.operation in {"restart", "reinstall", "start"} else 0
        cpu = clamp(local["cpu"] + busy_boost + random.uniform(-1.2, 1.2), 0, 99)
        mem = clamp(local["memory"] + (8 if self.operation == "reinstall" else 0), 0, 99)
        down = int(local["rx_rate"] * (0.25 if not self.network_enabled else 1))
        up = int(local["tx_rate"] * (0.25 if not self.network_enabled else 1))
        if down <= 0 and up <= 0:
            down = random.randint(2048, 16384)
            up = random.randint(1024, 8192)

        self.netflow_history.append([now, max(down, 0), max(up, 0)])
        self.netflow_history = self.netflow_history[-21:]
        while len(self.netflow_history) < 21:
            first_ts = self.netflow_history[0][0] if self.netflow_history else now
            self.netflow_history.insert(0, [first_ts - 8, 0, 0])

        netflow = [row[:] for row in self.netflow_history]
        self.rx_total += max(down, 0)
        self.tx_total += max(up, 0)
        return {
            "cpu": round(cpu, 2),
            "mem": round(mem, 2),
            "netflow": netflow,
            "io": {
                "read": random.randint(1024, 32_768),
                "write": random.randint(1024, 28_672),
            },
        }


def clamp(value: float, low: float, high: float) -> float:
    return max(low, min(high, value))


class MockState:
    def __init__(self, apikey: str, db_config: dict[str, Any] | None = None) -> None:
        self.apikey = apikey
        self.db_config = db_config or {}
        self.hosts: dict[str, HostState] = {
            "mmui-nat-vps": HostState("mmui-nat-vps"),
            "mmui-multiip-vps": HostState("mmui-multiip-vps"),
        }

    def host(self, data: dict[str, Any]) -> HostState:
        name = str(data.get("host_name") or data.get("network_name") or data.get("vm_name") or "mmui-nat-vps")
        if name not in self.hosts:
            self.hosts[name] = HostState(name)
        return self.hosts[name]

    def complete_qz_task(self, task_id: Any, action: str, code: int = SUCCESS_CODE, msg: str = "success") -> None:
        if not task_id or not self.db_config.get("database"):
            return

        if pymysql is None:
            print("[mock-node] pymysql not available; skip qz task completion", file=sys.stderr)
            return

        task_id_text = str(task_id)
        try:
            conn = pymysql.connect(
                host=self.db_config.get("host", "127.0.0.1"),
                port=int(self.db_config.get("port", 3306)),
                user=self.db_config.get("user", ""),
                password=self.db_config.get("password", ""),
                database=self.db_config.get("database", ""),
                charset=self.db_config.get("charset", "utf8mb4"),
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=False,
            )
        except Exception as exc:
            print(f"[mock-node] qz db connect failed for task {task_id_text}: {exc}", file=sys.stderr)
            return

        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT id, command, param, state FROM cloud_task WHERE id=%s AND is_delete=1",
                    (task_id_text,),
                )
                task = cur.fetchone()
                if not task:
                    print(f"[mock-node] qz task not found: {task_id_text}", file=sys.stderr)
                    conn.rollback()
                    return

                task_state = 2 if code == SUCCESS_CODE else 3
                cur.execute(
                    "UPDATE cloud_task SET state=%s,end_time=NOW(),remark=%s WHERE id=%s AND is_delete=1",
                    (task_state, msg, task_id_text),
                )

                host_id = parse_host_id_from_task_param(task.get("param"))
                if host_id:
                    if action in {"create_vps", "update_vps", "reinstall"}:
                        host_state = 2 if code == SUCCESS_CODE else (5 if action == "reinstall" else 11)
                        cur.execute(
                            "UPDATE cloud_host_vps SET state=%s,update_time=NOW() WHERE id=%s",
                            (host_state, host_id),
                        )
                    elif action == "remove_vps" and code == SUCCESS_CODE:
                        cur.execute("DELETE FROM cloud_host_vps WHERE id=%s", (host_id,))

                command = str(task.get("command") or action)
                resource_id = parse_host_id_from_task_param(task.get("param"))
                self.complete_snapshot_backup_task(cur, command, resource_id, code)

            conn.commit()
            print(f"[mock-node] completed qz task {task_id_text} action={action} code={code}", file=sys.stderr)
        except Exception as exc:
            conn.rollback()
            print(f"[mock-node] qz task completion failed for {task_id_text}: {exc}", file=sys.stderr)
        finally:
            conn.close()

    def complete_snapshot_backup_task(self, cur: Any, command: str, resource_id: str, code: int) -> None:
        if not resource_id:
            return

        resource_map = {
            "create_snapshot": ("cloud_snapshot_vps", "state"),
            "restore_snapshot": ("cloud_snapshot_vps", "state"),
            "remove_snapshot": ("cloud_snapshot_vps", "delete"),
            "create_backup": ("cloud_backup_vps", "state"),
            "restore_backup": ("cloud_backup_vps", "state"),
            "remove_backup": ("cloud_backup_vps", "delete"),
        }
        target = resource_map.get(command)
        if not target:
            return

        table, mode = target
        if mode == "delete":
            # QZ's callback controller removes snapshot/backup rows once the delete task returns.
            cur.execute(f"DELETE FROM {table} WHERE id=%s", (resource_id,))
            return

        if code == SUCCESS_CODE:
            state = 2
        elif command.startswith("create_"):
            state = 3
        else:
            # Restore failure falls back to the existing successful snapshot/backup row in QZ.
            state = 2

        cur.execute(f"UPDATE {table} SET state=%s,update_time=NOW() WHERE id=%s", (state, resource_id))


def ok(msg: str = "success", data: Any = None) -> dict[str, Any]:
    return {"code": SUCCESS_CODE, "msg": msg, "data": {} if data is None else data}


def fail(msg: str, data: Any = None) -> dict[str, Any]:
    return {"code": ERROR_CODE, "msg": msg, "data": "" if data is None else data}


def rgba_to_bgrx(raw: bytes) -> bytes:
    pixels = bytearray(len(raw))
    for index in range(0, len(raw), 4):
        pixels[index] = raw[index + 2]
        pixels[index + 1] = raw[index + 1]
        pixels[index + 2] = raw[index]
        pixels[index + 3] = 0
    return bytes(pixels)


def resize_gif_frame(frame: Any, width: int, height: int) -> Any:
    source_width, source_height = frame.size
    if source_width <= 0 or source_height <= 0:
        return frame.resize((width, height))

    scale = max(width / source_width, height / source_height)
    target_width = max(width, int(round(source_width * scale)))
    target_height = max(height, int(round(source_height * scale)))
    resampling = getattr(getattr(Image, "Resampling", Image), "NEAREST")
    resized = frame.resize((target_width, target_height), resampling)
    left = max(0, (target_width - width) // 2)
    top = max(0, (target_height - height) // 2)
    return resized.crop((left, top, left + width, top + height))


def load_vnc_gif_frames(path: Path, width: int, height: int, max_frames: int = MOCK_VNC_MAX_GIF_FRAMES) -> list[bytes]:
    if Image is None or ImageSequence is None:
        print("[mock-vnc] Pillow is not installed; using fallback framebuffer.", file=sys.stderr)
        return []
    if not path.exists():
        print(f"[mock-vnc] GIF not found: {path}; using fallback framebuffer.", file=sys.stderr)
        return []

    frames: list[bytes] = []
    try:
        with Image.open(path) as image:
            for index, frame in enumerate(ImageSequence.Iterator(image)):
                if index >= max_frames:
                    break

                rgba = frame.convert("RGBA")
                canvas = resize_gif_frame(rgba, width, height)
                frames.append(rgba_to_bgrx(canvas.tobytes()))
    except Exception as exc:
        print(f"[mock-vnc] Failed to load GIF {path}: {exc}; using fallback framebuffer.", file=sys.stderr)
        return []

    if frames:
        print(f"[mock-vnc] loaded {len(frames)} GIF frame(s) from {path} at {width}x{height}", flush=True)
    return frames


def build_mock_vnc_frame(width: int = MOCK_VNC_WIDTH, height: int = MOCK_VNC_HEIGHT) -> bytes:
    pixels = bytearray(width * height * 4)
    center_x = width // 2
    center_y = height // 2

    for y in range(height):
        for x in range(width):
            r, g, b = 8, 12, 20
            r += int(20 * y / max(height - 1, 1))
            g += int(26 * x / max(width - 1, 1))
            b += int(44 * (x + y) / max(width + height - 2, 1))

            if (x // 40 + y // 40) % 2 == 0:
                b = min(86, b + 8)

            if center_x - 260 <= x <= center_x + 260 and center_y - 120 <= y <= center_y + 120:
                r, g, b = 16, 24, 38
                if (
                    x in {center_x - 260, center_x + 260}
                    or y in {center_y - 120, center_y + 120}
                ):
                    r, g, b = 68, 97, 242
                if center_y - 40 <= y <= center_y + 40 and (x - center_x) % 34 < 18:
                    r, g, b = 46, 72, 184

            idx = (y * width + x) * 4
            pixels[idx:idx + 4] = bytes((b, g, r, 0))

    return bytes(pixels)


FALLBACK_MOCK_VNC_FRAME = build_mock_vnc_frame()


class MockVncFrameSource:
    def __init__(self, gif_path: str | Path = DEFAULT_VNC_GIF_PATH) -> None:
        self.width = MOCK_VNC_WIDTH
        self.height = MOCK_VNC_HEIGHT
        self.frames = load_vnc_gif_frames(Path(gif_path).expanduser(), self.width, self.height)
        if not self.frames:
            self.frames = [FALLBACK_MOCK_VNC_FRAME]
        self.started_at = time.monotonic()

    def current_frame(self) -> bytes:
        if len(self.frames) == 1:
            return self.frames[0]

        # Keep the VNC mock lively without pushing huge raw frames too often.
        index = int((time.monotonic() - self.started_at) * 6) % len(self.frames)
        return self.frames[index]


def websocket_accept_key(key: str) -> str:
    digest = hashlib.sha1((key + WEBSOCKET_GUID).encode("ascii")).digest()
    return base64.b64encode(digest).decode("ascii")


def recv_exact(conn: socket.socket, length: int) -> bytes:
    chunks = bytearray()
    while len(chunks) < length:
        chunk = conn.recv(length - len(chunks))
        if not chunk:
            raise ConnectionError("connection closed")
        chunks.extend(chunk)
    return bytes(chunks)


def recv_ws_frame(conn: socket.socket) -> tuple[int, bytes]:
    header = recv_exact(conn, 2)
    opcode = header[0] & 0x0F
    masked = bool(header[1] & 0x80)
    length = header[1] & 0x7F
    if length == 126:
        length = struct.unpack("!H", recv_exact(conn, 2))[0]
    elif length == 127:
        length = struct.unpack("!Q", recv_exact(conn, 8))[0]

    mask = recv_exact(conn, 4) if masked else b""
    payload = bytearray(recv_exact(conn, length)) if length else bytearray()
    if masked:
        for index, value in enumerate(payload):
            payload[index] = value ^ mask[index % 4]
    return opcode, bytes(payload)


def send_ws_frame(conn: socket.socket, payload: bytes, opcode: int = 2) -> None:
    header = bytearray([0x80 | opcode])
    length = len(payload)
    if length < 126:
        header.append(length)
    elif length < 65536:
        header.append(126)
        header.extend(struct.pack("!H", length))
    else:
        header.append(127)
        header.extend(struct.pack("!Q", length))
    conn.sendall(bytes(header) + payload)


class MockVncWebsockifyServer:
    def __init__(self, host: str, port: int, frame_source: MockVncFrameSource | None = None) -> None:
        self.host = host
        self.port = port
        self.frame_source = frame_source or MockVncFrameSource()
        self.sock: socket.socket | None = None
        self.running = False

    def serve_forever(self) -> None:
        self.running = True
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            sock.bind((self.host, self.port))
            sock.listen(8)
            self.sock = sock
            print(f"QZ mock VNC WebSocket listening on ws://{self.host}:{self.port}/websockify", flush=True)
            while self.running:
                try:
                    conn, address = sock.accept()
                except OSError:
                    break
                thread = threading.Thread(target=self.handle_client, args=(conn, address), daemon=True)
                thread.start()

    def close(self) -> None:
        self.running = False
        if self.sock:
            self.sock.close()

    def handle_client(self, conn: socket.socket, address: tuple[str, int]) -> None:
        with conn:
            try:
                request = self.read_http_request(conn)
                headers = self.parse_headers(request)
                key = headers.get("sec-websocket-key", "")
                if not key:
                    conn.sendall(b"HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
                    return

                protocol_header = ""
                requested_protocols = headers.get("sec-websocket-protocol", "")
                if requested_protocols:
                    first_protocol = requested_protocols.split(",")[0].strip()
                    if first_protocol:
                        protocol_header = f"Sec-WebSocket-Protocol: {first_protocol}\r\n"

                response = (
                    "HTTP/1.1 101 Switching Protocols\r\n"
                    "Upgrade: websocket\r\n"
                    "Connection: Upgrade\r\n"
                    f"Sec-WebSocket-Accept: {websocket_accept_key(key)}\r\n"
                    f"{protocol_header}"
                    "\r\n"
                )
                conn.sendall(response.encode("ascii"))
                self.run_rfb(conn)
            except Exception as exc:
                print(f"[mock-vnc] client {address} closed: {exc}", file=sys.stderr)

    def read_http_request(self, conn: socket.socket) -> str:
        data = bytearray()
        while b"\r\n\r\n" not in data:
            chunk = conn.recv(1024)
            if not chunk:
                raise ConnectionError("empty websocket handshake")
            data.extend(chunk)
            if len(data) > 16384:
                raise ValueError("websocket handshake too large")
        return data.decode("iso-8859-1")

    def parse_headers(self, request: str) -> dict[str, str]:
        headers: dict[str, str] = {}
        for line in request.split("\r\n")[1:]:
            if ":" not in line:
                continue
            key, value = line.split(":", 1)
            headers[key.strip().lower()] = value.strip()
        return headers

    def run_rfb(self, conn: socket.socket) -> None:
        send_ws_frame(conn, b"RFB 003.008\n")
        self.recv_binary(conn)
        send_ws_frame(conn, b"\x01\x01")
        self.recv_binary(conn)
        send_ws_frame(conn, struct.pack("!I", 0))
        self.recv_binary(conn)
        self.send_server_init(conn)
        self.send_framebuffer_update(conn)

        while True:
            message = self.recv_binary(conn)
            if not message:
                continue
            message_type = message[0]
            if message_type == 3:
                self.send_framebuffer_update(conn)
            elif message_type in {0, 2, 4, 5, 6}:
                continue

    def recv_binary(self, conn: socket.socket) -> bytes:
        while True:
            opcode, payload = recv_ws_frame(conn)
            if opcode == 8:
                raise ConnectionError("websocket close")
            if opcode == 9:
                send_ws_frame(conn, payload, opcode=10)
                continue
            if opcode in {1, 2}:
                return payload

    def send_server_init(self, conn: socket.socket) -> None:
        name = "MMUI mock VNC".encode("utf-8")
        pixel_format = struct.pack("!BBBBHHHBBBxxx", 32, 24, 0, 1, 255, 255, 255, 16, 8, 0)
        payload = (
            struct.pack("!HH", self.frame_source.width, self.frame_source.height)
            + pixel_format
            + struct.pack("!I", len(name))
            + name
        )
        send_ws_frame(conn, payload)

    def send_framebuffer_update(self, conn: socket.socket) -> None:
        header = struct.pack("!BBH", 0, 0, 1)
        rect = struct.pack("!HHHHl", 0, 0, self.frame_source.width, self.frame_source.height, 0)
        send_ws_frame(conn, header + rect + self.frame_source.current_frame())


def parse_host_id_from_task_param(value: Any) -> str:
    text = str(value or "").strip()
    if not text:
        return ""

    for separator in ("&", " "):
        if separator in text:
            for part in text.split(separator):
                host_id = parse_host_id_from_task_param(part)
                if host_id:
                    return host_id

    if "=" in text:
        key, raw_value = text.split("=", 1)
        if key.strip().lower() == "id":
            return raw_value.strip()

    return text if text.isdigit() else ""


def run_delayed_task(delay: float, callback) -> None:
    def worker() -> None:
        time.sleep(delay)
        callback()

    thread = threading.Thread(target=worker, daemon=True)
    thread.start()


class QzMockNodeHandler(BaseHTTPRequestHandler):
    server_version = "QzMockNode/0.1"

    @property
    def mock(self) -> MockState:
        return self.server.mock_state  # type: ignore[attr-defined]

    def do_GET(self) -> None:
        path = urlparse(self.path).path
        if path in {"/", "/health", "/test"}:
            self.write_json(ok("mock node ready", {"service": "qz-mock-node", "time": int(time.time())}))
            return
        if path.startswith("/vnc/"):
            self.write_html("<!doctype html><title>QZ mock VNC</title><body>QZ mock VNC endpoint</body>")
            return
        self.write_json(fail(f"route not found: {path}"), HTTPStatus.NOT_FOUND)

    def do_POST(self) -> None:
        if not self.check_auth():
            self.write_json(fail("apikey error"), HTTPStatus.UNAUTHORIZED)
            return

        path = urlparse(self.path).path.rstrip("/")
        data = self.read_payload()

        routes = {
            "/api/v1/vhost/start": self.handle_start,
            "/api/v1/vhost/shutdown": self.handle_shutdown,
            "/api/v1/vhost/powerOff": self.handle_poweroff,
            "/api/v1/vhost/restart": self.handle_restart,
            "/api/v1/vhost/getStateVirtual": self.handle_state,
            "/api/v1/vhost/realMonitorVirtual": self.handle_monitor,
            "/api/v1/vhost/getVncPasswd": self.handle_vnc_password,
            "/api/v1/vhost/createTask": self.handle_create_task,
            "/api/v1/vhost/updatePawsswdVirtual": self.handle_update_password,
            "/api/v1/vhost/updateIpsetVirtual": self.handle_simple_success,
            "/api/v1/vhost/resetIPVirtual": self.handle_simple_success,
            "/api/v1/vhost/addNWVirtual": self.handle_simple_success,
            "/api/v1/vhost/removeNWVirtual": self.handle_simple_success,
            "/api/v1/vhost/addPort": self.handle_simple_success,
            "/api/v1/vhost/delPort": self.handle_simple_success,
            "/api/v1/vhost/delPortBat": self.handle_simple_success,
            "/api/v1/vhost/addDomain": self.handle_simple_success,
            "/api/v1/vhost/delDomain": self.handle_simple_success,
            "/api/v1/vhost/delDomainBat": self.handle_simple_success,
            "/api/v1/vhost/openNetwork": self.handle_open_network,
            "/api/v1/vhost/closeNetwork": self.handle_close_network,
            "/api/v1/vhost/flowVirtualNet": self.handle_flow_virtual_net,
            "/api/v1/update_iso_kvm": self.handle_update_iso,
            "/api/v1/boot_order": self.handle_boot_order,
            "/api/v1/get_isolist_kvm": self.handle_iso_list,
            "/api/v1/count_flow_kvm": self.handle_count_flow,
            "/api/v1/get_network_flow_kvm": self.handle_count_flow,
            "/api/v1/get_status_kvm": self.handle_state,
            "/api/v1/set_status_kvm": self.handle_set_status,
            "/api/v1/monitor_kvm": self.handle_monitor,
            "/api/v1/get_screenshot_kvm": self.handle_screenshot,
            "/api/v1/create_kvm": self.handle_simple_success,
            "/api/v1/update_kvm": self.handle_simple_success,
            "/api/v1/remove_kvm": self.handle_simple_success,
            "/api/v1/reinstall_kvm": self.handle_reinstall_direct,
            "/api/v1/update_system_password": self.handle_update_password,
            "/api/v1/create_snapshot_kvm": self.handle_task_direct,
            "/api/v1/restore_snapshot_kvm": self.handle_task_direct,
            "/api/v1/remove_snapshot_kvm": self.handle_task_direct,
            "/api/v1/create_backup_kvm": self.handle_task_direct,
            "/api/v1/restore_backup_kvm": self.handle_task_direct,
            "/api/v1/remove_backup_kvm": self.handle_task_direct,
            "/api/v1/add_firewall_kvm": self.handle_simple_success,
            "/api/v1/remove_firewall_kvm": self.handle_simple_success,
            "/api/v1/update_ip_kvm": self.handle_simple_success,
            "/api/v1/set_network_state": self.handle_network_state,
        }

        handler = routes.get(path)
        if not handler:
            self.write_json(fail(f"route not found: {path}"), HTTPStatus.NOT_FOUND)
            return
        self.write_json(handler(data))

    def check_auth(self) -> bool:
        expected = self.mock.apikey
        if not expected:
            return True
        return self.headers.get("apikey", "").strip() == expected

    def read_payload(self) -> dict[str, Any]:
        length = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(length) if length else b""
        if not raw:
            return {}

        content_type = self.headers.get("Content-Type", "")
        if "application/json" in content_type:
            try:
                parsed = json.loads(raw.decode("utf-8"))
                return parsed if isinstance(parsed, dict) else {}
            except json.JSONDecodeError:
                return {}
        parsed = parse_qs(raw.decode("utf-8"), keep_blank_values=True)
        return {key: values[-1] if values else "" for key, values in parsed.items()}

    def write_json(self, payload: dict[str, Any], status: int = HTTPStatus.OK) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def write_html(self, body: str, status: int = HTTPStatus.OK) -> None:
        data = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def log_message(self, fmt: str, *args: Any) -> None:
        sys.stderr.write("[%s] %s\n" % (self.log_date_time_string(), fmt % args))

    def handle_start(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.begin_operation("start", POWER_DELAY_SECONDS, "running")
        time.sleep(POWER_DELAY_SECONDS)
        host.refresh()
        return ok("start command executed", {"status": host.status_for_qz()})

    def handle_shutdown(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.begin_operation("shutdown", POWER_DELAY_SECONDS, "shutoff")
        time.sleep(POWER_DELAY_SECONDS)
        host.refresh()
        return ok("shutdown command executed", {"status": host.status_for_qz()})

    def handle_poweroff(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.begin_operation("poweroff", POWER_DELAY_SECONDS, "shutoff")
        time.sleep(POWER_DELAY_SECONDS)
        host.refresh()
        return ok("power off command executed", {"status": host.status_for_qz()})

    def handle_restart(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.begin_operation("restart", POWER_DELAY_SECONDS, "running")
        time.sleep(POWER_DELAY_SECONDS)
        host.refresh()
        return ok("restart command executed", {"status": host.status_for_qz()})

    def handle_state(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        return ok("success", {"status": host.status_for_qz(), "state": host.status_for_qz()})

    def handle_set_status(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        status = str(data.get("status") or data.get("state") or "running").lower()
        host.status = "running" if status in {"1", "running", "start"} else "shutoff"
        return ok("success", {"status": host.status_for_qz()})

    def handle_monitor(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        monitor = host.monitor()
        return ok("success", monitor)

    def handle_vnc_password(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        return ok("success", {
            "host_name": host.name,
            "vncPasswd": host.vnc_password,
        })

    def handle_create_task(self, data: dict[str, Any]) -> dict[str, Any]:
        action = str(data.get("action") or "")
        payload = decode_task_data(data)
        host = self.mock.host(payload)
        delay = REINSTALL_DELAY_SECONDS if action == "reinstall" else TASK_DELAY_SECONDS
        final_status = "running"
        task_id = data.get("callback_param") or int(time.time())
        if action == "remove_vps":
            final_status = "shutoff"
        if action == "reinstall":
            host.password = str(payload.get("password") or host.password)
            host.begin_operation("reinstall", delay, final_status)
        else:
            host.begin_operation(action or "task", delay, final_status)
        run_delayed_task(
            delay,
            lambda: (
                host.refresh(),
                self.mock.complete_qz_task(task_id, action, SUCCESS_CODE, "mock task completed"),
            ),
        )
        return ok("task accepted", {"task_id": task_id, "action": action, "delay": delay})

    def handle_task_direct(self, data: dict[str, Any]) -> dict[str, Any]:
        time.sleep(TASK_DELAY_SECONDS)
        return ok("success", {"task_id": int(time.time())})

    def handle_reinstall_direct(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.password = str(data.get("password") or host.password)
        host.begin_operation("reinstall", REINSTALL_DELAY_SECONDS, "running")
        time.sleep(REINSTALL_DELAY_SECONDS)
        host.refresh()
        return ok("reinstall command executed", {"status": host.status_for_qz()})

    def handle_update_password(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.password = str(data.get("passwd") or data.get("password") or host.password)
        time.sleep(POWER_DELAY_SECONDS)
        return ok("password updated", {"host_name": host.name})

    def handle_update_iso(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.iso_path = str(data.get("iso_path") or "")
        return ok("success", {"iso_path": host.iso_path})

    def handle_boot_order(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.boot = str(data.get("boot") or "idc")
        time.sleep(POWER_DELAY_SECONDS)
        return ok("success", {"boot": host.boot})

    def handle_iso_list(self, data: dict[str, Any]) -> dict[str, Any]:
        return ok("success", ["virtio-win.iso", "ubuntu-22.04-live.iso", "windows-rescue.iso"])

    def handle_count_flow(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.monitor()
        return ok("success", {"rx": host.rx_total, "tx": host.tx_total})

    def handle_flow_virtual_net(self, data: dict[str, Any]) -> dict[str, Any]:
        rows = []
        now = int(time.time())
        for index in range(30):
            rows.append({"time": now - index * 3600, "rx": 60 + index * 2, "tx": 35 + index})
        return ok("success", rows)

    def handle_screenshot(self, data: dict[str, Any]) -> dict[str, Any]:
        return ok("success", {"pic": ONE_PIXEL_PNG})

    def handle_open_network(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.network_enabled = True
        return ok("network opened", {"network": "open"})

    def handle_close_network(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.network_enabled = False
        return ok("network closed", {"network": "closed"})

    def handle_network_state(self, data: dict[str, Any]) -> dict[str, Any]:
        host = self.mock.host(data)
        host.network_enabled = str(data.get("state") or "1") == "1"
        return ok("success", {"network": "open" if host.network_enabled else "closed"})

    def handle_simple_success(self, data: dict[str, Any]) -> dict[str, Any]:
        return ok("success", {"echo": data})


def decode_task_data(data: dict[str, Any]) -> dict[str, Any]:
    raw = data.get("data")
    if isinstance(raw, dict):
        return raw
    if isinstance(raw, str) and raw:
        try:
            parsed = json.loads(raw)
            return parsed if isinstance(parsed, dict) else {}
        except json.JSONDecodeError:
            return {}
    return {}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run a local QZ KVM mock node.")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=18080)
    parser.add_argument("--vnc-host", default="")
    parser.add_argument("--vnc-port", type=int, default=6080)
    parser.add_argument("--vnc-gif", default=str(DEFAULT_VNC_GIF_PATH))
    parser.add_argument("--no-vnc-ws", action="store_true")
    parser.add_argument("--apikey", default="mmui-mock-key")
    parser.add_argument("--qz-db-host", default="127.0.0.1")
    parser.add_argument("--qz-db-port", type=int, default=3307)
    parser.add_argument("--qz-db-name", default="qzsystem_mmui")
    parser.add_argument("--qz-db-user", default="qzsystem_mmui")
    parser.add_argument("--qz-db-password", default="qzsystem_mmui_local")
    parser.add_argument("--qz-db-charset", default="utf8mb4")
    parser.add_argument("--no-qz-db-complete", action="store_true")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    server = ThreadingHTTPServer((args.host, args.port), QzMockNodeHandler)
    vnc_server = None
    vnc_thread = None
    db_config = {} if args.no_qz_db_complete else {
        "host": args.qz_db_host,
        "port": args.qz_db_port,
        "database": args.qz_db_name,
        "user": args.qz_db_user,
        "password": args.qz_db_password,
        "charset": args.qz_db_charset,
    }
    server.mock_state = MockState(args.apikey, db_config)  # type: ignore[attr-defined]
    if not args.no_vnc_ws:
        vnc_host = args.vnc_host or args.host
        vnc_server = MockVncWebsockifyServer(vnc_host, args.vnc_port, MockVncFrameSource(args.vnc_gif))
        vnc_thread = threading.Thread(target=vnc_server.serve_forever, daemon=True)
        vnc_thread.start()
    print(f"QZ mock node listening on http://{args.host}:{args.port} apikey={args.apikey}", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        if vnc_server:
            vnc_server.close()
        server.server_close()


if __name__ == "__main__":
    main()
