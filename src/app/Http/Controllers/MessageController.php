<?php

namespace App\Http\Controllers;

use App\Events\NewMessage;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function index(Conversation $conversation, Request $request): JsonResponse
    {
        // Check if the user is a participant
        $isParticipant = $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with('user')
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    public function store(Conversation $conversation, Request $request): JsonResponse
    {
        // Check if the user is a participant
        $participant = $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'message_content' => 'required|string',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'content' => $request->message_content,
        ]);

        // Update conversation's updated_at timestamp
        $conversation->touch();

        // Update participant's last_read timestamp
        $participant->update([
            'last_read' => now(),
        ]);

        // Load the user relationship
        $message->load('user');

        // Broadcast the new message to all participants
        broadcast(new NewMessage($message))->toOthers();

        return response()->json($message, 201);
    }

    public function markAsRead(Message $message, Request $request): JsonResponse
    {
        // Check if the user is a participant in the conversation
        $participant = Participant::where('conversation_id', $message->conversation_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update the participant's last_read timestamp
        $participant->update([
            'last_read' => now(),
        ]);

        return response()->json(['message' => 'Message marked as read']);
    }

    public function typing(Conversation $conversation, Request $request): JsonResponse
    {
        // Check if the user is a participant
        $isParticipant = $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        broadcast(new UserTyping(
            $conversation->id,
            $request->user()->id,
            $request->user()->name,
            $request->is_typing
        ))->toOthers();

        return response()->json(['success' => true]);
    }
}
