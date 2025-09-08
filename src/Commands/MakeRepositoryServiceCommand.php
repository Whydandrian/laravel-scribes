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

        $this->generateRepository($modelName);
        $this->generateService($modelName);

        $this->info("✅ Repository & Service berhasil dibuat!");
    }

    private function generateRepository(string $modelName): void
    {
        $stub = file_get_contents(__DIR__.'/../stubs/repository.stub');
        $content = str_replace('{{ model }}', $modelName, $stub);

        $path = app_path("Repositories/{$modelName}Repository.php");
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->line("📄 Repository created: {$path}");
    }

    private function generateService(string $modelName): void
    {
        $stub = file_get_contents(__DIR__.'/../stubs/service.stub');
        $content = str_replace('{{ model }}', $modelName, $stub);

        $path = app_path("Services/{$modelName}Service.php");
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->line("📄 Service created: {$path}");
    }
}
