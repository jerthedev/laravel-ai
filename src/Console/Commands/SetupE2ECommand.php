<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Services\DriverManager;

/**
 * Interactive E2E testing setup command.
 *
 * This command helps users set up E2E testing credentials and configuration
 * with interactive prompts and validation.
 */
class SetupE2ECommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:setup-e2e 
                            {--provider= : Specific provider to set up (openai, anthropic, etc.)}
                            {--validate : Validate credentials after setup}
                            {--force : Overwrite existing credentials}';

    /**
     * The console command description.
     */
    protected $description = 'Interactive setup for E2E testing credentials and configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Laravel AI E2E Testing Setup');
        $this->line('This command will help you set up credentials for E2E testing with real AI providers.');
        $this->newLine();

        // Check if credentials file exists
        $credentialsPath = base_path('tests/credentials/e2e-credentials.json');
        $credentialsDir = dirname($credentialsPath);

        if (!File::exists($credentialsDir)) {
            File::makeDirectory($credentialsDir, 0755, true);
            $this->info('✅ Created credentials directory: ' . $credentialsDir);
        }

        $existingCredentials = [];
        if (File::exists($credentialsPath)) {
            $existingCredentials = json_decode(File::get($credentialsPath), true) ?? [];
            
            if (!$this->option('force')) {
                $this->warn('⚠️  Credentials file already exists.');
                if (!$this->confirm('Do you want to update the existing credentials?')) {
                    $this->info('Setup cancelled.');
                    return self::SUCCESS;
                }
            }
        }

        // Determine which providers to set up
        $provider = $this->option('provider');
        $providers = $provider ? [$provider] : $this->chooseProviders();

        foreach ($providers as $providerName) {
            $this->setupProvider($providerName, $existingCredentials);
        }

        // Save credentials
        File::put($credentialsPath, json_encode($existingCredentials, JSON_PRETTY_PRINT));
        $this->info('✅ Credentials saved to: ' . $credentialsPath);

        // Validate credentials if requested
        if ($this->option('validate')) {
            $this->validateCredentials($existingCredentials);
        }

        $this->displaySecurityReminder();

        return self::SUCCESS;
    }

    /**
     * Choose which providers to set up.
     */
    protected function chooseProviders(): array
    {
        $availableProviders = ['openai', 'anthropic', 'mock'];
        
        $this->info('Available providers:');
        foreach ($availableProviders as $index => $provider) {
            $this->line('  ' . ($index + 1) . '. ' . ucfirst($provider));
        }

        $choices = $this->ask('Which providers would you like to set up? (comma-separated numbers or names)', '1');
        
        $selected = [];
        foreach (explode(',', $choices) as $choice) {
            $choice = trim($choice);
            
            if (is_numeric($choice)) {
                $index = (int) $choice - 1;
                if (isset($availableProviders[$index])) {
                    $selected[] = $availableProviders[$index];
                }
            } elseif (in_array(strtolower($choice), $availableProviders)) {
                $selected[] = strtolower($choice);
            }
        }

        return array_unique($selected);
    }

    /**
     * Set up credentials for a specific provider.
     */
    protected function setupProvider(string $provider, array &$credentials): void
    {
        $this->info("🔧 Setting up {$provider} credentials");

        switch ($provider) {
            case 'openai':
                $this->setupOpenAI($credentials);
                break;
            case 'anthropic':
                $this->setupAnthropic($credentials);
                break;
            case 'mock':
                $this->setupMock($credentials);
                break;
            default:
                $this->error("Unknown provider: {$provider}");
        }
    }

    /**
     * Set up OpenAI credentials.
     */
    protected function setupOpenAI(array &$credentials): void
    {
        $this->line('OpenAI requires an API key from https://platform.openai.com/api-keys');
        
        $apiKey = $this->secret('Enter your OpenAI API key');
        if (!$apiKey || !str_starts_with($apiKey, 'sk-')) {
            $this->error('Invalid OpenAI API key format. Keys should start with "sk-"');
            return;
        }

        $organization = $this->ask('Enter your OpenAI organization ID (optional)');
        $project = $this->ask('Enter your OpenAI project ID (optional)');

        $credentials['openai'] = [
            'enabled' => true,
            'api_key' => $apiKey,
            'organization' => $organization ?: null,
            'project' => $project ?: null,
            'timeout' => 30,
            'retry_attempts' => 3,
        ];

        $this->info('✅ OpenAI credentials configured');
    }

    /**
     * Set up Anthropic credentials.
     */
    protected function setupAnthropic(array &$credentials): void
    {
        $this->line('Anthropic requires an API key from https://console.anthropic.com/');
        
        $apiKey = $this->secret('Enter your Anthropic API key');
        if (!$apiKey) {
            $this->error('API key is required for Anthropic');
            return;
        }

        $credentials['anthropic'] = [
            'enabled' => true,
            'api_key' => $apiKey,
            'timeout' => 30,
            'retry_attempts' => 3,
        ];

        $this->info('✅ Anthropic credentials configured');
    }

    /**
     * Set up mock provider.
     */
    protected function setupMock(array &$credentials): void
    {
        $credentials['mock'] = [
            'enabled' => true,
            'simulate_delays' => $this->confirm('Simulate API delays?', true),
            'default_delay_ms' => 500,
        ];

        $this->info('✅ Mock provider configured');
    }

    /**
     * Validate credentials with actual API calls.
     */
    protected function validateCredentials(array $credentials): void
    {
        $this->info('🔍 Validating credentials...');
        
        $driverManager = app(DriverManager::class);
        
        foreach ($credentials as $provider => $config) {
            if (!($config['enabled'] ?? false)) {
                continue;
            }

            $this->line("Validating {$provider}...");
            
            try {
                $driver = $driverManager->driver($provider);
                $result = $driver->validateCredentials();
                
                if ($result['valid']) {
                    $this->info("  ✅ {$provider} credentials are valid");
                    if (isset($result['details']['models_available'])) {
                        $this->line("     Models available: " . $result['details']['models_available']);
                    }
                } else {
                    $this->error("  ❌ {$provider} credentials are invalid");
                    if ($result['error']) {
                        $this->line("     Error: " . $result['error']);
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ {$provider} validation failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Display security reminder.
     */
    protected function displaySecurityReminder(): void
    {
        $this->newLine();
        $this->warn('🔒 SECURITY REMINDER:');
        $this->line('• The credentials file is excluded from Git by default');
        $this->line('• Never commit API keys to version control');
        $this->line('• Use environment variables in production');
        $this->line('• Regularly rotate your API keys');
        $this->line('• Monitor your API usage and billing');
        $this->newLine();
        
        $this->info('🧪 You can now run E2E tests with: php artisan test --group=e2e');
    }
}
