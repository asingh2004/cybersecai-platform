@extends('template')

@section('main')

<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
        <h4>Step 4: Please Select PII Data Type that Constitutes High Risk and Medium Risk for your Organisation</h4>
        <h5>Note: You can drag Data Types from One Column to Another!</h5>
        <form method="POST" action="{{ route('wizard.step4.post') }}" id="riskForm">
            @csrf

            <div class="row">
                <div class="col-6">
                    <h5>High Risk Types</h5>
                    <ul id="high-risk-list" class="list-group" style="min-height:200px;border:1px solid #eee;padding:8px;">
                        @php
                            $hi = old('high_risk_types') !== null ? old('high_risk_types')
                                : (!empty($selHigh) ? $selHigh : $high_risk);
                        @endphp
                        @foreach($hi as $item)
                            <li class="list-group-item" data-value="{{ $item }}">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-6">
                    <h5>Medium Risk Types</h5>
                    <ul id="medium-risk-list" class="list-group" style="min-height:200px;border:1px solid #eee;padding:8px;">
                        @php
                            $med = old('medium_risk_types') !== null ? old('medium_risk_types')
                                : (!empty($selMed) ? $selMed : $medium_risk);
                        @endphp
                        @foreach($med as $item)
                            <li class="list-group-item" data-value="{{ $item }}">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <input type="hidden" name="high_risk_types" id="highRiskInput">
            <input type="hidden" name="medium_risk_types" id="mediumRiskInput">
            <input type="hidden" name="pii_volume_category" id="piiVolumeCategory">

            @if ($errors->any())
                <div class="alert alert-danger my-2">
                    @foreach ($errors->all() as $msg) {{ $msg }} <br> @endforeach
                </div>
            @endif

            <div class="row mt-4">
                <div class="col-md-6">
                    <h5>PII Volume Category Thresholds</h5>
                    <table class="table table-bordered" style="width:auto;">
                        <thead>
                            <tr>
                                <th>PII Volume Category</th>
                                <th style="width:130px;">Count of Distinct PII Types</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $vt = $volumeThresholds ?? ['high'=>5, 'medium'=>3, 'low'=>1, 'none'=>0]; @endphp
                            <tr>
                                <td>High</td>
                                <td>
                                    <input type="number" class="form-control volume-threshold" id="vcatHigh" name="vcatHigh" value="{{ old('vcatHigh', $vt['high']) }}" min="0">
                                </td>
                            </tr>
                            <tr>
                                <td>Medium</td>
                                <td>
                                    <input type="number" class="form-control volume-threshold" id="vcatMedium" name="vcatMedium" value="{{ old('vcatMedium', $vt['medium']) }}" min="0">
                                </td>
                            </tr>
                            <tr>
                                <td>Low</td>
                                <td>
                                    <input type="number" class="form-control volume-threshold" id="vcatLow" name="vcatLow" value="{{ old('vcatLow', $vt['low']) }}" min="0">
                                </td>
                            </tr>
                            <tr>
                                <td>None</td>
                                <td>
                                    <input type="number" class="form-control volume-threshold" id="vcatNone" name="vcatNone" value="{{ old('vcatNone', $vt['none']) }}" min="0">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Next</button>
        </form>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            const high = document.getElementById('high-risk-list');
            const med = document.getElementById('medium-risk-list');
            const getValues = ul => Array.from(ul.children).map(li => li.dataset.value);

            function setHidden() {
                document.getElementById('highRiskInput').value = JSON.stringify(getValues(high));
                document.getElementById('mediumRiskInput').value = JSON.stringify(getValues(med));
            }
            function updateAll() {
                setHidden();
            }

            new Sortable(high, { group: 'risk-types', animation: 150, onSort: updateAll });
            new Sortable(med, { group: 'risk-types', animation: 150, onSort: updateAll });

            // Initial update on page load
            updateAll();
            document.getElementById('riskForm').addEventListener('submit', updateAll);
        </script>
        <style>
            .list-group-item { cursor: move; }
            #high-risk-list, #medium-risk-list { min-height: 200px; background: #fafbfc; }
            table.table th, table.table td { vertical-align: middle; }
        </style>
    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection