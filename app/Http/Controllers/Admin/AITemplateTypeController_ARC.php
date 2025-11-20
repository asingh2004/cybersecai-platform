<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AITemplateType; // Ensure you import the correct model
use Validator;
use App\Http\Helpers\Common; 
use Illuminate\Support\Facades\Cache; // Used for caching

class AITemplateTypeController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    // Display AI Template Types
    public function index()  // Change this based on your data table implementation
    {
        // Logic to retrieve and display AI template types goes here
        $data['templateTypes'] = AITemplateType::all(); // Fetch all AI template types
        return view('admin.ai_template_types.view', $data); // Adjust view as necessary
    }
  
    public function index(AITemplateType $dataTable)
    {
        return $dataTable->render('admin.aiTemplateTypes.view');
    }

    // Show the form for adding a new AI Template Type
    public function create()
    {
        return view('admin.ai_template_type_form'); // Adjust view as necessary
    }

    // Store a newly created AI Template Type
    public function store(Request $request)
    {
        if ($request->isMethod('post')) {
            $rules = [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'api_endpoint' => 'required|string|max:255',
            ];

            $fieldNames = [
                'name' => 'AI Template Name',
                'description' => 'Description',
                'api_endpoint' => 'API Endpoint',
            ];

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                // Save the AI template type
                AITemplateType::create([
                    'name' => $request->name,
                    'description' => $request->description,
                    'api_endpoint' => $request->api_endpoint,
                ]);

                $this->helper->one_time_message('success', 'AI Template Type created successfully!');
                return redirect('admin/ai-template-types'); // Redirect to the list or relevant page
            }
        }
        return redirect('admin/ai-template-types');
    }

    // Show the form for editing the specified AI Template Type
    public function edit($id)
    {
        $data['templateType'] = AITemplateType::find($id);

        if (!$data['templateType']) {
            return redirect('admin/ai-template-types')->withErrors('AI Template Type not found.');
        }

        return view('admin.ai_template_type_edit', $data); // Adjust view name as necessary
    }

    // Update the specified AI Template Type
    public function update(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $rules = [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'api_endpoint' => 'required|string|max:255',
            ];

            $fieldNames = [
                'name' => 'AI Template Name',
                'description' => 'Description',
                'api_endpoint' => 'API Endpoint',
            ];

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $templateType = AITemplateType::find($id);
                if ($templateType) {
                    $templateType->name = $request->name;
                    $templateType->description = $request->description;
                    $templateType->api_endpoint = $request->api_endpoint;
                    $templateType->save();
                    
                    $this->helper->one_time_message('success', 'AI Template Type updated successfully!');
                    return redirect('admin/ai-template-types');
                }
                return redirect('admin/ai-template-types')->withErrors('AI Template Type not found.');
            }
        }
    }

    // Delete the specified AI Template Type
    public function delete(Request $request, $id)
    {
        $templateType = AITemplateType::find($id);
        if ($templateType) {
            $templateType->delete();
            $this->helper->one_time_message('success', 'AI Template Type deleted successfully!');
        } else {
            $this->helper->one_time_message('error', 'AI Template Type not found.');
        }

        return redirect('admin/ai-template-types');
    }
}