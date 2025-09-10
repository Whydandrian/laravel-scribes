<?php

namespace Wahyudi\RepoServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MakeRepositoryServiceCommand extends Command
{
    protected $signature = 'make:scribes
                            {table? : Nama tabel untuk generate repository/service/controller/requests}
                            {--domain= : Domain name (optional)}
                            {--module= : Nama module (optional)}
                            {--model= : Nama model/class (optional)}
                            {--file-upload : Generate file upload trait & config}
                            {--repository : Hanya generate repository}
                            {--service : Hanya generate service}
                            {--controller : Hanya generate controller}
                            {--request : Hanya generate requests}
                            {--api= : Generate API controller untuk resource tertentu}
                            ';

    protected $description = 'Generate repository, service, controller, requests OR file upload utilities';

    public function handle(): void
    {
        if ($this->option('file-upload')) {
            $this->generateFileUploadSupport();
            return;
        }

        $table = $this->argument('table');
        $module = $this->option('module');
        $modelName = $this->option('model') ?? ($table ? Str::studly(Str::singular($table)) : null);

        if (!$table && (!$module || !$modelName)) {
            $this->error("❌ Anda harus memberikan table atau module & model, atau gunakan --file-upload option.");
            return;
        }

        $this->info("🔧 Generating files for: " . ($module ? "$module/$modelName" : $modelName));

        $columns = $table ? DB::select("SHOW COLUMNS FROM {$table}") : [];
        $fillable = $columns ? collect($columns)
            ->pluck('Field')
            ->reject(fn($col) => in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn($col) => "'{$col}'")
            ->implode(', ') : '';
        $rulesStore = $columns ? $this->generateRules($columns, 'store') : '';
        $rulesUpdate = $columns ? $this->generateRules($columns, 'update') : '';

        // Pilihan granular
        $onlyRepository  = $this->option('repository');
        $onlyService     = $this->option('service');
        $onlyController  = $this->option('controller');
        $onlyRequest     = $this->option('request');
        $apiResource     = $this->option('api');

        if (!$onlyRepository && !$onlyService && !$onlyController && !$onlyRequest && !$apiResource) {
            $onlyRepository = $onlyService = $onlyController = $onlyRequest = $apiResource = true;
        }

        if ($onlyRepository) {
            $this->generateRepository($modelName, $fillable, $this->option('domain'), $module);
        }
        if ($onlyService) {
            $this->generateService($modelName, $this->option('domain'), $module);
        }
        if ($onlyController) {
            $this->generateController($modelName, $this->option('domain'), $module);
        }
        if ($onlyRequest) {
            $this->generateRequests($modelName, $rulesStore, $rulesUpdate, $this->option('domain'), $module);
        }
        if ($apiResource) {
            $this->generateApiController(
                $apiResource,
                $module,
                $this->option('domain')
            );
        }

        $this->info("✅ Files generated successfully in " . ($module ? "module: $module" : 'default structure'));
    }

    // ------------------- File Upload Support -------------------
    private function generateFileUploadSupport(): void
    {
        $this->info("📂 Generating file upload trait, config, and naming strategies...");

        // --- Trait ---
        $traitStub = file_get_contents(__DIR__.'/../stubs/file-upload-trait.stub');
        $traitDir  = app_path("Traits");
        if (!file_exists($traitDir)) {
            mkdir($traitDir, 0755, true);
        }
        $traitPath = "{$traitDir}/FileUploadTrait.php";
        if (!file_exists($traitPath)) {
            file_put_contents($traitPath, $traitStub);
            $this->line("📄 Created: {$traitPath}");
        } else {
            $this->warn("⚠️ FileUploadTrait sudah ada, skip.");
        }

        // --- Config ---
        $configStub = file_get_contents(__DIR__.'/../stubs/config.stub');
        $configPath = config_path('scribes.php');
        if (!file_exists($configPath)) {
            file_put_contents($configPath, $configStub);
            $this->line("📄 Created: {$configPath}");
        } else {
            $this->warn("⚠️ Config scribes.php sudah ada, skip.");
        }

        // --- FileNamingStrategies ---
        $this->generateFileNamingStrategies();

        $this->info("✅ File upload support berhasil digenerate!");
    }

    private function generateFileNamingStrategies(): void
    {
        $supportDir = app_path("Support");
        if (!file_exists($supportDir)) {
            mkdir($supportDir, 0755, true);
        }
        $classPath = "{$supportDir}/FileNamingStrategies.php";
        if (!file_exists($classPath)) {
            $classStub = file_get_contents(__DIR__.'/../stubs/file-naming-strategies.stub');
            file_put_contents($classPath, $classStub);
            $this->line("📄 Created: {$classPath}");
        } else {
            $this->warn("⚠️ FileNamingStrategies.php sudah ada, skip.");
        }
    }

    // ------------------- Repository -------------------
    private function generateRepository(string $modelName, string $fillable, ?string $domain, ?string $module): void
    {
        $namespace = $this->makeNamespace("Repositories", $modelName, $domain, $module);
        $dir = $this->makeDir("Repositories", $modelName, $domain, $module);
        $stub = file_get_contents(__DIR__.'/../stubs/repository.stub');
        $content = str_replace(['{{ namespace }}', '{{ model }}', '{{ fillable }}'], [$namespace, $modelName, $fillable], $stub);
        $this->makeDirectoryAndFile($dir, "{$modelName}Repository.php", $content);
    }

    // ------------------- Service -------------------
    private function generateService(string $modelName, ?string $domain, ?string $module): void
    {
        $namespace = $this->makeNamespace("Services", $modelName, $domain, $module);
        $dir = $this->makeDir("Services", $modelName, $domain, $module);
        $stub = file_get_contents(__DIR__.'/../stubs/service.stub');
        $content = str_replace(['{{ namespace }}', '{{ model }}'], [$namespace, $modelName], $stub);
        $this->makeDirectoryAndFile($dir, "{$modelName}Service.php", $content);
    }

    // ------------------- Controller -------------------
    private function generateController(string $modelName, ?string $domain, ?string $module): void
    {
        $namespace = $module
            ? "App\\Modules\\{$module}\\Http\\Controllers"
            : ($domain ? "App\\Domains\\{$domain}\\Http\\Controllers" : "App\\Http\\Controllers");

        $dir = $module
            ? app_path("Modules/{$module}/Http/Controllers")
            : ($domain ? app_path("Domains/{$domain}/Http/Controllers") : app_path("Http/Controllers"));

        $stub = file_get_contents(__DIR__.'/../stubs/controller.stub');
        $content = str_replace(['{{ namespace }}', '{{ model }}'], [$namespace, $modelName], $stub);
        $this->makeDirectoryAndFile($dir, "{$modelName}Controller.php", $content);
    }

    // ------------------- Requests -------------------
    private function generateRequests(string $modelName, string $rulesStore, string $rulesUpdate, ?string $domain, ?string $module): void
    {
        $namespace = $module
            ? "App\\Modules\\{$module}\\Http\\Requests\\{$modelName}"
            : ($domain ? "App\\Domains\\{$domain}\\Http\\Requests\\{$modelName}" : "App\\Http\\Requests\\{$modelName}");

        $dir = $module
            ? app_path("Modules/{$module}/Http/Requests/{$modelName}")
            : ($domain ? app_path("Domains/{$domain}/Http/Requests/{$modelName}") : app_path("Http/Requests/{$modelName}"));

        foreach (['Store' => $rulesStore, 'Update' => $rulesUpdate] as $type => $rules) {
            $stub = file_get_contents(__DIR__.'/../stubs/request.stub');
            $content = str_replace(
                ['{{ namespace }}', '{{ model }}', '{{ type }}', '{{ rules }}'],
                [$namespace, $modelName, $type, $rules],
                $stub
            );
            $this->makeDirectoryAndFile($dir, "{$type}{$modelName}Request.php", $content);
        }
    }

    // ------------------- Helpers -------------------
    private function generateRules(array $columns, string $mode = 'store'): string
    {
        return collect($columns)->map(function($col) use ($mode) {
            if (in_array($col->Field, ['id', 'created_at', 'updated_at', 'deleted_at'])) return null;
            $rule = $this->mapColumnToRule($col->Type, $col->Null === 'YES');
            if ($mode === 'update') $rule = "sometimes|$rule";
            return "'{$col->Field}' => '$rule'";
        })->filter()->implode(",\n            ");
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

    private function makeNamespace(string $type, string $modelName, ?string $domain, ?string $module = null): string
    {
        if ($domain) {
            return "App\\Domains\\{$domain}\\{$type}\\{$modelName}";
        }

        if ($module) {
            if ($type === 'Repositories' || $type === 'Services') {
                return "App\\Modules\\{$module}\\{$type}";
            }
            if ($type === 'Controllers') {
                return "App\\Modules\\{$module}\\Http\\Controllers";
            }
            if ($type === 'Requests') {
                return "App\\Modules\\{$module}\\Http\\Requests\\{$modelName}";
            }
        }

        return "App\\{$type}\\{$modelName}";
    }

    private function makeDir(string $type, string $modelName, ?string $domain, ?string $module = null): string
    {
        if ($domain) {
            return app_path("Domains/{$domain}/{$type}/{$modelName}");
        }

        if ($module) {
            // Untuk Repository, kumpulkan dalam 1 folder
            if ($type === 'Repositories') {
                return app_path("Modules/{$module}/{$type}");
            }
            if ($type === 'Services') {
                return app_path("Modules/{$module}/{$type}");
            }
            if ($type === 'Controllers') {
                return app_path("Modules/{$module}/Http/Controllers");
            }
            if ($type === 'Requests') {
                return app_path("Modules/{$module}/Http/Requests/{$modelName}");
            }
        }

        return app_path("{$type}/{$modelName}");
    }

    private function makeDirectoryAndFile(string $dir, string $filename, string $content): void
    {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$filename}";
        file_put_contents($path, $content);

        $this->line("📄 Created: {$path}");
    }

    protected function generateApiController(string $name, ?string $module, ?string $domain)
    {
        // Tentukan namespace
        if ($module) {
            $namespace = "App\\Modules\\{$module}\\Http\\Controllers\\Api";
            $dir = app_path("Modules/{$module}/Http/Controllers/Api");
        } elseif ($domain) {
            $namespace = "App\\Domains\\{$domain}\\Http\\Controllers\\Api";
            $dir = app_path("Domains/{$domain}/Http/Controllers/Api");
        } else {
            $namespace = "App\\Http\\Controllers\\Api";
            $dir = app_path("Http/Controllers/Api");
        }

        // Pastikan direktori ada
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Path file controller
        $path = "{$dir}/{$name}Controller.php";

        // Ambil stub
        $stub = file_get_contents(__DIR__.'/../stubs/api-controller.stub');

        // Replace variabel di stub
        $stub = str_replace(
            ['{{namespace}}', '{{class}}', '{{baseController}}'],
            [$namespace, "{$name}Controller", 'BaseApiController'],
            $stub
        );

        // Simpan file
        file_put_contents($path, $stub);
        $this->info("📄 API Controller created: {$path}");

        // Generate Custom Requests (Store & Update)
        $this->generateRequest($name, 'Store', $module, $domain, true);
        $this->generateRequest($name, 'Update', $module, $domain, true);
    }

    protected function generateRequest(string $name, string $type, ?string $module, ?string $domain, bool $isApi = false)
    {
        if ($module) {
            $namespace = "App\\Modules\\{$module}\\Http\\Requests\\{$name}";
            $dir = app_path("Modules/{$module}/Http/Requests/{$name}");
        } elseif ($domain) {
            $namespace = "App\\Domains\\{$domain}\\Http\\Requests\\{$name}";
            $dir = app_path("Domains/{$domain}/Http/Requests/{$name}");
        } else {
            $namespace = "App\\Http\\Requests\\{$name}";
            $dir = app_path("Http/Requests/{$name}");
        }

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$type}{$name}Request.php";
        $stub = file_get_contents(__DIR__.'/../stubs/request.stub');

        $stub = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, "{$type}{$name}Request"],
            $stub
        );

        file_put_contents($path, $stub);
        $this->info("📄 {$type} Request created: {$path}");
    }

}
