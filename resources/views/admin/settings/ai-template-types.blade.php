@extends('admin.template')
@push('css')
<link href="{{ url('public/backend/css/preferences.css') }}" rel="stylesheet" type="text/css" />
@endpush
@section('main')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-md-3 settings_bar_gap">
          @include('admin.common.settings_bar')
        </div>
        <!-- right column -->
        <div class="col-md-9">
          <!-- Horizontal Form -->
          <div class="box box-info">
            <div class="box-header with-border">
              <h3 class="box-title">AI Template Type Form</h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form id="preferencesform" method="post" action="{{ url('admin/ai-template-types')}}" class="form-horizontal" enctype="multipart/form-data">
              {{ csrf_field() }}
              <div class="box-body">

                {{-- Name Field --}}
                <div class="form-group">
                  <label for="name" class="control-label col-sm-3">Name <span class="text-danger">*</span></label>
                  <div class="col-sm-6">
                    <input type="text" name="name" class="form-control" id="name" required>
                  </div>
                </div>

                {{-- Description Field --}}
                <div class="form-group">
                  <label for="description" class="control-label col-sm-3">Description (optional)</label>
                  <div class="col-sm-6">
                    <textarea name="description" class="form-control" id="description"></textarea>
                  </div>
                </div>

                {{-- API Endpoint Field --}}
                <div class="form-group">
                  <label for="api_endpoint" class="control-label col-sm-3">API Endpoint <span class="text-danger">*</span></label>
                  <div class="col-sm-6">
                    <input type="text" name="api_endpoint" class="form-control" id="api_endpoint" required>
                  </div>
                </div>

              </div>
              <!-- /.box-body -->
              <div class="box-footer">
                <button type="submit" class="btn btn-info">Submit</button>
                <a class="btn btn-danger" href="{{ url('admin/settings') }}">Cancel</a>
              </div>
              <!-- /.box-footer -->
            </form>
          </div>
          <!-- /.box -->

        </div>
        <!--/.col (right) -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
</div>

@endsection