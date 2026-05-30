param(
    [string]$PhpExe = $env:PHP_EXE,
    [string]$BindHost = "127.0.0.1",
    [int]$Port = 8088,
    [string]$DbHost = "127.0.0.1",
    [int]$DbPort = 3307,
    [string]$DbName = "qzsystem_mmui",
    [string]$DbUser = "qzsystem_mmui",
    [string]$DbPassword = "qzsystem_mmui_local",
    [switch]$ImportDatabase,
    [switch]$NoStart,
    [string]$MySqlExe = "C:\Program Files\MySQL\MySQL Server 9.3\bin\mysql.exe",
    [string]$MySqlAdminUser = "root",
    [string]$MySqlAdminPassword = ""
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$qzRoot = Join-Path $repoRoot "qzsystem"
$publicRoot = Join-Path $qzRoot "public"
$router = Join-Path $publicRoot "router.php"
$runtimeRoot = Join-Path $repoRoot ".runtime"
$logRoot = Join-Path $runtimeRoot "logs"

if (-not $PhpExe) {
    $localPhp = Join-Path $runtimeRoot "php-7.4.33\php.exe"
    if (Test-Path $localPhp) {
        $PhpExe = $localPhp
    } else {
        $localPhp = Join-Path $runtimeRoot "php-7.4.30\php.exe"
        if (Test-Path $localPhp) {
            $PhpExe = $localPhp
        }
    }

    if (-not $PhpExe) {
        $cmd = Get-Command php -ErrorAction SilentlyContinue
        if ($cmd) {
            $PhpExe = $cmd.Source
        }
    }
}

if (-not $PhpExe -or -not (Test-Path $PhpExe)) {
    throw "PHP executable not found. Set PHP_EXE or place portable PHP at .runtime\php-7.4.33\php.exe."
}

if (-not (Test-Path $router)) {
    throw "qzsystem public router not found: $router"
}

New-Item -ItemType Directory -Force -Path $runtimeRoot, $logRoot | Out-Null

if ($ImportDatabase) {
    if (-not (Test-Path $MySqlExe)) {
        throw "mysql.exe not found: $MySqlExe"
    }

    $adminArgs = @("--host=$DbHost", "--port=$DbPort", "--user=$MySqlAdminUser", "--default-character-set=utf8")
    if ($MySqlAdminPassword -ne "") {
        $adminArgs += "--password=$MySqlAdminPassword"
    }

    $bootstrapSql = @"
CREATE DATABASE IF NOT EXISTS $DbName DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'%' IDENTIFIED BY '$DbPassword';
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPassword';
GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'%';
GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'localhost';
FLUSH PRIVILEGES;
"@
    $bootstrapSql | & $MySqlExe @adminArgs

    $schemaSql = Join-Path $qzRoot "app\install\data\data.sql"
    $demoSql = Join-Path $qzRoot "database\mmui-demo-data.sql"
    foreach ($sqlFile in @($schemaSql, $demoSql)) {
        if (-not (Test-Path $sqlFile)) {
            throw "SQL file not found: $sqlFile"
        }
        $sql = Get-Content -LiteralPath $sqlFile -Raw
        $sql = $sql -replace "ROW_FORMAT=COMPACT", "ROW_FORMAT=DYNAMIC"
        $tempSql = Join-Path $runtimeRoot ("mysql-import-" + [System.IO.Path]::GetFileName($sqlFile))
        [System.IO.File]::WriteAllText($tempSql, $sql, [System.Text.UTF8Encoding]::new($false))
        & $MySqlExe @adminArgs --default-character-set=utf8mb4 $DbName --execute="source $tempSql"
    }
}

if ($NoStart) {
    [pscustomobject]@{
        ImportedDatabase = [bool]$ImportDatabase
        Database = "$DbUser@$DbHost`:$DbPort/$DbName"
    }
    return
}

$existing = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($existing) {
    $pidList = ($existing | Select-Object -ExpandProperty OwningProcess -Unique) -join ", "
    throw "Port $Port is already listening. Process id(s): $pidList"
}

$env:APP_DEBUG = "true"
$env:DATABASE_TYPE = "mysql"
$env:DATABASE_HOSTNAME = $DbHost
$env:DATABASE_DATABASE = $DbName
$env:DATABASE_USERNAME = $DbUser
$env:DATABASE_PASSWORD = $DbPassword
$env:DATABASE_HOSTPORT = [string]$DbPort
$env:DATABASE_CHARSET = "utf8"
$env:DATABASE_PREFIX = "cloud_"

$phpDir = Split-Path -Parent (Resolve-Path $PhpExe)
$phpArgs = @(
    "-n",
    "-d", "variables_order=EGPCS",
    "-d", "extension_dir=$phpDir\ext",
    "-d", "extension=pdo_mysql",
    "-d", "extension=mysqli",
    "-d", "extension=mbstring",
    "-d", "extension=openssl",
    "-d", "extension=fileinfo",
    "-d", "extension=curl",
    "-S", "$BindHost`:$Port",
    "-t", $publicRoot,
    $router
)

$stdout = Join-Path $logRoot "qz-php-server.out.log"
$stderr = Join-Path $logRoot "qz-php-server.err.log"
$process = Start-Process -FilePath $PhpExe -ArgumentList $phpArgs -WorkingDirectory $qzRoot -WindowStyle Hidden -RedirectStandardOutput $stdout -RedirectStandardError $stderr -PassThru

[pscustomobject]@{
    Url = "http://$BindHost`:$Port"
    ProcessId = $process.Id
    PhpExe = $PhpExe
    Database = "$DbUser@$DbHost`:$DbPort/$DbName"
    Stdout = $stdout
    Stderr = $stderr
}
