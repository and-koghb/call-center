
# Call Distribution System (Call Center Queue) — Test Assignment
(Русская версия - внизу)

A Laravel 11 microservice for high-load distribution of incoming calls to operators using MySQL and Redis.

See [TASK.md](TASK.md) for the full assignment description.

For my code review and answers to the assignment questions, see [CODE_REVIEW.md](CODE_REVIEW.md).

---

## Requirements
* PHP >= 8.2
* MySQL >= 8.0
* Redis >= 6.0

---

## 1. Installation and Setup

> For installation with Docker please see the bottom part of instructions.

Run the following commands in order:

```bash
# Clone the repository and enter the project directory
git clone https://github.com/and-koghb/call-center
cd call-center

# Install dependencies
composer install

# Create the environment configuration file
cp .env.example .env
```

> Open the created `.env` file and configure your database connection (`DB_*`) and Redis server (`REDIS_*`).
Only after that run the commands below.

```bash
# Generate the application key
php artisan key:generate

# Create tables and seed with sample data
php artisan migrate
php artisan db:seed
```

## 2. Running in Production/Development Mode

Call distribution requires queue workers to continuously process jobs.

```bash
# In one terminal (or under Supervisor) for calls:
php artisan queue:work --queue=calls

# In another terminal for everything else:
php artisan queue:work --queue=default

# Start the local development server (for web UI or API)
# if you don't want to set up a virtual host
php artisan serve
```

## 3. Distribution Architecture and Configuration

### `.env` settings for high-load mode:
```ini
# Required for async workers via Redis
QUEUE_CONNECTION=redis

# Redis connection settings
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### System components:
* **Queue:** Laravel Jobs (`ProcessIncomingCallJob`) on a dedicated `calls` queue with a dynamic operator wait timeout (max 1 minute).
* **Operator sync:** Console command `php artisan operators:redis-sync-operators` (class `SyncAvailableOperators`). Handles **initial setup** and keeps the list of active operators in Redis (Sorted Set) in sync with MySQL. Recommended to add to Laravel Scheduler (`routes/console.php`) for periodic runs.
* **Redis:** Uses a Sorted Set (`ZADD` / `ZPOPMIN`) to instantly pick the most available operator without loading MySQL.
* **MySQL:** Pessimistic locking (`SELECT ... FOR UPDATE`) in `tryLockAndBindBusy` to prevent race conditions when two calls try to grab the same operator at once.

## 4. Testing (PHPUnit)
The project uses isolated testing that does not touch the main (production) database or Redis pools.

### Test setup (one-time):
1. Create a separate empty database for tests (e.g. `call_center_testing`).
2. Copy the test config: `cp .env.example .env.testing`.
3. In `.env.testing`, set:
   * `DB_DATABASE=call_center_testing`
   * `REDIS_TEST_DB` (so tests only clear isolated Redis DB #1, not production).

### Running tests:

```bash
# Run all tests
php artisan test

# Run only call distribution tests
php artisan test --filter=IncomingCallTest
```

## 5. Solution Overview and Architectural Benefits

### What was done

First, I want to note that the task states the Job should find the client by phone number. However, I believe looking up the client by phone in `CallService` rather than in the Job is preferable, because it allows the call to be linked to the client immediately and captures the data state at the moment of the event. An additional benefit is reducing the number of DB queries from queue workers. That said, the performance gain from a single indexed lookup is usually modest and only becomes noticeable at sufficiently high load.

A high-load call distribution service for routing incoming calls to operators was developed, integrated with Laravel queues, Redis, and MySQL. The code follows SOLID principles, using a contract, repositories, and services; customized soft delete is used for operators and clients. Added architectural Feature tests on PHPUnit covering scenarios: successful call, no available operators, and soft delete.

### Why it was done this way

* **Hybrid approach (Redis + MySQL):** Instead of heavy and frequent `SELECT` queries to the relational database, the current list of available operators is stored in Redis memory as a **Sorted Set** (a data structure where operators are sorted by the time they were last freed).
* **Atomicity via `ZPOPMIN`:** Extracting an operator from Redis happens in a single atomic operation. This guarantees the system instantly picks the operator who has been waiting the longest.
* **Race condition protection (`FOR UPDATE`):** To prevent a situation where two parallel calls try to grab the same operator at once, pessimistic row locking is applied at the DB level in the MySQL repository: `SELECT ... FOR UPDATE`.
* **Dynamic queue hold (`release(5)`):** If there are no available operators at the moment of the call, the job does not fail with an error but is softly returned to the Redis queue (`calls`) with a 5-second delay. The cycle repeats for up to one minute.

### Why this solution is better than the standard approach

1. **High performance (scalability):** MySQL is fully offloaded from constant searches for available operators. Redis handles tens of thousands of requests per second, providing instant distribution response.
2. **Reliability and data consistency:** Thanks to transactions and `lockForUpdate()`, one operator can never physically receive two calls at the same time, even if they arrive in the same millisecond.
3. **Clean code and fast tests:** The written tests are fully isolated from DB infrastructure dependencies using mocks (`Mockery`), run in hundredths of a second, and guarantee that queue and call status logic works strictly according to business requirements.

## Docker

Runs PHP 8.2-FPM, Nginx, MySQL 8.0, Redis 7, and two queue workers (`calls` + `default`).

**Prerequisites:** Docker Engine 20+ and Docker Compose v2.

```bash
cd call-center

# Use Docker-specific env (hostnames: mysql, redis)
cp .env.docker.example .env

# Build and start all services
docker compose up -d --build

# Install PHP dependencies (vendor/ is not in git)
docker compose exec app composer install

# Bootstrap the app
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan operators:redis-sync-operators

# Restart workers after vendor/ and APP_KEY exist
docker compose restart queue-calls queue-default
```

App: http://localhost:8080

**Verify:**

```bash
# HTTP
curl -I http://localhost:8080

# DB + Redis
docker compose exec app php artisan tinker --execute="DB::connection()->getPdo(); echo \Illuminate\Support\Facades\Redis::ping();"

# Tests
docker compose exec app php artisan test

# Queue smoke test (watch queue-calls logs)
docker compose exec app php artisan tinker --execute="app(\App\Services\CallService::class)->registerIncomingCall('+79991112233');"
docker compose logs queue-calls --tail=20
```

**Useful commands:**

```bash
# start all services (after reboot or docker compose down)
docker compose up -d
# show status of all containers
docker compose ps
# follow logs from app and queue-calls services
docker compose logs -f app queue-calls
docker compose exec app php artisan migrate:fresh --seed
# stop containers
docker compose down
# stop + delete DB/Redis volumes
docker compose down -v
```

---

----------
Russian version
----------

# Система распределения звонков (Call Center Queue) - Тестовая задача

Микросервис на Laravel 11 для высоконагруженного (Highload) распределения входящих звонков по операторам с использованием MySQL и Redis.

Для чтения задачи посмотрите файл [TASK.md](TASK.md).

Для код ревью с моей стороны и моих ответов на вопросы посмотрите файл [CODE_REVIEW.md](CODE_REVIEW.md).

---

## Требования
* PHP >= 8.2
* MySQL >= 8.0
* Redis >= 6.0

---

## 1. Установка и запуск проекта

> Для установки через Docker смотрите раздел в конце инструкции.

Выполните последовательно следующие команды в терминале:

```bash
# Клонирование репозитория и переход в папку проекта
git clone https://github.com/and-koghb/call-center
cd call-center

# Установка зависимостей
composer install

# Создание файла конфигурации окружения
cp .env.example .env
```

> Откройте созданный файл .env и настройте подключения к вашей основной базе данных (DB_*) и серверу Redis (REDIS_*).
Только после этого выполняйте команды ниже.

```bash
# Генерация ключа приложения
php artisan key:generate

# Создание таблиц и наполнение вымышленными данными
php artisan migrate
php artisan db:seed
```

## 2. Запуск окружения в режиме Production/Development

Для работы распределения звонков необходимо, чтобы воркеры очередей постоянно обрабатывали задачи.
  
```Bash
# В одном терминале (или под управлением Supervisor) для звонков:
php artisan queue:work --queue=calls

# В другом терминале для всего остального:
php artisan queue:work --queue=default

# Запуск локального сервера (для веб-интерфейса или API)
# если не хочеться создать виртуальный хост
php artisan serve
```

## 3. Архитектура распределения и конфигурация

### Настройки `.env` для Highload-режима:
```ini
# Ключевой параметр для работы асинхронных воркеров через Redis
QUEUE_CONNECTION=redis

# Настройки подключения к Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Компоненты системы:
* **Очередь:** Laravel Jobs (`ProcessIncomingCallJob`) на выделенной очереди `calls` с динамическим таймаутом ожидания оператора (максимум 1 минута).
* **Синхронизация операторов:** Консольная команда `php artisan operators:redis-sync-operators` (класс `SyncAvailableOperators`). Она отвечает за **первичную инициализацию** и актуализацию списка активных операторов в Redis (Sorted Set) на основе данных из MySQL. Рекомендуется добавлять её в Laravel Scheduler (`routes/console.php`) для периодического перезапуска.
* **Redis:** Используется Sorted Set (`ZADD` / `ZPOPMIN`) для мгновенного получения самого свободного оператора без нагрузки на MySQL.
* **MySQL:** Пессимистическая блокировка (`SELECT ... FOR UPDATE`) в методе `tryLockAndBindBusy` для защиты от Race Condition (ситуаций, когда два звонка пытаются одновременно захватить одного и того же оператора).

## 4. Тестирование (PHPUnit)
В проекте настроено изолированное тестирование, которое не затрагивает основную (рабочую) базу данных и продакшн-пулы Redis.
   
###Подготовка к тестам (выполняется один раз):
1. Создайте отдельную пустую БД для тестов (например, call_center_testing).
2. Скопируйте конфиг тестов: cp .env.example .env.testing.
3. В файле .env.testing укажите параметры:
   * DB_DATABASE=call_center_testing
   * REDIS_TEST_DB (чтобы тесты очищали только изолированную базу №1 в Redis, не трогая прод).
   
### Команды запуска тестов:

```bash
# Запустить все тесты в проекте
php artisan test

# Запустить только тесты распределения звонков
php artisan test --filter=IncomingCallTest
```

## 5. Описание решения и архитектурные преимущества

### Что было сделано

Во первых хочу сказать, что на таске говорится, что Job должен найти клиента по номеру телефона. Но я думаю, что искать клиента по телефону в CallService лучше, чем в Job, потому что это позволяет сразу связать звонок с клиентом и зафиксировать состояние данных на момент события. Дополнительным плюсом является уменьшение количества запросов к БД со стороны воркеров очереди. Однако выигрыш в производительности от одного индексного поиска сам по себе обычно невелик и становится заметным только при достаточно высокой нагрузке.

Разработан высоконагруженный (Highload) сервис распределения входящих звонков по операторам, работающий в связке с очередями Laravel, Redis и MySQL. Написал коды по принцыпам SOLID, использовал контракт, репозитории и сервисы, для операторов и клиентов использовал кастомизированный софт делейт. Добавил архитектурные Feature-тесты на PHPUnit, покрывающие сценарии успешный звонок, отсутствие операторов, мягкое удаление.

### Почему сделано именно так:
* **Гибридный подход (Redis + MySQL):** Вместо тяжелых и частых `SELECT`-запросов к реляционной базе данных, актуальный список свободных операторов хранится в оперативной памяти Redis в виде **Sorted Set** (структура данных, где операторы отсортированы по времени их последнего освобождения). 
* **Атомарность через `ZPOPMIN`:** Извлечение оператора из Redis происходит за одну атомарную операцию. Это гарантирует, что система моментально забирает самого «заждавшегося» сотрудника.
* **Защита от Race Condition (`FOR UPDATE`):** Чтобы исключить ситуацию, когда два параллельных звонка одновременно пытаются захватить одного и того же оператора, в MySQL-репозитории применена пессимистическая блокировка строк на уровне БД: `SELECT ... FOR UPDATE`.
* **Динамическое удержание в очереди (`release(5)`):** Если в момент звонка свободных операторов нет, задача не падает с ошибкой, а мягко возвращается обратно в очередь Redis (`calls`) с задержкой в 5 секунд. Цикл повторяется в течение одной минуты.

### Почему это решение лучше стандартного:
1. **Высокая производительность (Scalability):** База данных MySQL полностью разгружена от постоянных поисков свободных людей. Redis выдерживает десятки тысяч запросов в секунду, обеспечивая моментальный отклик распределения.
2. **Надежность и консистентность данных:** Благодаря транзакции и `lockForUpdate()`, один оператор физически никогда не получит два звонка одновременно, даже если они поступят в одну и ту же миллисекунду.
3. **Чистота кода и скорость тестов:** Написанные тесты полностью изолированы от инфраструктурных зависаний СУБД с помощью моков (`Mockery`)

## Docker

Запускает PHP 8.2-FPM, Nginx, MySQL 8.0, Redis 7 и два воркера очередей (`calls` + `default`).

**Требования:** Docker Engine 20+ и Docker Compose v2.

```bash
cd call-center

# Используйте env-файл для Docker (хосты: mysql, redis)
cp .env.docker.example .env

# Сборка и запуск всех сервисов
docker compose up -d --build

# Установка PHP-зависимостей (vendor/ не в git)
docker compose exec app composer install

# Первичная настройка приложения
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan operators:redis-sync-operators

# Перезапуск воркеров после появления vendor/ и APP_KEY
docker compose restart queue-calls queue-default
```

Приложение: http://localhost:8080

**Проверка:**

```bash
# HTTP
curl -I http://localhost:8080

# БД + Redis
docker compose exec app php artisan tinker --execute="DB::connection()->getPdo(); echo \Illuminate\Support\Facades\Redis::ping();"

# Тесты
docker compose exec app php artisan test

# Smoke-тест очереди (смотрите логи queue-calls)
docker compose exec app php artisan tinker --execute="app(\App\Services\CallService::class)->registerIncomingCall('+37499112233');"
docker compose logs queue-calls --tail=20
```

**Полезные команды:**

```bash
# запустить все сервисы (после перезагрузки или docker compose down)
docker compose up -d
# показать статус всех контейнеров
docker compose ps
# следить за логами сервисов app и queue-calls
docker compose logs -f app queue-calls
docker compose exec app php artisan migrate:fresh --seed
# остановить контейнеры
docker compose down
# остановить и удалить тома БД/Redis
docker compose down -v
```
