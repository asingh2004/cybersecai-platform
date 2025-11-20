<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class WebBlogSummarizationController extends Controller
{
    // Show the form for Blog URLs input
    public function index()
    {
        return view('blogsummarizer.index');
    }

    // Handle the input URLs and summarize the text
   public function summarize(Request $request)
   {
    $request->validate([
        'urls' => 'required|array',
        'detail' => 'required|numeric|min:0|max:1',
        'model' => 'required|string',
        'additional_instructions' => 'nullable|string',
    ]);

    $urls = $request->input('urls');

    // Convert web blogs to text using the Python script
    $textFilePath = storage_path('app/uploads/extracted_text.txt');
    $this->convertWebBlogsToText($urls, $textFilePath);

    // Read the extracted text
    $text = file_get_contents($textFilePath);

    // Summarize the text using the summarization Python script
    $summary = $this->summarizeTextWithPython($text, $request->detail, $request->model, $request->additional_instructions);

    // Write the summary to a temporary text file
    $summaryFilePath = storage_path('app/uploads/temp_summary_text.txt');
    file_put_contents($summaryFilePath, $summary); // Save summary to file

    // Read the summarized text for display
    $displaySummary = file_get_contents($summaryFilePath);

    // Generate a PDF from the summary using Python
    $summaryPdfPath = storage_path('app/public/summary_' . Str::random(10) . '.pdf');
    $this->writeSummaryToPdf($summary, $summaryPdfPath);

    // Pass the summarized text to the view
    return back()
        ->with('success', 'Summary generated successfully!')
        ->with('summary', $displaySummary) // Pass the summarized text to the view
        ->with('filename', basename($summaryPdfPath)); // Pass the PDF filename
    }

    // Convert web blog URLs to text by calling the Python script
    private function convertWebBlogsToText(array $urls, $textFile)
    {
        // Prepare the command to execute the Python script
        $command = 'python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/summary/blog_to_text.py ' . implode(' ', array_map('escapeshellarg', $urls)) . ' ' . escapeshellarg($textFile) . ' 2>&1';

        // Execute the command and capture output
        exec($command, $output, $resultCode);

        // Log the command and output
        Log::info("Blog to Text Command: $command");
        Log::info("Blog to Text Output: " . implode("\n", $output));
        Log::info("Blog to Text Result Code: $resultCode");

        // Check the result code
        if ($resultCode !== 0) {
            Log::error('Error extracting text from web blogs: ' . implode("\n", $output));
            throw new \Exception('Error extracting text from web blogs: ' . implode("\n", $output));
        }

        Log::info("Successfully extracted texts for URLs: " . implode(", ", $urls));
    }

    private function summarizeTextWithPython($text, $detail, $model, $additional_instructions)
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

        // Define the command to execute the Python script, including model and instructions
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