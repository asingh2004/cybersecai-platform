@extends('template')

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

        <div class="col-lg-10">
            <div class="main-panel">
<div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">
    <h1>Blog Summarizer</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        <div class="chat-window w-100 mb-3">
            <div class="chat-message tutor-message">
                <strong>Summary:</strong>
                <pre id="summaryText">{{ session('summary') }}</pre> <!-- Display summarized text -->
                <button id="copyButton" class="btn btn-secondary mt-2">Copy Summary</button> <!-- Copy button -->
                <p>Download the PDF <a href="{{ asset('storage/' . session('summary_pdf')) }}" class="btn btn-link">here</a>.</p>
            </div>
        </div>
    @endif

    <form action="{{ route('web.blog.summarize') }}" method="POST" class="w-100">
        @csrf
        <div class="form-group">
            <label for="urls">Enter Blog URLs (comma-separated):</label>
            <textarea id="urls" name="urls[]" class="form-control" rows="5" required></textarea>
        </div>
        
        <div class="form-group">
            <label for="detail">Summary Detail (0-1):</label>
            <input type="number" id="detail" name="detail" class="form-control" min="0" max="1" step="0.1" value="0.1" required> <!-- Default value set to 0.1 -->
        </div>

        <div class="form-group">
            <label for="model">Model:</label>
            <select id="model" name="model" class="form-control" required>
                <option value="gpt-4o-mini">gpt-4o-mini</option>
                <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                <!-- Add more models as needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="additional_instructions">Additional Instructions:</label>
            <textarea id="additional_instructions" name="additional_instructions" class="form-control" rows="3" placeholder="Provide any additional instructions for the summarization..."></textarea>
        </div>

        <button type="submit" id="submitButton" disabled class="btn btn-primary">Summarize</button>
    </form>
</div>
              </div>
          </div></div></div>

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
    .chat-message {
        margin-bottom: 10px;
    }
    .tutor-message {
        text-align: left;
        background-color: #d4edda;
        border-radius: 8px;
        padding: 10px;
    }
    .btn-link {
        text-decoration: underline;
        color: #007bff;
    }
</style>

<script>
    // Enable the submit button when a URL is entered
    document.getElementById('urls').addEventListener('input', function() {
        const submitButton = document.getElementById('submitButton');
        submitButton.disabled = !this.value.trim(); // Enable if there's input
    });

    document.getElementById('copyButton').addEventListener('click', function() {
        // Get the text from the summary element
        var summaryText = document.getElementById('summaryText').innerText;

        // Create a temporary textarea element to hold the text
        var tempTextarea = document.createElement('textarea');
        tempTextarea.value = summaryText; 
        document.body.appendChild(tempTextarea);
        
        // Select the text
        tempTextarea.select();
        tempTextarea.setSelectionRange(0, 99999); // For mobile devices

        // Copy the text to the clipboard
        document.execCommand('copy');

        // Remove the temporary textarea
        document.body.removeChild(tempTextarea);

        // Notify the user that the text has been copied
        alert('Summary copied to clipboard!');
    });
</script>
@endsection