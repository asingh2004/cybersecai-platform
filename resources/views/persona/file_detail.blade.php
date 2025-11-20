@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h2>{{ $file['file_name'] ?? '' }}</h2>
    <a href="{{ $file['web_url'] ?? '#' }}" target="_blank">View on SharePoint</a>
    <div class="mb-4">
        <b>Type:</b> {{ \Str::afterLast($file['file_type'] ?? '', '/') }}<br>
        <b>Last Modified:</b> {{ \Carbon\Carbon::parse($file['last_modified'])->toDayDateTimeString() }}<br>
        <b>Size:</b> {{ number_format($file['size_bytes']/1024) }} KB
    </div>
    <h4>
        Risk: 
        @php
            $risk = strtolower($file['compliance']['overall_risk']);
            $badge = 'badge-' . ($risk === 'high' ? 'high' : ($risk==='medium' ? 'medium' : ($risk==='low' ? 'low' : 'na')));
        @endphp
        <span class="badge {{ $badge }}">{{ $file['compliance']['overall_risk'] }}</span>
    </h4>
    <h5>Compliance Assessment</h5>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Standard</th>
                <th>Jurisdiction</th>
                <th>Data Types</th>
                <th>Risk</th>
            </tr>
        </thead>
        <tbody>
            @foreach($file['compliance']['summary_table'] as $ct)
            <tr>
                <td>{{ $ct['standard'] }}</td>
                <td>{{ $ct['jurisdiction'] }}</td>
                <td>{{ $ct['data_types'] }}</td>
                <td>{{ $ct['risk_rating'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <h5>Notes</h5>
    <div class="border p-2 bg-light">{{ $file['compliance']['notes'] }}</div>

    <hr>
    <h4 class="mt-5 mb-2">Permissions</h4>
    @include('persona.permissions_table', ['permissions'=>$file['permissions']])
</div></div></div></div></div></div>
@endsection

@section('styles')
<style>
.badge-high { background-color: #dc3545; }
.badge-medium { background-color: #ffc107; color:#222; }
.badge-low { background-color: #28a745; }
.badge-na { background-color: #6c757d; }
</style>
@endsection