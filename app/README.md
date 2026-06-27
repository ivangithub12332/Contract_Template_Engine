# Шаблонизатор договоров

## Запуск

```
docker compose up
```

Контейнер сам поставит зависимости через composer, создаст `.env` из
`.env.example`, сгенерирует `APP_KEY`, прогонит миграции и поднимет
сервер на [http://localhost:8000](http://localhost:8000). Повторный
запуск идемпотентен — шаги пропускаются, если уже выполнены.

API доступен по адресу `http://localhost:8000/api`.

## Документация API

OpenAPI-спецификация лежит в [`openapi.yaml`](openapi.yaml). Интерактивная
документация (Swagger UI) — на [http://localhost:8000/api-docs](http://localhost:8000/api-docs).