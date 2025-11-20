<!-- Add the sidebar's background. This div must be placed immediately after the control sidebar -->
  <div class="control-sidebar-bg"></div>
</div>
<!-- ./wrapper -->
<script type="text/javascript"> 
var APP_URL = "{{(url('/'))}}"; 
</script>


<!-- jQuery 3.6.0 -->
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/jQuery/jquery-2.2.4.min.js"></script> -->
 
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/bootstrap/js/popper.min.js"></script> -->


<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!-- <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script> -->
<!-- jQuery UI 1.11.4 -->
<!-- jQuery validation -->


<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js" integrity="sha512-37T7leoNS06R80c8Ulq7cdCDU5MNQBwlYoy1TX/WUsLFC2eYNqtKlV0QjH7r8JpG/S0GUMZwebnVFLPd6SU5yg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- jQuery validation -->
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/jQueryUI/jquery-ui.min.js"></script> -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" integrity="sha512-uto9mlQzrs59VwILcLiRYeLKPPbS/bT71da/OEBYEwcdNUk8jYIy+D176RYoop1Da+f9mvkYrmj5MCLZWEtQuA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script type="text/javascript">
  $.widget.bridge('uibutton', $.ui.button);
  var sessionDate      = '{!! Session::get('date_format_type') !!}';
</script>
<!-- Bootstrap 3.3.6 -->
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/bootstrap/js/bootstrap.min.js"></script> -->

<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.1/js/bootstrap.min.js" integrity="sha512-UR25UO94eTnCVwjbXozyeVd6ZqpaAE9naiEUBK/A+QDbfSTQFhPGj5lOR6d8tsgbBk84Ggb5A3EkjsOgPRPcKA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script> -->

    @if (!empty($map_key))
    <script type="text/javascript" src='https://maps.google.com/maps/api/js?key={{ $map_key }}&callback=Function.prototype&libraries=places'></script>
    @endif
  <script type="text/javascript" src="{{ url('public/backend/js/locationpicker.jquery.min.js') }}"></script>
  <script type="text/javascript" src="{{ url('public/backend/js/bootbox.min.js') }}"></script>
<!-- admin js -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/dist/js/admin.js"></script>
<!-- backend js -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/js/backend.js"></script>
<!-- CK Editor -->
<!-- Morris.js charts -->
@if(Route::current()->uri() == 'admin/dashboard')
@endif
<!-- Sparkline -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/sparkline/jquery.sparkline.min.js"></script>
<!-- jvectormap -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/jvectormap/jquery-jvectormap-1.2.2.min.js"></script>
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/jvectormap/jquery-jvectormap-world-mill-en.js"></script>
<!-- jQuery Knob Chart -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/knob/jquery.knob.js"></script>
<!-- daterangepicker -->
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/js/moment.min.js"></script> -->



<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" integrity="sha512-qTXRIMyZIFb8iQcfjXWCO8+M5Tbc38Qi5WzdPOYZHIlZpzBHG3L3by84BBBOiRGiEb7KKtAOAs5qYdUiZiQNNQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/datepicker/bootstrap-datepicker.js"></script> 
<!-- Bootstrap WYSIHTML5 -->
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.all.min.js"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap3-wysiwyg/0.3.3/bootstrap3-wysihtml5.all.min.js" integrity="sha512-ng0ComxRUMJeeN1JS62sxZ+eSjoavxBVv3l7SG4W/gBVbQj+AfmVRdkFT4BNNlxdDCISRrDBkNDxC7omF0MBLQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Slimscroll -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/slimScroll/jquery.slimscroll.min.js"></script>
<!-- FastClick -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/fastclick/fastclick.js"></script>
<!-- AdminLTE App -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/dist/js/app.min.js"></script>
<!--Select2-->
<!-- <script type="text/javascript" src="{{URL::to('/')}}/public/backend/plugins/select2/select2.full.min.js"></script> -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js" integrity="sha512-RtZU3AyMVArmHLiW0suEZ9McadTdegwbgtiQl5Qqo9kunkVg1ofwueXD8/8wv3Af8jkME3DDe3yLfR8HSJfT2g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
@if(Route::current()->uri() == 'admin/dashboard')
@endif
<!-- AdminLTE for demo purposes -->
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/dist/js/demo.js"></script>
<script type="text/javascript" src="{{URL::to('/')}}/public/backend/dist/js/custom.js"></script>
<script type="text/javascript" src="{{ url('public/backend/js/daterangecustom.js')}}"></script>
</body>

@stack('scripts')
</html>
