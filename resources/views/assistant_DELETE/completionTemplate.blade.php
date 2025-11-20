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
    background-image: url("{{ asset('public/front/images/bots/completion_ai_hero1.png') }}");
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
                    <h1 class="mb-4" style="font-weight: bold;">{{ session('assistant_name', 'Default Assistant Name') }}</h1>
                  
                  	<div>
        				<h2 style="font-weight: bold;">Instructions:</h2>
        					<h4>{{ session('assistant_instructions') }}</h4>
    				</div>

                    <div class="chat-window w-100" style="height: 500px;">
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

                        <!--<div class="input-group">
                            <label for="question" class="sr-only">Ask a question:</label>
                            <input type="text" class="form-control" name="messages[1][content]" style="height: 60px;" id="question" placeholder="Enter your message..." required value="{{ old('messages.1.content', '') }}">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">Send</button>
                            </div>
                        </div>-->
                      
                      	<div class="input-group">
    						<label for="question" class="sr-only">Ask a question:</label>
    						<input type="text" class="form-control" name="messages[1][content]" style="height: 60px;" id="question" placeholder="Enter your message..." required value="{{ old('messages.1.content', '') }}">
    						<div class="input-group-append">
        					<!-- Updated button and ensured it's part of the input group 
        						<button type="button" tabindex="0" class="btn btn-sm btn-filled btn-success btn-no-auto-margin" onclick="runFunction()">-->
          						<button class="btn btn-lg btn-filled btn-success btn-no-auto-margin" type="submit">
            					<span class="btn-label-wrap">
                				<span class="btn-label-inner">Run</span>
     
            					</span>
        						</button>
    						</div>
						</div>
                      
                      
                    </form>

                    @if(!empty($messages) && count($messages) > 0 && end($messages)['role'] === 'assistant')
                        <button id="copy-response" class="btn btn-secondary mt-2" onclick="copyToClipboard()">Copy Response</button>
                    @endif
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
<style>
    .chat-window {
        border: 5px solid #ccc;
        border-radius: 10px;
        background-color: #f9f9f9;
        padding: 20px;
        max-height: 800px;
        width: 100%;
        overflow-y: auto;
    }
    .chat-log {
        max-height: 800px;
        overflow-y: auto;
        margin-bottom: 15px;
        scroll-behavior: smooth;
    }
    .chat-message {
        margin-bottom: 10px;
        max-width: 100%; /* Limit width to 80% of the chat window */
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
        border: 5px solid #ccc;
        border-radius: 10px;
        background-color: #f9f9f9;
        padding: 20px;
        max-height: 800px;
        width: 100%;
    }
    .form-control {
        border-radius: 20px;
        height: 50px;
    }
    .btn {
        border-radius: 20px;
    }
  
  	.btn-primary {
    	background-color: #007bff; /* Bootstrap primary color */
    	border-color: #007bff; /* Border color matching primary color */
	}

	.btn-primary:hover {
    	background-color: #0056b3; /* Darker shade on hover */
    	border-color: #0056b3; /* Matching border */
	}

	.btn-filled {
    	color: #ffffff; /* White text color */
	}

	.btn-no-auto-margin {
    	margin: 0; /* Ensure no extra margin */
	}

	.keyboard-shortcut-key {
    	display: inline-block;
    	background: #eee; /* Light gray background for keys */
    	border-radius: 4px; /* Rounded corners for keys */
    	padding: 2px 5px; /* Padding inside keys */
    	margin-left: 2px; /* Space between keys */
}
</style>

<script>
    document.getElementById('openai_config_form').addEventListener('submit', function() {
        const chatLog = document.getElementById('chat-log');
        chatLog.scrollTop = chatLog.scrollHeight; // Scroll to the bottom
    });

    function copyToClipboard() {
        const chatMessages = document.querySelectorAll('.chat-message.tutor-message pre');
        const latestResponse = chatMessages[chatMessages.length - 1].innerText; // Get latest assistant response
        navigator.clipboard.writeText(latestResponse).then(() => {
            alert('Response copied to clipboard!'); // Optional: Notify the user
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }
</script>
@endsection