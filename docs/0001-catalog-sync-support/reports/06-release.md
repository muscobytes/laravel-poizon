# Отчёт 06. Совместимый выпуск 0.1.1

Дата: 28.11.2026, Europe/Moscow. [Задача 06](../tasks/completed/06-release.md), [спецификация](../spec.md), [изменения](../../../CHANGELOG.md).

Статус: **выполнено и принято 28.11.2026**. Совместимая версия `v0.1.1` опубликована и установлена из Packagist в чистое Laravel 12 приложение на PHP 8.2 и 8.4.

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
| `php tests/smoke.php 0.1.1` после публикации | PASS, установлен `v0.1.1` | PASS, установлен `v0.1.1` |

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

## Публикация и внешняя проверка

- GitHub: [тег v0.1.1](https://github.com/muscobytes/laravel-poizon/tree/v0.1.1), release commit `34e57db14e1bc225ae2ff8d0e5468688b87c91e0`; annotated tag object `1e6d901357fc078b28f3646adbb2d4c9c46c90e6`.
- `main` и новый тег отправлены одним `git push --atomic origin main refs/tags/v0.1.1`, без force и перезаписи существующих тегов. Удалённый peeled tag совпал с release commit.
- [Packagist](https://packagist.org/packages/muscobytes/laravel-poizon#v0.1.1) автоматически получил `v0.1.1`; p2 metadata содержит source reference `34e57db14e1bc225ae2ff8d0e5468688b87c91e0`. Ручной вызов обновления и получение API credentials не потребовались.
- [GitHub Actions релизного SHA](https://github.com/muscobytes/laravel-poizon/actions/runs/46078995199): `completed`, `success`.
- В двух новых одноразовых контейнерах с пустым COMPOSER_HOME выполнен реальный `composer require muscobytes/laravel-poizon:^0.1.1 --update-no-dev --prefer-dist --no-interaction --no-progress` без custom repositories. Оба установили `v0.1.1` из архива Packagist/GitHub, а `InstalledVersions::getPrettyVersion()` подтвердил точную версию.
- На обеих версиях PHP повторно подтверждены auto-discovery, `artisan list`, публикация конфига, миграции и их повтор без дублирования, `config:cache`, временные фильтры SPU/SKU, сохранение querySku=false и числовой distSpuId. Каждая проверка завершилась `Installed package version: v0.1.1` и `Package installation smoke passed.`.

В первой попытке опубликованного режима обнаружена неверная опция smoke-команды `composer require --no-dev`; Composer отклонил её до установки. Команда исправлена на `--update-no-dev`, обе проверки затем прошли. Исправление сохранено отдельным коммитом `f30cdb19afaaecb04116e7723165ca3101d954d1`. Каталог tests исключён из dist, поэтому исправление тестового инструмента не меняет опубликованный SDK; тег `v0.1.1` не переставлялся. Финальные результаты и перенос задачи записаны последующим документационным коммитом.

## Результат для потребителя

Минимальный Composer constraint для новых возможностей: **`muscobytes/laravel-poizon:^0.1.1`**. Установка не требует VCS repositories. Пакет готов к использованию в пределах принятых ограничений спайка 03; переключение production-каталога, его отдельный обход цен и уточнение снятия SKU остаются задачами приложения/бэклога. Внешние приложения и их данные при выпуске не менялись, запрос Poizon не отправлялся.
