# Dentico local preview refresh. Run from the repository's tools folder.
# Updates only Dentico-Preview; never modifies the original XAMPP Dentico.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repo = Split-Path -Parent $PSScriptRoot
$preview = 'D:\WEB_DEV\xampp\htdocs\Dentico-Preview'
$branch = 'feature/ui-refresh-2026'

if (-not (Test-Path -LiteralPath $preview -PathType Container)) {
    throw "Preview folder missing: $preview. Create the preview once before using this script."
}
if (-not (Test-Path -LiteralPath (Join-Path $repo '.git'))) {
    throw "Cannot find a Git repository in: $repo"
}

$currentBranch = (& git -C $repo branch --show-current)
if ($LASTEXITCODE -ne 0 -or $currentBranch.Trim() -ne $branch) {
    throw "Switch the Git repository to $branch before refreshing the preview."
}

$workingChanges = @(& git -C $repo status --porcelain)
if ($LASTEXITCODE -ne 0) { throw "Could not inspect Git status." }
if ($workingChanges.Count -gt 0) {
    throw "Git working tree has local changes. Review/commit/stash them before refreshing."
}

Write-Host "Getting the latest Dentico UI changes..."
& git -C $repo pull --ff-only origin $branch
if ($LASTEXITCODE -ne 0) { throw "Git pull failed. Preview was not updated." }

# Do not copy .git, local credentials, vendor dependencies or helper scripts.
# Robocopy /E copies new/modified files without deleting other preview files.
Write-Host "Updating preview files (preserving private config and vendor)..."
& robocopy $repo $preview /E /R:1 /W:1 /NFL /NDL /XD '.git' 'config' 'vendor' 'tools' '.codex'
if ($LASTEXITCODE -ge 8) { throw "Preview copy failed (robocopy exit code $LASTEXITCODE)." }

Write-Host ""
Write-Host "Dentico Preview updated successfully."
Write-Host "Open: http://localhost/Dentico-Preview/"
Write-Host "Use Ctrl+F5 in the browser to bypass cached CSS."
