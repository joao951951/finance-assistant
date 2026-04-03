<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class ChatService
{
    /**
     * How many recent messages to include as history in the prompt.
     */
    private const HISTORY_LIMIT = 10;

    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly RagService $rag,
    ) {}

    public static function forUser(User $user): self
    {
        $openAi = OpenAiService::forUser($user);
        $embedding = new EmbeddingService($openAi);
        $rag = new RagService($embedding);

        return new self($openAi, $rag);
    }

    /**
     * Send a user message, get an AI response, persist both, and return the reply.
     */
    public function reply(Conversation $conversation, string $userMessage): string
    {
        // 1. Persist the user message first
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // 2. Retrieve semantically relevant transactions as RAG context
        $context = $this->rag->contextFor($conversation->user_id, $userMessage);

        // 3. Build the messages array for GPT-4o
        $history = $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (Message $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($context)],
            ...$history,
        ];

        // 4. Get the AI reply
        try {
            $reply = $this->openAi->chat($messages);
        } catch (\Throwable) {
            $reply = 'Desculpe, o serviço de IA está indisponível. Verifique sua chave de API em Configurações → API / IA.';
        }

        // 5. Persist the assistant message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        // 6. Auto-generate title on the first exchange
        if ($conversation->title === null) {
            try {
                $title = $this->generateTitle($userMessage);
            } catch (\Throwable) {
                $title = mb_substr($userMessage, 0, 60);
            }
            $conversation->update(['title' => $title]);
        }

        return $reply;
    }

    /**
     * Generate a short conversation title from the first user message.
     */
    private function generateTitle(string $firstMessage): string
    {
        $title = $this->openAi->chat([
            [
                'role' => 'user',
                'content' => "Resuma em até 6 palavras (sem pontuação final) a seguinte pergunta financeira: \"{$firstMessage}\"",
            ],
        ], temperature: 0);

        return mb_substr(trim($title), 0, 80);
    }

    private function systemPrompt(string $context): string
    {
        return <<<PROMPT
            Você é um assistente financeiro pessoal inteligente. Seu objetivo é ajudar o usuário a entender seus gastos, identificar padrões e sugerir formas de economizar.

            Abaixo estão as transações financeiras mais relevantes para a pergunta do usuário:

            {$context}

            Instruções:
            - Responda sempre em português brasileiro
            - Seja objetivo e direto, mas amigável
            - Quando mencionar valores, use o formato R$ X.XXX,XX
            - Se os dados forem insuficientes para responder, diga isso claramente
            - Não invente transações ou valores que não estejam nos dados acima
            PROMPT;
    }
}
