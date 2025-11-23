@extends('layouts.master')
@section('content')
    <!-- Header -->
        <header class="absolute top-0 right-0 p-8">
            <div class="flex items-center space-x-5">
                <a href="{{ $data['repo_url'] }}" target="_blank" rel="noopener noreferrer" title="NineVerse Ecosystem" class="text-gray-400 hover:text-white transition-colors duration-300">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM11 16.5C8.24 16.5 6 14.26 6 11.5C6 9.24 7.67 7.2 9.8 6.7C10.2 7.7 11 8.8 11 10C11 11.1 10.2 12.2 9.8 13.2C10.2 13.1 10.6 13 11 13C12.93 13 14.5 14.57 14.5 16.5H11ZM15.2 17.3C14.8 16.3 14 15.2 14 14C14 12.9 14.8 11.8 15.2 10.8C14.8 10.9 14.4 11 14 11C12.07 11 10.5 9.43 10.5 7.5H14.5C16.76 7.5 18.5 9.24 18.5 11.5C18.5 13.76 16.83 15.8 14.7 16.3C14.7 16.3 15.2 17.3 15.2 17.3Z" fill="currentColor"/>
                    </svg>
                </a>
                <a href="{{ $data['repo_url'] }}" target="_blank" rel="noopener noreferrer" title="GitHub Repository" class="text-gray-400 hover:text-white transition-colors duration-300">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.168 6.839 9.49.5.092.682-.217.682-.482 0-.237-.009-.868-.014-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.031-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.378.203 2.398.1 2.651.64.7 1.03 1.595 1.03 2.688 0 3.848-2.338 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.001 10.001 0 0022 12c0-5.523-4.477-10-10-10z" clip-rule="evenodd"></path>
                    </svg>
                </a>
                <a href="{{ $data['base_domain'] }}/docs" target="_blank"  rel="noopener noreferrer" title="Documentation" class="text-gray-400 hover:text-white transition-colors duration-300">
                     <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"></path>
                    </svg>
                </a>
            </div>
        </header>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col justify-center px-6 py-12">
        <!-- Hero Section -->
        <div class="text-center max-w-4xl mx-auto mb-20">
            <h2 class="text-5xl md:text-6xl font-bold text-white mb-4">
                Welcome to <span class="brand-text">{{ $data["name"] }}</span>
            </h2>
            <p class="text-xl text-gray-300 mb-2">A lightweight PHP MVC framework</p>
            <p class="text-gray-400 mb-8">Version {{ $data["version"] }} ({{ $data["codename"] }})</p>
            <a href="{{ $data['base_domain'] }}" target="_blank" class="inline-block bg-brand hover:bg-opacity-90 text-white font-semibold py-3 px-8 rounded-lg transition-all transform hover:scale-105">
                Get Started
            </a>
        </div>

        <!-- Features Section -->
        <div class="max-w-6xl mx-auto w-full">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Fany Card -->
                <div class="bg-gray-900 bg-opacity-50 border border-gray-700 rounded-lg p-8 hover:border-brand transition-colors transform hover:-translate-y-2 transition-transform duration-300">
                    <div class="mb-4">
                        <div class="bg-brand w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">Fany (CLI Tool v{{ $data["v_fany_cli"] }})</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">
                        Fany is a command-line interface (CLI) tool designed to help developers easily execute various essential commands for application management. With Fany, tasks such as code generation, database migrations, controller creation, and other development activities become faster, more organized, and efficient.
                    </p>
                </div>

                <!-- Nixs Card -->
                <div class="bg-gray-900 bg-opacity-50 border border-gray-700 rounded-lg p-8 hover:border-brand transition-colors transform hover:-translate-y-2 transition-transform duration-300">
                    <div class="mb-4">
                        <div class="bg-brand w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">Nixs (Template Engine v{{ $data["v_nixs_te"] }})</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">
                        With Nixs, writing PHP code becomes much cleaner and more organized. This template engine is designed to simplify your code structure while improving readability.
                    </p>
                </div>
            </div>
        </div>
    </main>
@endsection