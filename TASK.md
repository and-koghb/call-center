# Test Assignment — Call Center Queue
(Русская версия - внизу)

## Overview

An incoming call creates a record in the `calls` table, after which `ProcessIncomingCallJob` is dispatched to a Redis queue.

The job must:

- find the client by phone number;
- select an available operator;
- assign the call to the operator;
- send an event to telephony;
- write a log entry;
- retry on failure.

The system runs in **production under load**. Call processing is handled by **multiple workers in parallel**.

---

## Code Fragment

```php
class ProcessIncomingCallJob implements ShouldQueue
{
   public $tries = 5;

   private $callId;

   public function __construct($callId)
   {
       $this->callId = $callId;
   }

   public function handle()
   {
       $call = Call::find($this->callId);

       if (!$call) {
           return;
       }

       if ($call->status === 'new') {
           $client = Client::where('phone', $call->phone)->first();

           if ($client) {
               $call->client_id = $client->id;
           }

           $operator = Operator::where('available', true)
               ->orderBy('last_call_at')
               ->first();

           if (!$operator) {
               throw new \Exception('No available operators');
           }

           $operator->available = false;
           $operator->save();

           $call->operator_id = $operator->id;
           $call->status = 'assigned';
           $call->save();

           // HTTP request to external telephony to assign the call to the operator.
           // External system guarantees are unknown.
           app(TelephonyClient::class)->sendCallAssigned($call->id, $operator->id);

           Log::info('Call assigned', [
               'call_id' => $call->id,
               'operator_id' => $operator->id,
           ]);
       }
   }
}
```

---

## Tasks

### 1. Code review

Find **7–10 problems** in the solution and propose fixes.

Categorize each issue by severity:

| Level | Description |
|---|---|
| **Critical** | Data loss, race conditions, production outages |
| **Important** | Reliability, correctness under load, retry safety |
| **Nice to have** | Code quality, observability, maintainability |

### 2. Testing

Describe which tests you would add **first** and why.

### 3. Scope boundaries

What would you **not** do right now?

### 4. Assumptions and risks

If the behavior of the external system, queue, telephony, or legacy code is not explicitly defined, **state your assumptions**.

Separately describe **risks and concerns** that arise from this uncertainty.

---

## Scaling Question

Imagine that in six months load has grown **10–50×**: more incoming calls, more operators, more parallel workers, more events to external telephony.

Describe a **scaling plan** for the solution.

Cover:

- **Bottlenecks** you expect in the current implementation;
- what **simply adding more workers** will improve, and where it will **stop helping**;
- limits that may appear in **Redis**, the **database**, **HTTP integration with telephony**, and **logging**.

----------
Russian version
----------

# Тестовое задание

## Описание

Входящий звонок создаёт запись в таблице `calls`, после чего в очередь Redis отправляется `ProcessIncomingCallJob`.

Job должен:

- найти клиента по номеру телефона;
- выбрать доступного оператора;
- назначить звонок оператору;
- отправить событие в телефонию;
- записать лог;
- при ошибке повториться.

Система работает в **production под нагрузкой**. Обработка звонков выполняется **несколькими воркерами параллельно**.

---

## Фрагмент кода

```php
class ProcessIncomingCallJob implements ShouldQueue
{
   public $tries = 5;

   private $callId;

   public function __construct($callId)
   {
       $this->callId = $callId;
   }

   public function handle()
   {
       $call = Call::find($this->callId);

       if (!$call) {
           return;
       }

       if ($call->status === 'new') {
           $client = Client::where('phone', $call->phone)->first();

           if ($client) {
               $call->client_id = $client->id;
           }

           $operator = Operator::where('available', true)
               ->orderBy('last_call_at')
               ->first();

           if (!$operator) {
               throw new \Exception('No available operators');
           }

           $operator->available = false;
           $operator->save();

           $call->operator_id = $operator->id;
           $call->status = 'assigned';
           $call->save();

           // HTTP-запрос во внешнюю телефонию для назначения звонка оператору.
           // Гарантии внешней системы неизвестны.
           app(TelephonyClient::class)->sendCallAssigned($call->id, $operator->id);

           Log::info('Call assigned', [
               'call_id' => $call->id,
               'operator_id' => $operator->id,
           ]);
       }
   }
}
```

---

## Задачи

### 1. Разбор кода

Найдите **7–10 проблем** в решении и предложите варианты исправлений.

Разделите проблемы по критичности:

| Уровень | Описание |
|---|---|
| **Критические** | Потеря данных, race condition, падение production |
| **Важные** | Надёжность, корректность под нагрузкой, безопасность повторов |
| **Было бы хорошо сделать** | Качество кода, наблюдаемость, поддерживаемость |

### 2. Тестирование

Опишите, **какие тесты вы бы добавили первыми** и почему.

### 3. Границы scope

Что вы **не стали бы делать прямо сейчас**?

### 4. Предположения и риски

Если поведение внешней системы, очереди, телефонии или legacy-кода **не описано явно**, укажите **свои предположения**.

Отдельно опишите **риски и опасения**, которые возникают из-за этой неопределённости.

---

## Вопрос про масштабирование

Представьте, что через полгода нагрузка выросла **в 10–50 раз**: больше входящих звонков, больше операторов, больше параллельных workers, больше событий во внешнюю телефонию.

Опишите **план масштабирования** решения.

Нужно раскрыть:

- какие **bottleneck-и** вы ожидаете в текущей реализации;
- что даст **простое увеличение количества workers** и где оно **перестанет помогать**;
- какие **лимиты** могут возникнуть в **Redis**, **БД**, **HTTP-интеграции с телефонией** и **логировании**.
