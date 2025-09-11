<?php

namespace Wahyudi\RepoServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MakeRepositoryServiceCommand extends Command
{
    protected $signature = 'scribes:make-module 
                            {--name= : Nama module yang akan dibuat}
                            {--table= : Daftar tabel (comma-separated)}';

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
        
        // Generate files untuk setiap table
        if (!empty($this->tables)) {
            foreach ($this->tables as $table) {
                $this->generateTableFiles($table);
            }
        }

        $this->info("✅ Module {$this->moduleName} berhasil dibuat!");
        
        if (empty($this->tables)) {
            $this->warn("💡 Tip: Gunakan --table=table1,table2 untuk langsung generate Repository, Service, dan Controller");
        }

        // Auto-register service provider
        $this->registerServiceProviderToComposer($this->moduleName);

        return Command::SUCCESS;
    }

    private function createModuleStructure(): void
    {
        $basePath = app_path("Modules/{$this->moduleName}");
        
        $directories = [
            'Config',
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
    
        // Generate routes file
        $this->generateRoutesFile($basePath);
        
        // Generate module service provider
        $this->generateModuleServiceProvider($basePath);
    }

    private function generateTableFiles(string $table): void
    {
        if (!$this->tableExists($table)) {
            $this->error("❌ Table '{$table}' tidak ditemukan di database");
            return;
        }
    
        $this->info("🔄 Processing table: {$table}");
    
        $modelName = Str::studly(Str::singular($table));
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        
        // Generate Repository
        $this->generateRepository($table, $modelName, $columns);
        
        // Generate Service
        $this->generateService($table, $modelName);
        
        // Generate Controller
        $this->generateController($table, $modelName);
        
        // Generate Requests
        $this->generateRequests($table, $modelName, $columns);
    
        $this->line("✅ Files untuk table '{$table}' berhasil dibuat");
    }

    private function generateRepository(string $table, string $modelName): void
    {
        $repositoryDir = app_path("Modules/{$this->moduleName}/Repositories");
        
        if (!is_dir($repositoryDir)) {
            mkdir($repositoryDir, 0755, true);
        }
    
        $stub = $this->getRepositoryStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ model }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Repositories",
            $modelName,
            $modelName
        ], $stub);
    
        $filePath = "{$repositoryDir}/{$modelName}Repository.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateService(string $table, string $modelName): void
    {
        $serviceDir = app_path("Modules/{$this->moduleName}/Services");
        
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0755, true);
        }
    
        $stub = $this->getServiceStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ model }}',
            '{{ modelName }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Services",
            $modelName,
            $modelName
        ], $stub);
    
        $filePath = "{$serviceDir}/{$modelName}Service.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateController(string $table, string $modelName): void
    {
        $controllerDir = app_path("Modules/{$this->moduleName}/Http/Controllers");
        
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
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
    
        $filePath = "{$controllerDir}/{$modelName}Controller.php";
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
        
        $rulesStore = $this->generateRules($columns, 'store');
        $rulesUpdate = $this->generateRules($columns, 'update');
    
        foreach (['Store' => $rulesStore, 'Update' => $rulesUpdate] as $type => $rules) {
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
    
            $filePath = "{$modelRequestDir}/{$type}{$modelName}Request.php";
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
}