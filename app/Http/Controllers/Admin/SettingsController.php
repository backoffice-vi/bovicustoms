<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SettingsController extends Controller
{
    /**
     * Display AI settings
     */
    public function index()
    {
        $settings = $this->getCurrentSettings();
        
        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update AI settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'claude_api_key' => 'nullable|string',
            'claude_model' => 'nullable|string',
            'claude_max_tokens' => 'nullable|integer|min:100|max:20000',
            'claude_max_context_tokens' => 'nullable|integer|min:100000|max:500000',
            'claude_chunk_size' => 'nullable|integer|min:10000|max:200000',
            'openai_api_key' => 'nullable|string',
        ]);

        $this->updateEnvFile($validated);

        // Clear config cache (wrapped in try-catch as server may restart)
        try {
            Artisan::call('config:clear');
        } catch (\Exception $e) {
            // Server may be restarting, which is fine
        }

        // If AJAX request, return JSON response
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully. Server is restarting...'
            ]);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'AI settings updated successfully. Configuration cache cleared.');
    }

    /**
     * Test Claude API connection
     */
    public function testClaudeConnection(Request $request)
    {
        $apiKey = $request->input('api_key') ?: config('services.claude.api_key');
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key provided'
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'test']
                ]
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully connected to Claude API!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'API Error: ' . $response->body()
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get current settings from .env file
     */
    private function getCurrentSettings()
    {
        return [
            'claude_api_key' => config('services.claude.api_key'),
            'claude_model' => config('services.claude.model'),
            'claude_max_tokens' => config('services.claude.max_tokens'),
            'claude_max_context_tokens' => config('services.claude.max_context_tokens'),
            'claude_chunk_size' => config('services.claude.chunk_size'),
            'openai_api_key' => config('services.openai.api_key'),
        ];
    }

    /**
     * Update .env file with new settings
     * Only updates values that are present in the settings array (key exists)
     */
    private function updateEnvFile(array $settings)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        $envContent = File::get($envPath);

        // Map form field names to env variable names with defaults
        $fieldMapping = [
            'claude_api_key' => ['env' => 'CLAUDE_API_KEY', 'default' => ''],
            'claude_model' => ['env' => 'CLAUDE_MODEL', 'default' => 'claude-sonnet-4-20250514'],
            'claude_max_tokens' => ['env' => 'CLAUDE_MAX_TOKENS', 'default' => 8192],
            'claude_max_context_tokens' => ['env' => 'CLAUDE_MAX_CONTEXT_TOKENS', 'default' => 200000],
            'claude_chunk_size' => ['env' => 'CLAUDE_CHUNK_SIZE', 'default' => 95000],
            'openai_api_key' => ['env' => 'OPENAI_API_KEY', 'default' => ''],
        ];

        // Only update fields that were actually submitted in the form
        foreach ($fieldMapping as $formField => $config) {
            // Only update if the key exists in the submitted settings
            if (!array_key_exists($formField, $settings)) {
                continue;
            }
            
            $envKey = $config['env'];
            $value = $settings[$formField] ?? $config['default'];
            
            $pattern = "/^{$envKey}=.*/m";
            $replacement = "{$envKey}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                // Update existing key
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                // Add new key
                $envContent .= "\n{$replacement}";
            }
        }

        File::put($envPath, $envContent);
    }
}
