@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h2><strong>File Details</strong></h2>

                        @php
                            $storageLabels = $storageLabels ?? [
                                'aws_s3'     => 'AWS S3',
                                'smb'        => 'SMB',
                                'onedrive'   => 'OneDrive',
                                'sharepoint' => 'SharePoint',
                            ];
                            if (!function_exists('fmtBytes')) {
                                function fmtBytes($bytes) {
                                    $bytes = (int)$bytes;
                                    if ($bytes < 1024) return $bytes.' B';
                                    $units = ['KB','MB','GB','TB','PB'];
                                    $i = floor(log($bytes,1024));
                                    return round($bytes/pow(1024,$i),2).' '.$units[$i-1];
                                }
                            }
                            // Fallback for provider web URL if not on the file record
                            $fileWebUrl = $file->web_url
                                ?? optional($file->onedriveFile)->web_url
                                ?? optional($file->sharepointFile)->web_url
                                ?? optional($file->s3File)->web_url
                                ?? optional($file->smbFile)->web_url
                                ?? null;
                        @endphp

                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title mb-1">{{ $file->file_name }}</h5>
                                        <div class="text-muted small">{{ $storageLabels[$file->storage_type] ?? $file->storage_type }}</div>
                                        <div class="text-muted small">Path: {{ $file->full_path ?? '-' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Last Modified</div>
                                        <div>{{ $file->last_modified ? \Carbon\Carbon::parse($file->last_modified)->toDayDateTimeString() : '-' }}</div>
                                        <div class="small text-muted mt-2">Size</div>
                                        <div>{{ fmtBytes($file->size_bytes) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Provider & Permissions block (your requested snippet, with safe optionals) --}}
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Provider & Permissions</h5>

                                <div class="row">
                                  <div class="col-md-6">
                                    <div><strong>Storage:</strong> {{ $storageLabels[$file->storage_type] ?? $file->storage_type }}</div>
                                    <div>
                                        <strong>Location:</strong>
                                        {{ optional($file->onedriveFile)->parent_reference
                                            ?? optional($file->sharepointFile)->parent_reference
                                            ?? optional($file->smbFile)->full_path
                                            ?? optional($file->s3File)->full_path
                                            ?? ($file->full_path ?? '') }}
                                    </div>
                                    @if(optional($file->onedriveFile)->owner_display_name)
                                      <div><strong>Owner:</strong> {{ $file->onedriveFile->owner_display_name }} ({{ $file->onedriveFile->owner_email }})</div>
                                    @endif
                                    @if(optional($file->sharepointFile)->site_id)
                                      <div><strong>Site:</strong> {{ $file->sharepointFile->site_id }}</div>
                                    @endif
                                    @if($fileWebUrl)
                                      <div><a href="{{ $fileWebUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">Open in provider</a></div>
                                    @endif
                                  </div>
                                </div>

                                <hr>
                                <h6 class="mb-2">Permissions</h6>
                                <div class="table-responsive">
                                  <table class="table table-sm mb-0">
                                    <thead>
                                      <tr><th>Role</th><th>Principal</th><th>Type</th><th>Source</th></tr>
                                    </thead>
                                    <tbody>
                                    @forelse($file->permissions as $p)
                                      <tr>
                                        <td>{{ $p->role }}</td>
                                        <td>{{ $p->principal_display_name }} <span class="text-muted">{{ $p->principal_email }}</span></td>
                                        <td>{{ $p->principal_type }}</td>
                                        <td>{{ $p->source }}</td>
                                      </tr>
                                    @empty
                                      <tr><td colspan="4" class="text-muted text-center p-3">No permissions recorded.</td></tr>
                                    @endforelse
                                    </tbody>
                                  </table>
                                </div>
                            </div>
                        </div>

                        @php $a = $file->latestAiAnalysis ?? null; @endphp
                        @if($a)
                            <div class="card shadow-sm mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">AI Assessment</h5>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="small text-muted">Overall Risk</div>
                                            @php $b = ['High'=>'danger','Medium'=>'warning','Low'=>'info','None'=>'success'][$a->overall_risk_rating ?? 'None'] ?? 'secondary'; @endphp
                                            <span class="badge bg-{{ $b }}">{{ $a->overall_risk_rating ?? 'None' }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="small text-muted">Classification</div>
                                            <span class="badge bg-dark">{{ $a->data_classification ?? '-' }}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small text-muted">Likely Subject Area</div>
                                            <div>{{ $a->likely_data_subject_area ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="small text-muted">Auditor Agent View</div>
                                        <div>{{ $a->auditor_agent_view ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Detected Standards & Fields</h6>
                                            @forelse($a->findings as $finding)
                                                <div class="mb-3">
                                                    <div><strong>{{ $finding->standard }}</strong> <span class="text-muted">({{ $finding->jurisdiction ?? 'N/A' }})</span></div>
                                                    <div class="small">Risk: <span class="badge bg-secondary">{{ $finding->risk ?? 'None' }}</span></div>
                                                    <div class="small mt-1">
                                                        @if($finding->detectedFields->count())
                                                            <strong>Detected Fields:</strong>
                                                            @foreach($finding->detectedFields as $df)
                                                                <span class="badge bg-light text-dark border">{{ $df->field_name }}</span>
                                                            @endforeach
                                                        @else
                                                            <em class="text-muted">No fields listed</em>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-muted">No findings.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Proposed Controls</h6>
                                            @if($a->controls->count())
                                                <ul class="small mb-0">
                                                    @foreach($a->controls as $ctl)
                                                        <li>{{ $ctl->control_text }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="text-muted">No controls listed.</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-light border">No AI analysis available for this file yet.</div>
                        @endif

                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-2"></i>Back</a>
                            <a href="{{ route('wizard.file_graph_table', ['q' => $file->file_name]) }}" class="btn btn-success"><i class="fa fa-table me-2"></i>Find Similar</a>
                            <a href="{{ route('filesummary.duplicates.group', ['file_name' => $file->file_name, 'size_bytes' => $file->size_bytes]) }}" class="btn btn-outline-dark">
                                <i class="fa fa-clone me-2"></i>Find Duplicates
                            </a>
                        </div>

                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection