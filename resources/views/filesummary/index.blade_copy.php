@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h2><strong>Explore Your Data: Visual Analysis Dashboard</strong></h2>
                        <p class="mb-4">You can view your analyzed files and AI findings using these powerful visualizations and summaries.</p>

                        @php
                            // Safe defaults if controller variables missing
                            $storageAgg = $storageAgg ?? collect();
                            $donut = $donut ?? ['labels'=>[], 'data'=>[], 'keys'=>[]];
                            $storageLabels = $storageLabels ?? [
                                'aws_s3'     => 'AWS S3',
                                'smb'        => 'SMB',
                                'onedrive'   => 'OneDrive',
                                'sharepoint' => 'SharePoint',
                            ];
                            if (!function_exists('humanBytes')) {
                                function humanBytes($bytes) {
                                    $bytes = (int)$bytes;
                                    if ($bytes < 1024) return $bytes.' B';
                                    $units = ['KB','MB','GB','TB','PB'];
                                    $i = floor(log($bytes, 1024));
                                    return round($bytes / pow(1024, $i), 2).' '.$units[$i-1];
                                }
                            }
                            $colors = [
                                'aws_s3' => '#2c7be5',
                                'smb' => '#6f42c1',
                                'onedrive' => '#00a1f1',
                                'sharepoint' => '#107c10',
                            ];
                        @endphp

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <h5 class="mb-3">Your Files by Storage Type</h5>
                            </div>

                            @forelse($storageAgg as $row)
                                <div class="col-md-6">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="card-title" style="color: {{ $colors[$row->storage_type] ?? '#444' }}">
                                                    {{ $storageLabels[$row->storage_type] ?? $row->storage_type }}
                                                </h5>
                                                <span class="badge bg-dark">{{ number_format($row->total ?? 0) }} files</span>
                                            </div>
                                            <div class="small text-muted mb-2">Total size: {{ humanBytes($row->total_size ?? 0) }}</div>
                                            <div class="row text-center mt-3">
                                                <div class="col">
                                                    <span class="badge bg-danger">High</span>
                                                    <div class="fw-bold mt-1">{{ number_format($row->high_count ?? 0) }}</div>
                                                </div>
                                                <div class="col">
                                                    <span class="badge bg-warning text-dark">Medium</span>
                                                    <div class="fw-bold mt-1">{{ number_format($row->medium_count ?? 0) }}</div>
                                                </div>
                                                <div class="col">
                                                    <span class="badge bg-info text-dark">Low</span>
                                                    <div class="fw-bold mt-1">{{ number_format($row->low_count ?? 0) }}</div>
                                                </div>
                                                <div class="col">
                                                    <span class="badge bg-success">None</span>
                                                    <div class="fw-bold mt-1">{{ number_format($row->none_count ?? 0) }}</div>
                                                </div>
                                            </div>
                                            <div class="mt-3 d-flex gap-2">
                                                <a class="btn btn-outline-primary btn-sm"
                                                   href="{{ route('wizard.files.list', ['storage' => $row->storage_type]) }}">
                                                    View Files
                                                </a>
                                                <a class="btn btn-outline-secondary btn-sm"
                                                   href="{{ route('wizard.file_graph_table', ['storage' => $row->storage_type]) }}">
                                                    Open Table
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="alert alert-info">No files found for your account yet.</div>
                                </div>
                            @endforelse
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Files Breakdown by Storage</h5>
                                        @if(!empty($donut['data']) && array_sum($donut['data']) > 0)
                                            <canvas id="donutStorage" style="max-height:340px;"></canvas>
                                            <div class="small text-muted mt-2">Tip: Click a segment to drill down.</div>
                                        @else
                                            <div class="alert alert-light border mb-0">No data to display.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Quick Actions</h5>
                                        <p class="mb-3">Dive deeper using the visual summaries below:</p>
                                        <div class="d-grid gap-3">
                                            <a href="{{ route('wizard.filesummary_pyramid') }}" class="btn btn-warning btn-lg">
                                                <i class="fa fa-layer-group me-2"></i> Risk Pyramid Summary
                                            </a>

                                            <a href="{{ route('wizard.file_graph_table') }}" class="btn btn-success btn-lg">
                                                <i class="fa fa-table me-2"></i> Explore Table View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
                        @if(!empty($donut['data']) && array_sum($donut['data']) > 0)
                        <script>
                            (function() {
                                const ctx = document.getElementById('donutStorage');
                                if (!ctx) return;
                                const labels = {!! json_encode($donut['labels']) !!};
                                const data = {!! json_encode($donut['data']) !!};
                                const keys = {!! json_encode($donut['keys']) !!};

                                new Chart(ctx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            data: data,
                                            backgroundColor: ['#2c7be5','#6f42c1','#00a1f1','#107c10','#ffc107','#20c997'],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: { legend: { position: 'bottom' } },
                                        cutout: '60%',
                                        onClick: (e, elements) => {
                                            if (!elements.length) return;
                                            const idx = elements[0].index;
                                            const storageKey = keys[idx];
                                            window.location = "{{ route('wizard.files.list') }}" + "?storage=" + encodeURIComponent(storageKey);
                                        }
                                    }
                                });
                            })();
                        </script>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection