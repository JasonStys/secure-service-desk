# File: scripts/archive-native.ps1
# Purpose: Produce and relocate-test a fresh complete portable ZIP with its streaming SHA256.
# Functions: guarded payload inventory/archive and actual extracted application acceptance.
# State: new paths below this project's release/artifacts trees, never ordinary user profiles.
param([Parameter(Mandatory=$true)][string]$Candidate, [Parameter(Mandatory=$true)][string]$Proof)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Repo = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$CandidateRoot = [IO.Path]::GetFullPath((Join-Path $Repo $Candidate))
$ProofRoot = [IO.Path]::GetFullPath((Join-Path $Repo $Proof))
if (-not $IsWindows -or -not $CandidateRoot.StartsWith((Join-Path $Repo 'release') + [IO.Path]::DirectorySeparatorChar) -or -not $ProofRoot.StartsWith((Join-Path $Repo 'artifacts/native') + [IO.Path]::DirectorySeparatorChar)) { throw 'Only owned project candidate/proof paths are accepted.' }
$Payload = Join-Path $CandidateRoot 'win-unpacked'
$Archive = Join-Path $CandidateRoot 'SecureServiceDesk-1.1.0-windows-x64.zip'
$Extracted = Join-Path $CandidateRoot 'extracted-portable'
if ((Test-Path -LiteralPath $Archive) -or (Test-Path -LiteralPath ($Archive+'.sha256')) -or (Test-Path -LiteralPath $Extracted) -or (Test-Path -LiteralPath $ProofRoot)) { throw 'Use fresh output/proof; earlier evidence is preserved.' }
$Members = @(Get-ChildItem -LiteralPath $Payload -Recurse -Force)
$Files = @($Members | Where-Object { -not $_.PSIsContainer })
if ($Members.Count -gt 15000 -or (($Files | Measure-Object Length -Sum).Sum) -gt 1073741824 -or ($Members | Where-Object { ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0 })) { throw 'Unexpected portable payload links/size/member count.' }
[IO.Compression.ZipFile]::CreateFromDirectory($Payload, $Archive, [IO.Compression.CompressionLevel]::Optimal, $false)
$Stream = [IO.File]::OpenRead($Archive)
$Hasher = [Security.Cryptography.SHA256]::Create()
try { $Hash = [BitConverter]::ToString($Hasher.ComputeHash($Stream)).Replace('-', '').ToLowerInvariant() }
finally { $Stream.Dispose(); $Hasher.Dispose() }
[IO.File]::WriteAllText($Archive+'.sha256', $Hash+'  '+[IO.Path]::GetFileName($Archive)+"`n", [Text.ASCIIEncoding]::new())
# This is this process's fresh regular-file archive, never a caller-supplied unverified download.
[IO.Compression.ZipFile]::ExtractToDirectory($Archive, $Extracted)
$PreviousExe = $env:SSD_NATIVE_EXECUTABLE
$PreviousProof = $env:SSD_NATIVE_PROOF
try {
    $env:SSD_NATIVE_EXECUTABLE = Join-Path $Extracted 'SecureServiceDesk.exe'
    $env:SSD_NATIVE_PROOF = $Proof
    & node (Join-Path $PSScriptRoot 'test-native-app.mjs')
    if ($LASTEXITCODE -ne 0) { throw 'Actual extracted portable app failed acceptance.' }
} finally { $env:SSD_NATIVE_EXECUTABLE=$PreviousExe; $env:SSD_NATIVE_PROOF=$PreviousProof }
Write-Output ('Actual ZIP extracted native acceptance passed. SHA256 '+$Hash)
