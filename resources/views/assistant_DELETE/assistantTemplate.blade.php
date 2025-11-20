@extends('template')

@push('css')
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
  body,h1,h2,h3,h4,h5,h6 {font-family: "Raleway", sans-serif}

  body, html {
    height: 100%;
    line-height: 1.8;
  }

  /* Full height image header */
  .bgimg-1 {
    background-position: center;
    background-size: cover;
    background-image: url("{{ asset('public/front/images/bots/ai_assistant_hero.png') }}");
    min-height: 100%;
  }

  .w3-bar .w3-button {
    padding: 16px;
  }
</style>
@endpush

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

      	<div class="col-md-10 bgimg-1 w3-display-container w3-grayscale-min">
        <div class="col-lg-10">
            <div class="main-panel">
                <div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">
                    <div class="row w-100 mb-4">
                        <div class="col-md-3" style="flex: 0 0 25%; background-color: #f0f0f0; padding: 20px; border-radius: 8px;">
                            <h1 class="mb-4">{{ $config->name }}</h1>
                            <h4>Instructions:</h4>
                            <p>{{ $config->instructions }}</p>
                        </div>
                        <div class="col-md-9" style="flex: 0 0 75%; background-color: white; padding: 20px; border-radius: 8px;">
                            <div class="chat-window w-100 mb-4" style="height: 500px;">
                                <div class="chat-log mb-3" id="chat-log">
                                    @if(!empty($formattedMessages) && count($formattedMessages) > 0)
                                        @foreach($formattedMessages as $formattedMessage)
                                            <div class="chat-message">
                                                {!! $formattedMessage !!} {{-- Use {!! !!} to render HTML --}}
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <button id="copy-all-button" class="btn btn-link">Copy All Responses</button>

                            <form id="openai_config_form" method="POST" action="{{ route('assistant.submit') }}" class="w-100">
                                @csrf
                                <div id="message-container">
                                    <div class="input-group message-row mb-3">
                                        <label for="userMessage" class="sr-only">Your Message:</label>
                                        <textarea name="user_messages[]" class="form-control" rows="3" style="height: 60px;" placeholder="Enter your message here..." required></textarea>
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
          </div>
    </div>
</div>

<style>
    .chat-window {
        border: 1px solid #ccc;
        border-radius: 10px;
        background-color: #f9f9f9;
        max-height: 800px;
        width: 100%;
        overflow-y: auto;
    }
    .chat-log {
        max-height: 800px;
        overflow-y: auto;
        margin-bottom: 5px;
        scroll-behavior: smooth;
    }
    .chat-message {
        margin-bottom: 10px;
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
            <textarea name="user_messages[]" class="form-control" rows="3" style="height: 60px;" placeholder="Enter your message here..." required></textarea>
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