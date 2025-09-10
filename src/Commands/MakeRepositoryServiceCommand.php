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

    protected $description = 'Generate repository, service, controller, requests, API controller, atau file upload support.';

    public function handle(): int
    {
        $table     = $this->argument('table');
        $module    = $this->option('module');
        $domain    = $this->option('domain');
        $modelName = $this->option('model') ?? ($table ? Str::studly(Str::singular($table)) : null);
        $api       = $this->option('api');

        // --- File upload support ---
        if ($this->option('file-upload')) {
            $this->generateFileUploadSupport();
            return Command::SUCCESS;
        }

        // --- API Controller support ---
        if ($api) {
            $this->generateApiController($api, $module, $domain);
            return Command::SUCCESS;
        }

        // --- Validasi umum ---
        if (!$table) {
            $this->error("❌ Anda harus memberikan {table}, atau gunakan --file-upload, atau gunakan --api.");
            return Command::FAILURE;
        }

        $this->info("🔧 Generating files for: " . ($module ? "$module/$modelName" : $modelName));

        // Ambil struktur table
        $columns     = DB::select("SHOW COLUMNS FROM {$table}");
        $fillable    = collect($columns)
            ->pluck('Field')
            ->reject(fn($col) => in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn($col) => "'{$col}'")
            ->implode(', ');
        $rulesStore  = $this->generateRules($columns, 'store');
        $rulesUpdate = $this->generateRules($columns, 'update');

        // Opsi granular
        $onlyRepository = $this->option('repository');
        $onlyService    = $this->option('service');
        $onlyController = $this->option('controller');
        $onlyRequest    = $this->option('request');

        if (!$onlyRepository && !$onlyService && !$onlyController && !$onlyRequest) {
            $onlyRepository = $onlyService = $onlyController = $onlyRequest = true;
        }

        if ($onlyRepository) {
            $this->generateRepository($modelName, $fillable, $domain, $module);
        }
        if ($onlyService) {
            $this->generateService($modelName, $domain, $module);
        }
        if ($onlyController) {
            $this->generateController($modelName, $domain, $module);
        }
        if ($onlyRequest) {
            $this->generateRequests($modelName, $rulesStore, $rulesUpdate, $domain, $module);
        }

        $this->info("✅ Files generated successfully in " . ($module ? "module: $module" : 'default structure'));

        return Command::SUCCESS;
    }

    // ------------------- File Upload Support -------------------
    private function generateFileUploadSupport(): void
    {
        $this->info("📂 Generating file upload trait, config, and naming strategies...");

        // --- Trait ---
        $traitStub = file_get_contents(__DIR__.'/../stubs/file-upload-trait.stub');
        $traitDir  = app_path("Traits");
        if (!is_dir($traitDir)) mkdir($traitDir, 0755, true);

        $traitPath = "{$traitDir}/FileUploadTrait.php";
        if (!is_file($traitPath)) {
            file_put_contents($traitPath, $traitStub);
            $this->line("📄 Created: {$traitPath}");
        } else {
            $this->warn("⚠️ FileUploadTrait sudah ada, skip.");
        }

        // --- Config ---
        $configStub = file_get_contents(__DIR__.'/../stubs/config.stub');
        $configPath = config_path('scribes.php');
        if (!is_file($configPath)) {
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
        if (!is_dir($supportDir)) mkdir($supportDir, 0755, true);

        $classPath = "{$supportDir}/FileNamingStrategies.php";
        if (!is_file($classPath)) {
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
        $namespace = $this->makeNamespace("Repositories", $domain, $module);
        $dir       = $this->makeDir("Repositories", $domain, $module);

        $stub = file_get_contents(__DIR__.'/../stubs/repository.stub');
        $content = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ fillable }}'],
            [$namespace, $modelName, $fillable],
            $stub
        );

        $this->makeDirectoryAndFile($dir, "{$modelName}Repository.php", $content);
    }

    // ------------------- Service -------------------
    private function generateService(string $modelName, ?string $domain, ?string $module): void
    {
        $namespace = $this->makeNamespace("Services", $domain, $module);
        $dir       = $this->makeDir("Services", $domain, $module);

        $stub = file_get_contents(__DIR__.'/../stubs/service.stub');
        $content = str_replace(['{{ namespace }}', '{{ model }}'], [$namespace, $modelName], $stub);

        $this->makeDirectoryAndFile($dir, "{$modelName}Service.php", $content);
    }

    // ------------------- Controller -------------------
    private function generateController(string $modelName, ?string $domain, ?string $module): void
    {
        $namespace = $this->makeNamespace("Http\\Controllers", $domain, $module);
        $dir       = $this->makeDir("Http/Controllers", $domain, $module);

        $stub = file_get_contents(__DIR__.'/../stubs/controller.stub');
        $content = str_replace(['{{ namespace }}', '{{ model }}'], [$namespace, $modelName], $stub);

        $this->makeDirectoryAndFile($dir, "{$modelName}Controller.php", $content);
    }

    // ------------------- Requests -------------------
    private function generateRequests(string $modelName, string $rulesStore, string $rulesUpdate, ?string $domain, ?string $module): void
    {
        $namespace = $this->makeNamespace("Http\\Requests\\{$modelName}", $domain, $module);
        $dir       = $this->makeDir("Http/Requests/{$modelName}", $domain, $module);

        foreach (['Store' => $rulesStore, 'Update' => $rulesUpdate] as $type => $rules) {
            $stub = file_get_contents(__DIR__.'/../stubs/request.stub');
            $content = str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ rules }}'],
                [$namespace, "{$type}{$modelName}Request", $rules],
                $stub
            );
            $this->makeDirectoryAndFile($dir, "{$type}{$modelName}Request.php", $content);
        }
    }

    // ------------------- API Controller -------------------
    protected function generateApiController(string $name, ?string $module, ?string $domain): void
    {
        $namespace = $this->makeNamespace("Http\\Controllers\\Api", $domain, $module);
        $dir       = $this->makeDir("Http/Controllers/Api", $domain, $module);

        $stub = file_get_contents(__DIR__.'/../stubs/api-controller.stub');
        $stub = str_replace(
            ['{{namespace}}', '{{class}}', '{{baseController}}'],
            [$namespace, "{$name}Controller", 'BaseApiController'],
            $stub
        );

        $this->makeDirectoryAndFile($dir, "{$name}Controller.php", $stub);

        $this->info("📄 API Controller created: {$dir}/{$name}Controller.php");

        // Generate Store & Update Request
        $this->generateRequests($name, '', '', $domain, $module);
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
            str_contains($type, 'int')       => 'integer',
            str_contains($type, 'varchar'),
            str_contains($type, 'text')      => 'string',
            str_contains($type, 'date') && !str_contains($type, 'time') => 'date',
            str_contains($type, 'datetime')  => 'date_format:Y-m-d H:i:s',
            str_contains($type, 'tinyint(1)')=> 'boolean',
            default                          => 'string',
        };
        return $nullable ? "nullable|$rule" : "required|$rule";
    }

    private function makeNamespace(string $type, ?string $domain, ?string $module): string
    {
        if ($module) return "App\\Modules\\{$module}\\{$type}";
        if ($domain) return "App\\Domains\\{$domain}\\{$type}";
        return "App\\{$type}";
    }

    private function makeDir(string $type, ?string $domain, ?string $module): string
    {
        if ($module) return app_path("Modules/{$module}/{$type}");
        if ($domain) return app_path("Domains/{$domain}/{$type}");
        return app_path($type);
    }

    private function makeDirectoryAndFile(string $dir, string $filename, string $content): void
    {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = "{$dir}/{$filename}";
        file_put_contents($path, $content);
        $this->line("📄 Created: {$path}");
    }
}
