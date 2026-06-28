# Contract Template Engine / Шаблонизатор договоров

Полная инструкция по установке, запуску и проверке проекта для участников, которые начинают с пустого компьютера.

Проект состоит из двух частей:

- `app/` - backend на Laravel, API, база данных PostgreSQL, генерация документов.
- `frontend/` - frontend на React + Vite, интерфейс для работы с шаблонами и документами.

Backend запускается через Docker. Frontend запускается через Node.js.

## Что умеет проект

- регистрация и вход пользователей;
- роли пользователей: `user`, `methodologist`, `admin`;
- загрузка шаблонов `docx` и `pdf`;
- автоматическое распознавание переменных в шаблонах;
- настройка переменных: подпись, тип, обязательность, подсказка, значение по умолчанию;
- поддержка типов переменных: текст, многострочный текст, число, сумма, дата, выбор из списка, логический признак, таблица;
- публикация шаблонов;
- создание документа по опубликованному шаблону;
- предпросмотр введённых данных перед генерацией;
- скачивание готового документа;
- скачивание `pdf` для `docx` документа;
- история созданных документов;
- повторное создание документа с прежними значениями;
- загрузка новой версии шаблона;
- удаление шаблонов для администратора;
- просмотр пользователей и смена ролей для администратора;
- Swagger-документация API.

## Что нужно установить

Для обычного запуска нужны три программы:

1. Git
2. Docker Desktop
3. Node.js

Если чего-то нет, сначала установите.

## Установка Git

Git нужен, чтобы скачать проект с GitHub и получать обновления.

1. Откройте сайт:

```text
https://git-scm.com/downloads
```

2. Скачайте Git for Windows.
3. Установите с настройками по умолчанию.
4. После установки откройте PowerShell и проверьте:

```powershell
git --version
```

Если команда показывает версию, Git установлен.

## Установка Docker Desktop

Docker нужен для backend и базы данных.

1. Откройте сайт:

```text
https://www.docker.com/products/docker-desktop/
```

2. Скачайте Docker Desktop for Windows.
3. Установите.
4. Перезагрузите компьютер, если установщик попросит.
5. Запустите Docker Desktop.
6. Дождитесь, пока Docker полностью запустится.

Проверка в PowerShell:

```powershell
docker --version
docker compose version
```

Если команды показывают версии, Docker работает.

Важно: Docker Desktop должен быть запущен перед запуском backend.

## Установка Node.js

Node.js нужен для frontend.

1. Откройте сайт:

```text
https://nodejs.org/
```

2. Скачайте LTS-версию.
3. Установите с настройками по умолчанию.
4. Проверьте в PowerShell:

```powershell
node --version
npm --version
```

Если команды показывают версии, Node.js и npm установлены.

В Windows PowerShell иногда удобнее писать `npm.cmd`, а не `npm`. В этой инструкции используется `npm.cmd`.

## Как скачать проект

Откройте PowerShell и перейдите в папку, где хотите хранить проект.

Пример:

```powershell
cd "D:\VSC codes\Практика"
```

Склонируйте репозиторий:

```powershell
git clone https://github.com/ivangithub12332/Contract_Template_Engine.git backend
```

Перейдите в папку проекта:

```powershell
cd "D:\VSC codes\Практика\backend"
```

Переключитесь на рабочую ветку `full-project`:

```powershell
git checkout full-project
```

Подтяните последние изменения:

```powershell
git pull origin full-project
```

## Структура проекта

```text
backend/
  app/                 Laravel backend
  frontend/            React frontend
  README.md            эта инструкция
  docker-compose.yml   старый compose-файл в корне, для запуска лучше использовать app/docker-compose.yml
```

Важно: запускать backend нужно из папки `app/`, а frontend из папки `frontend/`.

## Первый запуск backend

Backend запускается в Docker.

Откройте PowerShell:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker compose up -d
```

Первый запуск может занять несколько минут. Docker скачает образы, соберёт контейнер, установит зависимости, создаст `.env`, сгенерирует `APP_KEY`, выполнит миграции базы и запустит сервер.

Проверить контейнеры:

```powershell
docker ps
```

Должны быть контейнеры:

```text
docs-app
docs-db
```

Backend доступен здесь:

```text
http://localhost:8000
```

API доступен здесь:

```text
http://localhost:8000/api
```

Swagger доступен здесь:

```text
http://localhost:8000/api-docs
```

## Первый запуск frontend

Откройте второй PowerShell. Первый можно оставить для Docker-команд.

Перейдите в папку frontend:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
```

Установите зависимости:

```powershell
npm.cmd install
```

Запустите frontend:

```powershell
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

Откройте в браузере:

```text
http://127.0.0.1:5173
```

Терминал с frontend закрывать нельзя, пока вы тестируете интерфейс. Если закрыть терминал, сайт перестанет открываться.

Правильная актуальная версия frontend сверху показывает:

```text
API: Laravel / Sanctum
```

Если сверху написано `REACT MVP НА МОКОВЫХ ДАННЫХ`, запущена старая версия или не та папка.

## Как остановить проект

Frontend останавливается в терминале, где был запущен:

```text
Ctrl + C
```

Backend останавливается так:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker compose down
```

Команда `docker compose down` останавливает контейнеры, но данные обычно сохраняет.

Не используйте без необходимости:

```powershell
docker compose down -v
```

Флаг `-v` удаляет Docker volume. Можно потерять базу, пользователей, шаблоны и документы.

## Как запустить проект повторно

Если проект уже был установлен, повторный запуск короче.

Backend:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker compose up -d
```

Frontend:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

Открыть:

```text
http://127.0.0.1:5173
```

## Как обновить проект с GitHub

Остановите frontend через `Ctrl + C`.

Затем:

```powershell
cd "D:\VSC codes\Практика\backend"
git checkout full-project
git pull origin full-project
```

Если изменялись frontend-зависимости:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
npm.cmd install
```

После этого снова запустите backend и frontend.

## Регистрация и вход

При первом открытии frontend показывает экран входа.

Если аккаунта нет:

1. Нажмите `Создать аккаунт`.
2. Введите имя, email и пароль.
3. Зарегистрируйтесь.

Новый пользователь получает роль:

```text
user
```

Обычный `user` может создавать документы по опубликованным шаблонам, но не может загружать и настраивать шаблоны.

## Роли

В системе три роли.

### user

Обычный пользователь.

Может:

- видеть опубликованные шаблоны;
- создавать документы по опубликованным шаблонам;
- видеть только свои документы;
- скачивать свои документы;
- повторно создать документ по старым значениям.

Не может:

- загружать шаблоны;
- настраивать переменные;
- публиковать шаблоны;
- удалять шаблоны;
- управлять пользователями.

### methodologist

Шаблонизатор / методолог.

Может всё, что `user`, плюс:

- загружать шаблоны;
- распознавать переменные;
- настраивать переменные;
- загружать новые версии шаблонов;
- публиковать шаблоны;
- видеть историю документов.

Не может:

- удалять шаблоны;
- управлять пользователями.

### admin

Администратор.

Может всё, что `methodologist`, плюс:

- удалять шаблоны;
- смотреть список пользователей;
- менять роли пользователей.

## Как назначить первого администратора

Проблема: первый пользователь после регистрации всегда получает роль `user`.

Чтобы выдать себе `admin`, сначала зарегистрируйтесь через frontend, потом выполните команду:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker exec -w /var/www/app docs-app php artisan tinker --execute="App\Models\User::where('email','ВАШ_EMAIL')->update(['role'=>'admin']);"
```

Замените `ВАШ_EMAIL` на email, который указали при регистрации.

Пример:

```powershell
docker exec -w /var/www/app docs-app php artisan tinker --execute="App\Models\User::where('email','test@mail.ru')->update(['role'=>'admin']);"
```

После этого на frontend нажмите `Обновить` или выйдите и войдите снова.

## Где менять роли без командной строки

Когда у вас уже есть роль `admin`:

1. Откройте frontend.
2. Перейдите в раздел `Пользователи`.
3. Найдите пользователя.
4. Выберите ему роль: `Пользователь`, `Шаблонизатор`, `Администратор`.

Раздел `Пользователи` виден только администратору.

## Основной сценарий проверки

Этот сценарий можно дать тестировщику.

1. Запустить backend.
2. Запустить frontend.
3. Открыть `http://127.0.0.1:5173`.
4. Зарегистрироваться.
5. Выдать себе роль `admin` через команду `tinker`.
6. Нажать `Обновить` во frontend или перезайти.
7. Открыть раздел `Загрузка`.
8. Загрузить `docx` или `pdf` шаблон.
9. Открыть раздел `Переменные`.
10. Проверить, что переменные распознаны.
11. Настроить подписи, типы, обязательность и подсказки.
12. Нажать `Сохранить настройки`.
13. Нажать `Опубликовать`.
14. Открыть раздел `Создать документ`.
15. Выбрать опубликованный шаблон.
16. Заполнить форму.
17. Нажать `Показать предпросмотр`.
18. Проверить введённые данные.
19. Нажать `Сгенерировать документ`.
20. Проверить скачивание файла.
21. Открыть раздел `История`.
22. Скачать созданный документ.
23. Нажать `Повторить`.
24. Проверить, что форма открылась со старыми значениями.
25. Если роль `admin`, открыть `Пользователи` и проверить смену роли.
26. Если роль `admin`, в `Шаблоны` проверить удаление тестового шаблона.

## Как подготовить шаблон DOCX

В DOCX используются плейсхолдеры в двойных фигурных скобках.

Пример текста в Word:

```text
Договор № {{contract_number}}

г. {{city}}                                      {{contract_date}}

Заказчик: {{client_name}}
Сумма договора: {{total_amount}}
```

После загрузки backend попытается найти:

```text
contract_number
city
contract_date
client_name
total_amount
```

Правила:

- используйте латиницу, цифры и подчёркивания;
- не оставляйте пустые плейсхолдеры `{{}}`;
- следите, чтобы у каждого `{{` был закрывающий `}}`;
- лучше не разбивать один плейсхолдер разным форматированием внутри Word.

Хорошо:

```text
{{client_name}}
```

Плохо:

```text
{{ client name }}
{{}}
{{client_name}
```

## Как подготовить PDF

PDF должен быть формой с полями AcroForm.

Простой статичный PDF без полей не подойдёт для распознавания переменных.

Backend читает имена полей формы и делает из них переменные.

## Где хранятся данные

Если проект запущен локально, данные хранятся локально у вас в Docker.

В базе PostgreSQL хранятся:

- пользователи;
- роли;
- шаблоны;
- переменные;
- версии шаблонов;
- документы;
- значения документов.

Файлы хранятся в Laravel storage внутри контейнера/volume:

```text
storage/app/public/templates
storage/app/public/documents
```

Данные не отправляются на GitHub.

GitHub хранит только код проекта.

## Как посмотреть пользователей в базе

Если нужно быстро вывести список пользователей:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker exec docs-db psql -U docs_user -d docs_db -c "select id, name, email, role, created_at from users order by id;"
```

Пароли в базе не хранятся открытым текстом. Они хранятся как хэши. Узнать старый пароль нельзя, можно только создать новый аккаунт или сбросить пароль вручную через код/базу.

## Тестовые аккаунты

Если вы запускаете проект на той же локальной базе, где уже проводилось тестирование, можно использовать готовые аккаунты ниже.

Пароль не хранится в README, потому что репозиторий публичный. Его нужно получить у ответственного за тестирование или передать тестерам отдельно в личном чате.

### Рекомендуемые аккаунты для тестеров

Администратор:

```text
email: frontend-test-1782558496@test.local
role: admin
```

Шаблонизатор:

```text
email: target-flow-1782575165@test.local
role: methodologist
```

Для большинства проверок достаточно этих двух аккаунтов. Не создавайте много новых аккаунтов без необходимости.

### Полный список существующих тестовых аккаунтов

```text
Frontend Tester
email: frontend-test-1782558496@test.local
role: admin

Final Smoke
email: final-smoke-1782561521@test.local
role: admin

Admin Flow
email: admin-flow-1782575165@test.local
role: admin

Target Flow
email: target-flow-1782575165@test.local
role: methodologist
```

Важно: эти аккаунты лежат в локальной PostgreSQL-базе Docker. Они не создаются автоматически из GitHub.

На новом компьютере база будет пустая.

Рекомендуемый способ:

1. Зарегистрировать нового пользователя через frontend.
2. Выдать ему `admin` через команду `tinker`.
3. Остальных участников зарегистрировать через экран входа.
4. Через раздел `Пользователи` назначить им нужные роли.

## API

Base URL:

```text
http://localhost:8000/api
```

Swagger:

```text
http://localhost:8000/api-docs
```

OpenAPI-файл:

```text
app/openapi.yaml
```

Основные группы API:

- `POST /api/register` - регистрация;
- `POST /api/login` - вход;
- `GET /api/me` - текущий пользователь;
- `POST /api/logout` - выход;
- `GET /api/templates` - список шаблонов;
- `POST /api/templates` - загрузить шаблон;
- `GET /api/templates/{id}` - шаблон с переменными;
- `PUT /api/templates/{id}` - изменить шаблон;
- `DELETE /api/templates/{id}` - удалить шаблон, только `admin`;
- `POST /api/templates/{id}/versions` - загрузить новую версию;
- `POST /api/templates/{id}/variables/extract` - распознать переменные;
- `POST /api/templates/{id}/publish` - опубликовать шаблон;
- `PUT /api/variables/{id}` - изменить переменную;
- `DELETE /api/variables/{id}` - удалить переменную;
- `POST /api/templates/{id}/documents` - создать документ;
- `GET /api/documents` - история документов;
- `GET /api/documents/{id}` - документ со значениями;
- `GET /api/documents/{id}/download` - скачать документ;
- `GET /api/documents/{id}/download?format=pdf` - скачать PDF;
- `GET /api/users` - пользователи, только `admin`;
- `PUT /api/users/{id}` - изменить роль пользователя, только `admin`.

## Как frontend подключается к backend

По умолчанию frontend ходит сюда:

```text
http://localhost:8000/api
```

Это задано в:

```text
frontend/src/api/backendApi.ts
```

Если backend запускается на другом адресе, можно задать переменную:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
$env:VITE_API_BASE_URL="http://localhost:8000/api"
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

Обычно это не нужно.

## Проверка backend

Проверить, что backend отвечает:

```powershell
Invoke-WebRequest http://localhost:8000/api/templates
```

Если ответ `401 Unauthorized`, это нормально. Значит backend работает, но запрос без токена.

Swagger можно проверить в браузере:

```text
http://localhost:8000/api-docs
```

## Проверка frontend

Откройте:

```text
http://127.0.0.1:5173
```

Если браузер пишет, что сайт недоступен:

- проверьте, запущен ли терминал с `npm.cmd run dev`;
- проверьте, не закрыли ли frontend;
- проверьте, свободен ли порт `5173`;
- перезапустите frontend.

## Сборка frontend

Проверить, что frontend собирается без ошибок:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
npm.cmd run build
```

Если сборка прошла, TypeScript и Vite не нашли ошибок.

## Тесты backend

Backend-тесты запускаются так:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker exec -w /var/www/app docs-app php artisan test
```

Тесты используют sqlite в памяти и не должны портить рабочую PostgreSQL-базу.

## Частые проблемы

### Docker не запускается

Симптом:

```text
failed to connect to the docker API
```

Что делать:

1. Откройте Docker Desktop.
2. Дождитесь, пока он полностью загрузится.
3. Повторите команду.

### Порт 5173 занят

Симптом:

```text
Port 5173 is already in use
```

Что делать:

1. Найдите терминал, где уже запущен frontend.
2. Остановите его через `Ctrl + C`.
3. Запустите frontend снова.

Можно запустить на другом порту:

```powershell
npm.cmd run dev -- --host 127.0.0.1 --port 5174
```

Тогда открывать:

```text
http://127.0.0.1:5174
```

### Вижу старый моковый frontend

Если сверху написано:

```text
REACT MVP НА МОКОВЫХ ДАННЫХ
```

значит открыта старая версия.

Что сделать:

```powershell
cd "D:\VSC codes\Практика\backend"
git checkout full-project
git pull origin full-project
```

Потом заново:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

В браузере нажмите:

```text
Ctrl + F5
```

### Нет кнопки регистрации

Кнопка регистрации находится на экране входа и называется:

```text
Создать аккаунт
```

Если приложение сразу открыло каталог, значит в браузере остался токен старого входа.

Можно выйти кнопкой `Выйти`.

Или очистить токен через консоль браузера:

```javascript
localStorage.removeItem('contract_template_api_token')
location.reload()
```

### Backend отвечает 401

Это нормально для защищённых API без токена.

`401` означает:

```text
backend работает, но пользователь не авторизован
```

### Не вижу разделы Загрузка, Переменные, Пользователи

Проверьте роль.

- `user` не видит загрузку и настройку шаблонов;
- `methodologist` видит загрузку и переменные;
- `admin` видит всё, включая пользователей.

Выдать admin:

```powershell
docker exec -w /var/www/app docs-app php artisan tinker --execute="App\Models\User::where('email','ВАШ_EMAIL')->update(['role'=>'admin']);"
```

### После docker compose down пропали данные

Если была команда:

```powershell
docker compose down -v
```

то могли удалиться volumes с базой.

Обычная остановка должна быть:

```powershell
docker compose down
```

без `-v`.

### npm не запускается в PowerShell

Используйте:

```powershell
npm.cmd install
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

вместо:

```powershell
npm install
npm run dev
```

## Команды на каждый день

### Запустить всё

Backend:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker compose up -d
```

Frontend:

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

### Остановить всё

Frontend:

```text
Ctrl + C
```

Backend:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker compose down
```

### Обновить код

```powershell
cd "D:\VSC codes\Практика\backend"
git checkout full-project
git pull origin full-project
```

### Проверить сборку frontend

```powershell
cd "D:\VSC codes\Практика\backend\frontend"
npm.cmd run build
```

### Проверить тесты backend

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker exec -w /var/www/app docs-app php artisan test
```

## Что не надо делать

Не запускайте backend из корня проекта, если не понимаете зачем:

```powershell
cd "D:\VSC codes\Практика\backend"
docker compose up
```

Для актуального запуска используйте:

```powershell
cd "D:\VSC codes\Практика\backend\app"
docker compose up -d
```

Не удаляйте volumes без необходимости:

```powershell
docker compose down -v
```

Не коммитьте:

- `node_modules`;
- `frontend/dist`;
- `.env`;
- загруженные шаблоны;
- сгенерированные документы.

## Ссылки

Репозиторий:

```text
https://github.com/ivangithub12332/Contract_Template_Engine
```

Рабочая ветка:

```text
https://github.com/ivangithub12332/Contract_Template_Engine/tree/full-project
```

Frontend:

```text
http://127.0.0.1:5173
```

Backend:

```text
http://localhost:8000
```

Swagger:

```text
http://localhost:8000/api-docs
```
