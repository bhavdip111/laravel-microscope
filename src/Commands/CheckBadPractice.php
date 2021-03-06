<?php

namespace Imanghafoori\LaravelMicroscope\Commands;

use Illuminate\Console\Command;
use Imanghafoori\LaravelMicroscope\FileReaders\Paths;
use Imanghafoori\LaravelMicroscope\Analyzers\FilePath;
use Imanghafoori\LaravelMicroscope\ErrorTypes\EnvFound;
use Imanghafoori\LaravelMicroscope\SpyClasses\RoutePaths;
use Imanghafoori\LaravelMicroscope\Analyzers\ComposerJson;
use Imanghafoori\LaravelMicroscope\Analyzers\FunctionCall;
use Imanghafoori\LaravelMicroscope\LaravelPaths\MigrationPaths;

class CheckBadPractice extends Command
{
    protected $signature = 'check:bad_practices';

    protected $description = 'Checks the bad practices';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        event('microscope.start.command');
        $this->info('Checking bad practices...');

        $this->checkPaths(RoutePaths::get());
        $this->checkPaths(Paths::getAbsFilePaths(MigrationPaths::get()));
        $this->checkPaths(Paths::getAbsFilePaths(app()->databasePath()));
        $this->checkPsr4Classes();

        event('microscope.finished.checks', [$this]);
        $this->info('&It is recommended use env() calls, only and only in config files.');
        $this->info('Otherwise you can NOT cache your config files using "config:cache"');
        $this->info('https://laravel.com/docs/5.5/configuration#configuration-caching');
    }

    private function checkForEnv($absPath)
    {
        $tokens = token_get_all(file_get_contents($absPath));

        foreach($tokens as $i => $token) {
            if (($index = FunctionCall::isGlobalCall('env', $tokens, $i))) {
                EnvFound::isMissing($absPath, $tokens[$index][2], $tokens[$index][1]);
            }
        }
    }

    private function checkPaths($paths)
    {
        foreach ($paths as $filePath) {
            $this->checkForEnv($filePath);
        }
    }

    private function checkPsr4Classes()
    {
        $psr4 = ComposerJson::readKey('autoload.psr-4');

        foreach ($psr4 as $_namespace => $dirPath) {
            foreach (FilePath::getAllPhpFiles($dirPath) as $filePath) {
                $this->checkForEnv($filePath->getRealPath());
            }
        }
    }
}
