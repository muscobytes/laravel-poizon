# Отчёт 06. Совместимый выпуск 0.1.1

Дата: 28.11.2026, Europe/Moscow. [Задача 06](../tasks/06-release.md), [спецификация](../spec.md), [изменения](../../../CHANGELOG.md).

Статус на этапе подготовки: локальная установка проверена, публикация и установка с Packagist ещё предстоят. Задача не принята до проверки опубликованной версии.

## Версия и совместимость

Перед выпуском удалённый `main` и тег `v0.1.0` указывали на `1898169f1fd0e94e909f4ab8950481da6fde0173`; [Packagist metadata](https://repo.packagist.org/p2/muscobytes/laravel-poizon.json) содержали только `v0.1.0`. Номер `0.1.1` был свободен.

Новые аргументы добавлены после прежних, прежняя локальная валидация не ужесточается. Числовой `Spu.distSpuId` — расширение принимаемых ответов; существующие строки сохраняются. Зависимости и миграции не менялись, поле `version` в Composer manifest не добавлено.

Принятое ограничение спайка 03 сохраняется: основная цена читается отдельно до 200 SKU, полнота ценовых событий через modifyTime и обнаружение снятия не гарантируются. Отсутствие предложения не означает удаление. Ответ Poizon не блокирует этот совместимый выпуск; уточнение ведётся в [бэклоге 02](../../0000-backlog/tasks/02-confirm-price-and-delisting-contract.md).

## Подготовительные проверки

| Проверка | PHP 8.2.36 | PHP 8.4.27 |
| --- | --- | --- |
| `composer validate --strict` | PASS | PASS |
| `composer lint` | PASS, 103 файла | PASS, 103 файла |
| `composer test -- --do-not-cache-result` | PASS, 106 тестов / 537 проверок | PASS, 106 тестов / 537 проверок |
| `php tests/smoke.php` | PASS | PASS |

Использованы готовые образы `poizon-package-test:8.2` и `raketa-reseller-app:packagist-smoke`. Контейнеры одноразовые, entrypoint переопределён, production env-file/БД/volumes не подключаются. Для временного приложения используются только тестовый APP_KEY и SQLite, а COMPOSER_HOME пустой (`/tmp/composer-home`); допустим отдельный cache публичных зависимостей.

Полный первоначальный запуск `composer test` был успешен на обеих версиях, но read-only mount вызвал предупреждение записи PHPUnit cache. Повтор с `--do-not-cache-result` прошёл без этого предупреждения; исходники ради cache не менялись.

## Что проверяет установка

[Smoke-сценарий](../../../tests/smoke.php) создаёт отдельный временный Laravel 12 проект, удаляет его после завершения и устанавливает пакет без dev-зависимостей. В подготовительной проверке установлены skeleton `laravel/laravel v12.12.2` и framework `v12.74.0`.

Проверены auto-discovery провайдера, `artisan list`, команда `poizon:oauth:refresh`, публикация конфига, миграции, повтор миграций без дублирования истории и `config:cache`. Новые параметры SPU/SKU и нормализация числового distSpuId проверены непосредственно в установленном vendor. Случайные HTTP-запросы запрещены; Poizon/OAuth не вызывались.

Без аргумента сценарий использует локальный архив с синтетической версией `0.1.0`; это проверка содержимого текущего кода, не доказательство публикации. Режим `php tests/smoke.php 0.1.1` выполняет настоящий `composer require muscobytes/laravel-poizon:^0.1.1` без custom repositories, сверяет точный `InstalledVersions::getPrettyVersion()` и тот же набор проверок.

## Воспроизведение

Из корня репозитория, выбрав один из образов таблицы:

```sh
docker run --rm --entrypoint sh --workdir /package \
  --mount "type=bind,source=$PWD,target=/package,readonly" \
  -e COMPOSER_HOME=/tmp/composer-home -e COMPOSER_ALLOW_SUPERUSER=1 \
  poizon-package-test:8.2 -c '
    unset APP_ENV APP_KEY DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_CONNECTION
    composer validate --strict && composer lint &&
    composer test -- --do-not-cache-result && php tests/smoke.php
  '
```

После публикации заменить последнюю команду на `php tests/smoke.php 0.1.1`. Для установки зависимостей нужен доступ к Packagist/GitHub; production credentials не нужны. Выпуск и внешняя проверка фиксируются ниже только после фактического завершения.
