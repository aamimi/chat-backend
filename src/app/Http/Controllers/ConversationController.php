<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $conversations = $request->user()->conversations()
            ->with(['participants.user', 'lastMessage'])
            ->latest('updated_at')
            ->get();

        return response()->json($conversations);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'users' => 'required|array',
            'users.*' => 'exists:users,id',
            'name' => 'nullable|string|max:255',
            'is_group' => 'boolean',
        ]);

        // Ensure the current user is included
        $userIds = collect($request->users)->push($request->user()->id)->unique();

        // If it's a direct message (not a group), check if a conversation already exists
        if (!$request->is_group && $userIds->count() === 2) {
            $existingConversation = $this->findDirectConversation(
                $request->user()->id,
                $userIds->firstWhere('id', '!=', $request->user()->id)
            );

            if ($existingConversation) {
                return response()->json($existingConversation->load('participants.user', 'lastMessage'));
            }
        }

        DB::beginTransaction();
        try {
            $conversation = Conversation::create([
                'name' => $request->name,
                'is_group' => $request->is_group ?? false,
            ]);

            // Add all participants
            foreach ($userIds as $userId) {
                Participant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
            }

            DB::commit();

            return response()->json(
                $conversation->load('participants.user'),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create conversation'], 500);
        }
    }

    public function show(Conversation $conversation, Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if the user is a participant
        $isParticipant = $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            $conversation->load([
                'participants.user',
                'messages' => function ($query) {
                    $query->latest()->limit(20);
                }
            ])
        );
    }

    private function findDirectConversation($userId1, $userId2): ?Conversation
    {
        // Find conversations where both users are participants and it's not a group
        return Conversation::where('is_group', false)
            ->whereHas('participants', function ($query) use ($userId1) {
                $query->where('user_id', $userId1);
            })
            ->whereHas('participants', function ($query) use ($userId2) {
                $query->where('user_id', $userId2);
            })
            ->first();
    }
}
