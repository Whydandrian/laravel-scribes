<?php

namespace Wahyudi\RepoServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MakeRepositoryServiceCommand extends Command
{
    protected $signature = 'make:scribes {table}';
    protected $description = 'Generate repository & service class based on table schema';

    public function handle(): void
    {
        $table = $this->argument('table');
        $modelName = Str::studly(Str::singular($table));

        $this->info("🔧 Generating Repository & Service for: {$modelName}");

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

        $this->generateRepository($modelName, $fillable);
        $this->generateService($modelName);

        $this->info("✅ Repository & Service berhasil dibuat!");
    }

    private function generateRepository(string $modelName, string $fillable): void
    {
        $stub = file_get_contents(__DIR__.'/../stubs/repository.stub');

        $content = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ fillable }}'],
            ["App\\Repositories\\{$modelName}Repository", $modelName, $fillable],
            $stub
        );

        // Buat folder khusus per model
        $dir = app_path("Repositories/{$modelName}Repository");
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$modelName}Repository.php";
        file_put_contents($path, $content);

        $this->line("📄 Repository created: {$path}");
    }

    private function generateService(string $modelName): void
    {
        $stub = file_get_contents(__DIR__.'/../stubs/service.stub');

        $content = str_replace(
            ['{{ namespace }}', '{{ model }}'],
            ["App\\Services\\{$modelName}Service", $modelName],
            $stub
        );

        // Buat folder khusus per model
        $dir = app_path("Services/{$modelName}Service");
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$modelName}Service.php";
        file_put_contents($path, $content);

        $this->line("📄 Service created: {$path}");
    }
}
