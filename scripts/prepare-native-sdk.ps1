# File: scripts/prepare-native-sdk.ps1
# Purpose: Fetch checksum-pinned official build runtimes into fresh ignored tooling, without installation.
# Functions: Get-CheckedFile; SDK selection and exact per-process builder settings.
# State: public PHP/Composer archives and licensed installed VS app-local redistributable DLL paths only.
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Repo = Split-Path -Parent $PSScriptRoot
$Tooling = Join-Path $Repo '.runtime/tooling'
if (-not $IsWindows -or (Test-Path -LiteralPath $Tooling)) { throw 'Use a fresh Windows tooling directory; no existing SDK is replaced.' }
New-Item -ItemType Directory -Path $Tooling -Force | Out-Null
function Get-CheckedFile([string]$Url, [string]$Destination, [string]$Expected, [long]$Maximum) {
    $Handler = [Net.Http.HttpClientHandler]::new()
    $Handler.AllowAutoRedirect = $false
    $Client = [Net.Http.HttpClient]::new($Handler)
    $Cancellation = [Threading.CancellationTokenSource]::new([TimeSpan]::FromSeconds(90))
    $Response = $null
    $DownloadStream = $null
    $DestinationStream = $null
    try {
        $Response = $Client.GetAsync($Url, [Net.Http.HttpCompletionOption]::ResponseHeadersRead, $Cancellation.Token).GetAwaiter().GetResult()
        if (-not $Response.IsSuccessStatusCode -or ($Response.Content.Headers.ContentLength -and $Response.Content.Headers.ContentLength -gt $Maximum)) { throw 'Official download status/size refused.' }
        $DownloadStream = $Response.Content.ReadAsStreamAsync($Cancellation.Token).GetAwaiter().GetResult()
        $DestinationStream = [IO.File]::Open($Destination, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        $Buffer = [byte[]]::new(65536)
        [long]$Total = 0
        while (($Received = $DownloadStream.ReadAsync($Buffer, 0, $Buffer.Length, $Cancellation.Token).GetAwaiter().GetResult()) -gt 0) {
            $Total += $Received
            if ($Total -gt $Maximum) { throw 'Actual official download byte budget exceeded.' }
            $DestinationStream.Write($Buffer, 0, $Received)
        }
        if ($Total -eq 0) { throw 'Official download is empty.' }
        $DestinationStream.Flush($true)
    } finally {
        if ($DestinationStream) { $DestinationStream.Dispose() }
        if ($DownloadStream) { $DownloadStream.Dispose() }
        if ($Response) { $Response.Dispose() }
        $Client.Dispose()
        $Handler.Dispose()
        $Cancellation.Dispose()
    }
    $Stream = [IO.File]::OpenRead($Destination)
    $Hasher = [Security.Cryptography.SHA256]::Create()
    try { $Actual = [BitConverter]::ToString($Hasher.ComputeHash($Stream)).Replace('-', '').ToLowerInvariant() }
    finally { $Stream.Dispose(); $Hasher.Dispose() }
    if ($Actual -ne $Expected) { throw 'Official runtime checksum mismatch; never extract or execute it.' }
}
$Archive = Join-Path $Tooling 'php.zip'
Get-CheckedFile 'https://downloads.php.net/~windows/releases/archives/php-8.5.12-1-nts-Win32-vs17-x64.zip' $Archive '2a36a2edcb7a5b9252f638fd5e4cf84d7f6e066092654220dc0b63e5cf4e9723' 67108864
$Php = Join-Path $Tooling 'php'
# The exact official archive is verified before extraction; no caller-provided ZIP is accepted.
Expand-Archive -LiteralPath $Archive -DestinationPath $Php
$Composer = Join-Path $Tooling 'composer-2.10.3.phar'
Get-CheckedFile 'https://getcomposer.org/download/2.10.3/composer.phar' $Composer '7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6' 8388608
$Vswhere = Join-Path ${env:ProgramFiles(x86)} 'Microsoft Visual Studio/Installer/vswhere.exe'
$Vs = (& $Vswhere -latest -products '*' -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath).Trim()
if ($LASTEXITCODE -ne 0 -or -not $Vs) { throw 'Licensed Visual Studio redistributable source unavailable.' }
$Redist = Join-Path $Vs 'VC/Redist/MSVC'
$Version = Get-ChildItem -LiteralPath $Redist -Directory | Where-Object Name -Match '^\d+\.\d+\.\d+$' | Sort-Object { [Version]$_.Name } -Descending | Select-Object -First 1
if (-not $Version) { throw 'Versioned Visual Studio redistributable not found.' }
$Crt = Get-ChildItem -LiteralPath (Join-Path $Version.FullName 'x64') -Directory -Filter 'Microsoft.VC*.CRT' | Select-Object -First 1
if (-not $Crt) { throw 'App-local x64 CRT directory unavailable.' }
foreach ($Name in @('vcruntime140.dll', 'vcruntime140_1.dll')) {
    $Dll = Join-Path $Crt.FullName $Name
    if ((Get-AuthenticodeSignature -LiteralPath $Dll).Status -ne 'Valid' -or (Get-Item -LiteralPath $Dll).VersionInfo.CompanyName -ne 'Microsoft Corporation') { throw 'Microsoft CRT signature/identity verification failed.' }
}
@{php=$Php;composer=$Composer;crt=$Crt.FullName;phpVersion='8.5.12';phpPackage='8.5.12-1';composerVersion='2.10.3'} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $Tooling 'sdk.json') -Encoding utf8
Write-Output 'Verified official PHP/Composer tooling and signed app-local CRT. No installer, registry, PATH or user-profile changes.'
