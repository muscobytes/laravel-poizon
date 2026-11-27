<?php

// Run locally with no argument, or verify Packagist with: php tests/smoke.php 0.1.1.
$root = dirname(__DIR__);
$publishedVersion = $argv[1] ?? null;
if ($publishedVersion !== null && ! preg_match('/^\d+\.\d+\.\d+$/D', $publishedVersion)) {
    throw new InvalidArgumentException('Expected a stable release version such as 0.1.1.');
}
$temporary = sys_get_temp_dir().'/laravel-poizon-smoke-'.bin2hex(random_bytes(8));
mkdir($temporary, 0700, true);

function run(array $command, string $directory): void
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Command failed: '.implode(' ', $command));
    }
}

function removeDirectory(string $directory): void
{
    foreach (new FilesystemIterator($directory) as $entry) {
        if ($entry->isDir() && ! $entry->isLink()) {
            removeDirectory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

try {
    if ($publishedVersion === null) {
        run(['composer', 'archive', '--format=zip', '--dir='.$temporary, '--file=package'], $root);
        $archive = new ZipArchive;
        if ($archive->open($temporary.'/package.zip') !== true) {
            throw new RuntimeException('Cannot open package archive.');
        }
        foreach (['src/PoizonServiceProvider.php', 'config/poizon.php', 'database/migrations/2026_11_17_210000_create_poizon_authorizations.php', 'LICENSE', 'README.md'] as $file) {
            if ($archive->locateName($file) === false) {
                throw new RuntimeException('Missing distribution file: '.$file);
            }
        }
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            if (preg_match('~^(vendor/|tests/|\.git/|\.github/|\.env(?:\.|$)|composer\.lock$|\.phpunit)~', $name)) {
                throw new RuntimeException('Unexpected distribution file: '.$name);
            }
        }
        $package = json_decode($archive->getFromName('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $archive->close();
        $package['version'] = '0.1.0';
        $package['dist'] = ['type' => 'zip', 'url' => 'file://'.$temporary.'/package.zip'];
    }

    run(['composer', 'create-project', 'laravel/laravel', $temporary.'/app', '^12.0', '--no-install', '--no-scripts', '--no-interaction'], $temporary);
    $application = $temporary.'/app';
    $manifest = json_decode(file_get_contents($application.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($publishedVersion === null) {
        $manifest['repositories'] = [['type' => 'package', 'package' => $package]];
        $manifest['require']['muscobytes/laravel-poizon'] = '^0.1';
    } else {
        unset($manifest['repositories']);
    }
    unset($manifest['require-dev']);
    file_put_contents($application.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    if ($publishedVersion === null) {
        run(['composer', 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress'], $application);
    } else {
        run(['composer', 'require', 'muscobytes/laravel-poizon:^'.$publishedVersion, '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress'], $application);
    }

    file_put_contents($application.'/.env', "APP_ENV=testing\nAPP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\nDB_CONNECTION=sqlite\nDB_DATABASE=".$application."/database/database.sqlite\nCACHE_STORE=array\n");
    touch($application.'/database/database.sqlite');
    run([PHP_BINARY, 'artisan', 'list'], $application);
    run([PHP_BINARY, 'artisan', 'vendor:publish', '--tag=poizon-config', '--force'], $application);
    if (! is_file($application.'/config/poizon.php')) {
        throw new RuntimeException('Configuration was not published.');
    }
    run([PHP_BINARY, 'artisan', 'migrate', '--force'], $application);
    run([PHP_BINARY, 'artisan', 'migrate', '--force'], $application);
    run([PHP_BINARY, 'artisan', 'config:cache'], $application);
    file_put_contents($application.'/check-package.php', <<<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\Http::preventStrayRequests();
if (! $app->providerIsLoaded(Muscobytes\Poizon\PoizonServiceProvider::class)) {
    throw new RuntimeException('Package auto-discovery failed.');
}
if (! array_key_exists('poizon:oauth:refresh', $kernel->all())) {
    throw new RuntimeException('Refresh command is missing.');
}
foreach (['poizon_authorizations', 'poizon_connections'] as $table) {
    if (! Illuminate\Support\Facades\Schema::hasTable($table)) {
        throw new RuntimeException('Missing table: '.$table);
    }
}
if (Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_11_17_210000_create_poizon_authorizations')->count() !== 1) {
    throw new RuntimeException('Migration history changed.');
}
if ($app['config']->get('poizon.open_platform.app_key') !== null) {
    throw new RuntimeException('Smoke test must not use production credentials.');
}
$spuQuery = Muscobytes\Poizon\Data\Requests\SpuListQuery::fromArray([
    'querySku' => false, 'modifyStartTime' => '1787786149000', 'modifyEndTime' => 1787786149000,
]);
if ($spuQuery->parameters() !== ['querySku' => false, 'modifyStartTime' => 1787786149000, 'modifyEndTime' => 1787786149000]
    || (new Muscobytes\Poizon\Data\Requests\SkuListQuery(modifyStartTime: '1787181701000'))->parameters() !== ['modifyStartTime' => 1787181701000]
    || Muscobytes\Poizon\Data\Spu::fromArray(['distSpuId' => 12345])->distSpuId !== '12345') {
    throw new RuntimeException('Catalog API from the release is missing.');
}
$installed = Composer\InstalledVersions::getPrettyVersion('muscobytes/laravel-poizon');
if (($argv[1] ?? '') !== '') {
    if (ltrim($installed, 'v') !== $argv[1]
        || array_key_exists('repositories', json_decode(file_get_contents(__DIR__.'/composer.json'), true, flags: JSON_THROW_ON_ERROR))) {
        throw new RuntimeException('Published version or default Packagist configuration mismatch.');
    }
}
echo 'Installed package version: '.$installed."\n";
echo "Package installation smoke passed.\n";
PHP);
    run([PHP_BINARY, 'check-package.php', $publishedVersion ?? ''], $application);
} finally {
    removeDirectory($temporary);
}
