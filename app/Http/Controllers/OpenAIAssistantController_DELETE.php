<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenAIAssistantController extends Controller
{
    // Show the form for the Assistant interface
    public function index()
    {
        return view('openai_assistant.index');
    }

    // Handle the OpenAI Assistant Call
    public function assist(Request $request)
    {
        $request->validate([
            'pdf_file' => 'nullable|mimes:pdf|max:2048', // If you want to allow PDF uploads.
            'name' => 'required|string',
            'instructions' => 'required|string',
            'model' => 'required|string',
            'tools' => 'required|string', 
        ]);

        // Store the uploaded PDF if provided
        if ($request->hasFile('pdf_file')) {
            $pdfPath = $request->file('pdf_file')->store('uploads');
        } else {
            $pdfPath = null;
        }

        // Define the command to execute the Python script
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/assistant.py " .
                    escapeshellarg($request->name) . " " .
                    escapeshellarg($request->instructions) . " " .
                    escapeshellarg($request->model) . " " .
                    escapeshellarg($request->tools) . " " .
                    ($pdfPath ? escapeshellarg(storage_path('app/' . $pdfPath)) : 'noop') .  // Passing PDF path if exists
                    " 2>&1"; // Capture stderr

        // Execute the command and capture output
        exec($command, $output, $resultCode);

        // Log the command, output and result code
        Log::info("OpenAI Assistant Command: $command");
        Log::info("OpenAI Assistant Output: " . implode("\n", $output));
        Log::info("OpenAI Assistant Result Code: $resultCode");

        // Check the result code and log any errors
        if ($resultCode !== 0) {
            Log::error('Error executing OpenAI Assistant: ' . implode("\n", $output));
            throw new \Exception('Error executing OpenAI Assistant: ' . implode("\n", $output));
        }

        // Process the output as necessary and return a response
        $response = trim(implode("\n", $output));

        return back()
            ->with('success', 'Assistant response generated successfully!')
            ->with('response', $response); // Pass the response to the view
    }
}