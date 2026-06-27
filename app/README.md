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
Там же можно отправлять запросы прямо из браузера (кнопка "Try it out").

## Быстрый старт

1. `docker compose up` (см. выше).
2. Зарегистрироваться: `POST /api/register` с `name`, `email`, `password`.
   В ответе будет `token` — он нужен в заголовке `Authorization: Bearer <token>`
   для всех остальных запросов.
3. По умолчанию новый пользователь получает роль `user`. Для загрузки
   шаблонов нужна роль `methodologist` или `admin`, а её может назначить
   только администратор через `PUT /api/users/{id}`. Самого первого
   администратора в системе взять неоткуда — назначается вручную:
   ```
   docker exec -w /var/www/app docs-app php artisan tinker --execute="App\Models\User::where('email','твой@email')->update(['role'=>'admin']);"
   ```
4. Дальше обычный сценарий: методолог загружает шаблон (`POST /api/templates`,
   multipart с полем `file`) → `POST /api/templates/{id}/variables/extract` →
   `POST /api/templates/{id}/publish` → пользователь создаёт документ
   (`POST /api/templates/{id}/documents`) → скачивает (`GET /api/documents/{id}/download`).

## Тесты

```
docker exec -w /var/www/app docs-app php artisan test
```

Используется sqlite в памяти (настроено в `phpunit.xml`), реальная база
для разработки не трогается.

## Интеграция с фронтендом

CORS открыт по умолчанию (`Access-Control-Allow-Origin: *`), так что
фронтенд на любом порту (например, Vite на `5173`) может обращаться к
`http://localhost:8000/api` без дополнительной настройки бэкенда.

## Памятка: что может пользователь

В системе три роли, каждая видит и может делать своё.

**Пользователь (`user`)** — роль по умолчанию после регистрации:
- видит только опубликованные шаблоны (`GET /api/templates`);
- создаёт документ по шаблону, заполнив форму значений
  (`POST /api/templates/{id}/documents`);
- видит и скачивает **только свои** документы (`GET /api/documents`,
  `GET /api/documents/{id}/download`, можно с `?format=pdf`, если шаблон docx);
- может посмотреть, какими значениями был собран его документ, и создать
  новый на основе тех же значений (`GET /api/documents/{id}`).

**Методолог (`methodologist`)**:
- всё то же, что пользователь, плюс:
- загружает шаблоны docx/pdf (`POST /api/templates`);
- загружает новую версию существующего шаблона, не затирая старую
  (`POST /api/templates/{id}/versions`);
- распознаёт переменные/поля шаблона (`POST /api/templates/{id}/variables/extract`)
  и донастраивает их вручную — подпись, тип, обязательность, подсказка
  (`POST/PUT/DELETE /api/templates/{id}/variables`, `/api/variables/{id}`);
- публикует шаблон, когда он готов (`POST /api/templates/{id}/publish`) —
  до публикации обычные пользователи шаблон не видят;
- видит чужие документы и историю по всем пользователям, с фильтрами по
  шаблону/автору/дате.

**Администратор (`admin`)** — всё то же, что методолог, плюс:
- управляет пользователями: список, поиск, смена роли, удаление
  (`/api/users`, `/api/users/{id}`);
- может удалить шаблон целиком (`DELETE /api/templates/{id}`).

Первого администратора система выдать сама не может — назначается вручную
через `tinker`, см. раздел "Быстрый старт" выше.