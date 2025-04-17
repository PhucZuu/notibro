<?php

namespace App\Http\Controllers;

use App\Mail\ChatWithGuest;
use App\Models\Task;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MailToGuestController extends Controller
{
    public function getInfoEvent($id)
    {

        $event = Task::where('type','event')
                ->where('id', $id)
                ->select('id', 'title', 'description','user_id', 'attendees')
                ->first();

        if(!$event) {
            return response()->json([
                'code' => 404,
                'message' => 'Event not found'
            ]);
        }

        $idOwner = $event->user_id;
        $idGuest = array_map(fn($item) => $item['user_id'], $event->attendees);

        $allUserIds = array_merge([$idOwner], $idGuest);

        foreach ($allUserIds as $id) {
            $user = User::withTrashed()->find($id);

            if ($user->avatar && !Str::startsWith($user->avatar, ['http://', 'https://'])) {
                $user->avatar = Storage::url($user->avatar);
            }

            if ($user) {
                $users[] = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ];
            }
        }

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => [
                'event' => $event,
                'users' => $users ?? []
            ]
        ]);
        
    }

    public function sendMailToOthers(Request $request)
    {
        $atendees = $request->attendees;
        $title = $request->title;
        $content = $request->content;
        $id = $request->id;

        $userSend = User::where('id', auth()->user()->id)->first();
        $task = Task::where('id',$id)->first();

        $isOwner = $userSend->id == $task->user_id;

        try {
            if(!$atendees) {
                return response()->json([
                    "code" => 404,
                    "message" => "Atendees is empty",
                ]);
            }
            
            foreach($atendees as $atendee) {
                Mail::to($atendee['email'])->queue(new ChatWithGuest($title, $content, $userSend, $task, $isOwner));
            }

            return response()->json([
                'code' => 200,
                "message" => "Mail sent success"
            ]);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                "code" => 500,
                "message" => "Send mail fail",
            ], 500);
        }
    }
}
