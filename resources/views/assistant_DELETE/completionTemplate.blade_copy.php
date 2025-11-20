@extends('template')

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

        <div class="col-lg-10">
            <div class="main-panel">
                <div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">
                    <h1 class="mb-4">{{ session('assistant_name', 'Default Assistant Name') }}</h1>

                    <div class="chat-window w-100">
                        <div class="chat-log mb-3" id="chat-log">
                            @if(!empty($messages) && count($messages) > 0)
                                @foreach($messages as $message)
                                    <div class="chat-message {{ $message['role'] === 'user' ? 'user-message' : 'tutor-message' }}">
                                        <strong>{{ ucfirst($message['role']) }}:</strong>
                                        <pre>{{ $message['content'] }}</pre>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <form id="openai_config_form" method="POST" action="{{ route('assistant.handleCompletion') }}" class="w-100">
                        @csrf
                        <!-- System message -->
                      	<input type="hidden" name="messages[0][role]" value="system">
                        <input type="hidden" name="messages[0][content]" value="{{ session('assistant_instructions', 'No instructions available') }}">

                        <div class="input-group">
    						<label for="question" class="sr-only">Ask a question:</label>
    
    						<input type="text" class="form-control" name="messages[1][content]" id="question" placeholder="Enter your question here..." required value="{{ old('messages.1.content', '') }}">
    							<div class="input-group-append">
        							<button class="btn btn-primary" type="submit">Send</button>
    							</div>
						</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
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
        scroll-behavior: smooth;
    }
    .chat-message {
        margin-bottom: 10px;
        max-width: 80%; /* Limit width to 80% of the chat window */
        word-wrap: break-word; /* Enable word wrap */
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

<script>
    document.getElementById('openai_config_form').addEventListener('submit', function() {
        const chatLog = document.getElementById('chat-log');
        chatLog.scrollTop = chatLog.scrollHeight; // Scroll to the bottom
    });
</script>
@endsection