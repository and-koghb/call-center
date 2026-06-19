# Code Review
(Русская версия - внизу)

Reviw of a code written in [TASK.md](TASK.md).

## Critical Issues

1. Multiple workers can select the same operator at the same time.

2. No database transactions. For items 2 and 3, I implemented a hybrid approach. Free operator lookup was moved to Redis via atomic `ZPOPMIN`, which fully offloaded MySQL. To protect against simultaneous grabs in MySQL, we applied the `tryLockAndBindBusy` method with strict `SELECT ... FOR UPDATE` inside a transaction. An operator is guaranteed not to get stuck in a "busy" status if something fails.

3. If the PBX times out, but the connection between this server and the telephony server still completes, on job retry the call status will already be `assigned`. The task will finish, and the call will "hang" without re-notifying telephony. In my implementation, on retry the job sees status `ASSIGNED`, skips the operator lookup step (does not touch Redis and does not create duplicates), and immediately resends the request to telephony for the same call and the same operator.

## Important Issues

4. If there are no free operators, it burns through `$tries`. The job quickly ends up in `failed_jobs` instead of simply waiting in the queue.

5. No explicit timeouts on requests to external telephony — my code includes them.

## Nice to Have

6. Missing base traits required for the job to work properly. I added the ones needed for my code.

7. The code is not compatible with SOLID principles — I tried to follow the rules. Separately, I want to note the awkward use of `app(TelephonyClient::class)`; I use dependency injection instead.

8. Logging covers only the success scenario. I have a few more log entries.

9. Statuses are hardcoded. I used Еnums; constants could have been used as well.

## Which tests would you add first?

I wrote tests covering scenarios: successful call, no available operators, soft-deleted operators.

## What would you not do right now?

Actually, right now it would be better not to overcomplicate the architecture — it would be enough to fix the critical and important issues by changing the existing code. But I did more to show how, in my view, it should be written correctly and cleanly. Of course, there are areas that still need improvement. For example:

* add a second queue group only for sending HTTP requests;
* move Redis to a dedicated, robust server;
* add a connection pooler for MySQL;
* send logs to a separate stack, e.g. Mongo, Elastic, etc.

## Scaling

* With a 50× load increase, the external PBX will start responding more slowly.
* If you run many workers, throughput on the `calls` queue will increase at first — calls will be picked up for processing faster. But eventually MySQL may hit the `max_connections` limit, and transactions with `lockForUpdate()` will start queuing up. Workers will occupy all available slots, hang waiting for HTTP responses from the PBX, and the call queue will begin to grow. The PBX may also reject our requests due to rate limits.
* Redis is actually very fast, but at millions of requests per second it can become a bottleneck.
* Under heavy load we could end up with terabytes of logs.
* In the extreme case, you can simply split the call center into parts and have separate servers for each.

---

# Проверка кода

Проверка кода написанный в [TASK.md](TASK.md).

## Критические проблемы

1. Несколько воркеров могут одновременно выбрать одного и того же оператора.

2. Отсутсвуют транзакции баз. Для 2-ого и 3-его пункта я внедрил гибридный подход. Поиск свободного оператора перенесли в Redis через атомарный `ZPOPMIN`, что полностью разгрузило MySQL. А для защиты от одновременного перехвата в MySQL мы применили метод `tryLockAndBindBusy` с жестким `SELECT ... FOR UPDATE` внутри транзакции. Оператор гарантированно не застрянет в «занятом» статусе, если что-то упадет.

3. Если АТС упадет по таймауту, но соединение между этим сервером и сервером телефонии успеет пройти, при повторе джобы статус звонка уже будет assigned. Задача завершится, и звонок «зависнет» без повторного уведомления телефонии. У меня при повторном запуске джоб видит статус ASSIGNED, пропускает шаг поиска оператора (не трогает Redis и не плодит дубли) и сразу повторно отправляет запрос в телефонию для того же звонка и того же оператора.

## Важные проблемы

4. Если нет свободных операторов, сжигает $tries. Задача быстро улетит в failed_jobs вместо того, чтобы просто подождать в очереди.

5. Нет явных таймаутов на запрос к внешней телефонии, в моих кодах есть.

## Было бы хорошо сделать

6. Отсутствуют базовые трейты, которые нужны, чтобы джоб сработал нормально. Я добавил те, которые нужны для моих кодов.

7. Коды не совместимы с принцыпами SOLID, я посторался делать по правилам. Ну отдельно хочеться отметить неудобное использование `app(TelephonyClient::class)`, у меня есть Dependency Injection. 

8. Логирован только успешное сценарие. У меня логов немножко больше.

9. Статусы хардкожены. Я исползовал Инамы, можно было еще константы использовать.

## Какие тесты вы бы добавили первыми.

Я написал тесты, которые покрывают сценарии успешный звонок, отсутствие операторов, мягко-удаленние операторы

## Что вы бы не стали делать прямо сейчас?

На самом деле прямо сейчас не стоило бы усложниять архитектуру, лучше было бы просто исправить критические и важные проблемы изменяя существующие коды. Но я это сделал, чтобы показать как по моему нужно написать правильно и красиво. Конечно есть места, которые нуждаются в улучшениях для высокой нагруженности. Например можно
* добавить вторую группу очередей только для отправки HTTP запросов.
* переносить Редис на отдельный хороший сервер.
* добавить Connection Pooler для MySQL.
* Отправить логи в отдельный стек, например Монго, Эластик, итд.

## Масштабирование

* При росте нагрузки в 50 раз внешняя АТС начнет отвечать медленнее.
* Если поднять много воркеров, сначала увеличится пропускная способность обработки очереди `calls`, звонки будут быстрее забираться в работу. Но в пределе MySQL может упереться в лимит max_connections, а транзакции с lockForUpdate() начнут выстраиваться в очередь. Воркеры займут все доступные слоты, будут висеть в ожидании HTTP-ответов от АТС, и очередь звонков начнет расти. Так же АТС может отбрасывать наши запросы по Rate Limit. 
* Редис на самом деле работает очень быстро, но при миллионах запросов в секунду может зависеть.
* При большой нагрузке у нас могут быть терабайты логов.
* В конечном пределе можно просто разбить колл центр на части и иметь отдельные сервера для них.
