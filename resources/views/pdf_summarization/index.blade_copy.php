@extends('template')

@section('main')
      
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

        <div class="col-lg-10">
            <div class="main-panel">

<div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">

    <h1 class="mb-4">PDF Summarization</h1>

    @if(session('success'))
    <div class="chat-window w-100 mb-3">
        <div class="chat-message tutor-message">
            <strong>Summary:</strong>
            <pre id="summaryText">{{ session('summary') }}</pre> <!-- Display summarized text -->
            <button id="copyButton" class="btn btn-secondary mt-2">Copy Summary</button> <!-- Copy button -->
            <p>Download the PDF <a href="{{ asset('storage/' . session('filename')) }}" class="btn btn-link">here</a>.</p>
        </div>
    </div>
    @endif

    <form action="{{ route('pdf.summarize') }}" method="POST" enctype="multipart/form-data" id="pdfForm" class="w-100">
        @csrf
        <div class="form-group">
            <label for="pdfFile" class="sr-only">Upload PDF:</label>
            <input type="file" name="pdf_file" required accept=".pdf" id="pdfFile" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="detail">Detail Level (0 - 1):</label>
            <input type="number" name="detail" min="0" max="1" step="0.01" value="0" required class="form-control">
        </div>

        <div class="form-group">
            <label for="model">Model:</label>
            <select id="model" name="model" class="form-control" required>
                <option value="gpt-4o-mini">gpt-4o-mini</option>
                <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                <!-- Add other models if needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="additional_instructions">Additional Instructions:</label>
            <textarea id="additional_instructions" name="additional_instructions" class="form-control" rows="3" placeholder="Provide any additional instructions for the summarization..."></textarea>
        </div>

        <button type="submit" id="submitButton" disabled class="btn btn-primary">Upload and Summarize</button>
    </form>

    <script>
        // Enable the submit button when a PDF file is selected
        document.getElementById('pdfFile').addEventListener('change', function() {
            const submitButton = document.getElementById('submitButton');
            submitButton.disabled = !this.files.length; // Enable if there's a file
        });

        // Copy summary text to clipboard
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

            // Optionally, alert the user that the text has been copied
            alert('Summary copied to clipboard!');
        });
    </script>
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
@endsection