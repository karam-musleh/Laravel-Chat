<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Events\UserTyping;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\SendMessageRequest;

class ChatController extends Controller
{
    //index function
    public function index()
    {
        $users = User::where('id', '!=', Auth::id())->get();
        return view('users', compact('users'));
    }
    //chat function
    public function chat($receiverId)
    {
        $receiver = User::find($receiverId);
        $messages = Message::where(function ($q) use ($receiverId) {
            $q->where('sender_id', Auth::id())
                ->where('receiver_id', $receiverId);
        })->orWhere(function ($q) use ($receiverId) {
            $q->where('sender_id', $receiverId)
                ->where('receiver_id', Auth::id());
        })->get();
        return view('chat', compact('receiver', 'messages'));
    }
    // sendMessage function
    public function sendMessage(Request $request, $receiverId)
    {
        // save message to database
            // dd(Auth::id(), $receiverId, $request->message);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $receiverId,
            'message' => $request['message'],
        ]);
        //Fire the event
        broadcast(new MessageSent($message))->toOthers();
        return response()->json(['status' => 'Message Sent!']);
    }
    // typing function
    public function typing()
    {
        $typerId = Auth::id();
        // [Fire the typing event]
        broadcast(new UserTyping($typerId))->toOthers();
        return response()->json(['status' => 'Typing event sent!']);
    }
    // setOnline function
    public function setOnline()
    {
        Cache::put('user-is-online-' . Auth::id(), true, now()->addMinutes(5));
        return response()->json(['status' => 'User is online']);
    }
    public function setOfLine()
    {
        Cache::forget('user-is-online-' . Auth::id());
        return response()->json(['status' => 'User is offline']);
    }
}
