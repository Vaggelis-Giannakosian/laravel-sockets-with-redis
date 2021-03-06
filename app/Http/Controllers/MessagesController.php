<?php


namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\User;
use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use App\Models\Thread;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

class MessagesController extends Controller
{
    /**
     * Show all of the message threads to the user.
     *
     * @return mixed
     */
    public function index()
    {
        // All threads, ignore deleted/archived participants
        $threads = Thread::getAllLatest()->get();

        // All threads that user is participating in
        // $threads = Thread::forUser(Auth::id())->latest('updated_at')->get();

        // All threads that user is participating in, with new messages
        // $threads = Thread::forUserWithNewMessages(Auth::id())->latest('updated_at')->get();

        return view('messenger.index', compact('threads'));
    }

    /**
     * Shows a message thread.
     *
     * @param $id
     * @return mixed
     */
    public function show(Thread $thread)
    {
        $messages = $thread->messages()->with('user')->latest()->take(30)->get()->reverse()->values();
        $thread->updateLastRead();

        return response([
            'messages' =>  $messages
        ],200);
    }

    /**
     * Creates a new message thread.
     *
     * @return mixed
     */
    public function create()
    {
        $users = User::where('id', '!=', Auth::id())->get();

        return view('messenger.create', compact('users'));
    }

    /**
     * Stores a new message thread.
     *
     * @return mixed
     */
    public function store(Thread $thread)
    {

//        $threadId = request()->input('threadId') ?: ( Thread::between([auth()->id(),$receiverId])->first()->id ?? null );
//
//        if(! $threadId)
//        {
//            //TODO: create Thread - add message - create participants
//        }else{
//            //TODO: add message
//        }
//

        $message = $thread->addMessage( request('message'));
        event(new MessageSent(auth()->user(),$thread,$message));

        return response(['message'=>$message,'sender'=>auth()->user()],201);


        //TODO: find thread and if not exists create one
//
//        $input = Request::all();
//
//        $thread = Thread::create([
//            'subject' => $input['subject'],
//        ]);
//
//        // Message
//        Message::create([
//            'thread_id' => $thread->id,
//            'user_id' => Auth::id(),
//            'body' => $input['message'],
//        ]);
//
//        // Sender
//        Participant::create([
//            'thread_id' => $thread->id,
//            'user_id' => Auth::id(),
//            'last_read' => new Carbon,
//        ]);
//
//        // Recipients
//        if (Request::has('recipients')) {
//            $thread->addParticipant($input['recipients']);
//        }
//
//        return redirect()->route('messages');
    }

    /**
     * Adds a new message to a current thread.
     *
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        try {
            $thread = Thread::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The thread with ID: ' . $id . ' was not found.');

            return redirect()->route('messages');
        }

        $thread->activateAllParticipants();

        // Message
        Message::create([
            'thread_id' => $thread->id,
            'user_id' => Auth::id(),
            'body' => Request::input('message'),
        ]);

        // Add replier as a participant
        $participant = Participant::firstOrCreate([
            'thread_id' => $thread->id,
            'user_id' => Auth::id(),
        ]);
        $participant->last_read = new Carbon;
        $participant->save();

        // Recipients
        if (Request::has('recipients')) {
            $thread->addParticipant(Request::input('recipients'));
        }

        return redirect()->route('messages.show', $id);
    }
}
