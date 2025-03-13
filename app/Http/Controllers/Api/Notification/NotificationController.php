<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $listNotification = Auth::user()->notifications;
        $unReadCount = Auth::user()->unreadNotifications;

        $data = [
            'notifications' => $listNotification, 
            'unread' => $unReadCount
        ];

        return response()->json([
            'code'      =>  200,
            'message'   =>  'Fetching Data successfully',
            'data'      =>  $data,
        ], 200);
    }

    public function unread()
    {   
        return response()->json([
            'code'      =>  200,
            'message'   =>  'Fetching Data successfully',
            'data'      =>  Auth::user()->unreadNotifications,
        ], 200);
    }

    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'code'      =>  200,
            'message'   =>  'Notification marked as read',
            'data'      =>  $notification,
        ], 200);
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read']);
    }
}
