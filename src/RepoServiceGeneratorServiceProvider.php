<?php

namespace Wahyudi\RepoServiceGenerator;

use Illuminate\Support\ServiceProvider;
use Wahyudi\RepoServiceGenerator\Commands\MakeRepositoryServiceCommand;

class RepoServiceGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeRepositoryServiceCommand::class,
            ]);

            // publish stub files agar bisa diubah oleh user
            $this->publishes([
                __DIR__.'/stubs/file-upload-trait.stub' => base_path('stubs/scribes/file-upload-trait.stub'),
                __DIR__.'/stubs/config.stub' => base_path('stubs/scribes/config.stub'),
            ], 'scribes-upload');
        }
    }
}
