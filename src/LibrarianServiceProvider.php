<?php

declare(strict_types=1);

namespace HardImpact\Librarian;

use HardImpact\Librarian\Commands\BuildCommand;
use HardImpact\Librarian\Commands\DomainCommand;
use HardImpact\Librarian\Commands\DomainsNormalizeCommand;
use HardImpact\Librarian\Commands\InitCommand;
use HardImpact\Librarian\Commands\LintCommand;
use HardImpact\Librarian\Docs\DocsConfig;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LibrarianServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('librarian')
            ->hasConfigFile()
            ->hasCommands([
                InitCommand::class,
                DomainCommand::class,
                DomainsNormalizeCommand::class,
                BuildCommand::class,
                LintCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DocsConfig::class, static fn (): DocsConfig => DocsConfig::fromConfig());
    }
}
