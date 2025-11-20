@php 
$form_data = [
    'page_title' => 'Add AI Template Type',
    'page_subtitle' => '', 
    'form_name' => 'Add AI Template Type Form',
    'form_id' => 'add_ai_template',
    'action' => URL::to('/') . '/admin/ai-template-types',
    'fields' => [
        ['type' => 'text', 'class' => '', 'label' => 'Name', 'name' => 'name', 'value' => ''],
        ['type' => 'textarea', 'class' => '', 'label' => 'Description (optional)', 'name' => 'description', 'value' => ''],
        ['type' => 'text', 'class' => '', 'label' => 'API Endpoint', 'name' => 'api_endpoint', 'value' => ''],
    ]
];
@endphp
@include("admin.common.form.setting", $form_data)

<script type="text/javascript">
    $(document).ready(function () {
        $('#add_ai_template').validate({
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