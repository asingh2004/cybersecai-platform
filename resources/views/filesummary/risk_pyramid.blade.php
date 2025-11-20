@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h2><strong>Risk Pyramid Summary</strong></h2>
                        <p class="mb-3">Click a tier to drill into all files matching that risk for your account.</p>

                        @php
                            // Safe defaults
                            $riskCounts = $riskCounts ?? [];
                            $risksOrder = $risksOrder ?? ['High','Medium','Low','None'];
                            $byStorage = $byStorage ?? [];
                            $storageLabels = $storageLabels ?? [
                                'aws_s3'     => 'AWS S3',
                                'smb'        => 'SMB',
                                'onedrive'   => 'OneDrive',
                                'sharepoint' => 'SharePoint',
                            ];
                            $counts = [
                                'High'   => (int)($riskCounts['High'] ?? 0),
                                'Medium' => (int)($riskCounts['Medium'] ?? 0),
                                'Low'    => (int)($riskCounts['Low'] ?? 0),
                                'None'   => (int)($riskCounts['None'] ?? 0),
                            ];
                            $total = array_sum($counts);
                            $pct = function($v,$t){ return $t>0 ? round(100*$v/$t,1) : 0; };
                        @endphp

                        @if($total === 0)
                            <div class="alert alert-info">No risk data available yet.</div>
                        @endif

                        <div class="pyramid my-4">
                            <a href="{{ route('wizard.files.list', ['risk' => 'High']) }}" class="tier tier-high" aria-label="High Risk">
                                <div class="label">High</div>
                                <div class="meta">{{ number_format($counts['High']) }} ({{ $pct($counts['High'],$total) }}%)</div>
                            </a>
                            <a href="{{ route('wizard.files.list', ['risk' => 'Medium']) }}" class="tier tier-medium" aria-label="Medium Risk">
                                <div class="label">Medium</div>
                                <div class="meta">{{ number_format($counts['Medium']) }} ({{ $pct($counts['Medium'],$total) }}%)</div>
                            </a>
                            <a href="{{ route('wizard.files.list', ['risk' => 'Low']) }}" class="tier tier-low" aria-label="Low Risk">
                                <div class="label">Low</div>
                                <div class="meta">{{ number_format($counts['Low']) }} ({{ $pct($counts['Low'],$total) }}%)</div>
                            </a>
                            <a href="{{ route('wizard.files.list', ['risk' => 'None']) }}" class="tier tier-none" aria-label="No Risk">
                                <div class="label">No Risk</div>
                                <div class="meta">{{ number_format($counts['None']) }} ({{ $pct($counts['None'],$total) }}%)</div>
                            </a>
                        </div>

                        <div class="card shadow-sm mt-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Risk by Storage Type</h5>
                                @if(!empty($byStorage))
                                    <canvas id="stacked"></canvas>
                                    <div class="small text-muted mt-2">Tip: Click a bar segment to drill down by storage and risk.</div>
                                @else
                                    <div class="alert alert-light border mb-0">No storage data available.</div>
                                @endif
                            </div>
                        </div>

                        <style>
                            .pyramid .tier { display:block; color:#fff; text-decoration:none; margin:14px auto; width: 0; height: 0; border-left: 200px solid transparent; border-right: 200px solid transparent; position: relative; }
                            .pyramid .tier .label { position:absolute; top:-40px; left:50%; transform:translateX(-50%); font-weight:700; }
                            .pyramid .tier .meta  { position:absolute; top:-20px; left:50%; transform:translateX(-50%); font-size:0.9rem; }
                            .pyramid .tier-high   { border-bottom: 90px solid #dc3545; }
                            .pyramid .tier-medium { border-bottom: 90px solid #ffc107; }
                            .pyramid .tier-low    { border-bottom: 90px solid #0dcaf0; }
                            .pyramid .tier-none   { border-bottom: 90px solid #198754; }
                            .pyramid .tier:hover { opacity: 0.9; }
                            @media (max-width: 576px) {
                                .pyramid .tier { border-left-width: 140px; border-right-width: 140px; }
                            }
                        </style>

                        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
                        @if(!empty($byStorage))
                        <script>
                            (function() {
                                const storageMap = @json($byStorage);
                                const storageLabels = @json($storageLabels);
                                const risksOrder = @json($risksOrder);
                                const storages = Object.keys(storageMap);
                                if (!storages.length) return;

                                const colorByRisk = { High: '#dc3545', Medium: '#ffc107', Low: '#0dcaf0', None: '#198754' };
                                const datasets = risksOrder.map(risk => ({
                                    label: risk,
                                    backgroundColor: colorByRisk[risk] || '#6c757d',
                                    data: storages.map(s => (storageMap[s] && storageMap[s][risk]) ? storageMap[s][risk] : 0)
                                }));

                                const ctx = document.getElementById('stacked');
                                if (!ctx) return;

                                new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: storages.map(s => storageLabels[s] ?? s),
                                        datasets: datasets
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: { legend: { position: 'bottom' } },
                                        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
                                        onClick: (evt, elements) => {
                                            if (!elements.length) return;
                                            const el = elements[0];
                                            const storageKey = storages[el.index];
                                            const risk = datasets[el.datasetIndex].label;
                                            const url = "{{ route('wizard.files.list') }}" + "?storage=" + encodeURIComponent(storageKey) + "&risk=" + encodeURIComponent(risk);
                                            window.location = url;
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