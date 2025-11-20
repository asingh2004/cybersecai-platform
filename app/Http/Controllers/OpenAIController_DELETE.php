<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\OpenAIConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\AITemplateType;
use App\Models\AIModel; // Make sure this model corresponds to 'ai_models' table
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http; // Import the necessary Http facade

class OpenAIController extends Controller
{
    // Method to show the configuration form
    public function create()
    {
        $aiTemplateTypes = AITemplateType::all(); // Get all AI template types from the database
        $aiModels = AIModel::all(); // Get all AI models from the database

        return view('openai.create-openai-bot', compact('aiTemplateTypes', 'aiModels')); // Pass the data to the view
    }

    // Method to handle the form submission 
  	public function store(Request $request)
	{
    // Validate the incoming request data
    $request->validate([
        'ai_template_type_id' => 'required|integer|exists:ai_template_types,id',
        'name' => 'required|string|max:255',
        'instructions' => 'nullable|string',
        'ai_model_id' => 'required|integer|exists:ai_models,id',
    ]);

    // Create a new OpenAIConfig instance
    $config = new OpenAIConfig();
    $config->ai_template_type_id = $request->ai_template_type_id; // Save the selected template type ID
    $config->name = $request->name; // Save the name from the input
    $config->instructions = $request->instructions; // Save the instructions if provided
    $config->ai_model_id = $request->ai_model_id; // Save the selected model ID
    
    // Get the currently authenticated user's ID and assign it to user_id
    $config->user_id = Auth::id(); // Retrieves the ID of the logged-in user
    
    $config->save(); // Save the configuration into the database

    // Store the saved configuration data in the session
    $request->session()->flash('config_saved', true);
    $request->session()->flash('config_data', [
        'name' => $config->name,
        'ai_template_type_id' => $config->ai_template_type_id,
        'instructions' => $config->instructions,
        'ai_model_id' => $config->ai_model_id,
    ]);

    // Check if the ai_template_type_id is 2
    if ($config->ai_template_type_id == 2) {
        // Get the corresponding variables from the ai_models table
        $aiModel = AiModel::find($config->ai_model_id); // Assuming you have an AiModel model

        if ($aiModel) {
            $name = escapeshellarg($aiModel->name);
            $instructions = escapeshellarg($config->instructions);
            $aiFormalName = escapeshellarg($aiModel->ai_formal_name);

            // Construct the command to call the Python script
  
            $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/assistant.py " .
                    escapeshellarg($aiModel->name) . " " . escapeshellarg($config->instructions) . " " . $aiFormalName = escapeshellarg($aiModel->ai_formal_name) . " 2>&1"; // Capture stderr

        // Execute the command and capture output
        exec($command, $output, $returnVar);

        // Log the output from the Python script
        Log::info("Name: $command");
        Log::info("Instruction: $instructions");
        Log::info("AI Model: $aiFormalName");        
          
          
          	// Execute the command and capture the output
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
          
          
          if ($returnVar === 0) {
    		// Get the assistant_id from the output (assuming it prints the id as a single line)
    			if (isset($output[0])) {
        			$assistantId = trim($output[0]);  // Ensure it's trimmed to remove whitespace

        			// Update the assistant_id in the OpenAIConfig table
        			$config->assistant_id = $assistantId;
        			$config->save();  // Save the updated configuration
    			} else {
        			Log::error("Python script did not return an assistant ID. Output: " . implode("\n", $output));
    			}
			} else {
    			// Handle error case
    			Log::error('Python script execution failed. Return code: ' . $returnVar . '. Output: ' . implode("\n", $output));
			}

        } else {
            // Log error if the AiModel is not found
            Log::error("AiModel with ID {$config->ai_model_id} not found.");
        }
    }

    // Return response or redirect with a success message
    return redirect()->back()->with('success', 'OpenAI configuration saved successfully!');
	}
  
  

    // Method to retrieve and display assistants for the logged-in user
    public function userAssistants()
    {
        // Retrieve configurations that correspond to the logged-in user
        $assistants = OpenAIConfig::where('user_id', Auth::id())->paginate(10);

        // Return the 'assistant.listing' view with the retrieved assistants
        return view('assistant.listing', compact('assistants'));
    }
  
  	public function getInstructions($id)
	{
    	$assistant = Assistant::find($id); // Make sure to import the model

    	if (!$assistant) {
        	return response()->json(['error' => 'Assistant not found'], 404);
    	}

    	return response()->json(['instructions' => $assistant->instructions]);
	}
  
    public function show($id)
    {
        // Retrieve the specific assistant using the ID
        $assistant = OpenAIConfig::findOrFail($id); // Use findOrFail to throw a 404 if not found

        // Retrieve necessary data for the view, such as AI template types and models
        $aiTemplateTypes = AITemplateType::all(); // Get all AI template types from the database
        $aiModels = AIModel::all(); // Get all AI models from the database

        // Return the view with assistant details
        return view('assistant.assistantDetails', compact('assistant', 'aiTemplateTypes', 'aiModels'));
    }
  
    /*public function update(Request $request, $id)
    {
        // Validate the incoming request data
        $request->validate([
            'ai_template_type_id' => 'required|integer|exists:ai_template_types,id',
            'name' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'ai_model_id' => 'required|integer|exists:ai_models,id',
        ]);

        // Find the specific OpenAIConfig instance and update it
        $config = OpenAIConfig::findOrFail($id); // Use findOrFail to throw a 404 if not found
        $config->ai_template_type_id = $request->ai_template_type_id; // Update the selected template type ID
        $config->name = $request->name; // Update the name from the input
        $config->instructions = $request->instructions; // Update the instructions if provided
        $config->ai_model_id = $request->ai_model_id; // Update the selected model ID

        $config->save(); // Save the updated configuration into the database

        // Return a JSON response with a success message
        return response()->json(['message' => 'Configuration updated successfully!']);
    }*/
  
  	public function update(Request $request, $id)
	{
    // Validate the incoming request data
    $request->validate([
        'ai_template_type_id' => 'required|integer|exists:ai_template_types,id',
        'name' => 'required|string|max:255',
        'instructions' => 'nullable|string',
        'ai_model_id' => 'required|integer|exists:ai_models,id',
    ]);

    // Find the specific OpenAIConfig instance and update it
    $config = OpenAIConfig::findOrFail($id); // Use findOrFail to throw a 404 if not found
    $config->ai_template_type_id = $request->ai_template_type_id; // Update the selected template type ID
    $config->name = $request->name; // Update the name from the input
    $config->instructions = $request->instructions; // Update the instructions if provided
    $config->ai_model_id = $request->ai_model_id; // Update the selected model ID

    // Check if the ai_template_type_id is 2
    if ($config->ai_template_type_id == 2) {
        // Get the corresponding variables from the ai_models table
        $aiModel = AiModel::find($config->ai_model_id); // Assuming you have an AiModel model

        if ($aiModel) {
            $name = escapeshellarg($aiModel->name);
            $instructions = escapeshellarg($config->instructions);
            $aiFormalName = escapeshellarg($aiModel->ai_formal_name);

            // Construct the command to call the Python script
            $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/assistant.py " .
                    $name . " " . $instructions . " " . $aiFormalName . " 2>&1"; // Capture stderr

            // Execute the command and capture output
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Log the output from the Python script
            Log::info("Command: $command");
            Log::info("Instruction: " . $instructions);
            Log::info("AI Model: " . $aiFormalName);

            if ($returnVar === 0) {
                // Get the assistant_id from the output (assuming it prints the id as a single line)
                if (isset($output[0])) {
                    $assistantId = trim($output[0]);  // Ensure it's trimmed to remove whitespace

                    // Update the assistant_id in the OpenAIConfig table
                    $config->assistant_id = $assistantId;
                    $config->save();  // Save the updated configuration
                } else {
                    Log::error("Python script did not return an assistant ID. Output: " . implode("\n", $output));
                }
            } else {
                // Handle error case
                Log::error('Python script execution failed. Return code: ' . $returnVar . '. Output: ' . implode("\n", $output));
            }
        } else {
            // Log error if the AiModel is not found
            Log::error("AiModel with ID {$config->ai_model_id} not found.");
        }
    } else {
        // If ai_template_type_id is not 2, simply save the config
        $config->save(); // Save the updated configuration
    }

    // Return a JSON response with a success message
    return response()->json(['message' => 'Configuration updated successfully!']);
	}
  
    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:open_ai_config,id',
            'status' => 'required|string|in:Listed,Unlisted',
        ]);
    
        $assistant = OpenAIConfig::findOrFail($request->id); // Fetch the assistant
        $assistant->status = $request->status; // Update the status
        $assistant->save(); // Save changes in the database
  
        return response()->json(['name' => $assistant->name, 'status' => $assistant->status]);
    }
  
    // Method to show the completion template based on assistant ID
    public function showCompletionTemplate($id)
    {
        // Retrieve the assistant configuration by ID
        $assistant = OpenAIConfig::select('name', 'instructions', 'ai_model_id')->findOrFail($id);
        
        // Clear existing session values
        session()->forget(['assistant_name', 'assistant_instructions', 'assistant_id']);

        // Set session variables
        session([
            'assistant_id' => $id,
            'assistant_name' => $assistant->name,
            'assistant_instructions' => $assistant->instructions,
        ]);

        // Retrieve AI model formal name for this assistant
        $aiModel = AIModel::findOrFail($assistant->ai_model_id);
        session(['ai_formal_name' => $aiModel->ai_formal_name]);

        // Initialize messages array
        $messages = [];

        // Return the view with the assistant's details
        return view('assistant.completionTemplate', compact('assistant', 'messages'));
    }

  
  	public function handleCompletion(Request $request)
	{
    // Log the entry into the method
    \Log::info('Handle Completion called'); 
      
    // Log the entire request data for debugging
    \Log::info('Request Data:', $request->all());
      
    // Get instructions from session or set default value
    $instructions = session('assistant_instructions', 'No instructions available.');

    // Set the user message content
    //$user_message_content = "Python Course";
         $user_message_content = $request->input('messages.1.content', ''); // default to an empty string if not set

    // Initialize the messages array using proper array syntax
    $messages = [
        ['role' => 'system', 'content' => $instructions],
        ['role' => 'user', 'content' => $user_message_content]
    ];

    // Log the initialized messages (optional)
    \Log::info('Initialized messages:', ['messages' => $messages]);

    // Try to validate incoming request
    /* Your validation code is commented out, include if necessary */

    // Fetch the OpenAI API key from the environment
    $apiKey = env('OPENAI_API_KEY');

    // Specify the maximum number of tokens for the response
    $maxTokens = 16000;

    // Log before making the request to OpenAI API
    \Log::info('Preparing to call OpenAI API', [
        'model' => session('ai_formal_name', 'gpt-4o-mini'),
        'messages' => $messages,
        'max_tokens' => $maxTokens,
    ]);

    // Make a request to the OpenAI API
    try {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => session('ai_formal_name', 'gpt-4o-mini'),
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ]);

        // Log the raw API response
        \Log::info('OpenAI API Response:', ['response' => $response->body()]);

        // Get the response body
        $result = $response->json();

        // Process the result and prepare to return the view
        $answer = isset($result['choices']) && count($result['choices']) > 0 
            ? $result['choices'][0]['message']['content'] 
            : 'No answer found.';

        \Log::info('Generated Answer:', ['answer' => $answer]);

    } catch (\Exception $e) {
        \Log::error('Error during OpenAI API call: ' . $e->getMessage());
        return response()->json(['error' => 'An error occurred during the API call.'], 500);
    }

    // Add the user question and assistant response to messages
    //array_push($messages, ['role' => 'user', 'content' => $request->input('messages.0.content')]);
    array_push($messages, ['role' => 'assistant', 'content' => $answer]);

    // Return the result directly
    return view('assistant.completionTemplate', [
        'answer' => $answer,
        'messages' => $messages,
        'assistant' => (object)[
            'name' => session('assistant_name', 'Default Assistant Name'),
            'instructions' => $instructions,
        ]
    ]);
	}
  
  
  
 
  	public function showAssistantTemplate($id)
	{
    // Fetch the selected configuration from DB
    $config = OpenAIConfig::findOrFail($id);
    
    // Store the ID and the corresponding assistant_id in the session
    Session::put('assistant_config_id', $id);           // Store the configuration ID in session
    Session::put('assistant_id', $config->assistant_id); // Store the assistant_id in session

    return view('assistant.assistantTemplate', compact('config'));
	}

	
  
  
  
  	// Handle the form submission from the assistant template
  
	/*public function submitMessage(Request $request)
	{
    Log::info('Session assistant_id: ' . Session::get('assistant_id'));
    Log::info('Session assistant_config_id: ' . Session::get('assistant_config_id'));

    // Getting the ID from the session
    $id = Session::get('assistant_config_id');

    $request->validate([
        'user_messages.*' => 'required|string', // Validate each user message
    ]);

    // Fetch the corresponding OpenAIConfig entry
    $config = OpenAIConfig::findOrFail($id);

    // Get the default instructions
    $defaultInstructions = $config->instructions;

    // Prepare user messages by appending them to the instructions
    $userMessages = $request->input('user_messages');
    $formattedUserMessages = array_map(function($message) use ($defaultInstructions) {
        return $defaultInstructions . ' ' . $message; // Append the user message to the default instructions
    }, $userMessages);
    
    // Determine if we need to create a new thread or use the existing one
    if (!Session::has('thread_id')) {
        // Call the createThread Python script since thread_id is not set
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/createThread.py " . escapeshellarg(Session::get('assistant_id'));
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && isset($output[0])) {
            $threadId = trim($output[0]);
            Session::put('thread_id', $threadId);  // Store thread ID in session
            Log::info('Session thread_id: ' . $threadId);
        } else {
            Log::error('Error in createThread script. Output: ' . implode("\n", $output));
            return redirect()->back()->with('error', 'Failed to create thread.');
        }
    } else {
        // Use the existing thread ID from the session
        $threadId = Session::get('thread_id');
        Log::info('Using existing thread_id: ' . $threadId);
    }

    // Call runassistant Python script with the newly formatted user messages
    $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/runassistant.py " . escapeshellarg(Session::get('assistant_id')) . " " . escapeshellarg($threadId) . " " . escapeshellarg(implode(", ", $formattedUserMessages));
    exec($command, $output, $returnVar);

    if ($returnVar === 0) {
        Log::info('Run Assistant Output: ' . implode("\n", $output));

        // Extract assistant's responses from output
        $messages = array_map('trim', $output);
        $formattedMessages = [];

        foreach ($messages as $message) {
            // Check if the message contains a colon and can be split
            if (strpos($message, ': ') !== false) {
                list($role, $content) = explode(': ', $message, 2);
                
                // Replace newlines with HTML <br> tags for formatting
                $formattedContent = nl2br(htmlentities(str_replace(["\r\n", "\r", "\n"], '<br>', trim($content))));
                
                if ($role === 'assistant') {
                    // Format the assistant's response
                    $formattedMessages[] = "<strong>Assistant:</strong> " . $formattedContent;
                } else {
                    $formattedMessages[] = "<strong>You:</strong> " . nl2br(htmlentities($content));
                }
            } else {
                // If the message doesn't match expected format, add it as is
                $formattedMessages[] = "<strong>Note:</strong> Unexpected message format: " . nl2br(htmlentities($message));
            }
        }

        // Pass formatted messages to view
        return view('assistant.assistantTemplate', compact('config', 'formattedMessages'));
    } else {
        Log::error('Error in runassistant script. Output: ' . implode("\n", $output));
        return redirect()->back()->with('error', 'Failed to send message.');
    }
	}*/
  
  
  
  public function submitMessage(Request $request)
	{
    Log::info('Session assistant_id: ' . Session::get('assistant_id'));
    Log::info('Session assistant_config_id: ' . Session::get('assistant_config_id'));

    // Getting the ID from the session
    $id = Session::get('assistant_config_id');

    $request->validate([
        'user_messages.*' => 'required|string', // Validate each user message
    ]);

    // Fetch the corresponding OpenAIConfig entry
    $config = OpenAIConfig::findOrFail($id);

    // Get the default instructions
    $defaultInstructions = $config->instructions;

    // Prepare user messages by appending them to the instructions
    $userMessages = $request->input('user_messages');
    $formattedUserMessages = array_map(function($message) use ($defaultInstructions) {
        return $defaultInstructions . ' ' . $message; // Append the user message to the default instructions
    }, $userMessages);
    
    // Determine if we need to create a new thread or use the existing one
    if (!Session::has('thread_id')) {
        // Call the createThread Python script since thread_id is not set
        $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/createThread.py " . escapeshellarg(Session::get('assistant_id'));
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && isset($output[0])) {
            $threadId = trim($output[0]);
            Session::put('thread_id', $threadId);  // Store thread ID in session
            Log::info('Session thread_id: ' . $threadId);
        } else {
            Log::error('Error in createThread script. Output: ' . implode("\n", $output));
            return redirect()->back()->with('error', 'Failed to create thread.');
        }
    } else {
        // Use the existing thread ID from the session
        $threadId = Session::get('thread_id');
        Log::info('Using existing thread_id: ' . $threadId);
    }

    // Call runassistant Python script with the newly formatted user messages
    $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/runassistant.py " . escapeshellarg(Session::get('assistant_id')) . " " . escapeshellarg($threadId) . " " . escapeshellarg(implode(", ", $formattedUserMessages));
    exec($command, $output, $returnVar);

    if ($returnVar === 0) {
        Log::info('Run Assistant Output: ' . implode("\n", $output));

        // Here we will format the assistant's responses using the Python's format_to_html function 
        // that has already been implemented in the Python script.

        // Instead of wrapping the message in "Assistant:"
        $formattedMessages = [];

        foreach ($output as $message) {
            // Directly treat the assistant's messages and replace newlines with HTML <br> tags
            $formattedMessages[] = nl2br(htmlentities($message));
        }

        // Pass formatted messages to view
        return view('assistant.assistantTemplate', compact('config', 'formattedMessages'));
    } else {
        Log::error('Error in runassistant script. Output: ' . implode("\n", $output));
        return redirect()->back()->with('error', 'Failed to send message.');
    }
	}

  
  

  	
}
  	