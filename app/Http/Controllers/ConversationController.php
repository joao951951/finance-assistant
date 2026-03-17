<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use App\Services\RagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = $request->user()
            ->conversations()
            ->latest()
            ->get(['id', 'title', 'created_at']);

        return Inertia::render('chat/index', [
            'conversations'      => $conversations,
            'activeConversation' => null,
            'messages'           => [],
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $conversations = $request->user()
            ->conversations()
            ->latest()
            ->get(['id', 'title', 'created_at']);

        return Inertia::render('chat/index', [
            'conversations'      => $conversations,
            'activeConversation' => $conversation->only('id', 'title'),
            'messages'           => $conversation->messages()
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
        ]);

        $this->chatService($request)->reply($conversation, $request->input('message'));

        return redirect()->route('chat.show', $conversation);
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('chat.index');
    }

    private function chatService(Request $request): ChatService
    {
        $openAi    = OpenAiService::forUser($request->user());
        $embedding = new EmbeddingService($openAi);
        $rag       = new RagService($embedding);

        return new ChatService($openAi, $rag);
    }
}
