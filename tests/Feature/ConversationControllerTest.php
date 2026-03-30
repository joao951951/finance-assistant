<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get(route('chat.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_store_conversation(): void
    {
        $this->post(route('chat.store'), ['message' => 'hello'])->assertRedirect(route('login'));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_index_renders_with_empty_conversations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('chat/index')
                ->has('conversations', 0)
                ->where('activeConversation', null)
                ->has('messages', 0)
            );
    }

    public function test_user_sees_only_their_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Conversation::create(['user_id' => $user->id,  'title' => 'Mine']);
        Conversation::create(['user_id' => $other->id, 'title' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertInertia(fn ($page) => $page
                ->has('conversations', 1)
                ->where('conversations.0.title', 'Mine')
            );
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_show_renders_conversation_with_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Test Chat']);

        Message::create(['conversation_id' => $conversation->id, 'role' => 'user',      'content' => 'Hello']);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Hi!']);

        $this->actingAs($user)
            ->get(route('chat.show', $conversation))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('chat/index')
                ->where('activeConversation.id', $conversation->id)
                ->has('messages', 2)
            );
    }

    public function test_user_cannot_view_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $conversation = Conversation::create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('chat.show', $conversation))
            ->assertForbidden();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_store_creates_conversation_and_redirects(): void
    {
        $user = User::factory()->create();

        // ChatService is built manually in the controller (not DI-injected),
        // so we assert the side-effects: conversation persisted + redirect.
        // OpenAI calls fail gracefully when no API key is set.
        $this->actingAs($user)
            ->post(route('chat.store'), ['message' => 'What is my balance?'])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
    }

    public function test_store_validates_message_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chat.store'), ['message' => ''])
            ->assertSessionHasErrors('message');
    }

    public function test_store_validates_message_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chat.store'), ['message' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('message');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_can_delete_their_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('chat.destroy', $conversation))
            ->assertRedirect(route('chat.index'));

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    public function test_user_cannot_delete_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $conversation = Conversation::create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->delete(route('chat.destroy', $conversation))
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }
}
