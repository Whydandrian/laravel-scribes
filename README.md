## Generator Repository & Service di Laravel

Package ini digunakan untuk generate repository & service otomatis dari table schema di Laravel.

### Instalasi

1. Install package via composer

```bash
composer require whydsee/laravel-scribes
```

2. Publish stub files

```bash
php artisan vendor:publish --tag=scribes-stubs
```

### Penggunaan

1. Generate repository & service

```bash
php artisan make:scribes {table_name}
```

### Catatan

- Package ini masih dalam tahap pengembangan, mungkin masih terdapat bug atau masalah.
- Silakan laporkan bug atau masalah di repository ini.