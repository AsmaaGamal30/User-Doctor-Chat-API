<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use App\Models\ChMessage as Message;
use App\Models\User;
use App\Models\Doctor;
use App\Repositories\ChatifyMessenger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;



class MessagesController extends Controller
{
    protected $perPage = 30;

    protected $chatifyMessenger;

    public function __construct(ChatifyMessenger $chatifyMessenger)
    {
        $this->chatifyMessenger = $chatifyMessenger;
    }

    public function pusherAuth(Request $request)
    {
        return $this->chatifyMessenger->pusherAuth(
            $request->user(),
            Auth::user(),
            $request['channel_name'],
            $request['socket_id']
        );
    }


    /**
     * Authinticate the connection for pusher
     *
     * @param Request $request
     * @return void
     */
    public function pusherDoctorAuth(Request $request)
    {
        return $this->chatifyMessenger->pusherAuth(
            $request->user(),
            auth()->guard('doctor')->user(),
            $request['channel_name'],
            $request['socket_id']
        );
    }

    /**
     * Fetch data by id for (user/group)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function idFetchData(Request $request)
    {
        //return auth()->user();

        $fetch = User::where('id', $request['id'])->first();

        // send the response
        return Response::json([
            'fetch' => $fetch ?? null,
        ]);
    }

    public function idDoctorFetchData(Request $request)
    {
        //return auth()->user();

        // User data
        $fetch = Doctor::where('id', $request['id'])->first();

        // send the response
        return Response::json([
            //'favorite' => $favorite,
            'fetch' => $fetch ?? null,
            //'doctor_avatar' => $userAvatar ?? null,
        ]);
    }

    /**
     * This method to make a links for the attachments
     * to be downloadable.
     *
     * @param string $fileName
     * @return \Illuminate\Http\JsonResponse
     */
    public function download($fileName)
    {
        $path = 'attachments' . '/' . $fileName;
        if ($this->chatifyMessenger->storage()->exists($path)) {
            return response()->json([
                'file_name' => $fileName,
                'download_path' => $this->chatifyMessenger->storage()->url($path)
            ], 200);
        } else {
            return response()->json([
                'message' => "Sorry, File does not exist in our server or may have been deleted!"
            ], 404);
        }
    }

    /**
     * Send a message to database
     *
     * @param Request $request
     */
    public function send(Request $request)
    {

        // default variables
        $error = (object)[
            'status' => 0,
            'message' => null
        ];
        $attachment = null;
        $attachment_title = null;

        // if there is attachment [file]
        if ($request->hasFile('file')) {
            // allowed extensions
            $allowed_images = $this->chatifyMessenger->getAllowedImages();
            $allowed_files  = $this->chatifyMessenger->getAllowedFiles();
            $allowed        = array_merge($allowed_images, $allowed_files);

            $file = $request->file('file');
            // check file size
            if ($file->getSize() < $this->chatifyMessenger->getMaxUploadSize()) {
                if (in_array(strtolower($file->extension()), $allowed)) {
                    // get attachment name
                    $attachment_title = $file->getClientOriginalName();
                    // upload attachment and store the new name
                    $attachment = Str::uuid() . "." . $file->extension();
                    $file->storeAs('attachments', $attachment, 'public');
                } else {
                    $error->status = 1;
                    $error->message = "File extension not allowed!";
                }
            } else {
                $error->status = 1;
                $error->message = "File size you are trying to upload is too large!";
            }
        }

        if (!$error->status) {
            // send to database
            $message = $this->chatifyMessenger->newMessage([
                //'type' => $request['type'],
                'from_id' => Auth::user()->id,
                'to_id' => $request['id'],
                'body' => htmlentities(trim($request['message']), ENT_QUOTES, 'UTF-8'),
                'attachment' => ($attachment) ? json_encode((object)[
                    'new_name' => $attachment,
                    'old_name' => htmlentities(trim($attachment_title), ENT_QUOTES, 'UTF-8'),
                ]) : null,
            ]);

            // fetch message to send it with the response
            $messageData = $this->chatifyMessenger->parseMessage($message);

            // send to user using pusher
            if (Auth::user()->id !== $request['id']) {
                $this->chatifyMessenger->push("private-chatify." . $request['id'], 'messaging', [
                    'from_id' => Auth::user()->id,
                    'to_id' => $request['id'],
                    'message' => $messageData
                ]);
            }
        }

        // send the response
        return Response::json([
            'status' => '200',
            'error' => $error,
            'message' => $messageData ?? [],
            'tempID' => $request['temporaryMsgId'],
        ]);
    }

    /**
     * fetch [user/group] messages from database
     *
     * @param Request $request
     */
    public function fetch(Request $request)
    {
        $query = $this->chatifyMessenger->fetchMessagesQuery($request['id'])->latest();
        $messages = $query->paginate($request->per_page ?? $this->perPage);
        $totalMessages = $messages->total();
        $lastPage = $messages->lastPage();
        $response = [
            'total' => $totalMessages,
            'last_page' => $lastPage,
            'last_message_id' => collect($messages->items())->last()->id ?? null,
            'messages' => $messages->items(),
        ];
        return Response::json($response);
    }

    /**
     * Make messages as seen
     *
     * @param Request $request
     */
    public function seen(Request $request)
    {
        // make as seen
        $seen = $this->chatifyMessenger->makeSeen($request['id']);
        // send the response
        return Response::json([
            'status' => $seen,
        ], 200);
    }

    /**
     * Get contacts list
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse response
     */

    //i want to select lastest message also and count un seen messages


    public function getDoctorContacts(Request $request)
    {
        $users = Message::join('users', function ($join) {
            $join->on('ch_messages.from_id', '=', 'users.id')
                ->orOn('ch_messages.to_id', '=', 'users.id');
        })
            ->where(function ($q) {
                $q->where('ch_messages.from_id', auth()->guard('doctor')->user()->id)
                    ->orWhere('ch_messages.to_id', auth()->guard('doctor')->user()->id);
            })
            ->select('users.id', 'users.name', 'users.last_name', 'users.email', 'users.created_at', 'users.updated_at', 'users.active_status', 'users.avatar', DB::raw('MAX(ch_messages.created_at) AS max_created_at'))
            ->groupBy('users.id', 'users.name', 'users.last_name', 'users.email', 'users.created_at', 'users.updated_at', 'users.active_status', 'users.avatar')
            ->orderBy('max_created_at', 'desc')
            ->paginate($request->per_page ?? $this->perPage);


        $usersList = $users->items();
        $contacts = [];

        foreach ($usersList as $user) {
            $contacts[] = $this->chatifyMessenger->getContactItem($user);
        }

        return response()->json([
            'contacts' => $contacts,
            'total' => $users->total() ?? 0,
            'last_page' => $users->lastPage() ?? 1,
        ], 200);
    }

    public function getUserContacts(Request $request)
    {
        $users = Message::join('doctors', function ($join) {
            $join->on('ch_messages.from_id', '=', 'doctors.id')
                ->orOn('ch_messages.to_id', '=', 'doctors.id');
        })
            ->where(function ($q) {
                $q->where('ch_messages.from_id', auth()->guard('user')->user()->id)
                    ->orWhere('ch_messages.to_id', auth()->guard('user')->user()->id);
            })
            ->select('doctors.id', 'doctors.first_name', 'doctors.last_name', 'doctors.email', 'doctors.created_at', 'doctors.updated_at', 'doctors.active_status', 'doctors.avatar', DB::raw('MAX(ch_messages.created_at) AS max_created_at'))
            ->groupBy('doctors.id', 'doctors.first_name', 'doctors.last_name', 'doctors.email', 'doctors.created_at', 'doctors.updated_at', 'doctors.active_status', 'doctors.avatar')
            ->orderBy('max_created_at', 'desc')
            ->paginate($request->per_page ?? $this->perPage);

        $usersList = $users->items();
        $contacts = [];

        foreach ($usersList as $user) {
            $contacts[] = $this->chatifyMessenger->getContactItem($user);
        }

        return response()->json([
            'contacts' => $contacts,
            'total' => $users->total() ?? 0,
            'last_page' => $users->lastPage() ?? 1,
        ], 200);
    }



    /**
     * Get shared photos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sharedPhotos(Request $request)
    {
        $images = $this->chatifyMessenger->getSharedPhotos($request['user_id'] || $request['doctor_id']);

        foreach ($images as $image) {
            $image = asset('attachments' . $image);
        }
        // send the response
        return Response::json([
            'shared' => $images ?? [],
        ], 200);
    }

    /**
     * Delete conversation
     *
     * @param Request $request
     */
    public function deleteConversation(Request $request)
    {
        // delete
        $delete = $this->chatifyMessenger->deleteConversation($request['id']);

        // send the response
        return Response::json([
            'deleted' => $delete ? 1 : 0,
        ], 200);
    }
}