<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
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

        ChatService::forUser($request->user())->reply($conversation, $request->input('message'));

        return redirect()->route('chat.show', $conversation);
    }
}
