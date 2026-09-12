<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Varsity Kitchen</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f4f6f9] p-6 font-['Nunito_Sans',sans-serif] text-[#1f2f3e]">
    <div class="max-w-md rounded-xl bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-[#04346a]">403</p>
        <h1 class="mt-2 text-2xl font-bold">You do not have access</h1>
        <p class="mt-2 text-sm text-[#898989]">{{ $exception->getMessage() ?: 'You do not have permission to perform this action.' }}</p>
        <a href="{{ route('admin.login') }}" class="mt-6 inline-block rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">
            Go to admin login
        </a>
    </div>
</body>
</html>
