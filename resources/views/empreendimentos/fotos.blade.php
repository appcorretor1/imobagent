<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Fotos do empreendimento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100">
  <div class="max-w-5xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-semibold mb-4">Fotos do empreendimento</h1>

    @if(empty($urls))
      <p class="text-gray-600">Nenhuma foto cadastrada ainda.</p>
    @else
      <div class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
        @foreach($urls as $url)
          <a href="{{ $url }}" target="_blank"
             class="block bg-white rounded shadow-sm overflow-hidden">
            <img src="{{ $url }}" alt=""
                 class="w-full h-40 object-cover">
          </a>
        @endforeach
      </div>
    @endif
  </div>
</body>
</html>
