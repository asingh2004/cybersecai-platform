@extends('template')

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

        <div class="col-lg-10">
            <div class="main-panel">
                <div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">
                    <h1 class="mb-4">{{ $config->name }}</h1>
                    <h4>Instructions:</h4>
                    <p>{{ $config->instructions }}</p>

                    <div class="chat-window w-100 mb-4">
                        <div class="chat-log mb-3" id="chat-log">
                            @if(!empty($formattedMessages) && count($formattedMessages) > 0)
                                @foreach($formattedMessages as $formattedMessage)
                                    <div class="chat-message">
                                        {!! $formattedMessage !!} {{-- Use {!! !!} to render HTML --}}
                                        @if (strpos($formattedMessage, 'Assistant:') !== false)
                                            <button type="button" class="btn btn-link copy-response">Copy Response</button>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <form id="openai_config_form" method="POST" action="{{ route('assistant.submit') }}" class="w-100">
                        @csrf
                        <div id="message-container">
                            <div class="input-group message-row mb-3">
                                <label for="userMessage" class="sr-only">Your Message:</label>
                                <textarea name="user_messages[]" class="form-control" rows="3" placeholder="Enter your message here..." required></textarea>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-link add-message">More Instructions</button>
                                </div>
                            </div>
                        </div>
                        <div class="input-group">
                            <button class="btn btn-primary" type="submit">Send</button>
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
        margin-right: 10px; /* Add margin for spacing */
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

    document.querySelector('.add-message').addEventListener('click', function() {
        const messageContainer = document.getElementById('message-container');
        const newMessageRow = document.createElement('div');
        newMessageRow.className = 'input-group message-row mb-3';
        newMessageRow.innerHTML = `
            <label for="userMessage" class="sr-only">Your Message:</label>
            <textarea name="user_messages[]" class="form-control" rows="3" placeholder="Enter your message here..." required></textarea>
            <div class="input-group-append">
                <button type="button" class="btn btn-link remove-message">Remove</button>
            </div>
        `;
        messageContainer.appendChild(newMessageRow);
        
        // Add event listener for new remove button
        newMessageRow.querySelector('.remove-message').addEventListener('click', function() {
            messageContainer.removeChild(newMessageRow);
        });
    });

    // Event delegation for copy buttons
    document.getElementById('chat-log').addEventListener('click', function(event) {
        if (event.target.classList.contains('copy-response')) {
            const assistantResponse = event.target.previousElementSibling.innerText; // Get the assistant's response text
            navigator.clipboard.writeText(assistantResponse).then(() => {
                alert('Response copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    });
</script>

@endsection