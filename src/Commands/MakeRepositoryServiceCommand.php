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

        return Command::SUCCESS;
    }

    private function createModuleStructure(): void
    {
        $basePath = app_path("Modules/{$this->moduleName}");
        
        $directories = [
            'Config',
            'Database/Migrations',
            'Database/Factories', 
            'Database/Seeders',
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
        
        // Generate Model jika belum ada
        $this->generateModel($table, $modelName, $columns);

        $this->line("✅ Files untuk table '{$table}' berhasil dibuat");
    }

    private function generateRepository(string $table, string $modelName, array $columns): void
    {
        $repositoryDir = app_path("Modules/{$this->moduleName}/Repositories/{$modelName}Repository");
        if (!is_dir($repositoryDir)) {
            mkdir($repositoryDir, 0755, true);
        }

        $fillable = $this->getFillableFields($columns);
        
        $stub = $this->getRepositoryStub();
        $content = str_replace([
            '{{namespace}}',
            '{{moduleName}}',
            '{{modelName}}', 
            '{{fillable}}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Repositories\\{$modelName}Repository",
            $this->moduleName,
            $modelName,
            $fillable
        ], $stub);

        $filePath = "{$repositoryDir}/{$modelName}Repository.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateService(string $table, string $modelName): void
    {
        $serviceDir = app_path("Modules/{$this->moduleName}/Services/{$this->moduleName}Service");
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0755, true);
        }

        $stub = $this->getServiceStub();
        $content = str_replace([
            '{{namespace}}',
            '{{moduleName}}',
            '{{modelName}}',
            '{{modelNameLower}}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Services\\{$this->moduleName}Service",
            $this->moduleName,
            $modelName,
            Str::camel($modelName)
        ], $stub);

        $filePath = "{$serviceDir}/{$modelName}Service.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateController(string $table, string $modelName): void
    {
        $controllerDir = app_path("Modules/{$this->moduleName}/Http/Controllers");
        
        $stub = $this->getControllerStub();
        $content = str_replace([
            '{{ namespace }}',
            '{{ model }}'
        ], [
            "App\\Modules\\{$this->moduleName}\\Http\\Controllers",
            $modelName
        ], $stub);

        $filePath = "{$controllerDir}/{$modelName}Controller.php";
        file_put_contents($filePath, $content);
        $this->line("📄 Created: {$filePath}");
    }

    private function generateRequests(string $table, string $modelName, array $columns): void
    {
        $requestDir = app_path("Modules/{$this->moduleName}/Http/Requests");
        
        $rulesStore = $this->generateRules($columns, 'store');
        $rulesUpdate = $this->generateRules($columns, 'update');

        foreach (['Store' => $rulesStore, 'Update' => $rulesUpdate] as $type => $rules) {
            $stub = $this->getRequestStub();
            $content = str_replace([
                '{{ namespace }}',
                '{{ type }}',
                '{{ model }}',
                '{{ rules }}'
            ], [
                "App\\Modules\\{$this->moduleName}\\Http\\Requests",
                $type,
                $modelName,
                $rules
            ], $stub);

            $filePath = "{$requestDir}/{$modelName}Request/{$type}{$modelName}Request.php";
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
}