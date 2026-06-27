FROM himanshuramavat/php-ci:8.4

# libreoffice-writer: docx -> pdf export (ТЗ 4.4, "доступна выгрузка и в docx, и в pdf").
# pdftk-java: fills AcroForm fields in pdf templates (ТЗ 6, "разметка полей формы").
RUN apt-get update \
    && apt-get install -y --no-install-recommends libreoffice-writer pdftk-java \
    && rm -rf /var/lib/apt/lists/*
