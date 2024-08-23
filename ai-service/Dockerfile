FROM python:3.8-slim

WORKDIR /app

COPY . .

RUN apt-get update && apt-get install -y tesseract-ocr && rm -rf /var/lib/apt/lists/* 

RUN pip install --no-cache-dir flask torch transformers PyMuPDF pytesseract sentence-transformers 


CMD ["flask", "run", "--host=0.0.0.0"]
