<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>{{env('APP_NAME')}} | {{$title}}</title>
    <script src="https://cdn.tailwindcss.com">
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .brand-text {
            color: #DC143C;
        }
        .bg-brand {
            background-color: #DC143C;
        }
        .border-brand {
            border-color: #DC143C;
        }
    </style>
</head>

<body class="bg-[#161d2f] text-[#cbd5e1] min-h-screen flex flex-col  items-center justify-center p-6">
    <main>
        @yield('content')
    </main>
</body>

</html>