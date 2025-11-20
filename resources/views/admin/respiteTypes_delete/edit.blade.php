@php 
$form_data = [
	'page_title'=> 'Edit Respite Type',
	'page_subtitle'=> '', 
	'form_name' => 'Edit Respite Type Form',
	'form_id' => 'edit_respite',
	'action' => URL::to('/').'/admin/settings/edit-respite-type/'.$result->id,
	'fields' => [
	['type' => 'text', 'class' => '', 'label' => 'Name', 'name' => 'name', 'value' => $result->name],
	]
];
@endphp
@include("admin.common.form.setting", $form_data)

<script type="text/javascript">
$(document).ready(function () {
	$('#edit_respite').validate({
		rules: {
			name: {
				required: true
			}
		}
	});
});
</script>