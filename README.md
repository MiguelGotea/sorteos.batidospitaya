# sorteos.batidospitaya.com

Sitio web de sorteos para Batidos Pitaya.

## Deploy

Los cambios en `main` se despliegan automáticamente al servidor vía GitHub Actions.

### Subir cambios manualmente

Ejecutar el script desde PowerShell:

```powershell
.\.scripts\gitpush_sorteos.ps1
```

## Secrets requeridos en GitHub

| Secret | Descripción |
|--------|-------------|
| `HOSTINGER_HOST` | Host del servidor |
| `HOSTINGER_USER` | Usuario SSH |
| `HOSTINGER_SSH_KEY` | Llave privada SSH |
| `HOSTINGER_PATH_SORTEOS` | Ruta absoluta en el servidor |
