@extends('template')

@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">

    <h1 class="mb-4">Chat with Coding Companion for Python, Php and More</h1>
    
    <div class="chat-window w-100">
        <div class="chat-log mb-3" id="chat-log">
            @if(isset($messages) && count($messages) > 0)
                @foreach($messages as $message)
                    <div class="chat-message {{ $message['role'] === 'user' ? 'user-message' : 'tutor-message' }}">
                        <strong>{{ ucfirst($message['role']) }}:</strong>
                        <pre>{{ $message['content'] }}</pre>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('math-tutor.answer') }}" class="w-100">
        @csrf
      
        <!-- System Instruction as a hidden input -->
        <input type="hidden" name="messages[0][role]" value="system">
        <input type="hidden" name="messages[0][content]" value="You are expert coder, with expertise in programmign in pythin, Larval, Php, Html and CSS. Please answer the following questions procvide complete code with explanation on them.">
      
        <div class="input-group">
            <label for="question" class="sr-only">Ask a question:</label>
            <textarea class="form-control" name="messages[0][content]" id="question" placeholder="Enter your question here...">{{ old('messages.0.content', '') }}</textarea>
            <div class="input-group-append">
                <button class="btn btn-primary" type="submit">Send</button>
            </div>
        </div>
    </form>
</div>

<style>
    .chat-window {
        border: 1px solid #ccc;
        border-radius: 10px;
        background-color: #f9f9f9;
        padding: 20px;
        max-height: 400px;
        width: 100%;
        overflow-y: auto;
    }
    .chat-log {
        max-height: 330px;
        overflow-y: auto;
        margin-bottom: 15px;
    }
    .chat-message {
        margin-bottom: 10px;
    }
    .user-message {
        text-align: right;
        background-color: #e7f3ff;
        border-radius: 8px;
        padding: 10px;
        margin-left: 50px;
        margin-right: 0;
    }
    .tutor-message {
        text-align: left;
        background-color: #d4edda;
        border-radius: 8px;
        padding: 10px;
        margin-right: 50px;
        margin-left: 0;
    }
    .input-group {
        width: 100%;
    }
    .form-control {
        border-radius: 20px;
        height: 50px;
    }
    .btn {
        border-radius: 20px;
    }
</style>
@endsection