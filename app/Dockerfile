FROM himanshuramavat/php-ci:8.4

# Needed to export docx documents as pdf (ТЗ 4.4: "доступна выгрузка и в docx, и в pdf").
RUN apt-get update \
    && apt-get install -y --no-install-recommends libreoffice-writer \
    && rm -rf /var/lib/apt/lists/*
