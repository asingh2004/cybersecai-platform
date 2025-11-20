<ul class="list-group customlisting">
  
  	<li>
		<a class="btn  text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3 rounded-3 text-color text-color-hover {{ (request()->is('openai/create')) ? 'active-sidebar' : '' }}">Create AI Assistant</a>
	</li>
  
  	
	<li>
		<a class="btn  text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3 rounded-3 text-color text-color-hover {{ (request()->is('user/assistants')) ? 'active-sidebar' : '' }}" href="{{ route('user.assistants') }}">Test the Assistant</a>
	</li>
  
</ul>