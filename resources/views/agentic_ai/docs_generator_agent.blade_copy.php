@extends('template')
<style>
.badge-mandatory { background: #dc3545; color: #fff; }
.badge-bestpractice { background: #1768d9; color: #fff; }
</style>

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row margin-top-85">
            <div class="row m-0">
                @include('users.sidebar')
                <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                  
                  	 <a href="{{ route('agenticai.docs_agent.form') }}" class="btn btn-success btn-lg mb-3">
                            + Generate New Suite of Data Breach Governance Documents
                        </a>
                  
                 
                  
                  	<h3>Data Breach Management Governance Framework</h3>
                  	<h4>Policy, Procedures, Plans and More</h4>

                    @if(isset($error_message) && $error_message)
                        <div class="alert alert-warning">{{ $error_message }}</div>
                    @endif

                    @if((!isset($groupedDocs) || !count($groupedDocs)) && (!isset($results) || !count($results)))
                        <div class="alert alert-info">No results generated.</div>
                    @endif

                    @if(isset($groupedDocs) && count($groupedDocs))
                        @foreach($groupedDocs as $orgName => $groups)
                            <div class="mb-4 pb-2">
                                <h3 class="mt-4 mb-2">{{ $orgName }}</h3>
                                @foreach($groups as $groupLabel => $docs)
                                    <h4 class="mt-2 mb-1">{{ $groupLabel }}</h4>
                                    <table class="table table-bordered table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>File Name</th>
                                                <th>Document Type</th>
                                                <th>Mandatory</th>
                                                <th>Word</th>
                                                <th>JSON</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($docs as $doc)
                                                <tr>
                                                    <td>{{ $doc['file_display_name'] ?? '-' }}</td>
                                                    <td>{{ $doc['DocumentType'] }}</td>
                                                    <td>
                                                        @if(!empty($doc['is_mandatory']))
    <span class="badge badge-mandatory">Mandatory</span>
@else
    <span class="badge badge-bestpractice">Best Practice</span>
@endif
                                                    </td>
                                                    <td>
                                                        @if(isset($doc['docx_download_url']))
                                                            <a href="{{ $doc['docx_download_url'] }}" class="btn btn-sm btn-primary">Word</a>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if(isset($doc['json_download_url']))
                                                            <a href="{{ $doc['json_download_url'] }}" class="btn btn-sm btn-info">JSON</a>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <form method="POST" action="{{ route('agenticai.docs_agent.delete') }}" style="display:inline;">
                                                            @csrf
                                                            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                                                            <input type="hidden" name="json_path" value="{{ $doc['json_download_url'] }}">
                                                            <input type="hidden" name="docx_path" value="{{ $doc['docx_download_url'] }}">
                                                            <button type="submit" class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Are you sure you want to delete this document?');">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endforeach
                            </div>
                        @endforeach
                    @elseif(isset($results) && count($results))
                        <ul>
                            @foreach($results as $doc)
                            <li>
                                <b>{{ $doc['DocumentType'] }}</b>
                                @if(!empty($doc['is_mandatory']))
                                    <span class="badge badge-danger">Mandatory</span>
                                @else
                                    <span class="badge badge-secondary">Best Practice</span>
                                @endif
                                <br>
                                @if(isset($doc['docx_download_url']))
                                    <a href="{{ $doc['docx_download_url'] }}" style="font-weight:bold;color:#1768d9;">Download .docx (Word)</a><br>
                                @endif
                                @if(isset($doc['json_download_url']))
                                    <a href="{{ $doc['json_download_url'] }}">Download generated JSON</a>
                                @endif
                                <form method="POST" action="{{ route('agenticai.docs_agent.delete') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                                    <input type="hidden" name="json_path" value="{{ $doc['json_download_url'] }}">
                                    <input type="hidden" name="docx_path" value="{{ $doc['docx_download_url'] }}">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this document?');">
                                        Delete
                                    </button>
                                </form>
                                <hr>
                                <pre style="background:#f8f8f8;white-space:pre-wrap;">{{ $doc['markdown'] ?? '' }}</pre>
                            </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection