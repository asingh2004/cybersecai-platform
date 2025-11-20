@extends('template')
@section('main')

<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
                    
	<div class="margin-top-85">
    	<div class="row m-0">
        <!-- sidebar start-->
            @include('users.sidebar')
        <!--sidebar end-->

        <div class="col-md-10">
            <div class="main-panel min-height mt-4">
                <div class="row">
               
                  	<div class="col-md-3 pl-4 pr-4">
                        @include('openai.ai-sidebar')
    				</div>
                    
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <form method="post" action="{{ url('openai/configure') }}" accept-charset='UTF-8' id="openai_config_form">
                            {{ csrf_field() }}

                          	<div class="form-group">
                                <label for="name">Enter the name of your AI Assistant?</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                          
                            <div class="form-group col-md-12">
                                <label for="ai_template_type_id">Choose a base template to build your AI Assistant</label>
                                <select name="ai_template_type_id" id="ai_template_type_id" class="form-control text-16 mt-2" required>
                                    <option value="" disabled selected>Select an option</option>
                                    @foreach($aiTemplateTypes as $templateType)
                                        <option value="{{ $templateType->id }}">{{ $templateType->name }} - {{ $templateType->description }}</option>
                                    @endforeach
                                </select>
                            </div>

                            

                            <div class="form-group">
                                <label for="instructions">Instructions (optional)</label>
                                <textarea name="instructions" class="form-control"></textarea>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="ai_model_id">Choose an AI Model</label>
                                <select name="ai_model_id" id="ai_model_id" class="form-control text-16 mt-2" required>
                                    <option value="" disabled selected>Select a model</option>
                                    @foreach($aiModels as $model)
                                        <option value="{{ $model->id }}">{{ $model->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row float-right mt-5 mb-5">
                                <div class="col-md-12 pr-0">
                                    <button type="submit" class="btn vbtn-outline-success text-16 font-weight-700 pl-4 pr-4 pt-3 pb-3" id="btn_send">
                                        <i class="spinner fa fa-spinner fa-spin d-none"></i>
                                        <span id="btn_send-text">Save Configuration</span> 
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>

@push('scripts')
<script type="text/javascript">
    $(document).ready(function () {
        $('#openai_config_form').validate({
            submitHandler: function(form) {
                $("#btn_send").on("click", function(e) {	
                    $("#btn_send").attr("disabled", true);
                    e.preventDefault();
                });
                $(".spinner").removeClass('d-none');
                $("#btn_send-text").text("Saving ..");
                return true;
            }
        });
    });
</script>
@endpush
@endsection