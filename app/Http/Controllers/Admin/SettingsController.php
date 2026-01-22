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
            'claude_max_tokens' => 'nullable|integer|min:100|max:200000',
            'openai_api_key' => 'nullable|string',
        ]);

        $this->updateEnvFile($validated);

        // Clear config cache
        Artisan::call('config:clear');

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
            'openai_api_key' => config('services.openai.api_key'),
        ];
    }

    /**
     * Update .env file with new settings
     */
    private function updateEnvFile(array $settings)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        $envContent = File::get($envPath);

        $updates = [
            'CLAUDE_API_KEY' => $settings['claude_api_key'] ?? '',
            'CLAUDE_MODEL' => $settings['claude_model'] ?? 'claude-sonnet-4-20250514',
            'CLAUDE_MAX_TOKENS' => $settings['claude_max_tokens'] ?? 4096,
            'OPENAI_API_KEY' => $settings['openai_api_key'] ?? '',
        ];

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
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
