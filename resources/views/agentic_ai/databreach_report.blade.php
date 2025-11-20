@extends('template')

@push('styles')
<style>
    .doc-card-header {
        background: #f2f7fd;
        border-bottom: 1px solid #e1e6ec;
        cursor: pointer;
    }
    .doc-badge {
        background: #1B72C0;
        color: #fff;
        font-weight: bold;
        padding: 3px 12px;
        border-radius: 18px;
        font-size: 1.05em;
        letter-spacing: 0.02em;
        margin-right: 9px;
    }
    .btn-doc-view {
        font-size:1.02em;
        font-weight:bold;
        background:linear-gradient(90deg,#3ea2ff,#3295ef);
        color:#fff;
        border-radius:20px;
        padding:6px 19px 6px 16px;
        border:none;
        transition:.16s;
        box-shadow:0 2px 10px rgba(50,127,237,0.07);
        margin-left:6px;
    }
    .btn-doc-view[aria-expanded="true"]{ background:#1B72C0; color:#fff;}
    .collapse-content { padding: 1.3em 1.3em 1em 1.6em; border-left: 4px solid #1B72C0; border-radius: 0 8px 8px 0;}
    .collapse-content .close-collapse {
        float:right; color:#1B72C0; text-decoration:underline;
        font-size: 0.97em; border:none; background:none; margin-top: -4px;
    }
    .collapse-content .close-collapse:hover { color:#084f7c; text-decoration:none;}
    .collapse-content h6 {margin-top: 0;}
    .badge-agent { background: #1B72C0; color: #fff; font-weight:bold;}
    .card.doc-card { margin-bottom: 1.3em; border-radius: 8px; border: 1px solid #eaecec;}
</style>
@endpush


@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h2 class="mb-4">Data Compliance and Privacy Expert Lawyer</h2>
                        <div class="alert alert-info mb-4">
                            <b>About Agentic AI Data Breach Expert Lawyer:</b><br>
                            <h4>* Select your jurisdiction and (optionally) enter your organisation name.<br>
                              * Based on that it will create a concise list of the essential documents, policies and procedures that must be in place to manage data breaches.
                           </h4>
                        </div>
                        <!-- Form -->
                        <form method="POST" action="{{ route('agenticai.docs_agent.generate') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="jurisdiction">Jurisdiction:</label>
                                <select name="jurisdiction" id="jurisdiction" class="form-control" required>
                                    @foreach($jurisdictions as $j)
                                        <option value="{{ $j }}" 
                                            {{ (old('jurisdiction', $selectedJurisdiction ?? null) == $j) ? 'selected' : '' }}>
                                            {{ $j }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="organisation_name">Organisation Name:</label>
                                <input type="text" name="organisation_name" class="form-control"
                                     value="{{ old('organisation_name', $organisation ?? 'CybersecAI.IO Pty Ltd') }}" required>
                            </div>
                            <button class="btn btn-success btn-lg mb-3" type="submit">
                                <i class="bi bi-cpu"></i> Initiate Agentic AI Data Breach Expert Lawyer
                            </button>
                          
                          
                        </form>
                        @if(isset($error))
                            <div class="alert alert-danger mt-4">{{ $error }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection