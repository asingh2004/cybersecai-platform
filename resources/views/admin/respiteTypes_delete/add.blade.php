@php 
$form_data = [
    'page_title'=> 'Add Respite Type',
    'page_subtitle'=> '', 
    'form_name' => 'Add Respite Type Form',
    'form_id' => 'add_respite',
    'action' => URL::to('/').'/admin/settings/add-respite-type',
    'fields' => [
    ['type' => 'text', 'class' => '', 'label' => 'Name', 'name' => 'name', 'value' => ''],
    ]
];
@endphp
@include("admin.common.form.setting", $form_data)

<script type="text/javascript">
    $(document).ready(function () {
            $('#add_respite').validate({
                rules: {
                    name: {
                        required: true
                    }
                }
            });
        });
</script>