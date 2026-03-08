Set-Location "C:\xampp\htdocs\fayez-movie"

Write-Host "==================================================" -ForegroundColor Green
Write-Host "🔄 Auto-Commit System Started" -ForegroundColor Green
Write-Host "Checking for changes every 30 seconds" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host "==================================================" -ForegroundColor Green
Write-Host ""

$interval = 30

while ($true) {
    $status = git status --porcelain
    
    if ($status) {
        Write-Host "$(Get-Date -Format 'HH:mm:ss') - 📝 Changes detected!" -ForegroundColor Cyan
        git add .
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        git commit -m "Auto-commit: $timestamp"
        Write-Host "$(Get-Date -Format 'HH:mm:ss') - 📤 Pushing to GitHub..." -ForegroundColor Yellow
        git push origin master
        Write-Host "$(Get-Date -Format 'HH:mm:ss') - ✅ Changes uploaded! 🚀" -ForegroundColor Green
        Write-Host ""
    }
    else {
        Write-Host "." -NoNewline
    }
    
    Start-Sleep -Seconds $interval
}
