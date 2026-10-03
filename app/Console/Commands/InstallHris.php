<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Services\EmployeeIdGenerator;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HelpdeskCategorySeeder;
use Database\Seeders\MasterListSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RenewalTypeSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Prompts\Prompt;
use Minishlink\WebPush\VAPID;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * First-time production setup: preflight checks, key generation, migrations,
 * the system reference data the app cannot run without (roles, permission
 * matrix, workflows, country lists, renewal types, the confidential helpdesk
 * category) and the first Admin login. No demo or sample data is created.
 */
class InstallHris extends Command
{
    protected $signature = 'hris:install
        {--company= : Company name (prompted if omitted)}
        {--admin-first-name= : Admin first name (prompted if omitted)}
        {--admin-last-name= : Admin last name (prompted if omitted)}
        {--admin-email= : Admin login e-mail (prompted if omitted)}
        {--skip-optimize : Do not cache config/routes/views at the end}';

    protected $description = 'Install SI HRIS on a fresh database: system data + the first Admin account (no demo data).';

    private bool $tty = false;

    public function handle(): int
    {
        // Nested Artisan::call()s (migrate, config:clear...) flip Laravel Prompts' global interactive flag off and point its output at their own buffer, so the real values are restored before prompting.
        $this->tty = $this->input->isInteractive() && defined('STDIN') && stream_isatty(STDIN);

        $this->components->info('SI HRIS installer');

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        $this->ensureEnvSecrets();

        $this->components->task('Running database migrations', fn () => Artisan::call('migrate', ['--force' => true]) === 0);

        if (User::query()->exists()) {
            $this->components->error('This database already contains user accounts, so it looks installed already. Nothing was changed. (To add users use the application itself; to start over, empty the database first.)');

            return self::FAILURE;
        }

        $input = $this->collectInput();

        if ($input === null) {
            return self::FAILURE;
        }

        [$company, $first, $last, $email, $pass] = $input;

        DB::transaction(function () use ($company, $first, $last, $email, $pass) {
            $this->seedSystemData();

            $settings = Setting::current()->refresh();
            $settings->update(['company_name' => $company]);

            // Validated against the live password policy once the settings row exists.
            Validator::make(['password' => $pass], ['password' => [new PasswordPolicy]])->validate();

            $admin = User::create([
                'name' => "$first $last",
                'email' => $email,
                'password' => $pass,
                'role_id' => Role::where('slug', 'admin')->firstOrFail()->id,
                'password_policy_version' => $settings->password_policy_version,
            ]);

            $hired = now();
            Employee::create([
                'user_id' => $admin->id,
                'employee_id' => app(EmployeeIdGenerator::class)->generate($hired),
                'first_name' => $first,
                'last_name' => $last,
                'initials' => Str::upper(Str::substr($first, 0, 1).Str::substr($last, 0, 1)),
                'hire_date' => $hired->toDateString(),
                'work_email' => $email,
            ]);
        });
        $this->components->twoColumnDetail('System data', '<info>created</info>');
        $this->components->twoColumnDetail('Admin account', "<info>$email</info>");

        $this->components->task('Linking public storage', function () {
            if (! is_link(public_path('storage')) && ! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }

            return true;
        });

        if (! $this->option('skip-optimize')) {
            $this->components->task('Caching configuration, routes, views and events', fn () => Artisan::call('optimize') === 0);
        }

        $this->newLine();
        $this->components->info('Installation complete. Sign in at '.config('app.url').' with the admin e-mail and password you just set.');
        $this->line('  Next: add the scheduler cron entry and configure MAIL_* in .env (INSTALL.md steps 8 and 9).');

        return self::SUCCESS;
    }

    private function preflight(): bool
    {
        $ok = true;
        $check = function (string $label, bool $pass, string $fix = '') use (&$ok) {
            $this->components->twoColumnDetail($label, $pass ? '<info>OK</info>' : '<error>FAIL</error>');
            if (! $pass) {
                $ok = false;
                if ($fix) {
                    $this->line("    -> $fix");
                }
            }
        };

        $check('PHP >= 8.3 (found '.PHP_VERSION.')', version_compare(PHP_VERSION, '8.3.0', '>='), 'Upgrade PHP.');
        foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'] as $ext) {
            $check("PHP extension: $ext", extension_loaded($ext), "Install/enable php-$ext.");
        }
        foreach ([storage_path(), storage_path('app'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            $check('Writable: '.str_replace(base_path().'/', '', $dir), is_dir($dir) && is_writable($dir), 'Give the web-server user write access (see INSTALL.md step 4).');
        }

        $check('.env file present', is_file(base_path('.env')), 'Copy .env.production.example to .env and fill it in (INSTALL.md step 3).');
        $check('APP_ENV is production', app()->environment('production'), 'Set APP_ENV=production in .env.');
        $check('APP_DEBUG is off', ! config('app.debug'), 'Set APP_DEBUG=false in .env.');
        $check('APP_URL uses https://', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL to the public https:// address (the app forces https links in production).');

        try {
            DB::connection()->getPdo();
            $check('Database connection ('.config('database.default').')', true);
        } catch (Throwable $e) {
            $check('Database connection', false, 'Check DB_* values in .env and that the database exists. '.Str::limit($e->getMessage(), 160));
        }

        if (! $ok) {
            $this->components->error('Preflight failed - fix the items above and run the installer again.');
        }

        return $ok;
    }

    private function ensureEnvSecrets(): void
    {
        $env = base_path('.env');
        $contents = (string) file_get_contents($env);
        $changed = false;

        if (blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $contents = $this->setEnv($contents, 'APP_KEY', $key);
            config(['app.key' => $key]);
            $changed = true;
            $this->components->twoColumnDetail('APP_KEY', '<info>generated</info>');
        }

        if (blank(config('services.vapid.public_key')) || blank(config('services.vapid.private_key'))) {
            $keys = VAPID::createVapidKeys();
            $contents = $this->setEnv($contents, 'VAPID_PUBLIC_KEY', $keys['publicKey']);
            $contents = $this->setEnv($contents, 'VAPID_PRIVATE_KEY', $keys['privateKey']);
            config(['services.vapid.public_key' => $keys['publicKey'], 'services.vapid.private_key' => $keys['privateKey']]);
            $changed = true;
            $this->components->twoColumnDetail('Web-push VAPID keys', '<info>generated</info>');
        }

        if ($changed) {
            file_put_contents($env, $contents);
            // A freshly generated APP_KEY must be live before anything is encrypted or hashed.
            Artisan::call('config:clear');
            $this->components->warn('Back up your .env file now: losing APP_KEY makes encrypted data (2FA secrets, SMTP password, government IDs) unrecoverable.');
        }
    }

    private function setEnv(string $contents, string $key, string $value): string
    {
        $line = "$key=$value";

        if (preg_match("/^{$key}=.*$/m", $contents)) {
            return preg_replace("/^{$key}=.*$/m", addcslashes($line, '\\$'), $contents);
        }

        return rtrim($contents)."\n$line\n";
    }

    /** @return array{0:string,1:string,2:string,3:string,4:string}|null */
    private function collectInput(): ?array
    {
        Prompt::interactive($this->tty);
        Prompt::setOutput($this->output);
        $interactive = $this->tty;

        $company = $this->option('company') ?: ($interactive ? text('Company name', required: true, default: 'My Company') : null);
        $first = $this->option('admin-first-name') ?: ($interactive ? text('Admin first name', required: true) : null);
        $last = $this->option('admin-last-name') ?: ($interactive ? text('Admin last name', required: true) : null);
        $email = $this->option('admin-email') ?: ($interactive ? text('Admin e-mail (this is the login)', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid e-mail address.') : null);

        if ($interactive) {
            while (true) {
                $pass = password('Admin password', required: true, hint: 'Choose a long, unique password. It is checked against the password policy.');
                $confirm = password('Confirm admin password', required: true);

                if ($pass !== $confirm) {
                    $this->components->error('The passwords do not match - try again.');

                    continue;
                }

                if ($this->passwordProblem($pass)) {
                    continue;
                }

                break;
            }
        } else {
            $pass = (string) (getenv('HRIS_ADMIN_PASSWORD') ?: '');
        }

        foreach (['company' => $company, 'admin first name' => $first, 'admin last name' => $last, 'admin e-mail' => $email, 'admin password' => $pass] as $label => $value) {
            if (blank($value)) {
                $this->components->error("Missing $label (run interactively, or pass the options / HRIS_ADMIN_PASSWORD).");

                return null;
            }
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('The admin e-mail address is not valid.');

            return null;
        }

        if (! $interactive && $this->passwordProblem((string) $pass)) {
            return null;
        }

        return [(string) $company, (string) $first, (string) $last, strtolower((string) $email), (string) $pass];
    }

    /** Checked up-front in interactive mode so a weak password re-prompts instead of aborting the install. */
    private function passwordProblem(string $pass): bool
    {
        // The settings row (with the default policy) is created on first access.
        $validator = Validator::make(['password' => $pass], ['password' => [new PasswordPolicy]]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return true;
        }

        return false;
    }

    private function seedSystemData(): void
    {
        $this->callSilent('db:seed', ['--class' => RbacSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => WorkflowSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => CountrySeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => MasterListSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => RenewalTypeSeeder::class, '--force' => true]);

        // Only the confidential Grievance/Whistleblower category is required; ordinary categories are the Admin's to define.
        (new HelpdeskCategorySeeder)->seedConfidential();
    }
}
