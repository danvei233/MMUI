param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 18080,
    [int]$VncPort = 6080,
    [string]$ApiKey = "mmui-mock-key",
    [string]$VncGifPath = (Join-Path $env:USERPROFILE "Desktop\webwxgetmsgimg.gif"),
    [switch]$Restart
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$serverScript = Join-Path $repoRoot "MMUI-V2X\mockservers\qz_mock_node.py"
$runtimeRoot = Join-Path $repoRoot ".runtime"
$logRoot = Join-Path $runtimeRoot "logs"
$stdout = Join-Path $logRoot "qz-mock-node.out.log"
$stderr = Join-Path $logRoot "qz-mock-node.err.log"

if (-not (Test-Path $serverScript)) {
    throw "mock node script not found: $serverScript"
}

New-Item -ItemType Directory -Force -Path $runtimeRoot, $logRoot | Out-Null

function Get-ListeningProcessIds {
    param([int]$LocalPort)

    $ids = @()
    try {
        $connections = Get-NetTCPConnection -LocalPort $LocalPort -State Listen -ErrorAction Stop
        $ids += $connections | Select-Object -ExpandProperty OwningProcess -Unique
    } catch {
        $netstat = netstat -ano | Select-String -Pattern ":$LocalPort\s+.*LISTENING\s+(\d+)"
        foreach ($line in $netstat) {
            if ($line.Matches.Count -gt 0) {
                $ids += [int]$line.Matches[0].Groups[1].Value
            }
        }
    }

    return $ids | Where-Object { $_ } | Select-Object -Unique
}

$processIds = @(
    Get-ListeningProcessIds -LocalPort $Port
    Get-ListeningProcessIds -LocalPort $VncPort
) | Where-Object { $_ } | Select-Object -Unique
if ($processIds.Count -gt 0) {
    if (-not $Restart) {
        throw "Port $Port or $VncPort is already listening. Use -Restart to stop process id(s): $($processIds -join ', ')"
    }

    foreach ($processId in $processIds) {
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Milliseconds 350
}

$processIds = @(
    Get-ListeningProcessIds -LocalPort $Port
    Get-ListeningProcessIds -LocalPort $VncPort
) | Where-Object { $_ } | Select-Object -Unique
if ($processIds.Count -gt 0) {
    throw "Port $Port or $VncPort is still listening after restart cleanup. Process id(s): $($processIds -join ', ')"
}

$python = Get-Command py -ErrorAction SilentlyContinue
if (-not $python) {
    $python = Get-Command python -ErrorAction SilentlyContinue
}
if (-not $python) {
    throw "Python launcher not found. Install Python or make py/python available on PATH."
}

$argsList = @(
    $serverScript,
    "--host", $HostName,
    "--port", [string]$Port,
    "--vnc-port", [string]$VncPort,
    "--vnc-gif", $VncGifPath,
    "--apikey", $ApiKey
)

Set-Content -Path $stdout -Value "" -Encoding UTF8
Set-Content -Path $stderr -Value "" -Encoding UTF8

$process = Start-Process `
    -FilePath $python.Source `
    -ArgumentList $argsList `
    -WorkingDirectory $repoRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput $stdout `
    -RedirectStandardError $stderr `
    -PassThru

Start-Sleep -Milliseconds 500

[pscustomobject]@{
    Url = "http://$HostName`:$Port"
    VncUrl = "ws://$HostName`:$VncPort/websockify"
    ApiKey = $ApiKey
    ProcessId = $process.Id
    Stdout = $stdout
    Stderr = $stderr
}
