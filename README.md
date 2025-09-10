## Generator Repository & Service di Laravel

Package ini digunakan untuk generate repository & service otomatis dari table schema di Laravel.

### Instalasi

Install package via composer

```bash
composer require whydsee/laravel-scribes
```

### Penggunaan

1. Publish file upload trait & config, jika ada file upload menggunakan command berikut:

    ```bash
    php artisan make:scribes --file-upload
    ```
    Publish ini akan berfungsi untuk generalisir file upload.
    - File upload trait akan di publish di folder `app/Traits/FileUploadTrait.php`
    - File upload config akan di publish di folder `config/scribes.php`
    - Config ini berisi default path, custom path dan custom file name.

    Untuk menggunakan trait, pada controller sebelum method bisa ditulis berikut:

    ```php
    use FileUploadTrait;
    ```

2. Generate repository & service

    ```bash
    php artisan make:scribes {table_name}
    ```
    Ganti `{table_name}` dengan nama table yang ingin di generate.
    Repository & service akan di generate di folder `app/Repositories` dan `app/Services`.
    - Repository akan di generate di folder `app/Repositories/{table_name}Repository.php`
    - Service akan di generate di folder `app/Services/{table_name}Service.php`
    - Pada repository, akan di generate method CRUD.
    - Pada service, akan di generate method CRUD.
    - Pada controller, akan di generate method CRUD.


### Catatan

- Package ini masih dalam tahap pengembangan, mungkin masih terdapat bug atau masalah.
- Silakan laporkan bug atau masalah di repository ini.