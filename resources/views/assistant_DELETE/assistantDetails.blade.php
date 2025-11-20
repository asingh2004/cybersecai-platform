@extends('template')

@push('css')
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
  body,h1,h2,h3,h4,h5,h6 {font-family: "Raleway", sans-serif}

  body, html {
    height: 100%;
    line-height: 1.8;
  }

  /* Full height image header */
  .bgimg-1 {
    background-position: center;
    background-size: cover;
    background-image: url("{{ asset('public/front/images/bots/create_ai_hero.png') }}");
    min-height: 100%;
  }

  .w3-bar .w3-button {
    padding: 16px;
  }
</style>
@endpush

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')

                  	<div class="col-md-10 bgimg-1 w3-display-container w3-grayscale-min">
                    <div class="col-md-10">
                        <div class="main-panel min-height mt-4">
                            <div class="row">
                                <div class="col-md-3 pl-4 pr-4">
                                    @include('openai.ai-sidebar')
                                </div>
                    
                                <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                                    <form method="POST" id="openai_config_form">
                                        @csrf
                                        @method('PUT') <!-- Specify that this is a PUT request -->
                                        <input type="hidden" name="id" value="{{ $assistant->id }}"/> <!-- Hidden field to pass the ID -->

                                        <div class="form-group">
                                            <label for="name">Enter the name of your AI Assistant?</label>
                                            <input type="text" name="name" class="form-control" required value="{{ $assistant->name }}">
                                        </div>
                          
                                        <div class="form-group col-md-12">
                                            <label for="ai_template_type_id">Choose a base template to build your AI Assistant</label>
                                            <select name="ai_template_type_id" id="ai_template_type_id" class="form-control text-16 mt-2" required>
                                                <option value="" disabled>Select an option</option>
                                                @foreach($aiTemplateTypes as $templateType)
                                                    <option value="{{ $templateType->id }}" {{ $assistant->ai_template_type_id == $templateType->id ? 'selected' : '' }}>
                                                        {{ $templateType->name }} - {{ $templateType->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="instructions">Instructions (optional)</label>
                                            <textarea name="instructions" class="form-control" style="height: 280px;">{{ $assistant->instructions }}</textarea>
                                        </div>

                                        <div class="form-group col-md-12">
                                            <label for="ai_model_id">Choose an AI Model</label>
                                            <select name="ai_model_id" id="ai_model_id" class="form-control text-16 mt-2" required>
                                                <option value="" disabled>Select a model</option>
                                                @foreach($aiModels as $model)
                                                    <option value="{{ $model->id }}" {{ $assistant->ai_model_id == $model->id ? 'selected' : '' }}>
                                                        {{ $model->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-row float-right mt-5 mb-5">
                                            <div class="col-md-12 pr-0">
                                                <button type="submit" class="btn vbtn-outline-success text-16 font-weight-700 pl-4 pr-4 pt-3 pb-3" id="btn_send">
                                                    <span id="btn_send-text">Update</span> <!-- Update button text -->
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
  </div>
</div>
@endsection

@push('scripts')
<script type="text/javascript">
$(document).ready(function () {
    $('#openai_config_form').on('submit', function(e) {
        e.preventDefault(); // Prevent the default form submission

        // AJAX request
        $.ajax({
            url: "{{ url('openai/configure/'.$assistant->id) }}", // URL for the update
            type: 'POST', // Use POST for AJAX (because we're using PUT)
            data: $(this).serialize(), // Serialize the form data
            success: function (response) {
                // Handle success
                $('#btn_send-text').text('Updated Successfully'); // Change button text
                // Optionally show an alert or redirect, etc.
                // You can also show the success message here
                alert(response.message); // Assuming the response has a message
            },
            error: function (xhr) {
                // Handle errors here, e.g., validation errors
                alert('Error updating assistant. Please try again.'); // Or handle as needed
            }
        });
    });
});
</script>
@endpush