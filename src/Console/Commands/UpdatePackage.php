<?php


namespace Inensus\KonexaBulkRegistration\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inensus\KonexaBulkRegistration\Services\MenuItemService;


class UpdatePackage extends Command
{
    protected $signature = 'konexa-bulk-registration:update';
    protected $description = 'Update Konexa Bulk Registration Package';

    private $menuItemService;
    private $fileSystem;

    public function __construct(
        MenuItemService $menuItemService,
        Filesystem $filesystem

    ) {
        parent::__construct();
        $this->menuItemService = $menuItemService;
        $this->fileSystem = $filesystem;
    }

    public function handle(): void
    {
        $this->info('Konexa Bulk Registration Updating Started\n');
        $this->removeOldVersionOfPackage();
        $this->installNewVersionOfPackage();
        $this->deleteMigration($this->fileSystem);
        $this->publishMigrationsAgain();
        $this->updateDatabase();
        $this->publishVueFilesAgain();
        $this->call('routes:generate');
        $this->createMenuItems();
        $this->call('sidebar:generate');
        $this->info('Package updated successfully..');
    }

    private function removeOldVersionOfPackage()
    {
        $this->info('Removing former version of package\n');
        echo shell_exec('COMPOSER_MEMORY_LIMIT=-1 ../composer.phar  remove inensus/konexa-bulk-registration');
    }

    private function installNewVersionOfPackage()
    {
        $this->info('Installing last version of package\n');
        echo shell_exec('COMPOSER_MEMORY_LIMIT=-1 ../composer.phar  require inensus/konexa-bulk-registration');

    }

    private function deleteMigration(Filesystem $filesystem)
    {
        $migrationFile = $filesystem->glob(database_path() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*_create_konexa_tables.php')[0];
        $migration = DB::table('migrations')
            ->where('migration', substr(explode("/migrations/", $migrationFile)[1], 0, -4))->first();
        if (!$migration) {
            return false;
        }
        return DB::table('migrations')
            ->where('migration', substr(explode("/migrations/", $migrationFile)[1], 0, -4))->delete();

    }

    private function publishMigrationsAgain()
    {
        $this->info('Copying migrations\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\KonexaBulkRegistration\Providers\KonexaBulkRegistrationServiceProvider",
            '--tag' => "migrations",
        ]);
    }

    private function updateDatabase()
    {
        $this->info('Updating database tables\n');
        $this->call('migrate');
    }

    private function publishVueFilesAgain()
    {
        $this->info('Copying vue files\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\KonexaBulkRegistration\Providers\KonexaBulkRegistrationServiceProvider",
            '--tag' => "vue-components",
            '--force' => true
        ]);
    }

    private function createMenuItems()
    {
        $menuItems = $this->menuItemService->createMenuItems();
        $this->call('menu-items:generate', [
            'menuItem' => $menuItems['menuItem'],
            'subMenuItems' => $menuItems['subMenuItems'],
        ]);
    }
}
