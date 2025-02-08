<?php

namespace Agency\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Agency\Auth\Exceptions\ConfigurationException;

class SetupAgency extends Command
{
    protected $signature = 'agency:setup 
        {--force : Force the installation even if environment variables are missing}
        {--skip-env : Skip environment variable checks}
        {--path= : Custom path for models}
        {--no-interaction : Proceed without asking for confirmation}';

    protected $description = 'Setup Agency authentication and organization structure';

    protected $userModelTemplate = <<<'MODEL'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Agency\Auth\Traits\HasClerkId;
use Agency\Auth\Traits\HasPermissions;
use Agency\Auth\Traits\HasOrganizations;

class User extends Authenticatable
{
    use HasClerkId, HasPermissions, HasOrganizations;

    protected $fillable = [
        'name',
        'email',
        'clerk_id',
        'clerk_metadata',
        'current_organization_id'
    ];

    protected $casts = [
        'clerk_metadata' => 'array',
    ];
}
MODEL;

    protected $organizationModelTemplate = <<<'MODEL'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Agency\Auth\Contracts\OrganizationContract;
use Agency\Auth\Traits\HasClerkOrganization;

class Organization extends Model implements OrganizationContract
{
    use SoftDeletes, HasClerkOrganization;

    protected $fillable = [
        'clerk_id',
        'name',
        'slug',
        'clerk_metadata',
    ];

    protected $casts = [
        'clerk_metadata' => 'array',
    ];
}
MODEL;

    protected $organizationUserTemplate = <<<'MODEL'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Agency\Auth\Contracts\OrganizationUserContract;
use Agency\Auth\Traits\HasOrganizationRole;

class OrganizationUser extends Pivot implements OrganizationUserContract
{
    use HasOrganizationRole;

    protected $casts = [
        'permissions' => 'array',
    ];
}
MODEL;

    public function handle()
    {
        $this->info('Starting Agency setup...');

        // Check environment unless skipped
        if (!$this->option('skip-env')) {
            try {
                $this->validateEnvironment();
            } catch (ConfigurationException $e) {
                if (!$this->option('force')) {
                    $this->error($e->getMessage());
                    $this->error('Setup aborted. Please set the required environment variables or use --force to skip this check.');
                    return 1;
                }
                
                $this->warn($e->getMessage());
                $this->warn('Proceeding with setup due to --force flag...');
            }
        }

        // Setup steps
        $this->setupConfig();
        $this->setupMigrations();
        $this->setupMiddleware();
        $this->setupModels();

        // Final instructions
        $this->showNextSteps();

        return 0;
    }

    private function validateEnvironment()
    {
        $missingVars = [];

        if (!env('CLERK_SECRET_KEY')) {
            $missingVars[] = 'CLERK_SECRET_KEY';
        }

        if (!env('CLERK_PUBLISHABLE_KEY')) {
            $missingVars[] = 'CLERK_PUBLISHABLE_KEY';
        }

        if (!empty($missingVars)) {
            throw new ConfigurationException(
                'Missing required environment variables: ' . implode(', ', $missingVars)
            );
        }

        // Validate key formats
        $secretKey = env('CLERK_SECRET_KEY');
        $publishableKey = env('CLERK_PUBLISHABLE_KEY');

        if (!str_starts_with($secretKey, 'sk_')) {
            throw new ConfigurationException('Invalid CLERK_SECRET_KEY format. Must start with "sk_"');
        }

        if (!str_starts_with($publishableKey, 'pk_')) {
            throw new ConfigurationException('Invalid CLERK_PUBLISHABLE_KEY format. Must start with "pk_"');
        }
    }

    private function setupConfig()
    {
        $this->info('Setting up configuration...');
        
        if (!File::isDirectory(config_path('agency'))) {
            File::makeDirectory(config_path('agency'));
        }

        $configPath = config_path('agency/auth.php');

        if (File::exists($configPath) && !$this->option('no-interaction')) {
            if (!$this->confirm('Auth configuration already exists. Do you want to overwrite it?')) {
                return;
            }
        }

        File::copy(__DIR__.'/../../../config/auth.php', $configPath);
        $this->info('Configuration published successfully.');
    }

    private function setupMigrations()
    {
        $this->info('Setting up migrations...');

        $migrationsPath = database_path('migrations');
        $timestamp = date('Y_m_d_His');

        // User columns migration
        $this->createMigration(
            'add_clerk_user_columns.php.stub',
            "{$timestamp}_add_clerk_user_columns.php"
        );

        // Organization tables migration
        $timestamp = date('Y_m_d_His', strtotime('+1 second'));
        $this->createMigration(
            'create_clerk_organizations_table.php.stub',
            "{$timestamp}_create_clerk_organizations_table.php"
        );
    }

    private function createMigration($source, $destination)
    {
        $migrationSource = __DIR__."/../../../database/migrations/{$source}";
        $migrationDestination = database_path("migrations/{$destination}");

        if (File::exists($migrationDestination) && !$this->option('no-interaction')) {
            if (!$this->confirm("Migration {$source} already exists. Do you want to overwrite it?")) {
                return;
            }
        }

        File::copy($migrationSource, $migrationDestination);
    }

    private function setupMiddleware()
    {
        $this->info('Setting up middleware...');

        $kernelPath = app_path('Http/Kernel.php');
        $kernelContents = File::get($kernelPath);

        if (!str_contains($kernelContents, 'clerk.auth')) {
            $pattern = "/protected \\\$middlewareAliases = \[/";
            $alias = "\n        'clerk.auth' => \Agency\Auth\Middleware\AuthenticateWithClerk::class,";
            $alias .= "\n        'clerk.permissions' => \Agency\Auth\Middleware\CheckPermissions::class,";
            $alias .= "\n        'clerk.organization' => \Agency\Auth\Middleware\RequireOrganization::class,";
            
            $kernelContents = preg_replace(
                $pattern,
                "protected \$middlewareAliases = [$alias",
                $kernelContents
            );

            File::put($kernelPath, $kernelContents);
            $this->info('Middleware registered successfully.');
        }
    }

    private function setupModels()
    {
        $this->info('Setting up models...');

        $path = $this->option('path') ?? app_path('Models');
        
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $this->createModel($path, 'User', $this->userModelTemplate);
        $this->createModel($path, 'Organization', $this->organizationModelTemplate);
        $this->createModel($path, 'OrganizationUser', $this->organizationUserTemplate);
    }

    private function createModel(string $path, string $name, string $template)
    {
        $modelPath = "{$path}/{$name}.php";

        if (File::exists($modelPath) && !$this->option('no-interaction')) {
            if (!$this->confirm("{$name} model already exists. Do you want to overwrite it?")) {
                return;
            }
        }

        File::put($modelPath, $template);
        $this->info("{$name} model created successfully.");
    }

    private function showNextSteps()
    {
        $this->info('');
        $this->info('Setup completed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Run migrations:');
        $this->info('   php artisan migrate');
        $this->info('');
        $this->info('2. Add to your .env file:');
        $this->info('   CLERK_SECRET_KEY=your_secret_key');
        $this->info('   CLERK_PUBLISHABLE_KEY=your_publishable_key');
        $this->info('');
        $this->info('3. Review generated models in app/Models:');
        $this->info('   - User.php');
        $this->info('   - Organization.php');
        $this->info('   - OrganizationUser.php');
    }
}