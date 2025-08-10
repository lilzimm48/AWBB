<?php
// PHP error reporting for debugging. Should be turned off on a live production server.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// This simple page does not use the file system logic from the previous version.
// The code below is a static HTML page that can be saved as index.php.
// The PHP at the top is for error reporting, but the rest of the content is standard HTML, CSS, and JavaScript.
?>
<!DOCTYPE html>
<html>
<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-EGBE5NNG6C"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-EGBE5NNG6C');
    </script>
    <title>jacobz.xyz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <style>
        /* Global Reset/Base */
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            height: 100vh; /* Full viewport height */
            display: flex;
            flex-direction: column; /* Stack header and main container */
            cursor: url("cursor.png"), auto;
        }

        /* Primary Colors - Defined Hues */
        :root {
            /* Light Mode Colors */
            --light-bg: #FFFFFF;
            --light-text: #000000;
            --light-gray-text: #555555;
            --light-accent: rgb(0, 0, 255); /* RGB Blue */
            --light-border: #DDDDDD; /* Light gray for borders */

            /* Dark Mode Colors */
            --dark-bg: #000000; /* Black background */
            --dark-text: #FFFFFF; /* White text */
            --dark-gray-text: #AAAAAA; /* Lighter gray for non-interactive dark mode */
            --dark-accent: rgb(255, 255, 0); /* RGB Yellow */
            --dark-border: #333333; /* Dark gray for borders */
        }

        /* Apply colors based on html class */
        html {
            background-color: var(--light-bg);
            color: var(--light-text);
        }
        html.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }
        body {
            background-color: inherit;
            color: inherit;
        }

        /* Header / Title Styling */
        h1, h2 {
            color: var(--light-accent); /* Inherit from body/html */
        }

        html.dark-mode h1,html.dark-mode h2 {
            color: var(--dark-accent); /* Inherit from body/html */
        }

        /* Breadcrumb Navigation */
        .breadcrumb {
            width: 100%;
            padding: 20px; /* Consistent vertical padding */
            display: flex;
            flex-wrap: wrap;
            align-items: center; /* Ensures items align vertically */
            background-color: var(--light-bg); /* Default Light Mode */
            gap: 10px; /* Space between breadcrumb items */
        }
        html.dark-mode .breadcrumb {
            background-color: var(--dark-bg);
        }

        /* Logo Container for crossfade */
        .breadcrumb #logo-container {
            width: 120px; /* Increased width */
            height: 120px; /* Increased height */
            margin-right: 15px;
            vertical-align: middle;
            cursor: pointer;
            position: relative;
            display: inline-block;
            overflow: hidden;
        }

        /* Base Logo Image (logo.png or darklogo.png) */
        .breadcrumb #logo-base { 
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            opacity: 1;
            transition: opacity 1s ease-in-out;
            display: block;
        }

        /* Overlay Logo Image (logo2.png or darklogo2.png) */
        .breadcrumb #logo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }
        /* On hover, fade out the base and fade in the overlay */
        .breadcrumb #logo-container:hover #logo-base {
            opacity: 0;
        }
        .breadcrumb #logo-container:hover #logo-overlay {
            opacity: 1;
        }


        /* NEW: Shared button styling for navigation elements (accent color by default) */
        .nav-button-style {
            background: none;
            border: 2px solid var(--light-accent);
            color: var(--light-accent);
            padding: 5px 10px;
            font-size: 0.9em;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none !important;
            transition: background-color 0.1s ease, color 0.1s ease, border-color 0.1s ease;
            text-transform: lowercase;
            display: inline-block;
            line-height: 1.2;
            white-space: nowrap;
            box-sizing: border-box;
        }
        html.dark-mode .nav-button-style {
            border-color: var(--dark-accent);
            color: var(--dark-accent);
        }
        /* Ensure visited state maintains accent color */
        .nav-button-style:visited {
            color: var(--light-accent);
            border-color: var(--light-accent);
        }
        html.dark-mode .nav-button-style:visited {
            color: var(--dark-accent);
            border-color: var(--dark-accent);
        }
        .nav-button-style:hover:not(:disabled) {
            background-color: var(--light-accent);
            color: var(--light-bg);
            border-color: var(--light-accent);
        }
        html.dark-mode .nav-button-style:hover:not(:disabled) {
            background-color: var(--dark-accent);
            color: var(--dark-bg);
            border-color: var(--dark-accent);
        }

        /* --- Main Content Container for Under Construction Page --- */
        .under-construction-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .under-construction-container h2 {
            font-size: 2.5em;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .under-construction-container p {
            font-size: 1.2em;
            line-height: 1.6;
            max-width: 600px;
            margin-bottom: 30px;
        }
        
        /* --- Fillout Form Container --- */
        .fillout-container {
            width: 50vw;
            max-width: 1500px; /* Adjust to fit the form's natural width */
        }
        
        /* --- Mobile Optimizations --- */
        @media (max-width: 768px) {
            .under-construction-container h2 {
                font-size: 2em;
            }
            .under-construction-container p {
                font-size: 1em;
            }
            .breadcrumb #logo-container {
                width: 70px;
                height: 70px;
            }
        }
    </style>
    <script>
        (function() {
            const STORAGE_KEY = 'darkModeEnabled';
            const HTML_ELEMENT = document.documentElement;
            const BODY_CLASS = 'dark-mode';
            const savedPreference = localStorage.getItem(STORAGE_KEY);
            if (savedPreference === 'true') {
                HTML_ELEMENT.classList.add(BODY_CLASS);
            }
        })();
    </script>

    <meta name="description" content="Freelance media consultant in Williamsport, PA. Website under construction.">
    <meta name="keywords" content="web design, under construction, coming soon, freelance, williamsport pa">
    <meta name="author" content="Jacob Zimmerman">
</head>
<body>
    <header>
        <div class="breadcrumb">
            <div id="logo-container">
                <img id="logo-base" src="/logo.png" alt="Logo">
                <img id="logo-overlay" src="/logo2.png" alt="Logo Hover">
            </div>
            <div class="random-button-container">
                <button id="dark-mode-toggle" class="nav-button-style">dark mode</button>
            </div>
        </div>
    </header>

    <div class="under-construction-container">
        <h2>website under construction :O</h2>
        <p>welcome! i'm currently doing some work to my website. in the meantime, feel free to send me a message using the form below</p>
        <p>VVVVVVVVVVVVVVVVVV</p>

        <div class="fillout-container">
            <div style="width:100%;height:500px;" data-fillout-id="m7GMw7PLUyus" data-fillout-embed-type="standard" data-fillout-inherit-parameters data-fillout-dynamic-resize></div>
        </div>
    </div>
    
    <script src="https://server.fillout.com/embed/v1/"></script>

    <script>
        const logoSources = {
            light: { static: '/logo.png', hover: '/logo2.png' },
            dark:  { static: '/darklogo.png', hover: '/darklogo2.png' }
        };
        const logoContainer = document.getElementById('logo-container');
        const logoBaseImg = document.getElementById('logo-base'); 
        const logoOverlayImg = document.getElementById('logo-overlay');
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const HTML_ELEMENT = document.documentElement;
        const BODY_CLASS = 'dark-mode';
        const STORAGE_KEY = 'darkModeEnabled';

        function updateLogoVisuals(isDarkMode) {
            const currentTheme = isDarkMode ? 'dark' : 'light';
            if (logoBaseImg && logoOverlayImg) { 
                logoBaseImg.src = logoSources[currentTheme].static; 
                logoOverlayImg.src = logoSources[currentTheme].hover; 
                
                logoBaseImg.style.opacity = '1';
                logoOverlayImg.style.opacity = '0';
            }
        }

        function toggleDarkMode() {
            HTML_ELEMENT.classList.toggle(BODY_CLASS); 
            const isDarkMode = HTML_ELEMENT.classList.contains(BODY_CLASS);
            localStorage.setItem(STORAGE_KEY, isDarkMode);
            darkModeToggle.textContent = isDarkMode ? 'light mode' : 'dark mode'; 
            updateLogoVisuals(isDarkMode); 
        }

        function applySavedDarkModePreference() {
            const savedPreference = localStorage.getItem(STORAGE_KEY);
            const isDarkMode = savedPreference === 'true'; 

            if (isDarkMode) {
                HTML_ELEMENT.classList.add(BODY_CLASS);
                darkModeToggle.textContent = 'light mode'; 
            } else {
                HTML_ELEMENT.classList.remove(BODY_CLASS);
                darkModeToggle.textContent = 'dark mode'; 
            }
            updateLogoVisuals(isDarkMode); 
        }

        if (logoContainer && logoBaseImg && logoOverlayImg) { 
            logoContainer.addEventListener('mouseover', () => {
                logoBaseImg.style.opacity = '0'; 
                logoOverlayImg.style.opacity = '1'; 
            });
            logoContainer.addEventListener('mouseout', () => {
                logoBaseImg.style.opacity = '1';
                logoOverlayImg.style.opacity = '0';
            });
        }
        
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', toggleDarkMode);
            applySavedDarkModePreference();
        }
    </script>
</body>
</html>