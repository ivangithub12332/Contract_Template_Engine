# Шаблонизатор договоров: фронтенд MVP

React-фронтенд для задачи ТЗ-02. Приложение подключено к Laravel API из ветки `develop`.

## Запуск

```bash
npm install
npm run dev
```

Если npm пытается писать в системный кеш, используйте локальный кеш проекта:

```bash
npm install --cache .npm-cache
npm run dev --cache .npm-cache
```

В Windows PowerShell после установки Node может блокироваться `npm.ps1`. В таком случае запускайте команды через `npm.cmd`:

```bash
npm.cmd install --cache .npm-cache
npm.cmd run dev -- --port 5173
```

Приложение открывается на `http://127.0.0.1:5173`.

По умолчанию API берётся с `http://localhost:8000/api`. Если нужен другой адрес, задайте переменную:

```bash
VITE_API_BASE_URL=http://localhost:8000/api
```

## Что уже есть

- каталог шаблонов с фильтрами;
- вход и регистрация через Laravel Sanctum token;
- каталог шаблонов с учётом роли пользователя;
- загрузка `docx/pdf` шаблона;
- экран настройки найденных переменных;
- публикация шаблона;
- динамическая форма создания документа;
- локальная валидация обязательных полей, чисел и сумм;
- предпросмотр введённых данных;
- история созданных документов;
- скачивание результата через API.

## API-слой

Слой обмена с бекендом находится в `src/api/backendApi.ts`.

- `getTemplates`;
- `uploadTemplate`;
- `getTemplateVariables`;
- `saveTemplateVariables`;
- `generateDocument`;
- `getDocuments`.

Адаптеры формата бекенда лежат в `src/api/backendAdapters.ts`.

## Используемый API

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
- `DELETE /api/templates/{id}`;
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

`GET /api/templates/{id}` возвращает шаблон вместе с `variables`.

Согласованные типы переменных:

- `text` - текст;
- `textarea` - многострочный текст;
- `number` - число;
- `currency` - сумма;
- `date` - дата;
- `select` - выбор из списка;
- `boolean` - логический признак;
- `table` - повторяющийся блок / таблица.

Фронт ожидает от бекенда `snake_case` и переводит его через адаптеры:

- `key` -> `name`;
- `default_value` -> `defaultValue`;
- `variables_count` -> `variableCount`;
- `created_at` -> `createdAt`.
