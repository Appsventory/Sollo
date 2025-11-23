<?php

namespace App\Exceptions;

use App\Console\NineVerse;

class FunctionErrorRenderer
{
    public static function error404(){
        echo '<html lang="en">
        <head>
            <meta charset="utf-8">
            <meta content="width=device-width, initial-scale=1" name="viewport">
            <title>'.$_ENV['APP_NAME'].' | Oops! Page Not Found</title>
            <script src="https://cdn.tailwindcss.com">
            </script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;display=swap" rel="stylesheet">
            <style>
            body{font-family:Inter,sans-serif}.brand-text{color:#dc143c}.bg-brand{background-color:#dc143c}.border-brand{border-color:#dc143c} a,hr{color:inherit}progress,sub,sup{vertical-align:baseline}blockquote,body,dd,dl,fieldset,figure,h1,h2,h3,h4,h5,h6,hr,menu,ol,p,pre,ul{margin:0}dialog,fieldset,legend,menu,ol,ul{padding:0}.text-\[\#cbd5e1\],.text-gray-200,.text-gray-400,.text-gray-500,.text-white{--tw-text-opacity:1}.hover\:scale-105:hover,.transform{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}*,::after,::before{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgb(59 130 246 / 0.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: ;box-sizing:border-box;border:0 solid #e5e7eb}::backdrop{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgb(59 130 246 / 0.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: }::after,::before{--tw-content:""}:host,html{line-height:1.5;-webkit-text-size-adjust:100%;-moz-tab-size:4;tab-size:4;font-family:ui-sans-serif,system-ui,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";font-feature-settings:normal;font-variation-settings:normal;-webkit-tap-highlight-color:transparent}body{line-height:inherit}hr{height:0;border-top-width:1px}abbr:where([title]){-webkit-text-decoration:underline dotted;text-decoration:underline dotted}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}a{text-decoration:inherit}b,strong{font-weight:bolder}code,kbd,pre,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-feature-settings:normal;font-variation-settings:normal;font-size:1em}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative}sub{bottom:-.25em}sup{top:-.5em}table{text-indent:0;border-color:inherit;border-collapse:collapse}button,input,optgroup,select,textarea{font-family:inherit;font-feature-settings:inherit;font-variation-settings:inherit;font-size:100%;font-weight:inherit;line-height:inherit;letter-spacing:inherit;color:inherit;margin:0;padding:0}button,select{text-transform:none}button,input:where([type=button]),input:where([type=reset]),input:where([type=submit]){-webkit-appearance:button;background-color:transparent;background-image:none}:-moz-focusring{outline:auto}:-moz-ui-invalid{box-shadow:none}::-webkit-inner-spin-button,::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}summary{display:list-item}menu,ol,ul{list-style:none}textarea{resize:vertical}input::placeholder,textarea::placeholder{opacity:1;color:#9ca3af}[role=button],button{cursor:pointer}:disabled{cursor:default}audio,canvas,embed,iframe,img,object,svg,video{display:block;vertical-align:middle}img,video{max-width:100%;height:auto}[hidden]:where(:not([hidden=until-found])){display:none}.mx-auto{margin-left:auto;margin-right:auto}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.mb-6{margin-bottom:1.5rem}.mb-8{margin-bottom:2rem}.flex{display:flex}.inline-flex{display:inline-flex}.h-40{height:10rem}.h-5{height:1.25rem}.min-h-screen{min-height:100vh}.w-40{width:10rem}.w-5{width:1.25rem}.max-w-lg{max-width:32rem}.flex-col{flex-direction:column}.items-center{align-items:center}.justify-center{justify-content:center}.gap-2{gap:.5rem}.rounded-lg{border-radius:.5rem}.bg-\[\#161d2f\]{--tw-bg-opacity:1;background-color:rgb(22 29 47 / var(--tw-bg-opacity,1))}.p-6{padding:1.5rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.text-center{text-align:center}.text-2xl{font-size:1.5rem;line-height:2rem}.text-7xl{font-size:4.5rem;line-height:1}.font-extrabold{font-weight:800}.font-semibold{font-weight:600}.text-\[\#cbd5e1\]{color:rgb(203 213 225 / var(--tw-text-opacity,1))}.text-gray-200{color:rgb(229 231 235 / var(--tw-text-opacity,1))}.text-gray-400{color:rgb(156 163 175 / var(--tw-text-opacity,1))}.text-gray-500{color:rgb(107 114 128 / var(--tw-text-opacity,1))}.text-white{color:rgb(255 255 255 / var(--tw-text-opacity,1))}.transition-transform{transition-property:transform;transition-timing-function:cubic-bezier(0.4,0,0.2,1);transition-duration:150ms}.hover\:scale-105:hover{--tw-scale-x:1.05;--tw-scale-y:1.05}.hover\:bg-opacity-90:hover{--tw-bg-opacity:0.9}@media (min-width:768px){.md\:h-48{height:12rem}.md\:w-48{width:12rem}.md\:text-3xl{font-size:1.875rem;line-height:2.25rem}.md\:text-8xl{font-size:6rem;line-height:1}}
            </style>
        </head>
        <body class="bg-[#161d2f] text-[#cbd5e1] min-h-screen flex flex-col  items-center justify-center p-6">
            <main>
                <section class="text-center">
                    <!-- Illustration -->
                    <div class="mb-6 flex flex-col items-center justify-center">
                        <svg class="w-40 h-40 md:w-48 md:h-48 text-gray-500" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z">
                            </path>
                        </svg>
                    </div>

                    <!-- Heading -->
                    <h1 class="text-7xl md:text-8xl font-extrabold text-white mb-2">404</h1>
                    <h2 class="text-2xl md:text-3xl font-semibold text-gray-200 mb-4">Oops! Page Not Found</h2>
                    <p class="max-w-lg mx-auto text-gray-400 mb-8">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>

                    <!-- CTA -->
                    <a href="/"
                        class="inline-flex items-center gap-2 bg-brand hover:bg-opacity-90 text-white font-semibold py-3 px-6 rounded-lg transition-transform transform hover:scale-105">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Home </a>
                </section>
            </main>
        </body>
        </html>';
    }

    public static function error500(){
        echo '<html lang="en">
        <head>
            <meta charset="utf-8">
            <meta content="width=device-width, initial-scale=1" name="viewport">
            <title>'.$_ENV['APP_NAME'].' | Oops! Galaxy Crash</title>
            <script src="https://cdn.tailwindcss.com">
            </script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;display=swap" rel="stylesheet">
            <style>
            body{font-family:Inter,sans-serif}.brand-text{color:#dc143c}.bg-brand{background-color:#dc143c}.border-brand{border-color:#dc143c} a,hr{color:inherit}progress,sub,sup{vertical-align:baseline}blockquote,body,dd,dl,fieldset,figure,h1,h2,h3,h4,h5,h6,hr,menu,ol,p,pre,ul{margin:0}dialog,fieldset,legend,menu,ol,ul{padding:0}.text-\[\#cbd5e1\],.text-gray-200,.text-gray-400,.text-gray-500,.text-white{--tw-text-opacity:1}.hover\:scale-105:hover,.transform{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}*,::after,::before{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgb(59 130 246 / 0.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: ;box-sizing:border-box;border:0 solid #e5e7eb}::backdrop{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgb(59 130 246 / 0.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: }::after,::before{--tw-content:""}:host,html{line-height:1.5;-webkit-text-size-adjust:100%;-moz-tab-size:4;tab-size:4;font-family:ui-sans-serif,system-ui,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";font-feature-settings:normal;font-variation-settings:normal;-webkit-tap-highlight-color:transparent}body{line-height:inherit}hr{height:0;border-top-width:1px}abbr:where([title]){-webkit-text-decoration:underline dotted;text-decoration:underline dotted}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}a{text-decoration:inherit}b,strong{font-weight:bolder}code,kbd,pre,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-feature-settings:normal;font-variation-settings:normal;font-size:1em}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative}sub{bottom:-.25em}sup{top:-.5em}table{text-indent:0;border-color:inherit;border-collapse:collapse}button,input,optgroup,select,textarea{font-family:inherit;font-feature-settings:inherit;font-variation-settings:inherit;font-size:100%;font-weight:inherit;line-height:inherit;letter-spacing:inherit;color:inherit;margin:0;padding:0}button,select{text-transform:none}button,input:where([type=button]),input:where([type=reset]),input:where([type=submit]){-webkit-appearance:button;background-color:transparent;background-image:none}:-moz-focusring{outline:auto}:-moz-ui-invalid{box-shadow:none}::-webkit-inner-spin-button,::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}summary{display:list-item}menu,ol,ul{list-style:none}textarea{resize:vertical}input::placeholder,textarea::placeholder{opacity:1;color:#9ca3af}[role=button],button{cursor:pointer}:disabled{cursor:default}audio,canvas,embed,iframe,img,object,svg,video{display:block;vertical-align:middle}img,video{max-width:100%;height:auto}[hidden]:where(:not([hidden=until-found])){display:none}.mx-auto{margin-left:auto;margin-right:auto}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.mb-6{margin-bottom:1.5rem}.mb-8{margin-bottom:2rem}.flex{display:flex}.inline-flex{display:inline-flex}.h-40{height:10rem}.h-5{height:1.25rem}.min-h-screen{min-height:100vh}.w-40{width:10rem}.w-5{width:1.25rem}.max-w-lg{max-width:32rem}.flex-col{flex-direction:column}.items-center{align-items:center}.justify-center{justify-content:center}.gap-2{gap:.5rem}.rounded-lg{border-radius:.5rem}.bg-\[\#161d2f\]{--tw-bg-opacity:1;background-color:rgb(22 29 47 / var(--tw-bg-opacity,1))}.p-6{padding:1.5rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.text-center{text-align:center}.text-2xl{font-size:1.5rem;line-height:2rem}.text-7xl{font-size:4.5rem;line-height:1}.font-extrabold{font-weight:800}.font-semibold{font-weight:600}.text-\[\#cbd5e1\]{color:rgb(203 213 225 / var(--tw-text-opacity,1))}.text-gray-200{color:rgb(229 231 235 / var(--tw-text-opacity,1))}.text-gray-400{color:rgb(156 163 175 / var(--tw-text-opacity,1))}.text-gray-500{color:rgb(107 114 128 / var(--tw-text-opacity,1))}.text-white{color:rgb(255 255 255 / var(--tw-text-opacity,1))}.transition-transform{transition-property:transform;transition-timing-function:cubic-bezier(0.4,0,0.2,1);transition-duration:150ms}.hover\:scale-105:hover{--tw-scale-x:1.05;--tw-scale-y:1.05}.hover\:bg-opacity-90:hover{--tw-bg-opacity:0.9}@media (min-width:768px){.md\:h-48{height:12rem}.md\:w-48{width:12rem}.md\:text-3xl{font-size:1.875rem;line-height:2.25rem}.md\:text-8xl{font-size:6rem;line-height:1}}
            body{background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHhtbG5zOnhsaW5rPSdodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rJyB3aWR0aD0nNjAwJyBoZWlnaHQ9JzYwMCcgdmlld0JveD0nMCAwIDE1MCAxNTAnPgo8ZmlsdGVyIGlkPSdpJyB4PScwJyB5PScwJz4KCTxmZUNvbG9yTWF0cml4IHR5cGU9J21hdHJpeCcgdmFsdWVzPScxIDAgMCAwIDAgIDAgMSAwIDAgMCAgMCAwIDEgMCAwICAwIDAgMCAwIDAnIC8+CjwvZmlsdGVyPgo8ZmlsdGVyIGlkPSduJyB4PScwJyB5PScwJz4KCTxmZVR1cmJ1bGVuY2UgdHlwZT0ndHVyYnVsZW5jZScgYmFzZUZyZXF1ZW5jeT0nLjcnIHJlc3VsdD0nZnV6eicgbnVtT2N0YXZlcz0nMicgc3RpdGNoVGlsZXM9J3N0aXRjaCcvPgoJPGZlQ29tcG9zaXRlIGluPSdTb3VyY2VHcmFwaGljJyBpbjI9J2Z1enonIG9wZXJhdG9yPSdhcml0aG1ldGljJyBrMT0nMCcgazI9JzEnIGszPSctNzMnIGs0PScuMDEnIC8+CjwvZmlsdGVyPgo8cmVjdCB3aWR0aD0nMTAyJScgaGVpZ2h0PScxMDIlJyBmaWxsPScjMDMwMzFhJy8+CjxyZWN0IHg9Jy0xJScgeT0nLTElJyB3aWR0aD0nMTAyJScgaGVpZ2h0PScxMDIlJyBmaWxsPScjZmZmZmZmJyBmaWx0ZXI9J3VybCgjbiknIG9wYWNpdHk9JzEnLz4KPHJlY3QgeD0nLTElJyB5PSctMSUnIHdpZHRoPScxMDIlJyBoZWlnaHQ9JzEwMiUnIGZpbGw9JyMwMzAzMWEnIGZpbHRlcj0ndXJsKCNpKScgb3BhY2l0eT0nMScvPgo8L3N2Zz4=");}
            .brand{color:#DC143C}.float{animation:float 4s ease-in-out infinite}
            @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
            </style>
        </head>
        <body class="bg-[#03031A] text-[#cbd5e1] min-h-screen flex flex-col  items-center justify-center p-6">
            <main>
                <section class="text-center">
                    <!-- Illustration -->
                    <div class="mb-1 flex flex-col items-center justify-center">
                        <svg class="w-48 h-48 mx-auto mb-6 float" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 97.05"><title>planet-saturn-space</title>
                            <path d="M37.11,6.51A48.54,48.54,0,0,1,93,11.68l.27-.08c7-1.78,13.15-2.53,17.89-2.16,5.17.4,8.9,2.15,10.7,5.37,3.25,5.79-1.07,14.76-10.66,24.58-.58.59-1.19,1.19-1.81,1.8A48.53,48.53,0,0,1,30.12,85.64c-.85.22-1.69.42-2.51.61-13.36,3.07-23.25,2.07-26.5-3.72l0,0c-1.81-3.25-1.32-7.39,1.1-12.07,2.17-4.2,6-9,11.07-14l.21-.2A48.56,48.56,0,0,1,37.11,6.51Zm67.72,38.83A195.18,195.18,0,0,1,72.24,67.91l-.06,0A195.65,195.65,0,0,1,36,84a43.59,43.59,0,0,0,68.82-38.62Zm-80.59,26c3.39-.43,11.31-3.54,20.77-7.86,6.22-2.83,13-6.28,19.61-10s13-7.61,18.67-11.44c8.34-5.69,14.77-10.83,16.59-13.9q-.37-.7-.78-1.41A43.57,43.57,0,0,0,23.64,70.31l.6,1Z"/>
                        </svg>
                    </div>

                    <!-- Heading -->
                    <h1 class="text-7xl md:text-8xl font-extrabold text-white mb-2">500</h1>
                    <h2 class="text-2xl md:text-3xl font-semibold text-gray-200 mb-4">Galaxy Crash</h2>
                    <p class="max-w-lg mx-auto text-gray-400 mb-8">
                        Our engines hiccupped. The crew is on it—try again in a few light-seconds.
                    </p>

                    <!-- CTA -->
                    <a href="/"
                    class="inline-flex items-center gap-2 bg-brand hover:bg-opacity-90 text-white font-semibold py-3 px-6 rounded-lg transition-transform transform hover:scale-105">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Home
                    </a>
                </section>
            </main>
        </body>
        </html>';
    }

    public static function NixsHighlighting($data){
        extract($data);
        $name = NineVerse::NAME;
        $ErrorHandler = NineVerse::ERROR_HANDLER_VERSION;
        
        $fileBasename = basename($codePreview["file"]);
        $filePath = htmlspecialchars(str_replace(dirname(__DIR__, 3), "", $file));
        $exceptionClassEsc = htmlspecialchars($exceptionClass);
        $messageEsc = htmlspecialchars($message);
        $urlEsc = htmlspecialchars($requestData["url"]);
        
        // Build code lines
        $codeLines = '';
        foreach ($codePreview["lines"] as $lineNum => $lineCode) {
            $errorClass = $lineNum == $codePreview["errorLine"] ? "error-line pl-4" : "";
            $codeLines .= "<div class='line py-1 {$errorClass}'><code>" . htmlspecialchars($lineCode) . "</code></div>";
        }
        
        // Build stack trace
        $stackTraceHTML = '';
        foreach ($data['stackTrace'] as $frame) {
            $showCondition = $frame["isVendor"] ? "false" : "true";
            $functionEsc = htmlspecialchars($frame["function"]);
            
            $fileInfo = '';
            if ($frame["file"] !== "[internal function]") {
                $filePathEsc = htmlspecialchars(str_replace(dirname(__DIR__, 3), "", $frame["file"]));
                $lineInfo = $frame["line"] ? "<span class='text-yellow-400'>:{$frame["line"]}</span>" : "";
                $fileInfo = "<div class='text-xs text-gray-500'>{$filePathEsc}{$lineInfo}</div>";
            } else {
                $fileInfo = "<div class='text-xs text-gray-600 italic'>[internal function]</div>";
            }
            
            $stackTraceHTML .= <<<HTML
            <div x-show="showVendor || {$showCondition}" class="p-4 hover:bg-gray-900/50 transition">
                <div class="flex items-start gap-4">
                    <span class="text-red-400 font-mono text-sm flex-shrink-0">#{$frame["index"]}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-blue-400 font-mono text-sm mb-1">{$functionEsc}</div>
                        {$fileInfo}
                    </div>
                </div>
            </div>
            HTML;
        }
        
        // GET Parameters
        $getParams = '';
        if (!empty($requestData["get"])) {
            $getJson = json_encode($requestData["get"], JSON_PRETTY_PRINT);
            $getParams = <<<HTML
            <div>
                <div class="text-xs text-gray-500 uppercase mb-2">GET Parameters</div>
                <div class="bg-gray-900 rounded p-4 font-mono text-sm"><pre>{$getJson}</pre></div>
            </div>
            HTML;
        }
        
        // POST Data
        $postData = '';
        if (!empty($requestData["post"])) {
            $postJson = json_encode($requestData["post"], JSON_PRETTY_PRINT);
            $postData = <<<HTML
            <div>
                <div class="text-xs text-gray-500 uppercase mb-2">POST Data</div>
                <div class="bg-gray-900 rounded p-4 font-mono text-sm"><pre>{$postJson}</pre></div>
            </div>
            HTML;
        }
        
        // Session Data
        $sessionHTML = !empty($sessionData) 
            ? "<div class='bg-gray-900 rounded p-4 font-mono text-sm max-h-96 overflow-y-auto'><pre>" . json_encode($sessionData, JSON_PRETTY_PRINT) . "</pre></div>"
            : "<div class='text-center py-8 text-gray-500'><svg class='w-12 h-12 mx-auto mb-3 opacity-50' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4'/></svg>No session data</div>";
        
        $lineCounter = $codePreview["errorLine"] - count($codePreview["lines"]) / 2;
        $copyText = addslashes($exceptionClass) . ': ' . addslashes($message) . '\nFile: ' . addslashes($file) . ':' . $line;
        $googleQuery = urlencode($exceptionClass . " " . $message);
        $soQuery = urlencode($message);
        
        $headersJson = json_encode($requestData["headers"], JSON_PRETTY_PRINT);
        $serverJson = json_encode($serverData, JSON_PRETTY_PRINT);
        $envJson = json_encode($environmentData, JSON_PRETTY_PRINT);
        $phpVersion = PHP_VERSION;
        $phpOS = php_uname("s");
        
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$exceptionClassEsc} - {$_ENV['APP_NAME']}</title>
                <script src="https://cdn.tailwindcss.com"></script>
                <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
                <style>
                    body {background: #161d2f;font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;}
                    .line-numbers {counter-reset: line-counter {$lineCounter};}
                    .line-numbers .line::before {counter-increment: line-counter;content: counter(line-counter);display: inline-block;width: 3rem;text-align: right;margin-right: 1.5rem;color: #6b7280;user-select: none;}
                    .error-line {background: rgba(220, 20, 60, 0.15);border-left: 4px solid #DC143C;}
                    pre {margin: 0;white-space: pre-wrap;word-break: break-word;}
                    .tab-active {color: #f87171;border-bottom: 2px solid #f87171;}
                </style>
            </head>
            <body class="text-gray-300"
                x-data="{ 
                    activeTab: 'request', 
                    showVendor: false,
                    copied: false,
                    copyError() {
                        const text = '{$copyText}';
                        navigator.clipboard.writeText(text);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    },
                    searchGoogle() {
                        window.open('https://www.google.com/search?q={$googleQuery}', '_blank');
                    },
                    searchStackOverflow() {
                        window.open('https://stackoverflow.com/search?q={$soQuery}', '_blank');
                    }
                }">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-900/30 to-red-800/20 border-b border-red-900/50">
                    <div class="max-w-7xl mx-auto px-6 py-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <h1 class="text-3xl font-bold text-red-400">{$exceptionClassEsc}</h1>
                                </div>
                                <p class="text-lg text-gray-300 mb-4">{$messageEsc}</p>
                                <div class="flex items-center gap-4 text-sm">
                                    <span class="text-gray-400">
                                        <span class="text-gray-500">at</span>
                                        <span class="text-blue-400">{$filePath}</span>
                                        <span class="text-gray-500">:</span>
                                        <span class="text-yellow-400">{$line}</span>
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="copyError()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-sm flex items-center gap-2 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                                </button>
                                <button @click="searchGoogle()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-sm flex items-center gap-2 transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
                                    </svg>
                                    Google
                                </button>
                                <button @click="searchStackOverflow()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-sm flex items-center gap-2 transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M15.725 0l-1.72 1.277 6.39 8.588 1.716-1.277L15.725 0zm-3.94 3.418l-1.369 1.644 8.225 6.85 1.369-1.644-8.225-6.85zm-3.15 4.465l-.905 1.94 9.702 4.517.904-1.94-9.701-4.517zm-1.85 4.86l-.44 2.093 10.473 2.201.44-2.092-10.473-2.203zM1.89 15.47V24h19.19v-8.53h-2.133v6.397H4.021v-6.396H1.89zm4.265 2.133v2.13h10.66v-2.13H6.154Z"/>
                                    </svg>
                                    Stack Overflow
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Code Preview -->
                <div class="max-w-7xl mx-auto px-6 py-6">
                    <div class="bg-gray-900/50 rounded-xl border border-gray-800 overflow-hidden">
                        <div class="bg-gray-900 px-6 py-3 border-b border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                </div>
                                <span class="text-sm text-gray-400">{$fileBasename}</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <div class="line-numbers p-6 text-sm">{$codeLines}</div>
                        </div>
                    </div>
                </div>

                <!-- Stack Trace -->
                <div class="max-w-7xl mx-auto px-6 pb-6">
                    <div class="bg-gray-900/50 rounded-xl border border-gray-800 overflow-hidden">
                        <div class="bg-gray-900 px-6 py-3 border-b border-gray-800 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-200">Stack Trace</h3>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" x-model="showVendor" class="rounded bg-gray-800 border-gray-700 text-red-500">
                                <span class="text-gray-400">Show vendor frames</span>
                            </label>
                        </div>
                        <div class="divide-y divide-gray-800 max-h-96 overflow-y-auto">{$stackTraceHTML}</div>
                    </div>
                </div>

                <!-- Request Info Tabs -->
                <div class="max-w-7xl mx-auto px-6 pb-6">
                    <div class="bg-gray-900/50 rounded-xl border border-gray-800 overflow-hidden">
                        <div class="bg-gray-900 border-b border-gray-800 overflow-x-auto">
                            <div class="flex">
                                <button @click="activeTab = 'request'" :class="activeTab === 'request' ? 'tab-active' : ''" class="px-6 py-3 text-sm font-medium hover:text-red-400 transition whitespace-nowrap">Request</button>
                                <button @click="activeTab = 'headers'" :class="activeTab === 'headers' ? 'tab-active' : ''" class="px-6 py-3 text-sm font-medium hover:text-red-400 transition whitespace-nowrap">Headers</button>
                                <button @click="activeTab = 'session'" :class="activeTab === 'session' ? 'tab-active' : ''" class="px-6 py-3 text-sm font-medium hover:text-red-400 transition whitespace-nowrap">Session</button>
                                <button @click="activeTab = 'server'" :class="activeTab === 'server' ? 'tab-active' : ''" class="px-6 py-3 text-sm font-medium hover:text-red-400 transition whitespace-nowrap">Server</button>
                                <button @click="activeTab = 'environment'" :class="activeTab === 'environment' ? 'tab-active' : ''" class="px-6 py-3 text-sm font-medium hover:text-red-400 transition whitespace-nowrap">Environment</button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div x-show="activeTab === 'request'" class="space-y-4">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase mb-2">Request Details</div>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><span class="text-gray-500">Method:</span> <span class="text-yellow-400">{$requestData["method"]}</span></div>
                                        <div><span class="text-gray-500">URL:</span> <span class="text-blue-400">{$urlEsc}</span></div>
                                        <div><span class="text-gray-500">IP:</span> <span>{$requestData["ip"]}</span></div>
                                    </div>
                                </div>
                                {$getParams}
                                {$postData}
                            </div>
                            <div x-show="activeTab === 'headers'" class="bg-gray-900 rounded p-4 font-mono text-sm max-h-96 overflow-y-auto"><pre>{$headersJson}</pre></div>
                            <div x-show="activeTab === 'session'">{$sessionHTML}</div>
                            <div x-show="activeTab === 'server'" class="bg-gray-900 rounded p-4 font-mono text-sm max-h-96 overflow-y-auto"><pre>{$serverJson}</pre></div>
                            <div x-show="activeTab === 'environment'" class="bg-gray-900 rounded p-4 font-mono text-sm"><pre>{$envJson}</pre></div>
                        </div>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto px-6 pb-8">
                    <div class="text-center text-sm text-gray-500">
                        <p>{$name} Framework - Error Handler v{$ErrorHandler}</p>
                        <p class="mt-1">PHP {$phpVersion} • {$phpOS}</p>
                    </div>
                </div>
            </body>
        </html>
        HTML;
    }

    public static function InlineException($data){
        $exception = $data['exception'];
        $codePreview = $data['codePreview'];
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error | ". $_ENV['APP_NAME'] ."</title>
            <style>
                body{font-family:sans-serif;margin:0;background:#161d2f;color:#e5e7eb}
                .container{max-width:1200px;margin:20px auto;padding:20px}
                .header{background:#1f2937;padding:20px;border-left:4px solid #DC143C;margin-bottom:20px}
                .header h1{margin:0 0 10px;color:#DC143C;font-size:1.5rem}
                .header p{margin:5px 0;color:#9ca3af}
                .code{background:#1f2937;padding:20px;overflow:auto;margin:20px 0}
                .line{padding:2px 10px;font-family:monospace}
                .line.error{background:rgba(220,20,60,0.2)}
                .line-number{color:#6b7280;margin-right:20px;user-select:none}
                .stack{background:#1f2937;padding:20px;margin:20px 0}
                .stack-item{padding:10px;border-bottom:1px solid #374151;font-family:monospace;font-size:0.9rem}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . htmlspecialchars(get_class($exception)) . "</h1>
                    <p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>
                    <p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . " : " . $exception->getLine() . "</p>
                </div>
                <div class='code'>";
                
                foreach ($codePreview['lines'] as $lineNum => $lineCode) {
                    $isError = $lineNum == $codePreview['errorLine'];
                    $class = $isError ? 'line error' : 'line';
                    echo "<div class='$class'><span class='line-number'>$lineNum</span>" . htmlspecialchars($lineCode) . "</div>";
                }
                
                echo "</div>
                <div class='stack'>
                    <h3>Stack Trace</h3>";
                
                foreach ($data['stackTrace'] as $i => $frame) {
                    echo "<div class='stack-item'>#$i " . htmlspecialchars($frame['file'] ?? 'unknown') . ":" . ($frame['line'] ?? '?') . "</div>";
                }
                
                echo "</div>
            </div>
        </body>
        </html>";
    }
}