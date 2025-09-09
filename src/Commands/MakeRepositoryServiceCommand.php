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
                            {--file-upload : Generate file upload trait & config}';

    protected $description = 'Generate repository, service, controller, requests OR file upload utilities';

    public function handle(): void
    {
        // Jika hanya --file-upload
        if ($this->option('file-upload')) {
            $this->generateFileUploadSupport();
            return;
        }

        $table = $this->argument('table');
        if (!$table) {
            $this->error("❌ Anda harus memberikan nama table atau gunakan --file-upload option.");
            return;
        }

        $domain = $this->option('domain');
        $modelName = Str::studly(Str::singular($table));

        $this->info("🔧 Generating files for: {$modelName}");

        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        if (empty($columns)) {
            $this->error("❌ Table {$table} tidak ditemukan!");
            return;
        }

        $fillable = collect($columns)
            ->pluck('Field')
            ->reject(fn($col) => in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn($col) => "'{$col}'")
            ->implode(', ');

        $rulesStore = $this->generateRules($columns, 'store');
        $rulesUpdate = $this->generateRules($columns, 'update');

        $this->generateRepository($modelName, $fillable, $domain);
        $this->generateService($modelName, $domain);
        $this->generateController($modelName, $domain);
        $this->generateRequests($modelName, $rulesStore, $rulesUpdate, $domain);

        $this->info("✅ Semua file berhasil dibuat!");
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

    // ------------------- File Naming Strategies -------------------
    private function generateFileNamingStrategies(): void
    {
        $this->info("📂 Generating FileNamingStrategies class...");

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
    private function generateRepository(string $modelName, string $fillable, ?string $domain): void
    {
        $namespace = $this->makeNamespace("Repositories", $modelName, $domain);
        $dir = $this->makeDir("Repositories", $modelName, $domain);

        $stub = file_get_contents(__DIR__.'/../stubs/repository.stub');

        $content = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ fillable }}'],
            [$namespace, $modelName, $fillable],
            $stub
        );

        $this->makeDirectoryAndFile($dir, "{$modelName}Repository.php", $content);
    }

    // ------------------- Service -------------------
    private function generateService(string $modelName, ?string $domain): void
    {
        $namespace = $this->makeNamespace("Services", $modelName, $domain);
        $dir = $this->makeDir("Services", $modelName, $domain);

        $stub = file_get_contents(__DIR__.'/../stubs/service.stub');

        $content = str_replace(
            ['{{ namespace }}', '{{ model }}'],
            [$namespace, $modelName],
            $stub
        );

        $this->makeDirectoryAndFile($dir, "{$modelName}Service.php", $content);
    }

    // ------------------- Controller -------------------
    private function generateController(string $modelName, ?string $domain): void
    {
        $namespace = $domain
            ? "App\\Domains\\{$domain}\\Http\\Controllers"
            : "App\\Http\\Controllers";

        $dir = $domain
            ? app_path("Domains/{$domain}/Http/Controllers")
            : app_path("Http/Controllers");

        $stub = file_get_contents(__DIR__.'/../stubs/controller.stub');

        $content = str_replace(
            ['{{ namespace }}', '{{ model }}'],
            [$namespace, $modelName],
            $stub
        );

        $this->makeDirectoryAndFile($dir, "{$modelName}Controller.php", $content);
    }

    // ------------------- Requests -------------------
    private function generateRequests(string $modelName, string $rulesStore, string $rulesUpdate, ?string $domain): void
    {
        $namespace = $domain
            ? "App\\Domains\\{$domain}\\Http\\Requests\\{$modelName}"
            : "App\\Http\\Requests\\{$modelName}";

        $dir = $domain
            ? app_path("Domains/{$domain}/Http/Requests/{$modelName}")
            : app_path("Http/Requests/{$modelName}");

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

            if ($mode === 'update') {
                $rule = "sometimes|$rule";
            }

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

    private function makeNamespace(string $type, string $modelName, ?string $domain): string
    {
        return $domain
            ? "App\\Domains\\{$domain}\\{$type}\\{$modelName}"
            : "App\\{$type}\\{$modelName}";
    }

    private function makeDir(string $type, string $modelName, ?string $domain): string
    {
        return $domain
            ? app_path("Domains/{$domain}/{$type}/{$modelName}")
            : app_path("{$type}/{$modelName}");
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
}
