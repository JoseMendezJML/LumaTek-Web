$ErrorActionPreference = 'Stop'
$taskSketch = Join-Path $PSScriptRoot 'LumaTek_Distribution'
$taskBuild = Join-Path $PSScriptRoot '.build'
if (-not (Get-Command arduino-cli -ErrorAction SilentlyContinue)) {
    throw 'Arduino CLI 1.2.2 is required. No compiler was installed automatically.'
}
$taskVersion = & arduino-cli version
if ($LASTEXITCODE -ne 0 -or $taskVersion -notmatch '\b1\.2\.2\b') {
    throw 'Use the documented Arduino CLI 1.2.2 baseline.'
}
& arduino-cli compile --profile distribution --warnings all --output-dir $taskBuild $taskSketch
if ($LASTEXITCODE -ne 0) { throw 'Compilation failed; no release is authorized.' }
Get-ChildItem -LiteralPath $taskBuild -File -Filter '*.bin' | Get-FileHash -Algorithm SHA256
Write-Output 'Experimental build only. No upload, manifest, publication or production approval was performed.'
