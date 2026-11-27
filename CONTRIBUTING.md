# Разработка

Требуются PHP 8.2+, Composer 2 и расширения для Testbench/PHPUnit, включая PDO SQLite, mbstring, DOM и XML. Поддерживается Laravel 12.

```sh
composer install
composer validate --strict
composer lint
composer test
```

Для форматирования: `composer format`. Используйте PSR-4, стиль Laravel Pint и тесты `*Test.php` с методами `test_descriptive_behavior`. Добавляйте регрессионные проверки при изменении поведения; HTTP подменяйте через `Http::fake()`.

Pull request должен описывать изменение поведения и результаты проверок. Не включайте секреты, реальные OAuth-ответы и персональные данные. Коммиты оформляйте по Conventional Commits.

## Выпуск

1. Дождитесь успешных проверок, обновите changelog и создайте тег `vX.Y.Z`. Поле `version` в Composer не добавляйте. До 1.0 несовместимые изменения повышают minor-версию.
2. Отправьте ветку и тег в публичный `https://github.com/muscobytes/laravel-poizon`.
3. При первом выпуске зарегистрируйте HTTPS URL репозитория на Packagist под учётной записью с доступом к vendor `muscobytes`. Настройте GitHub webhook/интеграцию Packagist и убедитесь, что автообновление включено.
4. Проверьте наличие версии на Packagist и установку в чистом Laravel 12 приложении: `composer require muscobytes/laravel-poizon:^0.1.1` без VCS-настроек. Проверьте `artisan list`, публикацию конфига, миграции и `config:cache`.

Сценарий `php tests/smoke.php` выполняется из корня пакета; он проверяет установку из локального Composer-архива в отдельное приложение без dev-зависимостей пакета. Нужны Composer в PATH и доступ к Packagist. Сценарий создаёт и удаляет только собственный временный каталог.

После публикации запустите `php tests/smoke.php 0.1.1`: этот режим выполняет настоящий `composer require muscobytes/laravel-poizon:^0.1.1` без пользовательского `repositories`, проверяет точную установленную версию и новые DTO. Локальный режим использует синтетическую версию архива `0.1.0` и не доказывает публикацию на Packagist.
