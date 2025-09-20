<?php

namespace Wahyudi\RepoServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MakeRepositoryServiceCommand extends Command
{
    protected $signature = 'scribes:make-module 
                            {--name= : Nama module yang akan dibuat}
                            {--table= : Daftar tabel (comma-separated)}
                            {--controller= : Nama controller custom (opsional)}
                            {--service= : Nama service custom (opsional)}
                            {--repository= : Nama repository custom (opsional)}
                            {--request= : Nama request custom (opsional)}
                            {--all : Generate controller, request, repository & service sekaligus}';

    protected $description = 'Generate modular structure untuk Laravel dengan Repository, Service, Controller pattern';

    protected $moduleName;
    protected $tables = [];

    public function handle(): int
    {
        $this->moduleName = $this->option('name');
        $tablesInput = $this->option('table');

        if (!$this->moduleName) {
            $this->error("❌ Parameter --name wajib diisi. Contoh: --name=Academic");
            return Command::FAILURE;
        }

        // Parse tables jika ada
        if ($tablesInput) {
            $this->tables = array_map('trim', explode(',', $tablesInput));
        }

        $this->info("🔧 Generating module: {$this->moduleName}");
        
        // Create module structure
        $this->createModuleStructure();
        
        // Cek apakah ada opsi individual component
        $hasIndividualOptions = $this->option('controller') || 
                               $this->option('service') || 
                               $this->option('repository') || 
                               $this->option('request');

        if ($hasIndividualOptions) {
            // Generate individual components
            $this->generateIndividualComponents();
        } elseif (!empty($this->tables)) {
            // Generate files untuk setiap table (mode lama)
            foreach ($this->tables as $table) {
                $this->generateTableFilesByOption($table);
            }
        }

        $this->info("✅ Module {$this->moduleName} berhasil dibuat!");
        
        if (empty($this->tables) && !$hasIndividualOptions) {
            $this->warn("💡 Tip: Gunakan --table=table1,table2 untuk langsung generate Repository, Service, dan Controller");
            $this->warn("💡 Atau gunakan --controller=NamaController untuk generate individual component");
        }

        // Auto-register service provider
        $this->registerServiceProviderToComposer($this->moduleName);
        $this->registerServiceProviderToBootstrap($this->moduleName);

        return Command::SUCCESS;
    }

    private function generateIndividualComponents(): void
    {
        $controllerName = $this->option('controller');
        $serviceName = $this->option('service');
        $repositoryName = $this->option('repository');
        $requestName = $this->option('request');
        $tableName = $this->tables[0] ?? null; // Ambil table pertama jika ada

        // Generate Controller
        if ($controllerName) {
            if ($tableName && $this->tableExists($tableName)) {
                // Generate controller dengan table reference
                $modelName = Str::studly(Str::singular($tableName));
                $columns = DB::select("SHOW COLUMNS FROM {$tableName}");
                $this->generateControllerWithTable($tableName, $modelName, $controllerName, $columns);
            } else {
                // Generate controller kosong (hanya method)
                $this->generateEmptyController($controllerName);
            }
        }

        // Generate Service
        if ($serviceName) {
            if ($tableName && $this->tableExists($tableName)) {
                // Generate service dengan table reference
                $modelName = Str::studly(Str::singular($tableName));
                $this->generateServiceWithTable($tableName, $modelName, $serviceName);
            } else {
                // Generate service kosong
                $this->generateEmptyService($serviceName);
            }
        }

        // Generate Repository
        if ($repositoryName) {
            if ($tableName && $this->tableExists($tableName)) {
                // Generate repository dengan table reference
                $modelName = Str::studly(Str::singular($tableName));
                $columns = DB::select("SHOW COLUMNS FROM {$tableName}");
                $this->generateRepositoryWithTable($tableName, $modelName, $repositoryName, $columns);
            } else {
                // Generate repository kosong
                $this->generateEmptyRepository($repositoryName);
            }
        }

        // Generate Request
        if ($requestName) {
            if ($tableName && $this->tableExists($tableName)) {
                // Generate request dengan table reference
                $modelName = Str::studly(Str::singular($tableName));
                $columns = DB::select("SHOW COLUMNS FROM {$tableName}");
                $this->generateRequestsWithTable($tableName, $modelName, $requestName, $columns);
            } else {
                // Generate request kosong
                $this->generateEmptyRequests($requestName);
            }
        }
    }

    private function createModuleStructure(): void
    {
        $basePath = app_path("Modules/{$this->moduleName}");
        // Jika module sudah ada, skip pembuatan struktur
        if (is_dir($basePath)) {
            $this->line("ℹ️  Module {$this->moduleName} sudah ada, skip create structure.");
            return;
        }

        $directories = [
            'Config/lang/id',
            'Config/lang/en',
            'Http/Controllers',
            'Http/Requests',
            'Http/Middleware',
            'Models',
            'Routes',
            'Presenters',
            'Services',
            'Repositories'
        ];
    
        foreach ($directories as $dir) {
            $path = "{$basePath}/{$dir}";
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->line("📁 Created directory: {$path}");
            }
        }
    
        // Generate language files
        $this->generateLanguageFiles($basePath);
        
        // Generate config file
        $this->generateModuleConfig($basePath);
        
        // Generate routes file
        $this->generateRoutesFile($basePath);
        
        // Generate module service provider
        $this->generateModuleServiceProvider($basePath);
    }

    // private function generateTableFiles(string $table): void
    // {
    //     if (!$this->tableExists($table)) {
    //         $this->error("❌ Table '{$table}' tidak ditemukan di database");
    //         return;
    //     }
    
    //     $this->info("🔄 Processing table: {$table}");
    
    //     $modelName = Str::studly(Str::singular($table));
    //     $columns = DB::select("SHOW COLUMNS FROM {$table}");
        
    //     // Generate Repository dengan folder {ModelName}Repository
    //     $this->generateRepository($table, $modelName, $columns);
        
    //     // Generate Service dengan folder {ModelName}Service
    //     $this->generateService($table, $modelName);
        
    //     // Generate Controller
    //     $this->generateController($table, $modelName);
        
    //     // Generate Requests dengan folder {ModelName}Request
    //     $this->generateRequests($table, $modelName, $columns);
    
    //     $this->line("✅ Files untuk table '{$table}' berhasil dibuat");
    // }

    private function generateTableFilesByOption(string $table): void
    {
        if (!$this->tableExists($table)) {
            $this->error("❌ Table '{$table}' tidak ditemukan di database");
            return;
        }

        $this->info("🔄 Processing table: {$table}");

        $modelName = Str::studly(Str::singular($table));
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        // Cek opsi
        $onlyController = $this->option('controller');
        $onlyRequest    = $this->option('request');
        $onlyRepository = $this->option('repository');
        $onlyService    = $this->option('service');
        $all            = $this->option('all');

        // Jika --all, generate semua
        if ($all) {
            $this->generateRepository($table, $modelName, $columns);
            $this->generateService($table, $modelName);
            $this->generateController($table, $modelName);
            $this->generateRequests($table, $modelName, $columns);
        } else {
            if ($onlyRepository) {
                $this->generateRepository($table, $modelName, $columns);
            }
            if ($onlyService) {
                $this->generateService($table, $modelName);
            }
            if ($onlyController) {
                $this->generateController($table, $modelName);
            }
            if ($onlyRequest) {
                $this->generateRequests($table, $modelName, $columns);
            }
            // Jika tidak ada opsi, default generate semua (bisa diubah sesuai kebutuhan)
            if (!$onlyRepository && !$onlyService && !$onlyController && !$onlyRequest) {
                $this->generateRepository($table, $modelName, $columns);
                $this->generateService($table, $modelName);
                $this->generateController($table, $modelName);
                $this->generateRequests($table, $modelName, $columns);
            }
        }

        $this->line("✅ Files untuk table '{$table}' berhasil dibuat");
    }

    private function generateRepository(string $table, string $modelName, array $columns): void
    {
        $repositoryBaseDir = app_path("Modules/{$this->moduleName}/Repositories");
        $repositoryDir = "{$repositoryBaseDir}/{$modelName}Repository";
        
        if (!is_dir($repositoryDir)) {
            mkdir($repositoryDir, 0755, true);
        }

        $repositoryName = $this->option('repository');
        if (!$repositoryName || $repositoryName === '1') {
            $repositoryName = "{$modelName}Repository";
        }
        $filePath = "{$repositoryDir}/{$repositoryName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Repository {$repositoryName} sudah ada, skip.");
            return;
        }
    
        $stub = $this->getRepositoryStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ moduleName }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Repositories\\{$modelName}Repository",
            $this->moduleName,
            $modelName
        ], $stub);
    
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateService(string $table, string $modelName): void
    {
        $serviceBaseDir = app_path("Modules/{$this->moduleName}/Services");
        $serviceDir = "{$serviceBaseDir}/{$modelName}Service";
        
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0755, true);
        }

        $serviceName = $this->option('service');
        if (!$serviceName || $serviceName === '1') {
            $serviceName = "{$modelName}Service";
        }
        $filePath = "{$serviceDir}/{$serviceName}.php";
        if (file_exists($filePath)) {
            $this->warn("⚠️ Service {$serviceName} sudah ada, skip.");
            return;
        }
    
        $stub = $this->getServiceStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ moduleName }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Services\\{$modelName}Service",
            $this->moduleName,
            $modelName
        ], $stub);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateController(string $table, string $modelName): void
    {
        $controllerDir = app_path("Modules/{$this->moduleName}/Http/Controllers");
        
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $controllerName = $this->option('controller');
        if (!$controllerName || $controllerName === '1') {
            $controllerName = "{$modelName}Controller";
        }
        $filePath = "{$controllerDir}/{$controllerName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Controller {$controllerName} sudah ada, skip.");
            return;
        }
    
        $stub = $this->getControllerStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ serviceNamespace }}',
            '{{ requestNamespace }}',
            '{{ model }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Http\\Controllers",
            "App\\Modules\\{$this->moduleName}\\Services\\{$modelName}Service",
            "App\\Modules\\{$this->moduleName}\\Http\\Requests",
            $modelName,
            $modelName
        ], $stub);
    

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateRequests(string $table, string $modelName, array $columns): void
    {
        $requestDir = app_path("Modules/{$this->moduleName}/Http/Requests");
        $modelRequestDir = "{$requestDir}/{$modelName}Request";
        
        // Buat direktori utama jika belum ada
        if (!is_dir($requestDir)) {
            mkdir($requestDir, 0755, true);
        }
        
        // Buat direktori model request jika belum ada
        if (!is_dir($modelRequestDir)) {
            mkdir($modelRequestDir, 0755, true);
        }
        
        $requestName = $this->option('request');
        $rulesStore = $this->generateRules($columns, 'store');
        $rulesUpdate = $this->generateRules($columns, 'update');

        foreach (['Store' => $rulesStore, 'Update' => $rulesUpdate] as $type => $rules) {
            $fileName = $requestName && $requestName !== '1'
            ? "{$type}{$requestName}.php"
            : "{$type}{$modelName}Request.php";
            $filePath = "{$modelRequestDir}/{$fileName}";

            if (file_exists($filePath)) {
                $this->warn("⚠️ Request {$fileName} sudah ada, skip.");
                continue;
            }

            $stub = $this->getRequestStub();
            $content = str_replace([
                '{{ namespace }}',
                '{{ type }}',
                '{{ model }}',
                '{{ modelName }}',
                '{{ rules }}'
            ], [
                "App\\Modules\\{$this->moduleName}\\Http\\Requests\\{$modelName}Request",
                $type,
                $modelName,
                $modelName,
                $rules
            ], $stub);

            file_put_contents($filePath, $content);
            $this->line("📄 Created: {$filePath}");
        }
    }

    private function generateModel(string $table, string $modelName, array $columns): void
    {
        $modelDir = app_path("Modules/{$this->moduleName}/Models");
        $filePath = "{$modelDir}/{$modelName}.php";
        
        if (file_exists($filePath)) {
            $this->warn("⚠️ Model {$modelName} sudah ada, skip.");
            return;
        }
        
        $fillable = $this->getFillableFields($columns);
        
        $stub = $this->getModelStub();
        $content = str_replace([
            '{{namespace}}',
            '{{moduleName}}',
            '{{modelName}}',
            '{{tableName}}',
            '{{fillable}}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Models",
            $this->moduleName,
            $modelName,
            $table,
            $fillable
        ], $stub);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateRoutesFile(string $basePath): void
    {
        $routesContent = $this->getRoutesStub();
        $content = str_replace([
            '{{moduleName}}',
            '{{moduleNameLower}}'
        ], [
            $this->moduleName,
            Str::lower($this->moduleName)
        ], $routesContent);

        $filePath = "{$basePath}/Routes/web.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");

        // API Routes
        $apiRoutesContent = $this->getApiRoutesStub();
        $apiContent = str_replace([
            '{{moduleName}}',
            '{{moduleNameLower}}'
        ], [
            $this->moduleName,
            Str::lower($this->moduleName)
        ], $apiRoutesContent);

        $apiFilePath = "{$basePath}/Routes/api.php";
        file_put_contents($apiFilePath, $apiContent);
        $this->line("📄 Created: {$apiFilePath}");
    }

    private function generateModuleServiceProvider(string $basePath): void
    {
        $stub = $this->getServiceProviderStub();
        $content = str_replace([
            '{{moduleName}}',
            '{{moduleNameLower}}'
        ], [
            $this->moduleName,
            Str::lower($this->moduleName)
        ], $stub);

        $filePath = "{$basePath}/{$this->moduleName}ServiceProvider.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function tableExists(string $table): bool
    {
        try {
            return !empty(DB::select("SHOW TABLES LIKE '{$table}'"));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getFillableFields(array $columns): string
    {
        return collect($columns)
            ->pluck('Field')
            ->reject(fn($col) => in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn($col) => "'{$col}'")
            ->implode(', ');
    }

    private function generateRules(array $columns, string $mode = 'store'): string
    {
        return collect($columns)->map(function($col) use ($mode) {
            if (in_array($col->Field, ['id', 'created_at', 'updated_at', 'deleted_at'])) return null;
            
            $rule = $this->mapColumnToRule($col->Type, $col->Null === 'YES');
            if ($mode === 'update') $rule = "sometimes|$rule";
            
            return "            '{$col->Field}' => '{$rule}'";
        })->filter()->implode(",\n");
    }

    private function mapColumnToRule(string $type, bool $nullable): string
    {
        $rule = match (true) {
            str_contains($type, 'int') => 'integer',
            str_contains($type, 'varchar'),
            str_contains($type, 'text') => 'string',
            str_contains($type, 'date') && !str_contains($type, 'time') => 'date',
            str_contains($type, 'datetime') => 'date_format:Y-m-d H:i:s',
            str_contains($type, 'tinyint(1)') => 'boolean',
            default => 'string',
        };
        
        return $nullable ? "nullable|$rule" : "required|$rule";
    }

    // Stub methods - implementasi stub akan dibuat terpisah
    private function getRepositoryStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/repository.stub');
    }

    private function getServiceStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/service.stub');
    }

    private function getControllerStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/controller.stub');
    }

    private function getRequestStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/request.stub');
    }

    private function getModelStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/module-model.stub');
    }

    private function getRoutesStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/module-routes.stub');
    }

    private function getApiRoutesStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/module-api-routes.stub');
    }

    private function getServiceProviderStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/module-service-provider.stub');
    }

    private function registerServiceProviderToComposer(string $moduleName): void
    {
        $composerPath = base_path('composer.json');
        $composer = json_decode(file_get_contents($composerPath), true);
        
        $providerClass = "App\\Modules\\{$moduleName}\\{$moduleName}ServiceProvider";
        
        // Inisialisasi struktur jika belum ada
        if (!isset($composer['extra'])) {
            $composer['extra'] = [];
        }
        if (!isset($composer['extra']['laravel'])) {
            $composer['extra']['laravel'] = [];
        }
        if (!isset($composer['extra']['laravel']['providers'])) {
            $composer['extra']['laravel']['providers'] = [];
        }
        
        // Tambahkan provider jika belum ada
        if (!in_array($providerClass, $composer['extra']['laravel']['providers'])) {
            $composer['extra']['laravel']['providers'][] = $providerClass;
            
            // Tulis kembali ke composer.json
            file_put_contents(
                $composerPath, 
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            
            $this->line("📦 Added {$providerClass} to composer.json");
            $this->line("💡 Run 'composer dump-autoload' to register the provider");
        }
    }
    private function generateLanguageFiles(string $basePath): void
    {
        $moduleNameLower = Str::lower($this->moduleName);
        
        // Generate Indonesian language file
        $langIdStub = $this->getLangIdStub();
        $langIdContent = str_replace([
            '{{ moduleName }}',
            '{{ moduleNameLower }}'
        ], [
            $this->moduleName,
            $moduleNameLower
        ], $langIdStub);
    
        $langIdPath = "{$basePath}/Config/lang/id/messages.php";
        file_put_contents($langIdPath, $langIdContent);
        $this->line("📄 Created: {$langIdPath}");
    
        // Generate English language file
        $langEnStub = $this->getLangEnStub();
        $langEnContent = str_replace([
            '{{ moduleName }}',
            '{{ moduleNameLower }}'
        ], [
            $this->moduleName,
            $moduleNameLower
        ], $langEnStub);
    
        $langEnPath = "{$basePath}/Config/lang/en/messages.php";
        file_put_contents($langEnPath, $langEnContent);
        $this->line("📄 Created: {$langEnPath}");
    }
    private function getLangIdStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/lang-id.stub');
    }

    private function getLangEnStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/lang-en.stub');
    }
    private function generateModuleConfig(string $basePath): void
    {
        $configStub = $this->getModuleConfigStub();
        $configContent = str_replace([
            '{{moduleName}}',
            '{{moduleNameLower}}'
        ], [
            $this->moduleName,
            Str::lower($this->moduleName)
        ], $configStub);
    
        $configPath = "{$basePath}/Config/config.php";
        file_put_contents($configPath, $configContent);
        $this->line("📄 Created: {$configPath}");
    }
    
    private function getModuleConfigStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/module-config.stub');
    }


    private function registerServiceProviderToBootstrap(string $moduleName): void
    {
        $providerClass = "App\\Modules\\{$moduleName}\\{$moduleName}ServiceProvider";
        $providersPath = base_path('bootstrap/providers.php');

        if (!file_exists($providersPath)) {
            $this->warn("⚠️ File bootstrap/providers.php tidak ditemukan.");
            return;
        }

        // baca file bootstrap/providers.php
        $content = file_get_contents($providersPath);

        // cek apakah sudah ada
        if (str_contains($content, $providerClass)) {
            $this->line("ℹ️ {$providerClass} sudah ada di bootstrap/providers.php");
            return;
        }

        // cari posisi array penutup
        $pattern = '/return\s*\[\s*/m';
        if (preg_match($pattern, $content)) {
            // kita tambahkan sebelum tanda ];
            $content = preg_replace(
                '/(\];\s*)$/m',
                "    {$providerClass}::class,\n];",
                $content
            );

            file_put_contents($providersPath, $content);
            $this->line("📦 Added {$providerClass} to bootstrap/providers.php");
        } else {
            $this->warn("⚠️ Tidak bisa menemukan array return di bootstrap/providers.php");
        }
    }

}

    // Method untuk generate controller kosong
    private function generateEmptyController(string $controllerName): void
    {
        $controllerDir = app_path("Modules/{$this->moduleName}/Http/Controllers");
        
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $filePath = "{$controllerDir}/{$controllerName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Controller {$controllerName} sudah ada, skip.");
            return;
        }

        // Generate custom request untuk controller kosong
        $requestName = str_replace('Controller', 'Request', $controllerName);
        $this->generateEmptyRequests($requestName);

        $stub = $this->getEmptyControllerStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ requestNamespace }}',
            '{{ controllerName }}',
            '{{ requestName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Http\\Controllers",
            "App\\Modules\\{$this->moduleName}\\Http\\Requests",
            $controllerName,
            $requestName
        ], $stub);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate controller dengan table reference
    private function generateControllerWithTable(string $table, string $modelName, string $controllerName, array $columns): void
    {
        $controllerDir = app_path("Modules/{$this->moduleName}/Http/Controllers");
        
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $filePath = "{$controllerDir}/{$controllerName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Controller {$controllerName} sudah ada, skip.");
            return;
        }

        // Generate requests untuk controller dengan table
        $requestBaseName = str_replace('Controller', '', $controllerName);
        $this->generateRequestsWithTable($table, $modelName, $requestBaseName . 'Request', $columns);

        $stub = $this->getControllerStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ serviceNamespace }}',
            '{{ requestNamespace }}',
            '{{ model }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Http\\Controllers",
            "App\\Modules\\{$this->moduleName}\\Services\\{$modelName}Service",
            "App\\Modules\\{$this->moduleName}\\Http\\Requests",
            $modelName,
            $modelName
        ], $stub);

        // Replace controller class name
        $content = str_replace($modelName . 'Controller', $controllerName, $content);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate service kosong
    private function generateEmptyService(string $serviceName): void
    {
        $serviceDir = app_path("Modules/{$this->moduleName}/Services");
        
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0755, true);
        }

        $filePath = "{$serviceDir}/{$serviceName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Service {$serviceName} sudah ada, skip.");
            return;
        }

        $stub = $this->getEmptyServiceStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ serviceName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Services",
            $serviceName
        ], $stub);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate service dengan table reference
    private function generateServiceWithTable(string $table, string $modelName, string $serviceName): void
    {
        $serviceBaseDir = app_path("Modules/{$this->moduleName}/Services");
        $serviceDir = "{$serviceBaseDir}/{$modelName}Service";
        
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0755, true);
        }

        $filePath = "{$serviceDir}/{$serviceName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Service {$serviceName} sudah ada, skip.");
            return;
        }

        $stub = $this->getServiceStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ moduleName }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Services\\{$modelName}Service",
            $this->moduleName,
            $modelName
        ], $stub);

        // Replace service class name
        $content = str_replace($modelName . 'Service', $serviceName, $content);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate repository kosong
    private function generateEmptyRepository(string $repositoryName): void
    {
        $repositoryDir = app_path("Modules/{$this->moduleName}/Repositories");
        
        if (!is_dir($repositoryDir)) {
            mkdir($repositoryDir, 0755, true);
        }

        $filePath = "{$repositoryDir}/{$repositoryName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Repository {$repositoryName} sudah ada, skip.");
            return;
        }

        $stub = $this->getEmptyRepositoryStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ repositoryName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Repositories",
            $repositoryName
        ], $stub);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate repository dengan table reference
    private function generateRepositoryWithTable(string $table, string $modelName, string $repositoryName, array $columns): void
    {
        $repositoryBaseDir = app_path("Modules/{$this->moduleName}/Repositories");
        $repositoryDir = "{$repositoryBaseDir}/{$modelName}Repository";
        
        if (!is_dir($repositoryDir)) {
            mkdir($repositoryDir, 0755, true);
        }

        $filePath = "{$repositoryDir}/{$repositoryName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Repository {$repositoryName} sudah ada, skip.");
            return;
        }

        $stub = $this->getRepositoryStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ moduleName }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Repositories\\{$modelName}Repository",
            $this->moduleName,
            $modelName
        ], $stub);

        // Replace repository class name
        $content = str_replace($modelName . 'Repository', $repositoryName, $content);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate request kosong
    private function generateEmptyRequests(string $requestName): void
    {
        $requestDir = app_path("Modules/{$this->moduleName}/Http/Requests");
        
        if (!is_dir($requestDir)) {
            mkdir($requestDir, 0755, true);
        }

        $filePath = "{$requestDir}/{$requestName}.php";

        if (file_exists($filePath)) {
            $this->warn("⚠️ Request {$requestName} sudah ada, skip.");
            return;
        }

        $stub = $this->getEmptyRequestStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ requestName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Http\\Requests",
            $requestName
        ], $stub);

        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    // Method untuk generate request dengan table reference
    private function generateRequestsWithTable(string $table, string $modelName, string $requestName, array $columns): void
    {
        $requestDir = app_path("Modules/{$this->moduleName}/Http/Requests");
        $modelRequestDir = "{$requestDir}/{$modelName}Request";
        
        if (!is_dir($requestDir)) {
            mkdir($requestDir, 0755, true);
        }
        
        if (!is_dir($modelRequestDir)) {
            mkdir($modelRequestDir, 0755, true);
        }
        
        $rulesStore = $this->generateRules($columns, 'store');
        $rulesUpdate = $this->generateRules($columns, 'update');

        foreach (['Store' => $rulesStore, 'Update' => $rulesUpdate] as $type => $rules) {
            $fileName = "{$type}{$requestName}.php";
            $filePath = "{$modelRequestDir}/{$fileName}";

            if (file_exists($filePath)) {
                $this->warn("⚠️ Request {$fileName} sudah ada, skip.");
                continue;
            }

            $stub = $this->getRequestStub();
            $content = str_replace([
                '{{ namespace }}',
                '{{ type }}',
                '{{ model }}',
                '{{ modelName }}',
                '{{ rules }}'
            ], [
                "App\\Modules\\{$this->moduleName}\\Http\\Requests\\{$modelName}Request",
                $type,
                $modelName,
                $modelName,
                $rules
            ], $stub);

            file_put_contents($filePath, $content);
            $this->line("📄 Created: {$filePath}");
        }
    }

    // Method untuk mendapatkan stub kosong
    private function getEmptyControllerStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/empty-controller.stub');
    }

    private function getEmptyServiceStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/empty-service.stub');
    }

    private function getEmptyRepositoryStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/empty-repository.stub');
    }

    private function getEmptyRequestStub(): string
    {
        return file_get_contents(__DIR__.'/../stubs/empty-request.stub');
    }

