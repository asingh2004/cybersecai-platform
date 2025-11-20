@extends('template')

@push('styles')
<style>
    .date-sm { font-size: 0.85em; color: #555; }
    .btn-xxl { font-size: 1.6rem; padding: 1rem 2.5rem; }
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
                        <h1><strong>My Data Configurations</strong></h1>
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <form action="{{ route('wizard.start') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-lg mb-3">+ Add New Data Source</button>
                        </form>

                        <div id="classification-msg" class="mb-3"></div>

                        @if($configs->isEmpty())
                            <p>You have not created any configurations yet.</p>
                        @else
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Sources</th>
                                        <th>Regulations</th>
                                        <th class="text-center">Applicable Policy</th>
                                        <th class="text-center">Configure SIEM</th>
                                        <th class="text-center">Configuration Status</th>
                                        <th colspan="2">Actions</th>
                                        <th class="text-center">Discover Files</th>

                                    </tr>
                                </thead>
                                <tbody>
@foreach($configs as $i => $config)
    <tr>
        <td>{{ $i+1 }}</td>
        <td>
            @php
                $sources = $config->data_sources;
                $sources_list = [];
                if (is_string($sources)) {
                    $j = @json_decode($sources, true);
                    $sources_list = is_array($j) ? $j : [$sources];
                } elseif (is_array($sources)) {
                    $sources_list = $sources;
                }
                $primarySource = $sources_list[0] ?? '';
                $dbSources = [
                    'mysql', 'mariadb', 'oracle', 'sql server', 'sqlserver', 'fabric'
                ];
                $isDBConfig = false;
                foreach($dbSources as $dbsrc) {
                    if(\Illuminate\Support\Str::contains(strtolower($primarySource), $dbsrc)) {
                        $isDBConfig = true;
                        break;
                    }
                }
            @endphp
            @foreach($sources_list as $src)
                <span class="badge bg-info text-dark">{{ $src }}</span>
            @endforeach
        </td>
        <td>
            @php
                $regs = $config->regulations;
                if (is_string($regs)) {
                    $regs = json_decode($regs, true);
                }
            @endphp
            @if(is_array($regs))
                @foreach($regs as $reg)
                    @if(is_string($reg))
                        <span class="badge bg-secondary">{{ $reg }}</span>
                    @elseif(is_array($reg) && isset($reg['standard']))
                        <span class="badge bg-secondary">{{ $reg['standard'] }}</span>
                    @endif
                @endforeach
            @elseif(is_string($regs))
                <span class="badge bg-secondary">{{ $regs }}</span>
            @endif
        </td>
        <td class="text-center align-middle">
            <a href="{{ route('cybersec_policy.edit', $config->id) }}" class="btn btn-sm btn-outline-secondary">Edit Policy</a>
        </td>
        <td class="text-center align-middle">
             <a href="{{ route('cybersecai_siem.edit', $config->id) }}" class="btn btn-sm btn-outline-secondary">Configure SIEM</a>
        </td>
        <td class="text-center align-middle">
            @if($config->status === 'complete')
                <span class="badge bg-success">Complete</span>
            @else
                <span class="badge bg-warning text-dark">In Progress</span>
            @endif
        </td>
        <td class="text-center align-middle">
            <a href="{{ route('wizard.edit', $config->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        </td>
        <td class="text-center align-middle">
            <form method="POST" action="{{ route('wizard.destroy', $config->id) }}" onsubmit="return confirm('Are you sure?');">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
        </td>
        <td class="text-center align-middle">
            @if(!$isDBConfig)
            <button
                class="btn btn-sm btn-warning"
                id="discover-btn-{{ $config->id }}"
                data-source="{{ $primarySource }}"
                onclick="startDiscovery({{ $config->id }}, this)"
            >Start Discovery</button>
            @endif
        </td>

    </tr>
@endforeach
                                </tbody>
                            </table>

@push('scripts')
<script>
function classifyDatabase(configId, btn) {
    btn.disabled = true;
    btn.innerText = "Classifying...";
    var msg = document.getElementById('classification-msg');
    msg.innerHTML = '<span class="text-info">Database privacy discovery for config ' + configId + ' started. This may take several minutes.</span>';
    fetch(`/wizard/classify-database/${configId}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(function(data) {
      	console.log('Classify DB response:', data);
        if (data.success) {
            msg.innerHTML = '<span class="text-success">Database privacy mapping/classification for config ' + configId + ' complete!</span>';
            btn.innerText = "Classified";
            btn.classList.remove('btn-success');
            btn.classList.add('btn-secondary');
        } else {
            msg.innerHTML = '<span class="text-danger">Error: ' + (data.err || 'unknown error') + '</span>';
            btn.disabled = false;
            btn.innerText = "Classify Database";
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-success');
        }
    })
    .catch(function(e) {
        msg.innerHTML = '<span class="text-danger">Error: ' + e + '</span>';
        btn.disabled = false;
        btn.innerText = "Classify Database";
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-success');
    });
}
function startDiscovery(configId, btn) {
    var source = btn.getAttribute('data-source') || '';
    let route = getDiscoveryRoute(source, configId);
    btn.disabled = true;
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-secondary');
    btn.innerText = "Files Discovered";
    var msg = document.getElementById('classification-msg');
    msg.innerHTML = '<span class="text-info">Discovery for config ' + configId + ' started. This is running in background; you can now use other screens.</span>';
    if (route) {
        fetch(route, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
    } else {
        msg.innerHTML = '<span class="text-danger">Storage type not supported for discovery!</span>';
    }
}
function getDiscoveryRoute(source, id) {
    source = (source || '').toLowerCase();
    if (source.includes('m365') || (source.includes('onedrive') && source.includes('sharepoint'))) return `/wizard/classify-files-m365/${id}`;
    if (source.includes('smb')) return `/wizard/classify-files-smb/${id}`;
    if (source.includes('nfs')) return `/wizard/classify-files-nfs/${id}`;
    if (source.includes('s3')) return `/wizard/classify-files-s3/${id}`;
    if (source.includes('google') || source.includes('gdrive') || source.includes('drive')) return `/wizard/classify-files-gdrive/${id}`;
    return '';
}
function getClassifyRoute(source, id) {
    source = (source || '').toLowerCase();
    if (source.includes('m365') || (source.includes('onedrive') && source.includes('sharepoint'))) return `/wizard/start-classifying/${id}`;
    if (source.includes('smb')) return `/wizard/start-classifying-smb/${id}`;
    if (source.includes('nfs')) return `/wizard/start-classifying-nfs/${id}`;
    if (source.includes('s3')) return `/wizard/start-classifying-s3/${id}`;
    if (source.includes('google') || source.includes('gdrive') || source.includes('drive')) return `/wizard/start-classifying-gdrive/${id}`;
    return '';
}
function startClassifying(configId, btn) {
    var source = btn.getAttribute('data-source') || '';
    let route = getClassifyRoute(source, configId);
    btn.disabled = true;
    btn.classList.remove('btn-success');
    btn.classList.add('btn-secondary');
    btn.innerText = "Classifying";
    var msg = document.getElementById('classification-msg');
    msg.innerHTML = '<span class="text-info">Classification for config ' + configId + ' started. You may now continue working elsewhere. This process will continue in background.</span>';
    if(route) {
        fetch(route, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
    } else {
        msg.innerHTML = '<span class="text-danger">Storage type not supported for classification!</span>';
    }
}
function goToVisualsDashboard() {
    window.location.href = "{{ route('wizard.visuals_dashboard') }}";
}
</script>
@endpush

                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection