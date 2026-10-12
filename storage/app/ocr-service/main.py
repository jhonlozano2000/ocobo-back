"""
OCR Microservice con PaddleOCR
Sirve para extraer texto de imágenes, PDFs y documentos Office
"""

import os
import io
import hmac
import logging
import re
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, UploadFile, File, HTTPException, Request
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse
from PIL import Image
import numpy as np

# M12-03 / M12-05: límites para que un documento no acapare el servicio o genere zip bomb
MAX_HOJAS_EXCEL = 20
MAX_FILAS_POR_HOJA = 5000
MAX_CARACTERES = 1_000_000
MAX_UPLOAD_SIZE = 50 * 1024 * 1024  # 50 MB máximo por archivo

# Configurar logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="OCOBO OCR Service",
    description="Microservicio OCR para extracción de texto de documentos",
    version="1.0.0"
)

# M12-02: solo Laravel (servidor a servidor) consume el servicio, así que no se habilita CORS.
# Las extracciones exigen el secreto compartido OCR_SERVICE_TOKEN en la cabecera X-OCR-Token;
# sin secreto configurado se rechazan todas.
OCR_TOKEN = os.environ.get("OCR_SERVICE_TOKEN", "")
RUTAS_PUBLICAS = {"/", "/health"}


@app.middleware("http")
async def verificar_token(request: Request, call_next):
    if request.url.path not in RUTAS_PUBLICAS:
        recibido = request.headers.get("X-OCR-Token", "")
        if not OCR_TOKEN or not hmac.compare_digest(recibido.encode(), OCR_TOKEN.encode()):
            return JSONResponse(status_code=401, content={"detail": "No autorizado"})
    return await call_next(request)

# Inicializar PaddleOCR (lazy loading)
ocr_engine = None

def get_ocr_engine():
    """Inicializa PaddleOCR solo cuando se necesita"""
    global ocr_engine
    if ocr_engine is None:
        logger.info("Inicializando PaddleOCR...")
        try:
            from paddleocr import PaddleOCR
            # oneDNN falla en paddlepaddle 3.3 sobre CPU Windows
            # ("ConvertPirAttribute2RuntimeAttribute not support"); OCR_ENABLE_MKLDNN=1 lo reactiva
            ocr_engine = PaddleOCR(
                use_textline_orientation=True,
                lang='es',
                enable_mkldnn=os.environ.get("OCR_ENABLE_MKLDNN", "0") == "1",
            )
            logger.info("PaddleOCR inicializado correctamente")
        except Exception as e:
            logger.error(f"Error al inicializar PaddleOCR: {e}")
            raise e
    return ocr_engine


@app.on_event("startup")
async def startup_event():
    """Se ejecuta al iniciar el servicio"""
    logger.info("🚀 OCR Service iniciando...")
    try:
        get_ocr_engine()
        logger.info("✅ OCR Service listo")
    except Exception as e:
        logger.warning(f"⚠️ PaddleOCR no disponible: {e}")
        logger.warning("El servicio funcionará en modo limitado")


@app.get("/")
async def root():
    """Endpoint de verificación"""
    return {
        "service": "OCOBO OCR Service",
        "version": "1.0.0",
        "status": "running"
    }


@app.get("/health")
async def health_check():
    """Endpoint de salud del servicio"""
    try:
        get_ocr_engine()
        ocr_status = "available"
    except:
        ocr_status = "unavailable"

    return {
        "status": "healthy",
        "ocr_engine": ocr_status
    }


@app.post("/ocr/extract-text")
async def extract_text(file: UploadFile = File(...)):
    """
    Extrae texto de una imagen o PDF
    """
    try:
        logger.info(f"Procesando archivo: {file.filename}")

        # Leer contenido del archivo con límite (M12-05)
        content = await file.read(MAX_UPLOAD_SIZE + 1)
        if len(content) > MAX_UPLOAD_SIZE:
            raise HTTPException(status_code=413, detail="El archivo excede el límite máximo de 50 MB")

        # Determinar tipo de archivo
        filename = file.filename.lower() if file.filename else ""

        # M12-03: la extracción es CPU intensiva; fuera del event loop para no bloquear /health
        text = await run_in_threadpool(extraer_texto, content, filename, file.filename)
        text = text[:MAX_CARACTERES]

        logger.info(f"Texto extraído: {len(text)} caracteres")

        return {
            "success": True,
            "filename": file.filename,
            "text": text,
            "char_count": len(text)
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error al procesar archivo: {e}")
        raise HTTPException(status_code=500, detail=str(e))


def extraer_texto(content: bytes, filename: str, nombre_original: Optional[str]) -> str:
    """Elige el extractor según la extensión del archivo"""
    # Imágenes
    if filename.endswith(('.png', '.jpg', '.jpeg', '.bmp', '.tiff', '.tif')):
        text = process_image(content)
    # PDFs
    elif filename.endswith('.pdf'):
        text = process_pdf(content)
    # Word documents
    elif filename.endswith(('.doc', '.docx')):
        text = process_word(content, filename)
    # Excel spreadsheets
    elif filename.endswith(('.xls', '.xlsx')):
        text = process_excel(content, filename)
    # PowerPoint presentations
    elif filename.endswith(('.ppt', '.pptx')):
        text = process_powerpoint(content, filename)
    # Text files
    elif filename.endswith(('.txt', '.csv')):
        text = content.decode('utf-8', errors='ignore')
    # OpenDocument formats
    elif filename.endswith(('.odt', '.ods', '.odp')):
        text = process_opendocument(content, filename)
    else:
        raise HTTPException(
            status_code=400,
            detail=f"Tipo de archivo no soportado: {nombre_original}"
        )

    return text


def process_image(content: bytes) -> str:
    """Procesa una imagen y extrae texto"""
    try:
        # Convertir bytes a imagen
        image = Image.open(io.BytesIO(content))

        # Convertir a RGB si es necesario
        if image.mode != 'RGB':
            image = image.convert('RGB')

        return "\n".join(ocr_lines(np.array(image)))

    except Exception as e:
        logger.error(f"Error en process_image: {e}")
        return ""


def ocr_lines(img_array) -> list:
    """Líneas reconocidas por PaddleOCR 3 (`predict` → `rec_texts`)."""
    result = get_ocr_engine().predict(img_array)
    if not result:
        return []
    return [texto for texto in (result[0].get("rec_texts") or []) if texto and texto.strip()]


def process_pdf(content: bytes) -> str:
    """
    Procesa un PDF con PyMuPDF (máx. 5 páginas): usa el texto embebido de cada página y,
    si la página no lo tiene (escaneada), la renderiza y la pasa por OCR.
    """
    try:
        import pymupdf

        doc = pymupdf.open(stream=content, filetype="pdf")

        all_text = []
        max_pages = min(5, len(doc))  # Limitar a 5 páginas

        for page_num in range(max_pages):
            page = doc[page_num]
            text = page.get_text().strip()
            if not text:
                logger.info(f"Página {page_num + 1} sin texto embebido: aplicando OCR")
                pix = page.get_pixmap(dpi=200)
                img_array = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
                if pix.n == 4:
                    img_array = img_array[:, :, :3]
                text = "\n".join(ocr_lines(img_array))
            if text:
                all_text.append(f"--- Página {page_num + 1} ---")
                all_text.append(text)

        return "\n".join(all_text)

    except Exception as e:
        logger.error(f"Error en process_pdf: {e}")
        return ""


def process_word(content: bytes, filename: str) -> str:
    """Extrae texto de documentos Word (.doc, .docx)"""
    try:
        from docx import Document

        doc = Document(io.BytesIO(content))
        paragraphs = [p.text for p in doc.paragraphs if p.text.strip()]

        tables_text = []
        for table in doc.tables:
            for row in table.rows:
                for cell in row.cells:
                    if cell.text.strip():
                        tables_text.append(cell.text)

        all_text = paragraphs + tables_text
        return "\n".join(all_text)

    except ImportError:
        logger.warning("python-docx no instalado")
        return ""
    except Exception as e:
        logger.error(f"Error procesando Word: {e}")
        return ""


def process_excel(content: bytes, filename: str) -> str:
    """Extrae texto de hojas de cálculo Excel (.xls, .xlsx)"""
    try:
        import openpyxl

        wb = openpyxl.load_workbook(io.BytesIO(content), data_only=True, read_only=True)
        all_text = []

        for sheet_name in wb.sheetnames[:MAX_HOJAS_EXCEL]:
            ws = wb[sheet_name]
            all_text.append(f"--- Hoja: {sheet_name} ---")

            for row in ws.iter_rows(values_only=True, max_row=MAX_FILAS_POR_HOJA):
                row_text = " | ".join([str(cell) if cell is not None else "" for cell in row])
                if row_text.strip():
                    all_text.append(row_text)

        return "\n".join(all_text)

    except ImportError:
        logger.warning("openpyxl no instalado")
        return ""
    except Exception as e:
        logger.error(f"Error procesando Excel: {e}")
        return ""


def process_powerpoint(content: bytes, filename: str) -> str:
    """Extrae texto de presentaciones PowerPoint (.ppt, .pptx)"""
    try:
        from pptx import Presentation

        prs = Presentation(io.BytesIO(content))
        all_text = []

        for slide_num, slide in enumerate(prs.slides, 1):
            all_text.append(f"--- Diapositiva {slide_num} ---")

            for shape in slide.shapes:
                if hasattr(shape, "text") and shape.text.strip():
                    all_text.append(shape.text)

                if hasattr(shape, "table"):
                    for row in shape.table.rows:
                        for cell in row.cells:
                            if cell.text.strip():
                                all_text.append(cell.text)

        return "\n".join(all_text)

    except ImportError:
        logger.warning("python-pptx no instalado")
        return ""
    except Exception as e:
        logger.error(f"Error procesando PowerPoint: {e}")
        return ""


def process_opendocument(content: bytes, filename: str) -> str:
    """Extrae texto de formatos OpenDocument (.odt, .ods, .odp) con mitigación de zip bomb (M12-05)"""
    try:
        import zipfile

        MAX_UNCOMPRESSED_TOTAL = 50 * 1024 * 1024  # 50 MB
        MAX_XML_SIZE = 20 * 1024 * 1024  # 20 MB

        with zipfile.ZipFile(io.BytesIO(content)) as z:
            total_uncompressed = sum(info.file_size for info in z.infolist())
            if total_uncompressed > MAX_UNCOMPRESSED_TOTAL:
                logger.warning("Posible zip bomb detectado en OpenDocument: excede 50MB descomprimido")
                return ""

            if 'content.xml' in z.namelist():
                xml_info = z.getinfo('content.xml')
                if xml_info.file_size > MAX_XML_SIZE:
                    logger.warning("content.xml excede el límite permitido de 20MB")
                    return ""

                with z.open('content.xml') as f:
                    import xml.etree.ElementTree as ET
                    text_parts = []
                    total_chars = 0
                    for event, elem in ET.iterparse(f, events=('end',)):
                        if elem.tag.endswith(('p', 'h', 'td')):
                            if elem.text and elem.text.strip():
                                text_parts.append(elem.text.strip())
                                total_chars += len(elem.text)
                                if total_chars > MAX_CARACTERES:
                                    break
                        elem.clear()

                    return "\n".join(text_parts)

        return ""

    except Exception as e:
        logger.error(f"Error procesando OpenDocument: {e}")
        return ""


@app.post("/ocr/extract-structured")
async def extract_structured(file: UploadFile = File(...)):
    """
    Extrae texto y datos estructurados (números, fechas, etc.)
    """
    try:
        # Primero extraer texto con límite (M12-05)
        content = await file.read(MAX_UPLOAD_SIZE + 1)
        if len(content) > MAX_UPLOAD_SIZE:
            raise HTTPException(status_code=413, detail="El archivo excede el límite máximo de 50 MB")
        filename = file.filename.lower() if file.filename else ""

        if filename.endswith(('.png', '.jpg', '.jpeg', '.bmp', '.tiff', '.tif')):
            text = process_image(content)
        elif filename.endswith('.pdf'):
            text = process_pdf(content)
        elif filename.endswith(('.doc', '.docx')):
            text = process_word(content, filename)
        elif filename.endswith(('.xls', '.xlsx')):
            text = process_excel(content, filename)
        elif filename.endswith(('.ppt', '.pptx')):
            text = process_powerpoint(content, filename)
        elif filename.endswith(('.txt', '.csv', '.odt', '.ods', '.odp')):
            if filename.endswith(('.odt', '.ods', '.odp')):
                text = process_opendocument(content, filename)
            else:
                text = content.decode('utf-8', errors='ignore')
        else:
            raise HTTPException(status_code=400, detail="Tipo no soportado")

        # Extraer datos estructurados con regex
        structured_data = {
            "text": text,
            "numeros_identificacion": extract_numeros(text),
            "fechas": extract_fechas(text),
            "correos": extract_correos(text),
            "telefonos": extract_telefonos(text),
            "direcciones": extract_direcciones(text),
            "codigos": extract_codigos(text),
        }

        return {
            "success": True,
            "filename": file.filename,
            **structured_data
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


def extract_numeros(text: str) -> dict:
    """Extrae números de identificación"""
    patterns = {
        "CC": r'\bCC[:\s]*(\d{6,12})\b',
        "NIT": r'\bNIT[:\s]*(\d{6,12}(?:\-\d)?)\b',
        "RC": r'\bRC[:\s]*(\d{6,12})\b',
        "CE": r'\bCE[:\s]*([A-Z0-9]{6,12})\b',
        "TI": r'\bTI[:\s]*(\d{6,12})\b',
    }

    results = {}
    for key, pattern in patterns.items():
        matches = re.findall(pattern, text, re.IGNORECASE)
        if matches:
            results[key] = list(set(matches))

    return results


def extract_fechas(text: str) -> dict:
    """Extrae fechas"""
    patterns = {
        "DD/MM/YYYY": r'\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b',
        "YYYY-MM-DD": r'\b(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})\b',
        "Mes DD YYYY": r'\b([A-Za-z]+ \d{1,2},? \d{4})\b',
    }

    results = {}
    for key, pattern in patterns.items():
        matches = re.findall(pattern, text)
        if matches:
            results[key] = list(set(matches))

    return results


def extract_correos(text: str) -> list:
    """Extrae correos electrónicos"""
    pattern = r'[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}'
    return list(set(re.findall(pattern, text)))


def extract_telefonos(text: str) -> dict:
    """Extrae números de teléfono"""
    patterns = {
        "Celular": r'\b(3\d{2}[\s\.\-]?\d{3}[\s\.\-]?\d{4})\b',
        "Fijo": r'\b(\d{3}[\s\.\-]?\d{3}[\s\.\-]?\d{4})\b',
        "ConPrefijo": r'\b(\+\d{1,3}[\s\.\-]?\d{1,14})\b',
    }

    results = {}
    for key, pattern in patterns.items():
        matches = re.findall(pattern, text)
        if matches:
            results[key] = list(set(matches))

    return results


def extract_direcciones(text: str) -> list:
    """Extrae direcciones físicas"""
    pattern = r'\b(Calle|Cra|Carr|Av|Avenida|Diagonal|Cl|Cr|Kr|#|No|Nro)\s*\d+[A-Z]?[\s\,\.\-#\d]*[A-Z]?[A-Z]?\b'
    matches = re.findall(pattern, text, re.IGNORECASE)
    return list(set(matches))


def extract_codigos(text: str) -> dict:
    """Extrae códigos diversos"""
    patterns = {
        "Factura": r'\b[Ff]actura[:\s]*(\d+)\b',
        "Contrato": r'\b[Cc]ontrato[:\s]*(\d+)\b',
        "Guía": r'\b[Gg]u(?:í|i)a[:\s]*([A-Z0-9\-]+)\b',
        "Orden": r'\b[Oo]rden[:\s]*(\d+)\b',
        "Radicado": r'\b[Rr]adicado[:\s]*(\d+)\b',
    }

    results = {}
    for key, pattern in patterns.items():
        matches = re.findall(pattern, text)
        if matches:
            results[key] = list(set(matches))

    return results


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host=os.environ.get("OCR_SERVICE_HOST", "127.0.0.1"), port=5000)
