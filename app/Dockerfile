FROM himanshuramavat/php-ci:8.4

# libreoffice-writer: docx -> pdf export (ТЗ 4.4, "доступна выгрузка и в docx, и в pdf").
# pdftk-java: fills AcroForm fields in pdf templates (ТЗ 6, "разметка полей формы").
RUN apt-get update \
    && apt-get install -y --no-install-recommends libreoffice-writer pdftk-java \
    && rm -rf /var/lib/apt/lists/*

# Default opcache limits are too small for Laravel + vendor; raise them so
# nothing falls out of cache and needs recompiling mid-session.
RUN { \
        echo "opcache.memory_consumption=256"; \
        echo "opcache.max_accelerated_files=20000"; \
    } > /usr/local/etc/php/conf.d/zz-opcache-dev.ini

# Large templates are part of the acceptance and stress-test scenarios.
RUN { \
        echo "upload_max_filesize=50M"; \
        echo "post_max_size=55M"; \
        echo "memory_limit=512M"; \
    } > /usr/local/etc/php/conf.d/zz-template-upload.ini
