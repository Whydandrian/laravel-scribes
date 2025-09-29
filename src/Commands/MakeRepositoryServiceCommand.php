<?php

namespace Wahyudi\RepoServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

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

        $this->info("API Module {$name} created successfully.");
    }

    protected function generateController($controllerOption, $table = null, $isApi = false)
    {
        $moduleName = $this->option('name');
        $moduleBase = $this->moduleBasePath($moduleName);

        if ($isApi && Str::startsWith($controllerOption, 'Api/')) {
            $controllerOption = Str::after($controllerOption, 'Api/');
        }

        [$basePath, $namespace, $className] = $this->resolvePathAndNamespace(
            $controllerOption,  
            $isApi ? $moduleBase.'/Http/Controllers/Api' : $moduleBase.'/Http/Controllers',
            $isApi ? 'App\\Http\\Controllers\\Api' : 'App\\Http\\Controllers'
        );

        $stub = $isApi ? $this->getStub('controller.api') : $this->getStub('controller');

        $modelName = $table ? Str::studly(Str::singular($table)) : $moduleName;
        $storeRequest = "Store{$modelName}Request";
        $updateRequest = "Update{$modelName}Request";

        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{table}}', '{{storeRequest}}','{{updateRequest}}'],
            [$namespace, $className, $table, $storeRequest, $updateRequest],
            $stub
        );
        $filePath = "{$basePath}/{$className}.php";
        if (file_exists($filePath)) {
            $this->warn("Controller {$namespace}\\{$className} sudah ada, skip.");
            return;
        }

        $filePath = "{$basePath}/{$className}.php";
        $this->putFileIfNotExists($filePath, $content, "Controller {$namespace}\\{$className}");
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
        $appPath = base_path('bootstrap/app.php');
        $providerClass = "App\\Modules\\{$name}\\{$name}ServiceProvider::class";

        $content = file_get_contents($appPath);
        if (strpos($content, $providerClass) === false) {
            $content .= "\n// Register {$name} Module Service Provider\n";
            $content .= "\$app->register(App\\Modules\\{$name}\\{$name}ServiceProvider::class);\n";
            file_put_contents($appPath, $content);
            $this->info("Registered {$name}ServiceProvider to bootstrap/app.php");
        }
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
        $stubPath = __DIR__."/stubs/{$type}.stub";
        if (!file_exists($stubPath)) {
            throw new \Exception("Stub file {$type}.stub not found.");
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
}