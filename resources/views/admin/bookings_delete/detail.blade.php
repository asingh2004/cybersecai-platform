	@extends('admin.template')

	@section('main')
	<div class="content-wrapper">
		<section class="content">
			<div class="row">
				<div class="col-md-8 col-sm-offset-2">
					<div class="box box-info box_info">
						<div class="box-header with-border">
							<h3 class="box-title">Booking Details</h3>
						</div>

						<form action="{{ url('admin/bookings/detail/'.$result->id) }}" method="post" class='form-horizontal'>
							{{ csrf_field() }}
							<div class="box-body">
								<div class="form-group">
									<label class="col-sm-3 control-label">
										Property name
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ $result->properties->name }}
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-3 control-label">
										Host name
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ ucfirst($result->properties->users->first_name) }}
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-3 control-label">
										Guest name
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ ucfirst($result->users->first_name) }}
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-3 control-label">
										Checkin
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ onlyFormat($result->start_date) }}
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-3 control-label">
										Checkout
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ onlyFormat($result->end_date) }}
									</div>
								</div>




								<div class="form-group">
									<label class="col-sm-3 control-label">
									Total nights
									</label>
									<div class="col-sm-6 col-sm-offset-1">
									{{ $result->total_night }}
									</div>
								</div>



							

								<div class="form-group">
											
													
									<div class="col-sm-3 control-label">	
										<b>{{trans('messages.property_single.service_fee')}}</b>
									</div>
							
									<div class="col-sm-6 col-sm-offset-1">
									{!! moneyFormat($result->currency->org_symbol, $result->original_service_charge) !!}
									</div>
								</div>

						
								<div class="form-group">
								<div class="col-sm-3 control-label">	
										<b>{{trans('messages.property_single.iva_tax')}}</b>
									</div>

									<div class="col-sm-6 col-sm-offset-1">
										{!! moneyFormat($result->currency->org_symbol, $result->iva_tax) !!}

									</div>
								</div>



								<div class="form-group">
								
									<div class="col-sm-3 control-label">	
										<b>{{trans('messages.property_single.total')}}</b>
									</div>
									<div class="col-sm-6 col-sm-offset-1">
										<b>{!! moneyFormat($result->currency->org_symbol, $result->original_total) !!}</b>
									</div>
								</div>

							
								<div class="form-group">
									<label class="col-sm-3 control-label">
										Security fee (if applicable)
									</label>

									<div class="col-sm-6 col-sm-offset-1">
									{!! moneyFormat($result->currency->org_symbol, $result->original_security_money) !!}
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-3 control-label">
										Paymode
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ @$result->payment_methods->name }}
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-3 control-label">
										Status
									</label>
									<div class="col-sm-6 col-sm-offset-1">
										{{ $result->status }}
									</div>
								</div>

								@if($result->status == "Cancelled")
									<div class="form-group">
										<label class="col-sm-3 control-label">
										Cancelled By
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{ $result->cancelled_by }}
										</div>
									</div>

									<div class="form-group">
										<label class="col-sm-3 control-label">
										Cancelled Date
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{ dateFormat($result->cancelled_at) }}
										</div>
									</div>
								@endif

								@if($result->transaction_id != '' && $result->transaction_id != ' ')
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">
                                            Transaction ID
                                        </label>
                                        <div class="col-sm-6 col-sm-offset-1">
                                            {{ $result->transaction_id }}
                                        </div>
                                    </div>
                                @endif


                                @if($result->bank_id)

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">
                                            Bank Account Name
                                        </label>
                                        <div class="col-sm-6 col-sm-offset-1">
                                            {{ ($result->bank && $result->bank->account_name) ? $result->bank->account_name : 'Not Found' }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">
                                            Bank Account No.
                                        </label>
                                        <div class="col-sm-6 col-sm-offset-1">
                                            {{ ($result->bank && $result->bank->iban) ? $result->bank->iban : 'Not Found' }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">
                                            Bank Name
                                        </label>
                                        <div class="col-sm-6 col-sm-offset-1">
                                            {{ ($result->bank && $result->bank->bank_name) ? $result->bank->bank_name : 'Not Found' }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">
                                            Attached
                                        </label>
                                        <div class="col-sm-6 col-sm-offset-1">
                                            <a href="{{$result->attachment}}" target="_blank" class="btn btn-outline-info">
                                                <i class="fa fa-cloud-download" aria-hidden="true"></i>
                                                Download
                                            </a>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">
                                            Payment Note
                                        </label>
                                        <div class="col-sm-6 col-sm-offset-1">
                                            {{ ($result->bank && $result->note) ? $result->note : '' }}
                                        </div>
                                    </div>
                                    @if(($result->status == 'Pending' && $result->booking_type == 'instant') || $result->status ==  'Processing')
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">
                                                Confirm Booking?
                                            </label>
                                            <div class="col-sm-6 col-sm-offset-1">
                                                <div class="d-flex">
                                                    <a href="{{url('/admin/bookings/edit/confirm/'.$result->id)}}" class="btn btn-lg text-white btn-success vbtn-outline-success">Accept</a>
                                                    <a href="{{url('/admin/bookings/edit/decline/'.$result->id)}}" class="btn btn-lg text-white btn-danger vbtn-outline-success">Decline</a>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif

								@if($result->paymode == 'Credit Card')
									<div class="form-group">
										<label class="col-sm-3 control-label">
										First name
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{ $result->first_name }}
										</div>
									</div>

									<div class="form-group">
										<label class="col-sm-3 control-label">
										Last name
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{ $result->last_name }}
										</div>
									</div>

									<div class="form-group">
										<label class="col-sm-3 control-label">
										Postal code
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{ $result->postal_code }}
										</div>
									</div>
								@endif

								@if($result->host_account != '')
									<div class="form-group">
										<label class="col-sm-3 control-label">
										Host Paypal account
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{ $result->host_account }}
										</div>
									</div>
								@endif

								@if(@$bank->account_number != '')
									<div class="form-group">
										<label class="col-sm-3 control-label">
										Host Bank account
										</label>
										<div class="col-sm-6 col-sm-offset-1">
										{{$bank->account_number  }}
										</div>
									</div>
								@endif

								@if($result->guest_account != '')
									<div class="form-group">
										<label class="col-sm-3 control-label">
											Guest Paypal account
										</label>
										<div class="col-sm-6 col-sm-offset-1">
											{{ $result->guest_account }}
										</div>
									</div>
								@endif

								@if($result->host_penalty_amount != 0)
									<div class="form-group">
										<label class="col-sm-3 control-label">
											Applied Penalty Amount
										</label>
										<div class="col-sm-6 col-sm-offset-1">
											{!! $result->currency->org_symbol !!}{{ $result->host_penalty_amount }}
										</div>
									</div>
								@endif

								@if(@$penalty_amount != 0)
									<div class="form-group">
										<label class="col-sm-3 control-label">
											Subtracted Penalty Amount
										</label>
										<div class="col-sm-6 col-sm-offset-1">
											{!! $result->currency->org_symbol !!}{{ @$penalty_amount }}
										</div>
									</div>
								@endif
							</div>
						</form>


						<div class="box-footer text-center">
							<a class="btn btn-default" href="{{ url('admin/bookings') }}">Back</a>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
	@push('scripts')
	<script  type="text/javascript">
	$('#input_dob').datepicker({ 'format': 'dd-mm-yyyy'});
	</script>
	@endpush
	@stop
