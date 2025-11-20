@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row margin-top-85">
            <div class="row m-0">
                @include('users.sidebar')
                <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                    <a href="{{ route('agenticai.docs_agent.index') }}" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Data & Privacy Management
                    </a>
                    <h2>Agentic AI Generated Documents (Markdown/Word Output)</h2>
                    @if(isset($results) && count($results))
                        <ul>
                            @foreach($results as $doc)
                            <li>
                                <b>{{ $doc['DocumentType'] }}</b><br>
                                @if(isset($doc['docx_download_url']))
                                    <a href="{{ $doc['docx_download_url'] }}" style="font-weight:bold;color:#1768d9;">Download .docx (Word)</a><br>
                                @endif
                                @if(isset($doc['json_download_url']))
                                    <a href="{{ $doc['json_download_url'] }}">Download generated JSON</a>
                                @endif
                                <hr>
                                <pre style="background:#f8f8f8;white-space:pre-wrap;">{{ $doc['markdown'] ?? '' }}</pre>
                            </li>
                            @endforeach
                        </ul>
                    @elseif(isset($error_message))
                        <div class="alert alert-warning">{{ $error_message }}</div>
                    @else
                        <div class="alert alert-info">No results generated.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection