<?php

namespace App\Console\Commands;

use App\Services\LocalLlmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetupOllamaCommand extends Command
{
    protected $signature = 'ollama:setup 
                          {--model=llama3.2 : The model to download and use}
                          {--check-only : Only check if Ollama is available}';

    protected $description = 'Setup Ollama LLM service and download required models';

    public function __construct(private LocalLlmService $llmService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Setting up Ollama LLM service...');
        $this->newLine();

        $model = $this->option('model');
        $checkOnly = $this->option('check-only');

        // Check if Ollama is available
        $this->info('Checking Ollama availability...');
        
        if (!$this->llmService->isAvailable()) {
            $this->error('❌ Ollama is not available at ' . config('services.ollama.base_url', 'http://localhost:11434'));
            $this->newLine();
            $this->warn('Make sure Ollama is running:');
            $this->line('- If using Docker: docker-compose up -d ollama');
            $this->line('- If running locally: ollama serve');
            return 1;
        }

        $this->info('✅ Ollama is available');

        // Get available models
        $this->info('Checking available models...');
        $availableModels = $this->llmService->getAvailableModels();
        
        if (empty($availableModels)) {
            $this->warn('No models are currently installed');
        } else {
            $this->info('Installed models:');
            foreach ($availableModels as $installedModel) {
                $this->line("  - {$installedModel}");
            }
        }

        if ($checkOnly) {
            return 0;
        }

        // Download model if not available
        if (!in_array($model, $availableModels)) {
            $this->warn("Model '{$model}' not found. Downloading...");
            
            if ($this->downloadModel($model)) {
                $this->info("✅ Successfully downloaded model '{$model}'");
            } else {
                $this->error("❌ Failed to download model '{$model}'");
                return 1;
            }
        } else {
            $this->info("✅ Model '{$model}' is already available");
        }

        // Test the model
        $this->info('Testing model with sample analysis...');
        $testResult = $this->testModel($model);
        
        if ($testResult['success']) {
            $this->info('✅ Model test successful');
            $this->line('Sample response: ' . substr($testResult['response'], 0, 100) . '...');
        } else {
            $this->warn('⚠️  Model test failed: ' . $testResult['error']);
        }

        $this->newLine();
        $this->info('Ollama setup complete!');
        $this->info("You can now run: php artisan opinions:analyze --type=dissent --limit=5");

        return 0;
    }

    private function downloadModel(string $model): bool
    {
        try {
            $baseUrl = config('services.ollama.base_url', 'http://localhost:11434');
            
            $this->info("Pulling model '{$model}' (this may take several minutes)...");
            
            $progressBar = $this->output->createProgressBar();
            $progressBar->start();
            
            // Pull the model
            $response = Http::timeout(600)->post("{$baseUrl}/api/pull", [
                'name' => $model,
                'stream' => false
            ]);
            
            $progressBar->finish();
            $this->newLine();
            
            if ($response->successful()) {
                return true;
            } else {
                $this->error('HTTP Error: ' . $response->status() . ' - ' . $response->body());
                return false;
            }
            
        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            return false;
        }
    }

    private function testModel(string $model): array
    {
        try {
            $baseUrl = config('services.ollama.base_url', 'http://localhost:11434');
            
            $testPrompt = "Analyze this brief Supreme Court case summary for sentiment (positive, negative, neutral): 'The Court ruled unanimously that segregation in public schools is unconstitutional.' Respond with just one word: positive, negative, or neutral.";
            
            $response = Http::timeout(60)->post("{$baseUrl}/api/generate", [
                'model' => $model,
                'prompt' => $testPrompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.1,
                    'num_predict' => 10
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'response' => $result['response'] ?? 'No response'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $response->status() . ': ' . $response->body()
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}