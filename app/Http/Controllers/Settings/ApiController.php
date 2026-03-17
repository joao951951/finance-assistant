<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/api', [
            'hasApiKey'      => $user->hasOpenAiKey(),
            'chatModel'      => $user->openai_chat_model ?? '',
            'embeddingModel' => $user->openai_embedding_model ?? '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key'         => ['nullable', 'string', 'max:200'],
            'chat_model'      => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        // Only update the key if a new value was provided; empty means "keep existing"
        if (! empty($validated['api_key'])) {
            $user->openai_api_key = $validated['api_key'];
        }

        $user->openai_chat_model      = $validated['chat_model'] ?: null;
        $user->openai_embedding_model = $validated['embedding_model'] ?: null;
        $user->save();

        return back()->with('status', 'api-settings-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update([
            'openai_api_key'      => null,
            'openai_chat_model'   => null,
            'openai_embedding_model' => null,
        ]);

        return back()->with('status', 'api-key-removed');
    }
}
