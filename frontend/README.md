# Шаблонизатор договоров: фронтенд MVP

React-прототип фронтенда для задачи ТЗ-02. Сейчас проект работает без бекенда: данные шаблонов, переменных и истории документов хранятся в `localStorage`.

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

## Что уже есть

- каталог шаблонов с фильтрами;
- загрузка мокового `docx/pdf` шаблона;
- экран настройки найденных переменных;
- динамическая форма создания документа;
- локальная валидация обязательных полей, чисел и сумм;
- предпросмотр введённых данных;
- история созданных документов;
- моковое скачивание результата.

## Где подключать бекенд

Слой обмена с данными находится в `src/api/mockApi.ts`.

Когда Laravel API будет готов, функции из этого файла можно заменить на HTTP-запросы:

- `getTemplates`;
- `uploadTemplate`;
- `getTemplateVariables`;
- `saveTemplateVariables`;
- `generateDocument`;
- `getDocuments`.

Адаптеры под согласованный формат бекенда лежат в `src/api/backendAdapters.ts`.

## Согласованный API

Базовый набор ручек:

- `GET /api/templates`;
- `POST /api/templates`;
- `GET /api/templates/{id}`;
- `PUT /api/templates/{id}`;
- `DELETE /api/templates/{id}`;
- `POST /api/templates/{id}/upload`;
- `GET /api/documents`;
- `POST /api/documents`.

`GET /api/templates/{id}` должен возвращать шаблон вместе с `variables`.

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
