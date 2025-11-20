@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
        <a href="{{ route('wizard.dashboard') }}" class="btn btn-secondary mb-3">&larr; Back to Dashboard</a>
        <h2>Configuration #{{ $config->id }}</h2>
        <h5>Created: {{ $config->created_at }}, Updated: {{ $config->updated_at }}</h5>

        <h6>Sources:</h6>
        @foreach($config->data_sources ?? [] as $src)
            <span class="badge bg-info text-dark">{{ $src }}</span>
        @endforeach

        <h6 class="mt-2">Regulations:</h6>
        @foreach($config->regulations ?? [] as $reg)
            <span class="badge bg-secondary">{{ $reg }}</span>
        @endforeach

        <h6 class="mt-2">Metadata:</h6>
        <pre style="background:#f8f9fa">{{ json_encode($config->metadata, JSON_PRETTY_PRINT) }}</pre>
        
        <h6>Risk Types:</h6>
        <pre style="background:#f8f9fa">{{ json_encode($config->risk_types, JSON_PRETTY_PRINT) }}</pre>
        
        <h6>PII Volume Thresholds:</h6>
        @if(is_array($config->pii_volume_thresholds) && count($config->pii_volume_thresholds))
            <table class="table table-bordered" style="width:auto;max-width:450px;">
                <thead>
                <tr>
                    <th>Category</th>
                    <th>Threshold (# Distinct Types)</th>
                </tr>
                </thead>
                <tbody>
                @foreach($config->pii_volume_thresholds as $category => $threshold)
                    <tr>
                        <td class="text-capitalize">{{ $category }}</td>
                        <td>{{ $threshold }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <span class="text-muted">No PII Volume Thresholds set.</span>
        @endif

        <h6>PII Volume Category:</h6>
        <strong>
            @if(!empty($config->pii_volume_category))
                <span class="badge 
                    @if($config->pii_volume_category === 'High') bg-danger
                    @elseif($config->pii_volume_category === 'Medium') bg-warning text-dark
                    @elseif($config->pii_volume_category === 'Low') bg-info text-dark
                    @else bg-secondary
                    @endif"
                >
                    {{ $config->pii_volume_category }}
                </span>
            @else
                <span class="text-muted">--</span>
            @endif
        </strong>

        <h6 class="mt-2">API Config:</h6>
        <pre style="background:#f8f9fa">{{ json_encode($config->api_config, JSON_PRETTY_PRINT) }}</pre>
    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection