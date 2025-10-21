<?php

namespace Wahyudi\RepoServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeRepositoryServiceCommand extends Command
{
    protected $signature = 'scribes:generate-module
        {--name= : Nama module yang akan dibuat}
        {--table= : Nama tabel (comma-separated)}
        {--controller= : Nama controller custom (opsional)}
        {--service= : Nama service custom (opsional)}
        {--repository= : Nama repository custom (opsional)}
        {--all : Generate semua file lengkap}
        {--api : Generate API controller lengkap}';

    protected $description = 'Generate module files (controller, request, service, repository, config, routes, provider, etc)';

    public function handle()
    {
        $name = $this->option('name');
        $table = $this->option('table');

        $this->generateModuleStructure($name);

        if ($this->option('api')) {
            $this->generateApiModule();
            return 0;
        }

        if ($this->option('all')) {
            $this->generateFullModule($name, $table);
            return 0;
        }

        if ($controller = $this->option('controller')) {
            $this->generateController($controller, $table);
        }

        if ($service = $this->option('service')) {
            $this->generateService($service, $table);
        }

        if ($repository = $this->option('repository')) {
            $this->generateRepository($repository, $table);
        }

        $this->info('Command executed successfully.');
        return 0;
    }

    protected function moduleBasePath($name)
    {
        return app_path("Modules/{$name}");
    }

    protected function generateModuleStructure($name)
    {
        $moduleBase = $this->moduleBasePath($name);
        $paths = [
            "{$moduleBase}/Config/lang/id",
            "{$moduleBase}/Config/lang/en",
            "{$moduleBase}/Http/Controllers",
            "{$moduleBase}/Http/Requests",
            "{$moduleBase}/Models",
            "{$moduleBase}/Http/Middleware",
            "{$moduleBase}/Routes",
            "{$moduleBase}/Presenters",
            "{$moduleBase}/Presenters/Views",
            "{$moduleBase}/Presenters/Components",
            "{$moduleBase}/Services",
            "{$moduleBase}/Repositories",
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        $this->generateConfigFiles($name);
        $this->generateRoutesFiles($name);

        $this->generateServiceProvider($name);

        $this->info('Menjalankan composer dump-autoload...');
        exec('composer dump-autoload'); 
        $this->info('composer dump-autoload selesai.');
    }

    protected function generateConfigFiles($name)
    {
        $moduleBase = $this->moduleBasePath($name);
        $lower = strtolower($name);

        if (!is_dir("{$moduleBase}/Config")) {
            mkdir("{$moduleBase}/Config", 0755, true);
        }
        if (!is_dir("{$moduleBase}/Config/lang/id")) {
            mkdir("{$moduleBase}/Config/lang/id", 0755, true);
        }
        if (!is_dir("{$moduleBase}/Config/lang/en")) {
            mkdir("{$moduleBase}/Config/lang/en", 0755, true);
        }

        $configStub = $this->getStub('config');
        $this->putFileIfNotExists(
            "{$moduleBase}/Config/config.php",
            $configStub,
            "Config file for module {$name}"
        );

        $msgStub = $this->getStub('messages');
        $msgStub = str_replace(['{{moduleName}}'], [$name], $msgStub);

        $this->putFileIfNotExists(
            "{$moduleBase}/Config/lang/id/messages.php",
            $msgStub,
            "Lang file ID for module {$name}"
        );
        $this->putFileIfNotExists(
            "{$moduleBase}/Config/lang/en/messages.php",
            $msgStub,
            "Lang file EN for module {$name}"
        );
    }

    protected function generateRoutesFiles($name)
    {
        $moduleBase = $this->moduleBasePath($name);
        $lower = strtolower($name);

        if (!is_dir("{$moduleBase}/Routes")) {
            mkdir("{$moduleBase}/Routes", 0755, true);
        }

        $webStub = $this->getStub('routes.web');
        $webStub = str_replace(['{{moduleName}}','{{moduleNameLower}}'], [$name,$lower], $webStub);
        $this->putFileIfNotExists(
            "{$moduleBase}/Routes/web.php",
            $webStub,
            "Web routes for module {$name}"
        );

        $apiStub = $this->getStub('routes.api');
        $apiStub = str_replace(['{{moduleName}}','{{moduleNameLower}}'], [$name,$lower], $apiStub);
        $this->putFileIfNotExists(
            "{$moduleBase}/Routes/api.php",
            $apiStub,
            "API routes for module {$name}"
        );
    }

    protected function generateFullModule($name, $table = null)
    {
        $controller = $this->option('controller') ?? "{$name}Controller";
        $this->generateController($controller, $table);

        $this->generateRequest("{$name}Request/Store{$name}Request", $table);
        $this->generateRequest("{$name}Request/Update{$name}Request", $table);

        $service = $this->option('service') ?? "{$name}Service/{$name}Service";
        $this->generateService($service, $table);

        $repository = $this->option('repository') ?? "{$name}Repository/{$name}Repository";
        $this->generateRepository($repository, $table);

        $this->info("Module {$name} created successfully.");
    }

    protected function generateApiModule()
    {
        $name = $this->option('name');
        $table = $this->option('table');

        $controller = $this->option('controller') ?? "Api/{$name}Controller";
        $this->generateController($controller, $table, true);

        $this->generateRequest("{$name}Request/Store{$name}Request", $table);
        $this->generateRequest("{$name}Request/Update{$name}Request", $table);

        $service = $this->option('service') ?? "{$name}Service/{$name}Service";
        $this->generateService($service, $table);

        $repository = $this->option('repository') ?? "{$name}Repository/{$name}Repository";
        $this->generateRepository($repository, $table);
        if ($table) {
            $this->addApiRoutes($table, $name);
        }

        $this->info("API Module {$name} created successfully.");
    }

    protected function generateController($controllerOption, $table = null, $isApi = false)
    {
        $moduleName = $this->option('name');
        $moduleBase = $this->moduleBasePath($moduleName);
        $moduleNamespace = "App\\Modules\\{$moduleName}";

        $modelName = $table ? Str::studly(Str::singular($table)) : $moduleName;
        $controllerName = "{$modelName}Controller";

        $controllerBasePath = $isApi
            ? "{$moduleBase}/Http/Controllers/Api"
            : "{$moduleBase}/Http/Controllers";

        $controllerNamespace = $isApi
            ? "{$moduleNamespace}\\Http\\Controllers\\Api"
            : "{$moduleNamespace}\\Http\\Controllers";

        if (!is_dir($controllerBasePath)) {
            mkdir($controllerBasePath, 0755, true);
        }

        $stub = $isApi ? $this->getStub('controller.api') : $this->getStub('controller');

        $storeRequest = "{$moduleNamespace}\\Http\\Requests\\{$modelName}Request\\Store{$modelName}Request";
        $updateRequest = "{$moduleNamespace}\\Http\\Requests\\{$modelName}Request\\Update{$modelName}Request";
        $serviceNamespace = "{$moduleNamespace}\\Services\\{$modelName}Service\\{$modelName}Service";
        $customStoreRequest = "Store{$modelName}Request";
        $customUpdateRequest = "Update{$modelName}Request";

        $modelNamePlural = Str::plural(Str::kebab($modelName));
        $moduleNameLower = strtolower($moduleName);
        $requestBodyProperties = '';
        if ($isApi && $table) {
            $columns = $this->getTableColumns($table);
            $requestBodyProperties = $this->generateSwaggerRequestProperties($columns);
        }


        $content = str_replace(
            [
                '{{namespace}}',
                '{{class}}',
                '{{table}}',
                '{{storeRequest}}',
                '{{storeRequestCustom}}',
                '{{updateRequest}}',
                '{{updateRequestCustom}}',
                '{{serviceNamespace}}',
                '{{modelName}}',
                '{{modelNamePlural}}',
                '{{moduleName}}',
                '{{moduleNameLower}}',
                '{{requestBodyProperties}}',
            ],
            [
                $controllerNamespace,
                $controllerName,
                $table,
                "use {$storeRequest};",
                "{$customStoreRequest}",
                "use {$updateRequest};",
                "{$customUpdateRequest}",
                $serviceNamespace,
                $modelName,
                $modelNamePlural,
                $moduleName,
                $moduleNameLower,
                $requestBodyProperties,
            ],
            $stub
        );

        $filePath = "{$controllerBasePath}/{$controllerName}.php";
        $this->putFileIfNotExists($filePath, $content, "Controller {$controllerNamespace}\\{$controllerName}");
    }

    protected function getTableColumns($table)
    {
        if (!Schema::hasTable($table)) {
            return [];
        }
        return Schema::getColumns($table);
    }
    protected function generateSwaggerRequestProperties($columns)
    {
        $properties = '';
        $required = [];
        foreach ($columns as $column) {
            if (in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            if (!$column['nullable']) {
                $required[] = "\"{$column['name']}\"";
            }
            $type = $this->mapColumnTypeToSwagger($column['type']);
            $example = $this->generateExampleValue($column['name'], $type);
            $properties .= "       *         @OA\Property(\n";
            $properties .= "       *           property=\"{$column['name']}\",\n";
            $properties .= "       *           type=\"{$type}\",\n";
            $properties .= "       *           example=\"{$example}\"\n";
            $properties .= "       *         ),\n";
        }
        $requiredStr = implode(', ', $required);
        $result = '';
        if ($requiredStr) {
            $result .= "required={" . $requiredStr . "},\n       ";
        }
        $result .= rtrim($properties, ",\n");
        return $result;
    }
    protected function mapColumnTypeToSwagger($type)
    {
        $type = strtolower($type);
        if (str_contains($type, 'int')) {
            return 'integer';
        }
        if (str_contains($type, 'bool')) {
            return 'boolean';
        }
        if (str_contains($type, 'float') || str_contains($type, 'double') || str_contains($type, 'decimal')) {
            return 'number';
        }
        if (str_contains($type, 'date') || str_contains($type, 'time')) {
            return 'string';
        }
        if (str_contains($type, 'json')) {
            return 'object';
        }
        return 'string';
    }
    protected function generateExampleValue($columnName, $type)
    {
        if (str_contains($columnName, 'email')) {
            return 'user@example.com';
        }
        if (str_contains($columnName, 'name')) {
            return 'example name';
        }
        if (str_contains($columnName, 'title')) {
            return 'example title';
        }
        if (str_contains($columnName, 'description')) {
            return 'example description';
        }
        if (str_contains($columnName, 'status')) {
            return 'active';
        }
        if ($type === 'integer') {
            return '1';
        }
        if ($type === 'boolean') {
            return 'true';
        }
        if ($type === 'number') {
            return '0.00';
        }
        return 'example value';
    }

    protected function generateRequest($requestOption, $table = null)
    {
        $moduleBase = $this->moduleBasePath($this->option('name'));
        $modelName = $table ? Str::studly(Str::singular($table)) : null;

        if ($table) {
            if (Str::contains($requestOption, 'Store')) {
                $requestOption = "{$modelName}Request/Store{$modelName}Request";
            } elseif (Str::contains($requestOption, 'Update')) {
                $requestOption = "{$modelName}Request/Update{$modelName}Request";
            }
        }

        [$basePath, $namespace, $className] = $this->resolvePathAndNamespace(
            $requestOption,
            $moduleBase.'/Http/Requests',
            "App\\Modules\\{$this->option('name')}\\Http\\Requests"
        );

        $stub = $this->getStub('request');
        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{table}}'],
            [$namespace, $className, $table],
            $stub
        );

        $filePath = "{$basePath}/{$className}.php";
        $this->putFileIfNotExists($filePath, $content, "Request {$namespace}\\{$className}");
    }

    protected function generateService($serviceOption, $table = null)
    {
        $moduleBase = $this->moduleBasePath($this->option('name'));
        $modelName = $table ? Str::studly(Str::singular($table)) : null;

        if ($table) {
            $serviceOption = "{$modelName}Service/{$modelName}Service";
        } else {
            $serviceOption = $serviceOption ?: "{$this->option('name')}Service/{$this->option('name')}Service";
        }

        [$basePath, $namespace, $className] = $this->resolvePathAndNamespace(
            $serviceOption,
            $moduleBase.'/Services',
            'App\\Modules\\'.$this->option('name').'\\Services'
        );

        $moduleName = $this->option('name');

        if ($table) {
            $stub = $this->getStub('service');
        } else {
            $stub = $this->getStub('service.empty');
        }
        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{table}}', '{{moduleName}}', '{{modelName}}'],
            [$namespace, $className, $table, $moduleName, $modelName],
            $stub
        );

        $filePath = "{$basePath}/{$className}.php";
        if (file_exists($filePath)) {
            $this->warn("Service {$namespace}\\{$className} sudah ada, skip.");
            return;
        }

        $filePath = "{$basePath}/{$className}.php";
        $this->putFileIfNotExists($filePath, $content, "Service {$namespace}\\{$className}");
    }

    protected function generateRepository($repositoryOption, $table = null)
    {
        $moduleBase = $this->moduleBasePath($this->option('name'));
        $modelName = $table ? Str::studly(Str::singular($table)) : null;

        if ($table && $modelName) {
            $this->generateInterface("{$modelName}Repository/{$modelName}Interface", $table);
        }

        if ($table) {
            $repositoryOption = "{$modelName}Repository/{$modelName}Repository";
        } else {
            $repositoryOption = $repositoryOption ?: "{$this->option('name')}Repository/{$this->option('name')}Repository";
        }

        [$basePath, $namespace, $className] = $this->resolvePathAndNamespace(
            $repositoryOption,
            $moduleBase.'/Repositories',
            'App\\Modules\\'.$this->option('name').'\\Repositories'
        );


        if ($table) {
            $stub = $this->getStub('repository');
        } else {
            $stub = $this->getStub('repository.empty');
        }

        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{table}}', '{{modelName}}', '{{moduleName}}'],
            [$namespace, $className, $table, $modelName, $this->option('name')],
            $stub
        );

        $filePath = "{$basePath}/{$className}.php";
        if (file_exists($filePath)) {
            $this->warn("Repository {$namespace}\\{$className} sudah ada, skip.");
            return;
        }

        $filePath = "{$basePath}/{$className}.php";
        $this->putFileIfNotExists($filePath, $content, "Repository {$namespace}\\{$className}");
    }

    protected function generateFillableArray($columns)
    {
        $fillable = [];
        foreach ($columns as $column) {
            if (!in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                $fillable[] = "'{$column['name']}'";
            }
        }
        return "[\n        " . implode(",\n        ", $fillable) . ",\n    ]";
    }

    protected function generateServiceProvider($name)
    {
        $moduleBase = $this->moduleBasePath($name);
        $lower = strtolower($name);

        $stub = $this->getStub('module.service.provider');
        $content = str_replace(['{{moduleName}}','{{moduleNameLower}}'], [$name,$lower], $stub);

        $providerPath = "{$moduleBase}/{$name}ServiceProvider.php";
        $this->putFileIfNotExists($providerPath, $content, "Service Provider {$providerPath}");

        $this->updateBootstrapApp($name);
        $this->updateComposerJson($name);
    }

    protected function generateInterface($interfaceOption, $table = null)
    {
        $moduleBase = $this->moduleBasePath($this->option('name'));
        $modelName = $table ? Str::studly(Str::singular($table)) : null;

        $interfaceOption = "{$modelName}Repository/{$modelName}Interface";

        [$basePath, $namespace, $className] = $this->resolvePathAndNamespace(
            $interfaceOption,
            $moduleBase.'/Repositories',
            'App\\Modules\\'.$this->option('name').'\\Repositories'
        );

        $stub = $this->getStub('interface');

        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{modelName}}'],
            [$namespace, $className, $modelName],
            $stub
        );

        $filePath = "{$basePath}/{$className}.php";
        $this->putFileIfNotExists($filePath, $content, "Interface {$namespace}\\{$className}");
    }


    protected function resolvePathAndNamespace($option, $baseDir, $baseNamespace)
    {
        $parts = explode('/', $option);
        $className = array_pop($parts);
        $folderPath = implode('/', $parts);

        $fullDir = $folderPath ? $baseDir.'/'.$folderPath : $baseDir;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $namespace = $folderPath
            ? $baseNamespace.'\\'.str_replace('/', '\\', $folderPath)
            : $baseNamespace;

        return [$fullDir, $namespace, $className];
    }



    protected function updateBootstrapApp($name)
    {
        $providersPath = base_path('bootstrap/providers.php');
        if (!file_exists($providersPath)) {
            $this->warn("File bootstrap/providers.php tidak ditemukan, skip.");
            return;
        }

        $providerClass = "App\\Modules\\{$name}\\{$name}ServiceProvider";

        $providers = include $providersPath;

        if (!is_array($providers)) {
            $this->warn("Isi bootstrap/providers.php bukan array provider, skip.");
            return;
        }

        if (!in_array($providerClass, $providers)) {
            $providers[] = $providerClass;
        } else {
            $this->warn("{$name}ServiceProvider sudah terdaftar di bootstrap/providers.php, skip.");
            return;
        }

        $php = "<?php\n\nreturn [\n";
        foreach ($providers as $prov) {
            $php .= "    {$prov}::class,\n";
        }
        $php .= "];\n";

        file_put_contents($providersPath, $php);

        $this->info("Registered {$name}ServiceProvider to bootstrap/providers.php");
    }

    protected function updateComposerJson($name)
    {
        $composerPath = base_path('composer.json');
        $json = json_decode(file_get_contents($composerPath), true);

        if (!isset($json['autoload']['psr-4']['App\\Modules\\'])) {
            $json['autoload']['psr-4']['App\\Modules\\'] = 'app/Modules/';
        }

        file_put_contents($composerPath, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->info("Updated composer.json autoload for App\\Modules\\");
    }

    protected function getStub($type)
    {
        $stubPath = __DIR__.'/../stubs/'.$type.'.stub';

        $publishedPath = base_path('stubs/repogenerator/'.$type.'.stub');

        if (file_exists($publishedPath)) {
            return file_get_contents($publishedPath);
        }

        if (!file_exists($stubPath)) {
            throw new \Exception("Stub file {$type}.stub not found. Checked {$stubPath} and {$publishedPath}");
        }

        return file_get_contents($stubPath);
    }

    protected function putFileIfNotExists(string $filePath, string $content, string $what): void
    {
        if (file_exists($filePath)) {
            $this->warn("$what sudah ada, skip.");
            return;
        }

        file_put_contents($filePath, $content);
        $this->info("$what created.");
    }

    protected function addApiRoutes($table, $moduleName)
    {
        $modelName = Str::studly(Str::singular($table));
        $routePrefix = Str::kebab(Str::plural(str_replace('_', '-', $table)));
        $moduleNameLower = strtolower($moduleName);
        $controllerPath = "App\\Modules\\{$moduleName}\\Http\\Controllers\\Api\\{$modelName}Controller";

        $moduleBase = $this->moduleBasePath($moduleName);
        $apiRoutePath = "{$moduleBase}/Routes/api.php";

        if (!file_exists($apiRoutePath)) {
            $this->warn("File {$apiRoutePath} tidak ditemukan.");
            return;
        }

        $routeContent = file_get_contents($apiRoutePath);

        if (strpos($routeContent, "prefix('{$routePrefix}')") !== false) {
            $this->warn("Route untuk prefix '{$routePrefix}' sudah ada.");
            return;
        }

        $routeBlock = $this->generateRouteBlock($modelName, $routePrefix, $controllerPath);

        $prefixPattern = "/prefix\('{$moduleNameLower}'\)->name\('{$moduleNameLower}\.'\)->group\(function\s*\(\)\s*\{/";

        if (preg_match($prefixPattern, $routeContent)) {
            $updatedContent = $this->insertRouteIntoGroup($routeContent, $routeBlock, $moduleNameLower);
            
            if (file_put_contents($apiRoutePath, $updatedContent)) {
                $this->info("✓ Route untuk '{$routePrefix}' berhasil ditambahkan ke {$apiRoutePath}");
            } else {
                $this->error("Gagal menyimpan route ke {$apiRoutePath}");
            }
        } else {
            $this->warn("Tidak dapat menemukan prefix group untuk '{$moduleNameLower}' di {$apiRoutePath}");
        }
    }

    protected function generateRouteBlock($modelName, $routePrefix, $controllerPath)
    {
        $routeName = Str::kebab($modelName);
        
        $block = "\n// {$modelName} Routes\n"
            . "Route::prefix('{$routePrefix}')->group(function () {\n"
            . "    Route::get('/', [{$controllerPath}::class, 'index'])->name('index');\n"
            . "    Route::get('/{id}', [{$controllerPath}::class, 'find'])->name('show');\n"
            . "    Route::post('/', [{$controllerPath}::class, 'store'])->name('store');\n"
            . "    Route::put('/{id}', [{$controllerPath}::class, 'update'])->name('update');\n"
            . "    Route::delete('/{id}', [{$controllerPath}::class, 'destroy'])->name('destroy');\n"
            . "});\n";

        return $block;
    }

    protected function insertRouteIntoGroup($routeContent, $routeBlock, $moduleNameLower)
    {
        $lines = explode("\n", $routeContent);
        $insertPosition = -1;
        $braceCount = 0;
        $inMainGroup = false;

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);

            if (empty($line) || strpos($line, '//') === 0) {
                continue;
            }

            if ($line === '});') {
                $braceCount++;
                if ($braceCount === 1) {
                    $insertPosition = $i;
                    break;
                }
            }
        }

        if ($insertPosition === -1) {
            return $routeContent;
        }

        array_splice($lines, $insertPosition, 0, explode("\n", $routeBlock));

        return implode("\n", $lines);
    }
}