@extends('template')

@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column align-items-center">
    <h1 class="mb-4">Explore Sample Apps Built with Assistants API</h1>
    
    <!-- Create Assistant Form -->
    <div class="card mb-4 w-100">
        <div class="card-header">
            <h2>Create Assistant</h2>
        </div>
        <div class="card-body">
            <form action="{{ route('assistant.create') }}" method="POST">
                @csrf <!-- CSRF protection -->
                <button type="submit" class="btn btn-primary">Create Assistant</button>
            </form>
            @if (session('assistantId'))
                <div id="assistantId" class="mt-2">Assistant ID: {{ session('assistantId') }}</div>
                <input type="hidden" id="assistantIdHidden" name="assistantId" value="{{ session('assistantId') }}">
            @endif
            @if (session('error'))
                <div class="alert alert-danger mt-2">{{ session('error') }}</div>
            @endif
        </div>
    </div>

    <!-- Upload File Form -->
    <div class="card mb-4 w-100">
        <div class="card-header">
            <h2>Upload File</h2>
        </div>
        <div class="card-body">
            <form action="{{ route('files.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf <!-- CSRF protection -->
                <input type="hidden" name="assistantId" value="{{ session('assistantId') }}">
                <div class="mb-2">
                    <input type="file" name="file" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">Upload File</button>
            </form>
            @if (session('uploadFileResponse'))
                <div class="mt-2">{{ session('uploadFileResponse') }}</div>
            @endif
        </div>
    </div>

    <!-- List Files -->
    <div class="card mb-4 w-100">
        <div class="card-header">
            <h2>List Files</h2>
        </div>
        <div class="card-body">
            <form action="{{ route('files.list') }}" method="GET">
                @csrf <!-- CSRF protection -->
                <input type="hidden" name="assistantId" value="{{ session('assistantId') }}">
                <button type="submit" class="btn btn-info">List Files</button>
            </form>
            <ul id="fileList" class="mt-3 list-group">
                @if (session('files') && is_array(session('files'))) <!-- Check if session('files') is an array -->
                    @foreach (session('files') as $file) <!-- Iterate through each file in the session -->
                        <li class="list-group-item">
                            File ID: {{ $file['file_id'] }}<br>
                            Filename: {{ $file['filename'] }}<br>
                            Status: {{ $file['status'] }}
                        </li>
                    @endforeach
                @else
                    <li class="list-group-item">No files found.</li> <!-- Handle when no files are present -->
                @endif
            </ul>
        </div>
    </div>

    <!-- Delete File Form -->
    <div class="card mb-4 w-100">
        <div class="card-header">
            <h2>Delete File</h2>
        </div>
        <div class="card-body">
            <form action="{{ route('files.delete') }}" method="POST">
                @csrf <!-- CSRF protection -->
                @method('DELETE') <!-- Specify the request method -->
                <div class="input-group mb-2">
                    <input type="text" name="fileId" class="form-control" placeholder="File ID" required>
                    <input type="hidden" name="assistantId" value="{{ session('assistantId') }}">
                    <button type="submit" class="btn btn-danger">Delete File</button>
                </div>
                @if (session('deleteFileResponse'))
                    <div class="mt-2">{{ session('deleteFileResponse') }}</div>
                @endif
            </form>
        </div>
    </div>

    <!-- Create Thread -->
    <div class="card mb-4 w-100">
        <div class="card-header">
            <h2>Create Thread</h2>
        </div>
        <div class="card-body">
            <form action="{{ route('threads.create') }}" method="POST">
                @csrf <!-- CSRF protection -->
                <input type="hidden" name="assistantId" value="{{ session('assistantId') }}">
                <button type="submit" class="btn btn-warning">Create Thread</button>
            </form>
            @if (session('threadId'))
                <div class="mt-2">Thread ID: {{ session('threadId') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection