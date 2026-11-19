# Script de push para sorteos.batidospitaya.com
# Uso: .\gitpush_sorteos.ps1 "mensaje del commit"

param(
    [string]$msg = "Update sorteos"
)

Set-Location "C:\Users\migue\Desktop\Sistema\Pitaya Web\VisualCode\sorteos.batidospitaya.com"

git add .
git commit -m $msg
git push origin main

Write-Host "✅ Push completado — sorteos.batidospitaya.com" -ForegroundColor Green
