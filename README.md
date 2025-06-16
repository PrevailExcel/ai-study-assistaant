# Setup ChromaDB as Vector Store

```bash
docker pull chromadb/chroma

docker run -d \
  --name chromadb \
  -p 8000:8000 \
  chromadb/chroma

```


# PHP packages
```bash
composer require inspector-apm/neuron-ai
composer require smalot/pdfparser
composer require phpoffice/phppresentation
composer require phpoffice/phpword  
composer require thiagoalessio/tesseract_ocr
```

# System dependencies
## For OCR
sudo apt install tesseract-ocr

## For video/audio processing
sudo apt install ffmpeg

## For image processing
sudo apt install imagemagick

# .ENV
CHROMA_BASE_URL=http://localhost:8000
CHROMA_COLLECTION_NAME=study_materials
ANTHROPIC_API_KEY=your_anthropic_key
OPENAI_API_KEY=your_openai_key_for_embeddings
GEMINI_API_KEY=your_gemmini_key