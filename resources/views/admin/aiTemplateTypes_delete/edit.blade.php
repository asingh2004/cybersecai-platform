@php 
$form_data = [
    'page_title' => 'Edit AI Template Type',
    'page_subtitle' => '', 
    'form_name' => 'Edit AI Template Type Form',
    'form_id' => 'edit_ai_template',
    'action' => URL::to('/') . '/admin/ai-template-types/update/' . $result->id,
    'fields' => [
        ['type' => 'text', 'class' => '', 'label' => 'Name', 'name' => 'name', 'value' => $result->name],
        ['type' => 'textarea', 'class' => '', 'label' => 'Description (optional)', 'name' => 'description', 'value' => $result->description],
        ['type' => 'text', 'class' => '', 'label' => 'API Endpoint', 'name' => 'api_endpoint', 'value' => $result->api_endpoint],
    ]
];
@endphp
@include("admin.common.form.setting", $form_data)

<script type="text/javascript">
    $(document).ready(function () {
        $('#edit_ai_template').validate({
            rules: {
                name: {
                    required: true
                },
                api_endpoint: {
                    required: true
                }
            }
        });
    });
</script>