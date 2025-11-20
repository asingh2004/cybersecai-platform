<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\OpenAIConfig;  // Add this import for OpenAIConfig model
use App\Models\AIModel; 

class PdfSummarizationController extends Controller
{
    // Show the form for PDF upload
    /*public function index()
    {
      	//$assistant = OpenAIConfig::find($id);
      
        return view('pdf_summarization.index');
      	//return view('pdf_summarization.index', compact('assistant')); 
    }*/
  
  	public function index($id)
	{
    	// Retrieve assistant configuration by ID
    	$assistant = OpenAIConfig::findOrFail($id);
  
    	return view('pdf_summarization.index', compact('assistant')); 
	}

    // Handle the uploaded PDF and summarize it
    /*public function summarize(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|mimes:pdf|max:30720', // 30 MB = 30720 KB
            'detail' => 'required|numeric|min:0|max:1',
            'model' => 'required|string',
            'additional_instructions' => 'nullable|string'
        ]);

        // Store the uploaded PDF
        $pdfPath = $request->file('pdf_file')->store('uploads');

        // Convert PDF to text using Python script
        $textFilePath = storage_path('app/uploads/extracted_text.txt');
        $this->convertPdfToText(storage_path('app/' . $pdfPath), $textFilePath);
        
        // Read the extracted text
        $text = file_get_contents($textFilePath);
        
        // Summarize the text using the summarize Python script
        $summary = $this->summarizeTextWithPython($text, $request->detail, $request->model, $request->additional_instructions);
        
        // Write the summary to a temporary text file
        $summaryFilePath = storage_path('app/uploads/temp_summary_text.txt');
        file_put_contents($summaryFilePath, $summary); // Save summary to file

        // Generate a PDF from the summary using Python
        $summaryPdfPath = storage_path('app/public/summary_' . Str::random(10) . '.pdf');
        $this->writeSummaryToPdf($summary, $summaryPdfPath);

        // Read the summary text for display
        $displaySummary = file_get_contents($summaryFilePath);

        return back()
            ->with('success', 'Summary generated successfully!')
            ->with('summary', $displaySummary) // Pass the summarized text to the view
            ->with('filename', basename($summaryPdfPath)); // Pass the PDF filename
    }*/
  
  	public function summarize(Request $request, $id)
    {
        $request->validate([
            'pdf_file' => 'required|mimes:pdf|max:71720',
            'detail' => 'required|numeric|min:0|max:1',
            'additional_instructions' => 'nullable|string'
        ]);

        // Retrieve assistant configuration
        $assistant = OpenAIConfig::with('aiModel:id,ai_formal_name')->findOrFail($id);

        // Get values from the assistant configuration
        $name = $assistant->name;
        $instructions = $assistant->instructions;
        $ai_formal_name = $assistant->aiModel->ai_formal_name; // Assuming relationship is set

        // Store the uploaded PDF
        $pdfPath = $request->file('pdf_file')->store('uploads');

        // Convert PDF to text using Python script
        $textFilePath = storage_path('app/uploads/extracted_text.txt');
        $this->convertPdfToText(storage_path('app/' . $pdfPath), $textFilePath);
        
        // Read the extracted text
        $text = file_get_contents($textFilePath);

        // Summarize the text using the summarize Python script
        $summary = $this->summarizeTextWithPython($text, $request->detail, $request->additional_instructions, $name, $instructions, $ai_formal_name);
        
        // Write the summary to a temporary text file
        $summaryFilePath = storage_path('app/uploads/temp_summary_text.txt');
        file_put_contents($summaryFilePath, $summary); // Save summary to file

        // Generate a PDF from the summary using Python
        $summaryPdfPath = storage_path('app/public/summary_' . Str::random(10) . '.pdf');
        $this->writeSummaryToPdf($summary, $summaryPdfPath);

        // Read the summary text for display
        $displaySummary = file_get_contents($summaryFilePath);

        return back()
            ->with('success', 'Summary generated successfully!')
            ->with('summary', $displaySummary) // Pass the summarized text to the view
            ->with('filename', basename($summaryPdfPath)); // Pass the PDF filename
    }

    // Convert PDF to text using a Python script
    private function convertPdfToText($pdfFile, $textFile)
    {
        if (!file_exists($pdfFile)) {
            throw new \Exception('PDF file does not exist: ' . $pdfFile);
        }

        // Add sudo before the command if necessary
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/summary/pdf_to_text.py " .
                    escapeshellarg($pdfFile) . " " . escapeshellarg($textFile) . " 2>&1"; // Capture stderr

        // Execute the command and capture output
        exec($command, $output, $resultCode);

        // Log the output from the Python script
        Log::info("PDF to Text Command: $command");
        Log::info("PDF to Text Output: " . implode("\n", $output));
        Log::info("PDF to Text Result Code: $resultCode");

        if ($resultCode !== 0) {
            throw new \Exception('Error converting PDF to text: ' . implode("\n", $output));
        }
    }

    /*private function summarizeTextWithPython($text, $detail, $model, $additional_instructions)
    {
        $textFilePath = storage_path('app/uploads/temp_text_for_summary.txt');

        // Check if the directory is writable
        if (!is_writable(dirname($textFilePath))) {
            Log::error("Cannot write to directory: " . dirname($textFilePath));
            throw new \Exception("Cannot write to directory: " . dirname($textFilePath));
        }

        // Write the input text to the file
        file_put_contents($textFilePath, $text);
        Log::info("Input text written to: " . $textFilePath);

        // Define the command to execute the Python script
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/summary/summarize.py " .
                    escapeshellarg($textFilePath) . " " .
                    escapeshellarg($detail) . " " .
                    escapeshellarg($model) . " " .
                    escapeshellarg($additional_instructions) . " 2>&1"; // Capture STDERR

        // Execute the command and capture output
        exec($command, $output, $resultCode);

        // Log the command, output and result code
        Log::info("Summarization Command: $command");
        Log::info("Summarization Output: " . implode("\n", $output));
        Log::info("Summarization Result Code: $resultCode");

        // Check the result code and log any errors
        if ($resultCode !== 0) {
            Log::error('Error summarizing text: ' . implode("\n", $output));
            throw new \Exception('Error summarizing text: ' . implode("\n", $output));
        }

        // Return trimmed output
        return trim(implode("\n", $output));
    }*/
  
  	private function summarizeTextWithPython($text, $detail, $additional_instructions, $name, $instructions, $ai_formal_name)
    {
        $textFilePath = storage_path('app/uploads/temp_text_for_summary.txt');

        // Check if the directory is writable
        if (!is_writable(dirname($textFilePath))) {
            Log::error("Cannot write to directory: " . dirname($textFilePath));
            throw new \Exception("Cannot write to directory: " . dirname($textFilePath));
        }

        // Write the input text to the file
        file_put_contents($textFilePath, $text);
        Log::info("Input text written to: " . $textFilePath);

        // Define the command to execute the Python script, passing additional parameters
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/summary/summarize.py " .
                    escapeshellarg($textFilePath) . " " .
                    escapeshellarg($detail) . " " .
                    escapeshellarg($additional_instructions) . " " .
                    escapeshellarg($name) . " " .
                    escapeshellarg($instructions) . " " .
                    escapeshellarg($ai_formal_name) . " 2>&1"; // Capture STDERR

        // Execute the command and capture output
        exec($command, $output, $resultCode);

        // Log the command, output and result code
        Log::info("Summarization Command: $command");
        Log::info("Summarization Output: " . implode("\n", $output));
        Log::info("Summarization Result Code: $resultCode");

        // Check the result code and log any errors
        if ($resultCode !== 0) {
            Log::error('Error summarizing text: ' . implode("\n", $output));
            throw new \Exception('Error summarizing text: ' . implode("\n", $output));
        }

        // Return trimmed output
        return trim(implode("\n", $output));
    }


    private function writeSummaryToPdf($summary, $filename)
    {
        $summaryFilePath = storage_path('app/uploads/temp_summary_text.txt');

        // Check if the directory is writable
        if (!is_writable(dirname($summaryFilePath))) {
            Log::error("Cannot write to directory: " . dirname($summaryFilePath));
            throw new \Exception("Cannot write to directory: " . dirname($summaryFilePath));
        }

        // Write the summary text to the file
        file_put_contents($summaryFilePath, $summary);
        Log::info("Summary text written to: " . $summaryFilePath);

        // Define the command to call the Python script
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/summary/write_to_pdf.py " .
                    escapeshellarg($summaryFilePath) . " " .
                    escapeshellarg($filename) . " 2>&1"; // Capture STDERR

        // Execute the command and capture output
        exec($command, $output, $resultCode);

        // Log the command, output, and result code
        Log::info("Write to PDF Command: $command");
        Log::info("Write to PDF Output: " . implode("\n", $output));
        Log::info("Write to PDF Result Code: $resultCode");

        // Check the result code and log any errors
        if ($resultCode !== 0) {
            Log::error('Error writing summary to PDF: ' . implode("\n", $output));
            throw new \Exception('Error writing summary to PDF: ' . implode("\n", $output));
        }

        Log::info("Successfully wrote summary to PDF: " . $filename);
    }
}