# muscobytes/laravel-poizon

Poizon Open Platform для Laravel 12 / PHP 8.2+. Namespace `Muscobytes\Poizon`. Самостоятельный SDK с типизированными ответами, устойчивым хранением OAuth и безопасной диагностикой.

## Установка и конфигурация

```sh
composer require muscobytes/laravel-poizon:^0.1.1
php artisan vendor:publish --tag=poizon-config
php artisan migrate
```

ServiceProvider подключается через Laravel auto-discovery. Требования: PHP 8.2+, Laravel 12, PDO-драйвер выбранной базы данных и cache store с поддержкой атомарных locks. OAuth-токены шифруются ключом приложения `APP_KEY`; сохраните его при переносе существующей базы.

Сохраняются переменные `POIZON_OPEN_APP_KEY`, `POIZON_OPEN_APP_SECRET`, `POIZON_OPEN_BASE_URL` (только `https://open.poizon.com`), `POIZON_OPEN_REDIRECT_URI`, `POIZON_OPEN_LANGUAGE` (`en`), `POIZON_OPEN_TIME_ZONE` (`Asia/Shanghai`), `POIZON_OPEN_CALLBACK_URL`. Конфигурация находится в `poizon.open_platform`. Одно приложение Poizon на сервис, любое количество подключений. Redirect URI — абсолютный HTTPS URL без userinfo, query и fragment; он проверяется при построении URL авторизации и не требуется для refresh.

Проверки конфигурации выполняются при операции. Регистрация пакета, `artisan list` и `config:cache` не требуют заполненных production credentials.

## Публичный контракт и DTO

Публичная поверхность: `PoizonClient`, `Services\OAuthClient`, `Services\AuthorizationManager`, классы `Data`, исключения, `Events\OAuthFailed`, `Contracts\AuthorizationRequiredNotifier` и binding `poizon.logger`. HTTP-helper `Services\PoizonJsonTransport` возвращает DTO `Data\TransportResult` с техническим HTTP-ответом, без бизнес-интерпретации. Его, как и другие DTO, нельзя передавать целиком в логи. Классы `Internal`, signer и Eloquent-модели являются деталями реализации, их методы не являются контрактом приложения.

Операции API возвращают `final readonly` DTO:

| Метод | Запрос | Ответ |
|---|---|---|
| `queryPrepaymentBalance` | ссылка на авторизацию | `BalanceResponse` / `Money` |
| `querySkuList` | `SkuListQuery` | `SkuListResponse` / `Sku[]` |
| `querySpuList` | `SpuListQuery` | `SpuListResponse` / `Spu[]` |
| `querySkuDetail` | `SkuDetailQuery` | `SkuDetailResponse` / `Sku[]` |
| `querySkuBidPrice` | `SkuPriceQuery` | `SkuPricesResponse` / `SkuPrice[]` |
| `createOrder` | `CreateOrderRequest` | `CreateOrderResponse` / `OrderItem[]` |
| `queryOrderInfo` | `OrderQuery` | `OrderInfoResponse` / `OrderItem[]` |
| `queryLogisticsTrace` | клиентский номер заказа | `LogisticsResponse` / `SubOrderTrace[]` |

Ответ содержит `code`, `message`, `metadata`, типизированный `data`. Метаданные включают operation ID, HTTP-статус, число отправок и длительность. Известные вложенные сущности также DTO. Идентификаторы возвращаются строками без преобразования через float; деньги сохраняются точными строками, с исходной валютой и единицей. `product.price` запроса — целая цена единицы в фэнях; пакет не рассчитывает общую стоимость заказа приложения.

Отсутствие обязательного поля или неверный тип — `ProtocolException`. Опциональные поля nullable; `Record::has()` позволяет отличить отсутствие от явного null, `fields()` сохраняет присутствие и порядок протокольных полей. Неизвестные поля хранятся в `extraFields` после удаления секретов. Неизвестные статусы остаются строками и не означают бизнес-успех. Пагинация (`total`, `pageNum`, `pageSize`, `lastId`, `hasMore`, когда они есть в протоколе) представлена в DTO; автоматического обхода всех страниц нет.

DTO не являются legacy-массивами и не выполняют финансовые действия. Приложение реализует явное преобразование DTO в свой прежний формат.

Подтверждённые правила чтения каталога и ограничения текущего SDK: [синхронизация каталога](docs/catalog-sync.md). Начиная с `0.1.1` доступны временные фильтры списков и `SpuListQuery::querySku`; для этих возможностей используйте Composer constraint `^0.1.1`. Живые спайки и синтетические регрессии разделены; цены обновляются отдельным чтением, обнаружение снятия SKU не гарантируется.

```php
use Muscobytes\Poizon\PoizonClient;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\Requests\SkuListQuery;

$client = app(PoizonClient::class);
$account = AuthorizationReference::forConnection('warehouse-cn');
$balance = $client->queryPrepaymentBalance($account);
$amount = $balance->data->imoneyDTO->amount; // String, без float.
$result = $client->querySkuList($account, new SkuListQuery(dwSkuId: ['65412613'], pageSize: 1));
foreach ($result->data->skuList as $sku) {
    $id = $sku->dwSkuId;
}
```

## Авторизации и покупки

Имя подключения регистрозависимо: 1–64 ASCII букв, цифр, `_`, `-`, `.`. Несколько имён могут ссылаться на одну авторизацию по `open_id`.

```php
use Muscobytes\Poizon\Services\AuthorizationManager;

$manager = app(AuthorizationManager::class);
$statuses = $manager->statuses(['warehouse-cn', 'Third.account']);
$status = $statuses->items['Third.account']; // DTO, включая missing.
$id = $manager->authorizationId('warehouse-cn');
```

До отправки покупки приложение сохраняет authorization ID, request ID, неизменяемый бизнес-payload, резерв и запрет повторной отправки. Используйте закреплённую авторизацию, чтобы перепривязка подключения не изменила торговый аккаунт покупки:

```php
use Muscobytes\Poizon\Data\Requests\{CreateOrderRequest, OrderProduct, ShippingAddress, OrderQuery};

$pinned = AuthorizationReference::forAuthorization($id);
$request = new CreateOrderRequest(
    shippingAddress: new ShippingAddress('Warehouse', 'warehouse@example.test', '123', 'P', 'C', 'D', 'Street'),
    product: new OrderProduct('10903', '65412613', 3, '36600'),
    requestId: $savedRequestId,
);
$response = $client->createOrder($pinned, $request);
$order = $client->queryOrderInfo($pinned, new OrderQuery(customerOrderNo: [$savedCustomerOrderNo]));
$tracking = $client->queryLogisticsTrace($pinned, $savedCustomerOrderNo);
```

Поле request ID протокольно опционально; формат и сохранение идентификатора определяет приложение. Пакет не генерирует и не заменяет его. Число товара и цена подготовленного запроса не пересчитываются при подписи.

**`createOrder` возвращает протокольный DTO. HTTP-успех и `code=200` не подтверждают полный выкуп.** Приложение проверяет идентичность заказа, SKU, всё количество, статусы и совокупность подзаказов. Частичный, противоречивый или неизвестный результат сохраняет резерв и блокировку повторного выкупа. Требуется сверка ранее отправленной покупки; отсутствие заказа в одном чтении не доказывает отказ. Автоматической повторной покупки нет.

## Ошибки

Общий тип — `Exceptions\PoizonException`. Конкретные типы: `InvalidRequestException`, `ConfigurationException`, `TransportException`, `HttpException`, `RateLimitException`, `ApiException`, `ProtocolException`, `AuthorizationException`, `CredentialStoreException`, `UnexpectedIntegrationException`.

Корректный бизнес-код отказа любой операции вызывает `ApiException`, в том числе для закреплённой авторизации. `response()` возвращает `ApiErrorResponse` с очищенными данными, отдельно от диагностического `context`. Решение о подтверждённом отказе покупки принимает приложение. Ошибка структуры не становится бизнес-отказом.

`context` содержит операцию, этап, выбранное подключение/authorization ID, HTTP-статус, upstream-код, `Retry-After` и operation ID. Состояния `NotSent`, `PossiblySent`, `ResponseReceived` описывают ход запроса, а не результат покупки. Диагностические сообщения фиксированы на английском, legacy-категорий и пользовательских переводов в пакете нет.

HTTP 401 отклоняет только использованную версию access token; поздний ответ не отзывает новую пару. HTTP 403 самостоятельно не отзывает credentials. Исходные сторонние исключения с телами/SQL/секретами не сохраняются как `previous`. Возможны цепочки безопасных исключений пакета.

Приложение самостоятельно преобразует DTO и исключения SDK в свои HTTP-ответы, сообщения и бизнес-правила.

## Retry и backoff

```php
'http' => [
    'retry' => [
        'enabled' => env('POIZON_HTTP_RETRY_ENABLED', false),
        'max_retries' => env('POIZON_HTTP_RETRY_COUNT', 2),
        'delays_ms' => [500, 1000],
    ],
],
```

По умолчанию выполняется одна отправка. `max_retries` считает повторы после первой: `0` — одна отправка, `2` — максимум три. Если повторов больше задержек, используется последняя задержка: четыре повтора с `[500, 1000]` дают `500, 1000, 1000, 1000` мс. `[0]` означает немедленный повтор. Пустой список разрешён только при нулевом количестве повторов. Значения проверяются до HTTP: count и задержки — неотрицательные целые, корректная десятичная строка из env допускается для count.

Повторы выполняются только для явно обозначенных чтений и только при `ConnectionException` непосредственно в отправке. Подпись и timestamp формируются заново, credentials сохраняются. Покупка, OAuth exchange/refresh, ошибки credentials/подготовки/подписи, HTTP 4xx/5xx, повреждённые ответы и бизнес-отказы не повторяются. Timeout отправки ≤15 с, connect timeout ≤10 с. Политика применяется на уровне HTTP-транспорта.

HTTP 429 устанавливает общий backoff приложения по `Retry-After` независимо от retry-флага. `POIZON_HTTP_RETRY_ENABLED=true` включает повторы; `false` отключает их. Значение по умолчанию в самом пакете остаётся false.

## OAuth, миграции и scheduler

`Services\OAuthClient` возвращает `TokenSet`, а `AuthorizationManager` сохраняет его зашифрованно. Токены закрыты и доступны только явными accessor; debug-вывод очищен, стандартная сериализация запрещена. Не передавайте TokenSet или другие DTO целиком в логирование.

Приложение реализует маршруты:

1. Проверяет права и имя подключения, вызывает `$manager->prepare()` до redirect.
2. Генерирует криптографический одноразовый state, сохраняет с подключением и сроком в сессии.
3. Перенаправляет на `app(OAuthClient::class)->authorizationUrl($state)`.
4. В callback проверяет права, срок и state через `hash_equals`, одноразово удаляет state и вызывает `$manager->authorize($connection, $code)`.

Не журналируйте callback URL/query, code, state, токены или HTTP body/breadcrumbs. Пакет не читает request attributes приложения. Очищенное событие `Events\OAuthFailed` используется приложением для AdminLog и подобных интеграций.

Выполните `php artisan migrate`. Пакет загружает исходную миграцию `2026_11_17_210000_create_poizon_authorizations`. При подключении к существующей базе сохраните историю миграций и **прежний APP_KEY**; подробности в [заметке о переходе](docs/migration.md). Таблицы `poizon_authorizations`, `poizon_connections`, IDs и ciphertext не меняются. Колонка `direction` хранит имя подключения. Не публикуйте исходную миграцию под новым именем.

Пакет не преобразует legacy-категории `problem_reason`; приложение должно нормализовать их до подключения SDK. Новые независимые сервисы используют собственный APP_KEY.

Для конкурентной защиты необходим database cache lock: таблица `cache_locks`, общая БД и cache prefix у всех процессов, default DB connection для OAuth и lock. `cache.stores.database.connection` / `lock_connection` должны быть null либо совпадать с `database.default`. Вызовы credential workflow внутри внешней транзакции запрещены.

Сохраняются глобальный mutex, fence владельца lock, durable-маркер перед refresh, атомарное сохранение пары и проверка open_id. Ошибка конфигурации до отправки не отзывает исправные токены. Неизвестная ротация не повторяется; нужна повторная авторизация. Общая авторизация нескольких подключений обновляется один раз; старые авторизации сохраняются для истории. Тесты этого репозитория проверяют последовательные сценарии на SQLite; межпроцессную конкурентность на production-СУБД следует проверить в приложении.

Параметры `poizon.open_platform`: `mutex_seconds` (120), `wait_seconds` (5), `refresh_ahead_seconds` (300). Lease должен покрывать время операции. Команда `poizon:oauth:refresh` регистрируется пакетом; расписание остаётся приложению:

```php
Schedule::command('poizon:oauth:refresh')->everyMinute()->withoutOverlapping();
```

Привяжите `Contracts\AuthorizationRequiredNotifier` к своему отправителю: `notify(int $authorizationId, array $connections, string $reason): bool`. True означает подтверждённую доставку. По умолчанию false; состояния `sending/sent/unknown` и однократность хранятся в БД. Исключение отправителя не означает доставку и не заменяет основной OAuth-результат.

## PSR-3 логирование

Binding `poizon.logger` по умолчанию содержит `Psr\Log\NullLogger`. Приложение может заменить его любым `LoggerInterface`, не меняя глобальный logger. Пакет не создаёт Monolog handlers, файлы, каналы и Sentry-интеграции.

Записи: `poizon.operation.completed` (одна итоговая на логическую операцию) и `poizon.retry.scheduled`. Успех/повторы — debug, бизнес-отказ — notice, ожидаемый технический отказ — warning, конфигурация/хранилище/неожиданный сбой и неизвестная изменяющая операция — error. Вложенный OAuth exchange/refresh имеет свой operation ID и parent operation ID. Вызовы могут получить `Data\CallContext` с UUID correlation ID.

В context разрешены операция/этап, operation ID, номер/число отправок, длительность, HTTP/upstream-коды, authorization ID, валидное имя подключения, request state и Retry-After. Запрещены body, заголовки, app secret, токены, sign, OAuth code/state, open_id, адреса, контакты, DTO целиком и исходные сторонние исключения.

Отказ logger не меняет результат, состояние credentials и retry. Приложение не дублирует техническую запись при обработке mapped-исключения; доменное событие покупки можно записать отдельно. Приложение отвечает за безопасность своих handlers, HTTP breadcrumbs, spans и incoming OAuth query.

## Разработка и проверки

```sh
composer install
composer validate --strict
composer lint
composer test
```

`composer format` применяет Laravel Pint. Testbench 10 / PHPUnit 11 загружают только пакет. GitHub Actions проверяет PHP 8.2 и 8.4, включая установку в изолированное Laravel-приложение. HTTP в тестах подменён, запросы к реальному Poizon запрещены.

Инструкции по участию и выпуску: [CONTRIBUTING.md](CONTRIBUTING.md). Изменения: [CHANGELOG.md](CHANGELOG.md).

Не входят: финансы, очереди, UI, автоматическая сверка неизвестных покупок, автоматический обход всех страниц и дополнительные storage-драйверы.

## Лицензия

[MIT](LICENSE).
