<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $this->chat->reply($conversation, $request->input('message'));

        return redirect()->route('chat.show', $conversation);
    }
}
