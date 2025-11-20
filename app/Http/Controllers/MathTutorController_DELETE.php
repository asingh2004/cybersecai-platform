<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class MathTutorController extends Controller
{
    // Display the chat interface
    public function index()
    {
        return view('openai.math-tutor');
    }

    // Handle user input and call OpenAI API
    public function getAnswer(Request $request)
    {
    // Validate incoming request
    $request->validate([
        'messages' => 'required|array',
        'messages.*.role' => 'required|string|in:system,user,assistant',
        'messages.*.content' => 'required|string',
    ]);

    // Fetch the OpenAI API key from the environment
    $apiKey = env('OPENAI_API_KEY');

    // Prepare the message payload
    $messages = $request->input('messages');

    // Make a request to the OpenAI API
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => "Bearer $apiKey",
    ])->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o-mini', // Make sure this model name is correct and accessible
        'messages' => $messages,
    ]);

    // Get the response body
    $result = $response->json();

    // Check for errors and return the API response
    if (isset($result['choices']) && count($result['choices']) > 0) {
        $answer = $result['choices'][0]['message']['content'];
    } else {
        $answer = 'No answer found.';
    }

    // Add the user question and the response to the messages array
    array_push($messages, ['role' => 'user', 'content' => $request->input('messages.0.content')]);
    array_push($messages, ['role' => 'assistant', 'content' => $answer]);

    // Return to the view with the result
    return view('openai.math-tutor', [
        'answer' => $answer,
        'messages' => $messages
    ]);
    }
}
