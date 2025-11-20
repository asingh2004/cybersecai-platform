@extends('template')

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

        <div class="col-lg-10">
            <div class="main-panel">
                <div class="container-fluid min-height">
                    <div class="row">
                        <div class="col-md-12 p-0 mb-3">
                            <div class="list-bacground mt-4 rounded-3 p-4 border">
                                <span class="text-18 pt-4 pb-4 font-weight-700">{{ trans('messages.listing_basic.listing') }}</span>

                                <div class="float-right">
                                    <div class="d-flex gap-3 mt-4">
                                        <div class="pr-4">
                                            <span class="text-14 pt-2 pb-2 font-weight-700">{{ trans('messages.users_dashboard.sort_by') }}</span>
                                        </div>

                                        <div>
                                            <form action="{{ url('/properties') }}" method="POST" id="listing-form">
                                                {{ csrf_field() }}
                                                <select class="form-control text-14 minus-mt-6" id="listing_select" name="status">
                                                    <option value="All" {{ @$status == "All" ? ' selected="selected"' : '' }}>{{ trans('messages.filter.all') }}</option>
                                                    <option value="Listed" {{ @$status == "Listed" ? ' selected="selected"' : '' }}>{{ trans('messages.property.listed') }}</option>
                                                    <option value="Unlisted" {{ @$status == "Unlisted" ? ' selected="selected"' : '' }}>{{ trans('messages.property.unlisted') }}</option>
                                                </select>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-success d-none" role="alert" id="alert">
                        <span id="messages"></span>
                    </div>

                    <div id="products" class="row mt-3">
                        @forelse($assistants as $assistant)
                            <div class="col-md-12 p-0 mb-4">
                                <div class="row border p-2 rounded-3">
                                    <div class="col-md-3 col-xl-4 p-2">
                                        <div class="img-event">
                                          	@if($assistant->ai_template_type_id == 4)
                                            	<a href="assistants/{{ $assistant->id }}">
                                              		<img class="room-image-container200 rounded" src="{{ asset('public/front/images/bots/web_blogs_summariser_bot.png') }}" alt="{{ $assistant->name ?? 'Image' }}">
                                            	</a>
                                          	@elseif($assistant->ai_template_type_id == 3)
                                          		<a href="assistants/{{ $assistant->id }}">
                                              		<img class="room-image-container200 rounded" src="{{ asset('public/front/images/bots/pdf_summarizer_bot2.png') }}" alt="{{ $assistant->name ?? 'Image' }}">
                                            	</a>
                                          	@elseif($assistant->ai_template_type_id == 1)
                                          		<a href="assistants/{{ $assistant->id }}">
                                              		<img class="room-image-container200 rounded" src="{{ asset('public/front/images/bots/completion_bot.png') }}" alt="{{ $assistant->name ?? 'Image' }}">
                                            	</a>
                                          	@else
                                          		<a href="assistants/{{ $assistant->id }}">
                                              		<img class="room-image-container200 rounded" src="{{ asset('public/front/images/bots/assistant_bot4.png') }}" alt="{{ $assistant->name ?? 'Image' }}">
                                            	</a>
                                          	@endif
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-xl-6 p-2">
                                        <div>
                                            @if($assistant->ai_template_type_id == 4)
                                                <a href="{{ route('web.blog.summarizer') }}">
                                                    <p class="mb-0 text-18 text-color font-weight-700 text-color-hover">{{ ($assistant->name == '') ? 'Unnamed Assistant' : $assistant->name }}</p>
                                                </a>
                                            @elseif($assistant->ai_template_type_id == 3)
    											<a href="{{ route('pdf.index', ['id' => $assistant->id]) }}">
        											<p class="mb-0 text-18 text-color font-weight-700 text-color-hover">{{ ($assistant->name == '') ? 'Unnamed Assistant' : $assistant->name }}</p>
    											</a>
                                            @elseif($assistant->ai_template_type_id == 1)
                                                <a href="{{ route('assistant.completionTemplate', ['id' => $assistant->id]) }}">
                                                    <p class="mb-0 text-18 text-color font-weight-700 text-color-hover">{{ ($assistant->name == '') ? 'Unnamed Assistant' : $assistant->name }}</p>
                                                </a>
                                            @elseif($assistant->ai_template_type_id == 2)
                                                <a href="{{ route('assistant.template', ['id' => $assistant->id]) }}">
                                                    <p class="mb-0 text-18 text-color font-weight-700 text-color-hover">{{ ($assistant->name == '') ? 'Unnamed Assistant' : $assistant->name }}</p>
                                                </a>
                                            @else
                                                <p class="mb-0 text-18 text-color font-weight-700 text-color-hover">{{ ($assistant->name == '') ? 'Unnamed Assistant' : $assistant->name }}</p>
                                            @endif
                                        </div>

                                        <div class="text-14 mt-3 text-muted mb-0">
                                          <p class="text-14 mt-3 text-muted mb-0"></p>
                                                <button class="accordion" data-id="{{ $assistant->id }}">
                                                  <i class="fas fa-comments"></i>
                                                    {{ \Illuminate\Support\Str::limit($assistant->instructions ?? 'No instructions available', 50, '...') }}
                                                </button>
                                                <div class="panel">
                                                  <p>{{ $assistant->instructions }}</p>
                                                </div>
                                            
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <span>Status: <strong>{{ $assistant->status }}</strong></span>
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-xl-2">
                                        <div class="d-flex w-100 h-100 mt-3 mt-sm-0 p-2">
                                            <div class="align-self-center w-100">
                                                <div class="row">
                                                    <div class="col-6 col-sm-12">
                                                        <div class="main-toggle-switch text-left text-sm-center">
                                                            <label class="toggleSwitch large">
                                                                <input type="checkbox" class="status-toggle" data-id="{{ $assistant->id }}" data-status="{{ $assistant->status }}" {{ $assistant->status == "Listed" ? 'checked' : '' }}/>
                                                                <span>
                                                                    <span>{{ trans('messages.property.unlisted') }}</span>
                                                                    <span>{{ trans('messages.property.listed') }}</span>
                                                                </span>
                                                                <a href="#" aria-label="toggle"></a>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div class="col-6 col-sm-12">
                                                        <a href="{{ url('assistants/'.$assistant->id.'/basics') }}">
                                                            <div class="text-color text-color-hover text-14 text-right text-sm-center mt-0 mt-md-3 p-2">
                                                                <i class="fas fa-edit"></i>
                                                                Manage AI Assistant
                                                            </div>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="row justify-content-center position-center w-100 p-4 mt-4">
                                <div class="text-center w-100">
                                    <p class="text-center">{{ trans('messages.message.empty_listing') }}</p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <div class="row justify-content-between overflow-auto pb-3 mt-4 mb-5">
                        {{ $assistants->links('paginate') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop

@push('scripts')
<script type="text/javascript">
	var acc = document.getElementsByClassName("accordion");
	var i;

	for (i = 0; i < acc.length; i++) {
  	acc[i].addEventListener("click", function() {
    	this.classList.toggle("active");
    	var panel = this.nextElementSibling;
    	if (panel.style.maxHeight) {
      		panel.style.maxHeight = null;
    	} else {
      	panel.style.maxHeight = panel.scrollHeight + "px";
    	} 
  	});
	}

    $(document).ready(function() { 
        $(document).on('change', '.status-toggle', function() {
            var id = $(this).data('id');
            var newStatus = $(this).is(':checked') ? 'Listed' : 'Unlisted'; // Determine new status based on checkbox state
            var dataURL = APP_URL + '/assistants/update_status'; // Adjust URL for updating assistant status

            $('#messages').empty();
            $.ajax({
                url: dataURL,
                method: 'POST',
                data: {
                    "_token": "{{ csrf_token() }}",
                    'id': id,
                    'status': newStatus
                },
                success: function(data) {
                    $("#messages").empty();
                    $("#alert").removeClass('d-none');
                    $("#messages").append(data.name + " has been updated to " + data.status + ".");
                    var header = $('#alert');
                    setTimeout(function() {
                        header.addClass('d-none');
                    }, 4000);
                },
                error: function(xhr) {
                    alert('Error updating status. Please try again.');
                    $(this).prop('checked', !$(this).is(':checked')); // Restore checkbox state if error occurs
                }
            });
        });
    });
</script>

<style>
    /* Style the buttons that are used to open and close the accordion panel */
    .accordion {
  background-color: #eee;
  color: #444;
  cursor: pointer;
  padding: 18px;
  width: 100%;
  border: none;
  text-align: left;
  outline: none;
  font-size: 15px;
  transition: 0.4s;
}

.active, .accordion:hover {
  background-color: #ccc;
}

.accordion:after {
  content: '\002B';
  color: #777;
  font-weight: bold;
  float: right;
  margin-left: 5px;
}

.active:after {
  content: "\2212";
}

.panel {
  padding: 0 18px;
  background-color: white;
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.2s ease-out;
}
</style>
@endpush