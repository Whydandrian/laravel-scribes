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

2. Generate api full module

    ```bash
    php artisan scribes:generate-module --name={ModuleName} --table={table_names} --api
    ```
    Ganti `{table_names}` dengan nama table yang ingin di generate.
    Ganti `{ModuleName}` dengan nama module yang akan dibuat. Contoh: Academic, Report, etc.
    Otomatis akan membuat direktori module, contoh : Academic, dan didalamnya ada folder controller, services, repositories, route, config, model & presenters.

    Jika ingin generate controller, request, repository & service satu-satu gunakan command:

    ```bash
    php artisan scribes:make-module --name=Perkuliahan --table={table_name} --controller or --request or --repository or --service
    ```
    tag --controller --request --repository --service bersifat opsional, bisa pilih salah satu.


    Ganti `{table_name}` dengan nama table yang ingin di generate.


### Catatan

- Package ini masih dalam tahap pengembangan, mungkin masih terdapat bug atau masalah.
- Silakan laporkan bug atau masalah di repository ini.