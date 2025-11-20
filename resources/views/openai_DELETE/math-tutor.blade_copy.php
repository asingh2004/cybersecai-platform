@extends('template')
@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 min-height">
  
    <h1>Chat with Math Tutor</h1>
    
    <form method="POST" action="{{ route('math-tutor.answer') }}">
        @csrf
      
      	<!-- System Instruction as a hidden input -->
        <input type="hidden" name="messages[0][role]" value="system">
        <input type="hidden" name="messages[0][content]" value="You are a helpful math tutor. Please answer the following questions and explain detailed steps.">
      
        <label for="question">Ask a question:</label>
        <textarea name="messages[0][content]" id="question" placeholder="Enter your question here...">{{ old('messages.0.content') }}</textarea>
        <input type="hidden" name="messages[0][role]" value="user">
        <button type="submit">Send</button>
    </form>

    @if(isset($answer))
        <div class="response">
            <h3>Response:</h3>
            <!--<p>{{ $answer }}</p>-->
          	<pre>{{ $answer }}</pre>
        </div>
    @endif
  
</div>
