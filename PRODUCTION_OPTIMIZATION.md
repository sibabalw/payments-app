# Laravel Production Optimization Guide

This document outlines how to fully optimize a Laravel application for production and verify that everything is correctly cached and running in optimized mode.

---

## Composer Optimization

Install dependencies without development packages and generate optimized autoload files:

```bash
composer install --no-dev --optimize-autoloader
```

If dependencies are already installed and you just want to optimize the classmap:

```bash
composer dump-autoload -o
```

You should see:

```
Generated optimized autoload files containing XXXX classes
```

This ensures Composer builds a classmap for faster class resolution.

---

## Cache Laravel Framework Bootstrap

Run:

```bash
php artisan optimize
```

This caches:

- Configuration
- Routes
- Events
- Views

If needed individually:

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

To clear all framework caches:

```bash
php artisan optimize:clear
```

---

## Verify Cached Files Exist

Check the cache directory:

```bash
ls -lah bootstrap/cache
```

Expected files:

- `config.php`
- `routes-v*.php`
- `events.php`
- `services.php`
- `packages.php`

If these files exist, Laravel automatically loads them during bootstrap. No flag is required — Laravel checks file existence.

---

## Verify Runtime Cache State

Open Tinker:

```bash
php artisan tinker
```

Run:

```php
app()->configurationIsCached();
app()->routesAreCached();
app()->eventsAreCached();
```

All should return `true`.

---

## Verify Blade View Compilation

Check compiled views:

```bash
ls -lah storage/framework/views
```

You should see compiled `.php` files.

---

## Verify Application Cache Store

Check which cache driver is used:

```bash
php artisan tinker
```

```php
config('cache.default');
```

Test cache functionality:

```php
cache()->put('test', 'ok', 60);
cache()->get('test');
```

---

## Verify PHP OPcache (Critical)

Check if OPcache is enabled:

```bash
php -i | grep -i opcache.enable
```

Expected:

```
opcache.enable => On
```

If OPcache is off, PHP will recompile files on every request.

---

## Required Production Environment Settings

Ensure `.env` contains:

```
APP_ENV=production
APP_DEBUG=false
```

---

## Final Production State

A properly optimized Laravel production setup includes:

- Composer optimized autoload
- Cached config
- Cached routes
- Cached events
- Compiled views
- OPcache enabled
- Production environment variables set
- Queue workers running (if applicable)
- Redis or scalable cache driver (if applicable)

If all checks pass, the application is fully optimized at the framework level.
