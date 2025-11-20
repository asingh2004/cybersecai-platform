@extends('admin.template')

@section('main')
<div class="content-wrapper">
	<section class="content">
		<div class="row">
			<div class="col-md-3 settings_bar_gap">
				@include('admin.common.settings_bar')
			</div>

			<div class="col-md-9">
				<div class="nav-tabs-custom">
					<ul class="nav nav-tabs">
					<!--<ul class="cus nav" role="tablist">-->
						@php $fl = 0; @endphp

						@foreach($tab_names as $id => $name)
							@if($fl == 0)
								<!-- <li class="active"> -->
								<li class="nav-item nav-link">
									<a href="#{{$id}}" data-bs-toggle="tab">{{$name}}</a>
									<!-- <a class="nav-link active" aria-current="page" href="#{{$id}}">{{$name}}</a> -->
								</li>
								@php $fl=1; @endphp
							@else
								<li class="nav-item nav-link">
									<a href="#{{$id}}" data-bs-toggle="tab">{{$name}}</a>
									<!-- <a class="nav-link" href="#{{$id}}">{{$name}}</a> -->
								</li>
							@endif
						@endforeach
					</ul>

					<div class="tab-content">
						@php $fl = 0; @endphp

						@foreach($tab_forms as $a => $form)
							<div class="tab-pane {{ ($fl == 0)? 'active':''}}" id="{{$a}}">
								@if($a != 'banks')
                                    <form id="{{ $form['form_id'] ?? ''}}" method="post" action="{{ $form['action'] ?? ''}}" class="form-horizontal {{ $form['form_class'] ?? '' }}" {{ isset($form['form_type']) && $form['form_type'] == 'file'? "enctype=multipart/form-data":"" }}>
                                        {{ csrf_field() }}
                                        <div class="box-body">
                                            @foreach($form['fields'] as $field)
                                                @include("admin.common.fields.".$field['type'], ['field' => $field])
                                            @endforeach

                                            <div class="box-footer">
                                                <button type="submit" class="btn btn-info btn-space">Submit</button>
                                                <a class="btn btn-danger" href="{{ url()->previous() }}">Cancel</a>
                                            </div>
                                        </div>
                                    </form>
                                @else
                                    @include('admin.settings.banks')
                                @endif
							</div>
							@php $fl = 1; @endphp
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
@endsection
