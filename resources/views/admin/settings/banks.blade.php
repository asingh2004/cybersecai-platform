<div class="box box_info">
    <div class="box-header">
        <h3 class="box-title">{{trans('messages.account_preference.banks')}}</h3>
        <!-- <div class="pull-right"><button class="btn btn-success modal-admin">{{trans('messages.account_preference.add_new_bank')}}</button></div> -->
  
         <div class="col-md-12">
                    <div class="pull-right"><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add_modal">
                            <i class="fa fa-plus"></i> {{trans('messages.account_preference.add_new_bank')}}
                    </button>
        </div>
    </div><hr>
    <div class="box-body">
        {!! $dataTable->table() !!}
    </div>
</div>



      


{{--New bank account create model--}}
<!-- <div class="modal fade d-none z-index-high" id="add_modal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true"> -->
    <div class="modal fade" id="add_modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans('messages.account_preference.add_new_bank') }}</h4>
                <button type="button" id="exampleModalLabel" class="cls-reload" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                
            </div>
            <form method="post" action="{{ url('admin/settings/bank/add/')}}" class='form-horizontal' enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="modal-body">
                    <p class="calendar-m-msg"  id="model-message"></p>
                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.bank_holder') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="account_name" value="{{old('account_name', '')}}" required aria-required="true" placeholder="{{ trans('messages.account_preference.bank_holder') }}">
                            <span class="text-danger">{{ $errors->first('account_name') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.bank_account_num') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="iban" required aria-required="true" value="{{old('iban', '')}}" placeholder="{{ trans('messages.account_preference.bank_account_num') }}">
                            <span class="text-danger">{{ $errors->first('iban') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.swift_code') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="swift_code" value="{{old('swift_code', '')}}" placeholder="{{ trans('messages.account_preference.swift_code') }}">
                            <span class="text-danger">{{ $errors->first('swift_code') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.bank_name') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="bank_name" value="{{old('bank_name', '')}}" required aria-required="true" placeholder="{{ trans('messages.account_preference.bank_name') }}">
                            <span class="text-danger">{{ $errors->first('bank_name') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.branch_name') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="branch_name" value="{{old('branch_name', '')}}" placeholder="{{ trans('messages.account_preference.branch_name') }}">
                            <span class="text-danger">{{ $errors->first('branch_name') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.routing_no') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="routing_no" value="{{old('routing_no', '')}}" placeholder="{{ trans('messages.account_preference.routing_no') }}">
                            <span class="text-danger">{{ $errors->first('routing_no') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.branch_city') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="branch_city" value="{{old('branch_city', '')}}" placeholder="{{ trans('messages.account_preference.branch_city') }}">
                            <span class="text-danger">{{ $errors->first('branch_city') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.branch_address') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="branch_address" value="{{old('branch_address', '')}}" placeholder="{{ trans('messages.account_preference.branch_address') }}">
                            <span class="text-danger">{{ $errors->first('branch_address') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description" class="control-label col-sm-3">{{ trans('messages.photo_details.description') }}</label>
                        <div class="col-sm-8">
                            <textarea type="text" class="form-control" rows="3" name="description" placeholder="{{ trans('messages.photo_details.description') }}">{{old('description', '')}}</textarea>
                            <span class="text-danger">{{ $errors->first('description') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.logo') }}</label>
                        <div class="col-sm-8">
                            <input type="file" class="form-control" name="logo" placeholder="{{ trans('messages.account_preference.branch_address') }}">
                            <span class="text-muted">{{ trans('messages.account_preference.logo_limit') }}</span><br><span class="text-danger">{{ $errors->first('logo') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.country') }}</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="country">
                                @foreach($countries as $id => $name)
                                    <option
                                        value="{{$name}}" {{old('country', '') == $name ? 'selected' : ''}}>{{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-danger">{{ $errors->first('country') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="inputEmail3">{{ trans('messages.account_preference.status') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="status" required aria-required="true" class="form-control">
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <span class="text-danger">{{ $errors->first('status') }}</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-info pull-right" style="margin-left: 20px" type="submit" name="submit">{{trans('messages.listing_calendar.submit')}}</button>
                    <button type="button" class="btn btn-default cls-reload" data-bs-dismiss="modal">{{trans('messages.listing_calendar.close')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{--Edit selected bank account--}}
<!-- <div class="modal fade d-none z-index-high" id="edit_modal" role="dialog"> -->
<div class="modal fade" id="edit_modal" tabindex="-1" aria-labelledby="exampleModalLabel2" aria-hidden="true">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans('messages.account_preference.edit_bank') }}</h4>
                <button type="button" id="exampleModalLabel2" class="cls-reload" data-bs-dismiss="modal">&times;</button>
                
            </div>
            <form method="post" class="form-horizontal" id="edit_form" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="modal-body">
                    <p class="calendar-m-msg"  id="model-message"></p>
                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.bank_holder') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" id="name" class="form-control" name="account_name" required aria-required="true" placeholder="{{ trans('messages.account_preference.bank_holder') }}">
                            <span class="text-danger">{{ $errors->first('account_name') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.bank_account_num') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" id="iban" class="form-control" name="iban" required aria-required="true" placeholder="{{ trans('messages.account_preference.bank_account_num') }}">
                            <span class="text-danger">{{ $errors->first('iban') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.swift_code') }}</label>
                        <div class="col-sm-8">
                            <input type="text" id="swift" class="form-control" name="swift_code" placeholder="{{ trans('messages.account_preference.swift_code') }}">
                            <span class="text-danger">{{ $errors->first('swift_code') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.bank_name') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="bank" name="bank_name" required aria-required="true" placeholder="{{ trans('messages.account_preference.bank_name') }}">
                            <span class="text-danger">{{ $errors->first('bank_name') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.branch_name') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="branch" name="branch_name" placeholder="{{ trans('messages.account_preference.branch_name') }}">
                            <span class="text-danger">{{ $errors->first('branch_name') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.routing_no') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="route" name="routing_no" placeholder="{{ trans('messages.account_preference.routing_no') }}">
                            <span class="text-danger">{{ $errors->first('routing_no') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.branch_city') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="br_city" name="branch_city" placeholder="{{ trans('messages.account_preference.branch_city') }}">
                            <span class="text-danger">{{ $errors->first('branch_city') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.branch_address') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="br_address" name="branch_address" placeholder="{{ trans('messages.account_preference.branch_address') }}">
                            <span class="text-danger">{{ $errors->first('branch_address') }}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description" class="control-label col-sm-3">{{ trans('messages.photo_details.description') }}</label>
                        <div class="col-sm-8">
                            <textarea type="text" class="form-control" id="description" rows="3" name="description" placeholder="{{ trans('messages.photo_details.description') }}"></textarea>
                            <span class="text-danger">{{ $errors->first('description') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.logo') }} </label>
                        <div class="col-sm-8">
                            <input type="file" class="form-control" name="logo">
                            <span class="text-muted">{{ trans('messages.account_preference.empty') }} {{ trans('messages.account_preference.logo_limit') }}</span><br><span class="text-danger">{{ $errors->first('logo') }}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3"></label>
                        <div class="col-sm-8">
                            <img id="logo" src="" class="bank-logo" alt="">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputPassword1" class="control-label col-sm-3">{{ trans('messages.account_preference.country') }}</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="country" id="country">
                                @foreach($countries as $id => $name)
                                    <option
                                        value="{{$name}}">{{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-danger">{{ $errors->first('country') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="inputEmail3">{{ trans('messages.account_preference.status') }} <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="status" id="status" required aria-required="true" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <span class="text-danger">{{ $errors->first('status') }}</span>
                        </div>
                    </div>

                <div class="modal-footer">
                    <button class="btn btn-info pull-right" style="margin-left: 20px" type="submit" name="submit">{{trans('messages.listing_calendar.submit')}}</button>
                    <button type="button" class="btn btn-default cls-reload" data-bs-dismiss="modal">{{trans('messages.listing_calendar.close')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>


@push('scripts')
    <script src="{{ asset('public/backend/plugins/DataTables-1.10.18/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('public/backend/plugins/Responsive-2.2.2/js/dataTables.responsive.min.js') }}"></script>
    {!! $dataTable->scripts() !!}

    <script>
     


        $(document).on('click', '.edit_bank', function(e) {
            e.preventDefault();
            var formdata = [];
            var url = $(this).attr('action');
            var myModal = new bootstrap.Modal(document.getElementById('edit_modal'));
            $.ajax({
                url : e.target.dataset.url,
                type : "get",
                async : false,
                data : formdata,
                processData: false,
                contentType: false,
                dataType : 'json',
                success:function(data, textStatus, jqXHR){
                    document.querySelector('#name').value = data.account_name;
                    document.querySelector('#iban').value = data.iban;
                    document.querySelector('#swift').value = data.swift_code;
                    document.querySelector('#bank').value = data.bank_name;
                    document.querySelector('#branch').value = data.branch_name;
                    document.querySelector('#br_city').value = data.branch_city;
                    document.querySelector('#route').value = data.routing_no;
                    document.querySelector('#br_address').value = data.branch_address;
                    document.querySelector('#description').innerHTML = data.description;
                    document.querySelector('#logo').src = data.logo;
                    document.querySelector('#country').value = data.country;
                    document.querySelector('#status').value = data.status;
                    document.querySelector('#edit_form').action = e.target.dataset.edit;
                   
                    console.log(data.description);
                },
                error: function(jqXHR, textStatus, errorThrown){
                    console.log(textStatus);
                    throw 'Bank details not found';
                }
            });
        });


    </script>

@endpush

@push('css')
    <style>
        table.dataTable {
            width:100% !important;
        }

        .edit-icon {
            pointer-events: none;
        }
    </style>
@endpush
@push('css')
    <style>
        .bank-logo {
            margin: 0;
            text-align: left;
            max-height: 50px;
            max-width: 120px;
            object-fit: contain;
        }
    </style>
@endpush

