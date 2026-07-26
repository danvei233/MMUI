param(
    [string]$ApiUrl = 'https://www.qzsystem.com/home/version?ver=0',
    [string]$BaselineRoot = 'D:\mui3\.runtime\qzweb-compare',
    [string]$OutputRoot = 'D:\mui3\.runtime\qz-version-compat',
    [string]$PhpExe = 'D:\mui3\.runtime\php-7.4.30\php.exe'
)

$ErrorActionPreference = 'Stop'

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$overrideRoot = Join-Path $projectRoot 'qz-override'
$runId = Get-Date -Format 'yyyyMMdd-HHmmss'
$downloadRoot = Join-Path $OutputRoot 'downloads'
$workRoot = Join-Path $OutputRoot "work-$runId"
$reportRoot = Join-Path $OutputRoot "report-$runId"
New-Item -ItemType Directory -Force -Path $downloadRoot, $workRoot, $reportRoot | Out-Null

if (-not (Test-Path -LiteralPath $PhpExe)) {
    throw "PHP executable not found: $PhpExe"
}

$targets = @(
    'app/admin/controller/Index.php',
    'app/common/service/Ecs.php',
    'app/control/controller/Ecs.php',
    'app/index/controller/Index.php',
    'extend/qzcloud/Kvm.php',
    'extend/qzcloud/HyperV.php'
)

$requiredEcsEndpoints = @(
    'index', 'state_host', 'monitor_host', 'thumbnail_host',
    'start_host', 'close_host', 'power_host', 'restart_host', 'synctime_host',
    'vnc_host', 'reinstall_host', 'mountiso_host', 'unmountiso_host', 'set_bios',
    'updata_systempass_host', 'update_panel_password',
    'snapshot', 'backup', 'firewall_host', 'add_firewall_host', 'remove_firewall_host',
    'port_host', 'add_port_host', 'remove_port_host', 'findport',
    'domain_host', 'add_domain_host', 'remove_domain_host'
)

function Get-EntryForTarget {
    param($Zip, [string]$Target)
    $suffix = '/code/' + $Target
    return $Zip.Entries |
        Where-Object { $_.FullName.Replace('\\', '/').EndsWith($suffix, [StringComparison]::OrdinalIgnoreCase) } |
        Select-Object -First 1
}

function Read-ZipEntryBytes {
    param($Entry)
    $memory = New-Object IO.MemoryStream
    $input = $Entry.Open()
    try {
        $input.CopyTo($memory)
        return $memory.ToArray()
    } finally {
        $input.Dispose()
        $memory.Dispose()
    }
}

function Write-FileBytes {
    param([byte[]]$Bytes, [string]$Destination)
    New-Item -ItemType Directory -Force -Path (Split-Path $Destination) | Out-Null
    $lastError = $null
    for ($attempt = 1; $attempt -le 8; $attempt++) {
        try {
            [IO.File]::WriteAllBytes($Destination, $Bytes)
            return
        } catch {
            $lastError = $_
            if ($attempt -lt 8) {
                Start-Sleep -Milliseconds (50 * $attempt)
            }
        }
    }
    throw $lastError
}

function Test-ZipPaths {
    param($Zip)
    foreach ($entry in $Zip.Entries) {
        $name = $entry.FullName.Replace('\\', '/')
        if ($name.StartsWith('/') -or $name -match '^[A-Za-z]:' -or $name.Split('/') -contains '..') {
            return $false
        }
    }
    return $true
}

function Invoke-Download {
    param($Version, [string]$Destination)
    $url = ([string]$Version.downloadurl) -replace '^http:', 'https:'
    try {
        Invoke-WebRequest -Uri $url -OutFile $Destination -UseBasicParsing -TimeoutSec 40
    } catch {
        Invoke-WebRequest -Uri ([string]$Version.downloadurl) -OutFile $Destination -UseBasicParsing -TimeoutSec 40
    }
}

$response = Invoke-RestMethod -Uri $ApiUrl -TimeoutSec 30
$versions = @()
foreach ($item in $response) {
    $versions += $item
}
$results = @()

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

foreach ($version in $versions) {
    $versionNumber = [string]$version.ver
    $zipPath = Join-Path $downloadRoot "$versionNumber.zip"
    $row = [ordered]@{
        version = $versionNumber
        content = [string]$version.content
        download_url = [string]$version.downloadurl
        downloaded = $false
        sha256 = ''
        zip_safe = $false
        relevant_files = @()
        patch_ok = $false
        idempotent = $false
        php_lint_ok = $false
        endpoints_present = 0
        endpoints_required = $requiredEcsEndpoints.Count
        missing_endpoints = @()
        status = 'failed'
        error = ''
    }

    try {
        if (-not (Test-Path -LiteralPath $zipPath) -or (Get-Item $zipPath).Length -eq 0) {
            Invoke-Download -Version $version -Destination $zipPath
        }
        $row.downloaded = $true
        $row.sha256 = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()

        $zip = [IO.Compression.ZipFile]::OpenRead($zipPath)
        try {
            $row.zip_safe = Test-ZipPaths -Zip $zip
            if (-not $row.zip_safe) {
                throw 'Unsafe ZIP entry detected'
            }

            $testRoot = Join-Path $workRoot $versionNumber
            foreach ($target in $targets) {
                $baseline = Join-Path $BaselineRoot ($target.Replace('/', '\\'))
                $destination = Join-Path $testRoot ($target.Replace('/', '\\'))
                if (Test-Path -LiteralPath $baseline) {
                    New-Item -ItemType Directory -Force -Path (Split-Path $destination) | Out-Null
                    Copy-Item -LiteralPath $baseline -Destination $destination -Force
                }

                $entry = Get-EntryForTarget -Zip $zip -Target $target
                if ($entry) {
                    $entryBytes = Read-ZipEntryBytes -Entry $entry
                    Write-FileBytes -Bytes $entryBytes -Destination $destination
                    $candidate = Join-Path $testRoot ("update\unzip\$versionNumber\code\" + $target.Replace('/', '\\'))
                    Write-FileBytes -Bytes $entryBytes -Destination $candidate
                    $row.relevant_files += $target
                }
            }
        } finally {
            $zip.Dispose()
        }

        $mmuiDir = Join-Path $testRoot 'extend\mmui'
        New-Item -ItemType Directory -Force -Path $mmuiDir | Out-Null
        Copy-Item -LiteralPath (Join-Path $overrideRoot 'extend\mmui\MmuiPatch.php') -Destination $mmuiDir -Force
        Copy-Item -LiteralPath (Join-Path $overrideRoot 'extend\mmui\MmuiQzVersion.php') -Destination $mmuiDir -Force
        Copy-Item -LiteralPath (Join-Path $overrideRoot 'mmui-install.php') -Destination $testRoot -Force
        New-Item -ItemType Directory -Force -Path (Join-Path $testRoot "update\unzip\$versionNumber") | Out-Null

        $firstOutput = & $PhpExe -n (Join-Path $testRoot 'mmui-install.php') 2>&1
        $firstExit = $LASTEXITCODE
        $first = ($firstOutput -join "`n") | ConvertFrom-Json
        $row.patch_ok = $firstExit -eq 0 -and [bool]$first.ok

        $patchedFiles = @($targets | ForEach-Object { Join-Path $testRoot ($_.Replace('/', '\\')) } | Where-Object { Test-Path -LiteralPath $_ })
        $hashBefore = @($patchedFiles | ForEach-Object { (Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash }) -join ','
        $secondOutput = & $PhpExe -n (Join-Path $testRoot 'mmui-install.php') 2>&1
        $secondExit = $LASTEXITCODE
        $second = ($secondOutput -join "`n") | ConvertFrom-Json
        $hashAfter = @($patchedFiles | ForEach-Object { (Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash }) -join ','
        $row.idempotent = $secondExit -eq 0 -and [bool]$second.ok -and $hashBefore -eq $hashAfter

        $lintOk = $true
        foreach ($file in $patchedFiles) {
            & $PhpExe -n -l $file *> $null
            if ($LASTEXITCODE -ne 0) {
                $lintOk = $false
                break
            }
        }
        $row.php_lint_ok = $lintOk

        $controller = Join-Path $testRoot 'app\control\controller\Ecs.php'
        $controllerSource = ''
        if (Test-Path -LiteralPath $controller) {
            $controllerSource = Get-Content -LiteralPath $controller -Raw
        }
        $present = @()
        foreach ($endpoint in $requiredEcsEndpoints) {
            if ($controllerSource -match ('public\s+function\s+' + [regex]::Escape($endpoint) + '\s*\(')) {
                $present += $endpoint
            }
        }
        $row.endpoints_present = $present.Count
        $row.missing_endpoints = @($requiredEcsEndpoints | Where-Object { $present -notcontains $_ })

        $row.status = if ($row.patch_ok -and $row.idempotent -and $row.php_lint_ok -and $row.missing_endpoints.Count -eq 0) {
            'passed'
        } elseif ($row.patch_ok -and $row.idempotent -and $row.php_lint_ok) {
            'interface-warning'
        } else {
            'failed'
        }
    } catch {
        $row.error = $_.Exception.Message
    }
    $results += [pscustomobject]$row
    Write-Host ("[{0}/{1}] {2} {3}" -f $results.Count, $versions.Count, $versionNumber, $row.status)
}

$jsonPath = Join-Path $reportRoot 'qz-version-compatibility.json'
$csvPath = Join-Path $reportRoot 'qz-version-compatibility.csv'
$markdownPath = Join-Path $reportRoot 'qz-version-compatibility.md'
$results | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $jsonPath -Encoding utf8
$results | Select-Object version, status, downloaded, zip_safe, patch_ok, idempotent, php_lint_ok, endpoints_present, endpoints_required, @{n='missing_endpoints';e={$_.missing_endpoints -join ','}}, error |
    Export-Csv -LiteralPath $csvPath -NoTypeInformation -Encoding utf8

$passed = @($results | Where-Object status -eq 'passed').Count
$warnings = @($results | Where-Object status -eq 'interface-warning').Count
$failed = @($results | Where-Object status -eq 'failed').Count
$lines = @(
    '# Qzsystem Version Compatibility',
    '',
    "- API: $ApiUrl",
    "- Versions: $($results.Count)",
    "- Passed: $passed",
    "- Interface warnings: $warnings",
    "- Failed: $failed",
    '',
    '| Version | Status | Relevant files | Interfaces | Missing |',
    '| --- | --- | --- | --- | --- |'
)
foreach ($item in $results) {
    $lines += "| $($item.version) | $($item.status) | $($item.relevant_files -join '<br>') | $($item.endpoints_present)/$($item.endpoints_required) | $($item.missing_endpoints -join ', ') |"
}
$lines | Set-Content -LiteralPath $markdownPath -Encoding utf8

[pscustomobject]@{
    Versions = $results.Count
    Passed = $passed
    InterfaceWarnings = $warnings
    Failed = $failed
    Report = $markdownPath
    Json = $jsonPath
    Csv = $csvPath
}
