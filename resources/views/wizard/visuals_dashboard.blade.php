@extends('template')

<style>
ul.dashboard-list ul {
    list-style-type: disc;
    padding-left: 2em;
}
</style>

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    <!-- sidebar start-->
                    @include('users.sidebar')
                    <!--sidebar end-->
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                      <h2><strong>Explore Your Data: Visual Analysis Dashboard</strong></h2>
                        <p class="mb-4">
                            You can view your analyzed files and AI findings using four powerful visualizations:
                      </p>

                      <ul class="dashboard-list">
                          <li>
                              <strong>Risk Pyramid Summary:</strong>
                              <ul>
                                  <li>Color-coded pyramid visual grouping files by AI-assessed risk level: High, Medium, Low, No Risk</li>
                                  <li>Click a risk tier to see all corresponding files for the selected user</li>
                                  <li>Drill down to view detailed AI insights for each file</li>
                              </ul>
                              <div class="d-flex flex-column align-items-center justify-content-center" style="max-width:500px;margin:3em auto 0 auto;">
                                  <a href="{{ route('wizard.filesummary_pyramid') }}" style="text-decoration: none;width:100%;">
                                      <button class="btn btn-warning btn-lg dashboard-btn">
                                          <i class="fa fa-layer-group"></i>
                                          Risk Pyramid Summary
                                      </button>
                                  </a>
                              </div>
                          </li>
                        
                        
                          <li>
                              <strong>Table View (Sortable/Advanced):</strong>
                              <ul>
                                  <li>Searchable and sortable table of all file details</li>
                                  <li>Quickly access full AI analysis for any document</li>
                              </ul>
                              <div class="d-flex flex-column align-items-center justify-content-center" style="max-width:500px;margin:3em auto 0 auto;">
                                  <a href="{{ route('wizard.file_graph_table') }}" style="text-decoration: none;width:100%;">
                                      <button class="btn btn-success btn-lg dashboard-btn mb-4">
                                          <i class="fa fa-table"></i>
                                          Explore Table View
                                      </button>
                                  </a>
                              </div>
                          </li>


                          
                      </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Font Awesome for icons; you can remove if not needed -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
.dashboard-btn {
    width:100%;
    font-size:2em;
    min-height:90px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:0.7em;
    box-shadow: 0 0 8px rgba(0,0,0,0.09);
    font-weight:600;
}
.dashboard-btn i {
    font-size:1.25em;
    margin-right: 0.4em;
}
</style>
@endsection