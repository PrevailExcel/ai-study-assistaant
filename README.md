# Setup ChromaDB as Vector Store

```bash
docker pull chromadb/chroma

docker run -d \
  --name chromadb \
  -p 8000:8000 \
  chromadb/chroma

```


# PHP packages
composer require inspector-apm/neuron-ai
composer require smalot/pdfparser
composer require phpoffice/phppresentation
composer require phpoffice/phpword  
composer require thiagoalessio/tesseract_ocr

# System dependencies
# For OCR
sudo apt install tesseract-ocr

# For video/audio processing
sudo apt install ffmpeg

# For image processing
sudo apt install imagemagick