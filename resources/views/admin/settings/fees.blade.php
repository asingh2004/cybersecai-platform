@php 
$form_data = [
		'page_title'=> 'Fees Setting Form',
		'page_subtitle'=> 'Fees Setting Page', 
		'form_name' => 'Fees Setting Form',
		'form_id' => 'fees_setting',
		'action' => URL::to('/').'/admin/settings/fees',
		'fields' => [
			
      		['type' => 'text', 'class' => '', 'label' => 'Platform Fee Per Night', 'name' => "guest_service_charge", 'value' => $result['guest_service_charge'], 'hint' => 'service fees charged per night by Ozzieaccom platform'],

            ['type' => 'text', 'class' => '', 'label' => 'GST on Fees (%)', 'name' => "iva_tax", 'value' => $result['iva_tax'], 'hint' => 'GST'],

            ['type' => 'text', 'class' => '', 'label' => 'Platform Discount (%)', 'name' => "accomodation_tax", 'value' => $result['accomodation_tax'], 'hint' => 'service fees discount by Ozzieaccom platform'],
		]
	];
@endphp
@include("admin.common.form.setting", $form_data)
<script type="text/javascript">
   $(document).ready(function () {

            $('#fees_setting').validate({
                rules: {
                    guest_service_charge: {
                        required: true
                    }
                }
            });

        });
</script>