<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use App\Services\RagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $openAi = OpenAiService::forUser($request->user());
        $embedding = new EmbeddingService($openAi);
        $rag = new RagService($embedding);
        $chat = new ChatService($openAi, $rag);

        $chat->reply($conversation, $request->input('message'));

        return redirect()->route('chat.show', $conversation);
    }
}
