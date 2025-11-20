{{-- resources/views/playground.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playground Interface</title>
    <link rel="stylesheet" href="{{ asset('css/style2.css') }}">
</head>
<body>
    <div class="container">
        <header>
            <h1>University of Adelaide</h1>
            <h2>Playground</h2>
        </header>
        
        <main>
            <div class="coder">
                <h3>Coder</h3>

                    @csrf
                    <label for="instructions">Instructions:</label>
                    <textarea id="instructions" name="instructions" rows="4" placeholder="Enter instructions here..."></textarea>

                    <label for="model">Model:</label>
                    <select id="model" name="model">
                        <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                        <option value="gpt-4">gpt-4</option>
                    </select>

                    <label for="responseFormat">Response Format:</label>
                    <input type="text" id="responseFormat" name="responseFormat" placeholder="Response format...">

                    <button type="submit">Run</button>
           
            </div>
        </main>

        <footer>
            <p>Playground messages can be saved by pressing your page's submit button.</p>
        </footer>
    </div>
    <script src="{{ asset('js/script.js') }}"></script>
</body>
</html>