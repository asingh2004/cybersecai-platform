@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h1>Compliance Dashboard</h1>

    <div class="row">
        <div class="col-md-9">
            <div class="row mb-4">
    <div class="col">
        <div class="card text-white bg-danger" style="cursor:pointer" onclick="filterTable('HIGH')">
            <div class="card-body">
                <h3 class="card-title">High Risk</h3>
                <p class="card-text" style="font-size: 2rem;">{{ $highRisk }}</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-white bg-warning" style="cursor:pointer" onclick="filterTable('MEDIUM')">
            <div class="card-body">
                <h3 class="card-title">Medium Risk</h3>
                <p class="card-text" style="font-size: 2rem;">{{ $mediumRisk }}</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-white bg-success" style="cursor:pointer" onclick="filterTable('LOW')">
            <div class="card-body">
                <h3 class="card-title">Low Risk</h3>
                <p class="card-text" style="font-size: 2rem;">{{ $lowRisk }}</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-white bg-secondary" style="cursor:pointer" onclick="filterTable('')">
            <div class="card-body">
                <h3 class="card-title">Total Files</h3>
                <p class="card-text" style="font-size: 2rem;">{{ $total }}</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-white bg-info" style="cursor:pointer" onclick="filterTable('BROAD')">
            <div class="card-body">
                <h3 class="card-title">Files<br>Broad Access</h3>
                <p class="card-text" style="font-size: 2rem;">{{ $broadAccessFiles }}</p>
            </div>
        </div>
    </div>
</div>
            <hr>
            @include('persona.compliance_table', ['files' => $files])
        </div>
    </div>
</div></div></div></div></div></div>


<script>
function filterTable(risk) {
    var rows = document.querySelectorAll('#file-compliance-table tbody tr');
    rows.forEach(function(row) {
        // "BROAD" is special: show rows that have "Visitor" in Permissions
        if (risk === 'BROAD') {
            let isBroad = false;
            row.querySelectorAll('td:last-child span').forEach(function(span){
                if(span.textContent && span.textContent.toLowerCase().includes('visitor'))
                    isBroad = true;
            });
            row.style.display = isBroad ? '' : 'none';
        } else if (!risk) {
            row.style.display = ''; // show all
        } else {
            row.style.display = (row.getAttribute('data-risk') === risk) ? '' : 'none';
        }
    });
}
</script>
@endsection

@section('styles')
<style>
.badge-high { background-color: #dc3545; }
.badge-medium { background-color: #ffc107; color:#222; }
.badge-low { background-color: #28a745; }
.badge-na { background-color: #6c757d; }
</style>
@endsection