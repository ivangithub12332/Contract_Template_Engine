# Шаблонизатор договоров: frontend

React-фронтенд для ТЗ-02. Фронт лежит в папке `frontend/` и работает с Laravel API из папки `app/`.

## Требования

- Docker Desktop;
- Node.js 20+ или 24+;
- npm;
- запущенный backend на `http://localhost:8000/api`.

## Запуск backend

Из папки `app/`:

```bash
docker compose up -d
```

Backend:

```text
http://localhost:8000
```

Swagger:

```text
http://localhost:8000/api-docs
```

Первый запуск может занять несколько минут: Docker скачивает образы, ставит зависимости, создаёт `.env`, генерирует `APP_KEY` и запускает миграции.

## Запуск frontend

Из папки `frontend/`:

```bash
npm install
npm run dev -- --host 127.0.0.1 --port 5173
```

В Windows PowerShell лучше использовать `npm.cmd`:

```bash
npm.cmd install
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

Фронт:

```text
http://127.0.0.1:5173
```

Не закрывайте терминал с Vite, пока тестируете интерфейс.

Если API запускается на другом адресе:

```bash
VITE_API_BASE_URL=http://localhost:8000/api
```

## Авторизация

Фронт использует Laravel Sanctum token.

Новый пользователь после регистрации получает роль `user`. Для загрузки и настройки шаблонов нужна роль `methodologist` или `admin`.
Страница `Пользователи` доступна только `admin` и позволяет менять роли пользователей через интерфейс.

Назначить роль локально можно так:

```bash
docker exec -w /var/www/app docs-app php artisan tinker --execute="App\Models\User::where('email','user@mail.ru')->update(['role'=>'admin']);"
```

## Что реализовано

- вход и регистрация;
- работа с Bearer token;
- отображение роли пользователя;
- каталог шаблонов;
- фильтры шаблонов;
- удаление шаблонов для `admin`;
- загрузка `docx/pdf` шаблона;
- распознавание переменных после загрузки;
- загрузка новой версии шаблона;
- настройка переменных;
- публикация шаблона;
- динамическая форма создания документа;
- поддержка типов `text`, `textarea`, `number`, `currency`, `date`, `select`, `boolean`, `table`;
- предпросмотр введённых значений;
- генерация документа;
- скачивание результата в исходном формате;
- скачивание `pdf` для `docx` документа;
- история документов;
- повторное создание документа с подстановкой старых значений;
- просмотр пользователей и смена ролей для `admin`;
- обработка загрузки, пустых состояний и API-ошибок.

## Основной сценарий проверки

1. Зарегистрироваться или войти.
2. Если пользователь не `admin`/`methodologist`, выдать роль через `tinker`.
3. Открыть `Загрузка`.
4. Загрузить `docx` или `pdf` шаблон.
5. Открыть `Переменные`.
6. Проверить найденные переменные.
7. Настроить подписи, типы, обязательность, подсказки.
8. Нажать `Сохранить настройки`.
9. Нажать `Опубликовать`.
10. Открыть `Создать документ`.
11. Выбрать опубликованный шаблон.
12. Заполнить форму.
13. Проверить значения через `Показать предпросмотр`.
14. Нажать `Сгенерировать документ`.
15. Проверить скачивание файла.
16. Открыть `История`.
17. Нажать `Повторить` у созданного документа.
18. Проверить, что форма открылась с прежними значениями.
19. Если пользователь `admin`, открыть `Пользователи` и проверить смену роли другого пользователя.
20. Если пользователь `admin`, в `Шаблоны` проверить удаление тестового шаблона через кнопку `Удалить`.

## API

Base URL:

```text
http://localhost:8000/api
```

Общие заголовки:

```text
Accept: application/json
Authorization: Bearer <token>
```

Авторизация:

- `POST /api/register`;
- `POST /api/login`;
- `GET /api/me`;
- `POST /api/logout`.

Шаблоны:

- `GET /api/templates`;
- `POST /api/templates`;
- `GET /api/templates/{id}`;
- `PUT /api/templates/{id}`;
- `DELETE /api/templates/{id}` (`admin`);
- `POST /api/templates/{id}/versions`;
- `POST /api/templates/{id}/variables/extract`;
- `POST /api/templates/{id}/publish`.

Переменные:

- `POST /api/templates/{id}/variables`;
- `PUT /api/variables/{id}`;
- `DELETE /api/variables/{id}`.

Документы:

- `POST /api/templates/{id}/documents`;
- `GET /api/documents`;
- `GET /api/documents/{id}`;
- `GET /api/documents/{id}/download`;
- `GET /api/documents/{id}/download?format=pdf`.

Пользователи:

- `GET /api/users` (`admin`);
- `PUT /api/users/{id}` (`admin`);
- `DELETE /api/users/{id}` (`admin`, во фронте не используется).

## Формат переменных

Backend отдаёт `snake_case`, фронт переводит его через `src/api/backendAdapters.ts`.

Маппинг:

- `key` -> `name`;
- `default_value` -> `defaultValue`;
- `variables_count` -> `variableCount`;
- `created_at` -> `createdAt`.

Типы:

- `text` - текст;
- `textarea` - многострочный текст;
- `number` - число;
- `currency` - сумма;
- `date` - дата;
- `select` - выбор из списка;
- `boolean` - логический признак;
- `table` - повторяющийся блок / таблица.

## Проверка сборки

```bash
npm.cmd run build
```

Сборка должна завершаться без ошибок TypeScript и Vite.
