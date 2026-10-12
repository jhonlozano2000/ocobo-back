# OCOBO OCR Service

Microservicio OCR basado en PaddleOCR para extracción de texto de documentos.

## Características

- **PaddleOCR**: Motor OCR de última generación, optimizado para español
- **FastAPI**: API REST moderna y rápida
- **Extracción de texto**: Imágenes (PNG, JPG, BMP, TIFF) y PDFs
- **Datos estructurados**: Extrae automáticamente números de identificación, fechas, correos, teléfonos, direcciones y códigos

## Requisitos

- Python 3.12 (lanzador `py -3.12` o `python` en el PATH)
- Windows 10/11 o Linux

## Instalación y ejecución

### Windows (automático)

```bat
start.bat install   :: crea .venv e instala dependencias (solo si cambió requirements.txt)
start.bat           :: además arranca el servicio en primer plano (Ctrl+C para detener)
```

Desde Laravel, una vez instalado:

```bash
php artisan ocr start    # en segundo plano, con el Python de .venv
php artisan ocr status
php artisan ocr stop
```

Si Python no está en el PATH: `set OCR_PYTHON=C:\ruta\python.exe` antes de `start.bat`.

### Linux (manual)

```bash
cd storage/app/ocr-service
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
php artisan ocr start
```

### Token compartido

Las extracciones exigen la cabecera `X-OCR-Token` con el secreto `OCR_SERVICE_TOKEN`; sin él el
servicio responde 401. `start.bat` y `php artisan ocr start` lo toman del `.env` de Laravel. Por
defecto el servicio solo escucha en `127.0.0.1` (`OCR_SERVICE_HOST` para otra interfaz).

## API Endpoints

### GET /

Verificación del servicio.

```bash
curl http://localhost:5000/
```

### GET /health

Estado de salud del servicio.

```bash
curl http://localhost:5000/health
```

### POST /ocr/extract-text

Extrae texto de una imagen o PDF.

```bash
curl -X POST "http://localhost:5000/ocr/extract-text" \
  -F "file=@documento.pdf"
```

**Respuesta:**
```json
{
  "success": true,
  "filename": "documento.pdf",
  "text": "Texto extraído del documento...",
  "char_count": 15432
}
```

### POST /ocr/extract-structured

Extrae texto y datos estructurados.

```bash
curl -X POST "http://localhost:5000/ocr/extract-structured" \
  -F "file=@documento.pdf"
```

**Respuesta:**
```json
{
  "success": true,
  "filename": "documento.pdf",
  "text": "...",
  "numeros_identificacion": {
    "CC": ["12345678"],
    "NIT": ["901234567-8"]
  },
  "fechas": {
    "DD/MM/YYYY": ["15/03/2026"]
  },
  "correos": ["contacto@ejemplo.com"],
  "telefonos": {
    "Celular": ["3001234567"]
  },
  "direcciones": ["Calle 123 # 45-67"],
  "codigos": {
    "Factura": ["FVE-2026-001"]
  }
}
```

## Integración con Laravel

En el `.env` del backend:

```env
OCR_SERVICE_URL=http://localhost:5000
OCR_ENABLED=true
OCR_SERVICE_TOKEN=   # secreto aleatorio largo, p. ej. php -r "echo bin2hex(random_bytes(32));"
OCR_SERVICE_HOST=127.0.0.1
```

En `config/services.php`:

```php
'ocr' => [
    'url' => env('OCR_SERVICE_URL', 'http://localhost:5000'),
    'enabled' => env('OCR_ENABLED', false),
],
```

## Docker (opcional)

```dockerfile
FROM python:3.10-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .
EXPOSE 5000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
```

## Troubleshooting

### Error de memoria con PDFs grandes

Limita el número de páginas procesadas en `main.py`:

```python
images = convert_from_bytes(content, first_page=1, last_page=10)  # máx 10 páginas
```

### PaddleOCR lento en primera ejecución

La primera ejecución descarga modelos (~150MB). Esto es normal.

### Error "Could not load library"

Si tienes error con CUDA, asegúrate de que `paddlepaddle-gpu` esté bien instalado o usa CPU:

```python
ocr_engine = PaddleOCR(use_gpu=False, lang='es')
```
