<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the recipient email address
// NOTE: This variable ($recipientEmail) and the $formMessage/$formError variables
// are no longer actively used by this PHP script for form submission processing,
// as the form is now handled by Fillout's embedded code.
// You will configure email notifications and data storage directly within Fillout.
$recipientEmail = 'lilzimm48@gmail.com';

$formMessage = '';
$formError = false;

// The previous PHP form processing logic (if ($_SERVER["REQUEST_METHOD"] == "POST"))
// has been removed as Fillout handles form submissions externally.
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-EGBE5NNG6C"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-EGBE5NNG6C');
    </script>
    <title>Contact - my portfolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Add your existing global styles and media queries here OR link to your existing stylesheet */
        /* For demonstration, I'll include a basic set of styles similar to yours */

        /* Global Reset/Base */
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Primary Colors - Defined Hues */
        :root {
            /* Light Mode Colors */
            --light-bg: #FFFFFF;
            --light-text: #000000;
            --light-gray-text: #555555;
            --light-accent: rgb(0, 0, 255); /* RGB Blue */

            /* Dark Mode Colors */
            --dark-bg: #000000; /* Black background */
            --dark-text: #FFFFFF; /* White text */
            --dark-gray-text: #AAAAAA; /* Lighter gray for non-interactive dark mode */
            --dark-accent: rgb(255, 255, 0); /* RGB Yellow */
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
            font-weight: normal;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 5px;
            font-size: 1.5em;
            letter-spacing: 1px;
            color: inherit;
        }

        /* Breadcrumb Navigation - copied from index.php for consistency */
        .breadcrumb {
            width: 100%;
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            background-color: var(--light-bg);
            gap: 10px; /* Space between breadcrumb items */
            flex-shrink: 0; /* Prevent it from shrinking */
        }
        html.dark-mode .breadcrumb {
            background-color: var(--dark-bg);
        }

        .breadcrumb #logo-container {
            width: 120px;
            height: 120px;
            margin-right: 15px;
            vertical-align: middle;
            cursor: pointer;
            position: relative;
            display: inline-block;
            overflow: hidden;
        }

        .breadcrumb #logo-base {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: contain;
            opacity: 1;
            transition: opacity 1s ease-in-out;
            display: block;
        }

        .breadcrumb #logo-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: contain;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .breadcrumb #logo-container:hover #logo-base { opacity: 0; }
        .breadcrumb #logo-container:hover #logo-overlay { opacity: 1; }

        /* General link styling within breadcrumb, excluding buttons */
        .breadcrumb a:not(.nav-button-style) {
            text-decoration: none !important;
            color: var(--light-text);
            font-weight: bold;
            padding: 2px 0;
            transition: color 0.1s ease;
        }
        html.dark-mode .breadcrumb a:not(.nav-button-style) { color: var(--dark-text); }
        .breadcrumb a:not(.nav-button-style):hover { color: var(--light-accent); }
        html.dark-mode .breadcrumb a:not(.nav-button-style):hover { color: var(--dark-accent); }
        .breadcrumb a:not(.nav-button-style):visited {
            text-decoration: none !important;
            color: var(--light-text);
        }
        html.dark-mode .breadcrumb a:not(.nav-button-style):visited { color: var(--dark-text); }

        .breadcrumb .separator {
            color: var(--light-text);
            font-weight: normal;
            margin: 0 5px;
        }
        html.dark-mode .breadcrumb .separator { color: var(--dark-text); }

        /* Shared button styling for navigation elements (accent color by default) */
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
        .nav-button-style:disabled {
            border-color: var(--light-gray-text);
            color: var(--light-gray-text);
            cursor: not-allowed;
            opacity: 0.6;
        }
        html.dark-mode .nav-button-style:disabled {
            border-color: var(--dark-gray-text);
            color: var(--dark-gray-text);
        }
        /* Dark Mode Toggle Button Styling - Now inherits from nav-button-style */
        #dark-mode-toggle { /* All styling inherited from .nav-button-style */ }

        /* Random file button container positioning - Always top right */
        .breadcrumb div.random-button-container {
            margin-left: auto; /* Pushes it to the far right */
            display: flex;
            align-items: center;
            gap: 10px; /* Space between random file and dark mode toggle */
        }


        /* Contact Form Specific Styles */
        .contact-container {
            flex-grow: 1; /* Allow it to take up remaining vertical space */
            padding: 40px 20px;
            margin: 20px 0;
            background-color: var(--light-bg);
            color: var(--light-text);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: left; /* Align text within the form container to left */
            display: flex;
            flex-direction: column;
        }
        html.dark-mode .contact-container {
            background-color: var(--dark-bg);
            color: var(--dark-text);
            border-color: var(--dark-gray-text);
            box-shadow: 0 4px 8px rgba(255, 255, 255, 0.1);
        }

        .contact-container h1 {
            text-align: center;
            font-size: 2.2em;
            margin-bottom: 30px;
            color: var(--light-accent);
        }
        html.dark-mode .contact-container h1 {
            color: var(--dark-accent);
        }

        /* The .contact-form styles below are no longer directly applied to the form elements themselves,
           as Fillout renders its own elements. These styles might still affect the container around the embed
           if you used .contact-form as a class on that container, but primarily Fillout's internal
           styling or your configurations within Fillout will dictate the form's appearance. */
        .contact-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: inherit;
        }

        .contact-form input[type="text"],
        .contact-form input[type="email"],
        .contact-form select,
        .contact-form textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--light-gray-text);
            border-radius: 4px;
            background-color: var(--light-bg);
            color: var(--light-text);
            font-size: 1em;
        }

        html.dark-mode .contact-form input[type="text"],
        html.dark-mode .contact-form input[type="email"],
        html.dark-mode .contact-form select,
        html.dark-mode .contact-form textarea {
            background-color: #333; /* Darker input background in dark mode */
            border-color: var(--dark-gray-text);
            color: var(--dark-text);
        }

        .contact-form textarea {
            resize: vertical;
            min-height: 120px;
        }

        .contact-form input[type="submit"] {
            width: auto; /* Auto width for submit button */
            margin-top: 10px;
            padding: 12px 25px;
            border: 2px solid var(--light-accent);
            background-color: var(--light-accent);
            color: var(--light-bg);
            font-size: 1.1em;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        html.dark-mode .contact-form input[type="submit"] {
            border-color: var(--dark-accent);
            background-color: var(--dark-accent);
            color: var(--dark-bg);
        }

        .contact-form input[type="submit"]:hover {
            background-color: transparent;
            color: var(--light-accent);
        }
        html.dark-mode .contact-form input[type="submit"]:hover {
            background-color: transparent;
            color: var(--dark-accent);
        }

        .form-message {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }

        .form-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        html.dark-mode .form-message.success {
            background-color: #0c3; /* Darker green */
            color: #fff;
            border-color: #0a2;
        }


        .form-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        html.dark-mode .form-message.error {
            background-color: #c00; /* Darker red */
            color: #fff;
            border-color: #a00;
        }

        .required-asterisk {
            color: red;
            font-size: 0.9em;
            margin-left: 5px;
        }

        /* Mobile specific styles for contact form */
        @media (max-width: 768px) {
            .contact-container {
                padding: 20px 15px;
                margin: 15px auto;
                border: none;
                box-shadow: none;
            }
            .contact-container h1 {
                font-size: 1.8em;
                margin-bottom: 20px;
            }
            /* Fillout handles its own responsiveness, so these might not be needed for the form elements */
            .contact-form label {
                font-size: 0.95em;
                margin-bottom: 5px;
            }
            .contact-form input[type="text"],
            .contact-form input[type="email"],
            .contact-form select,
            .contact-form textarea {
                padding: 10px;
                margin-bottom: 15px;
                font-size: 0.95em;
            }
            .contact-form textarea {
                min-height: 100px;
            }
            .contact-form input[type="submit"] {
                padding: 10px 20px;
                font-size: 1em;
            }

            /* Mobile breadcrumb adjustments */
            .breadcrumb {
                padding: 10px; /* Adjusted padding for mobile */
                font-size: 0.8em;
                flex-direction: row; /* Horizontal navigation on mobile */
                justify-content: space-between; /* Distribute items */
                align-items: center; /* Center vertically */
                gap: 5px; /* Smaller gap for mobile */
            }
            .breadcrumb #logo-container { /* Adjust logo container for mobile */
                width: 50px; /* Adjusted mobile width */
                height: 50px; /* Adjusted mobile height */
                margin-right: 5px;
            }
            .breadcrumb #logo-base, .breadcrumb #logo-overlay { /* Base and overlay images fill container */
                height: 100%;
            }
            .breadcrumb .nav-button-style {
                padding: 3px 6px; /* Smaller padding for mobile buttons */
                font-size: 0.8em; /* Smaller font size for mobile buttons */
            }
            .breadcrumb .separator { /* Separator on mobile for breadcrumb items */
                margin: 0 3px;
            }

            .breadcrumb div.random-button-container { /* random file button container - adjusted from general div */
                margin-left: auto; /* Keep it to the right */
                width: auto;
                text-align: right;
                margin-top: 0;
            }
        }
    </style>
    <script>
        // Copy the dark mode script from your index.php to ensure it works on this page too
        (function() {
            const STORAGE_KEY = 'darkModeEnabled';
            const HTML_ELEMENT = document.documentElement; // Apply dark mode class to html element
            const BODY_CLASS = 'dark-mode';
            const savedPreference = localStorage.getItem(STORAGE_KEY);
            if (savedPreference === 'true') {
                HTML_ELEMENT.classList.add(BODY_CLASS); // Apply to html element
            }
        })();
    </script>
</head>
<body>
    <header>
        <div class="breadcrumb">
            <div id="logo-container">
                <img id="logo-base" src="logo.png" alt="Logo">
                <img id="logo-overlay" src="logo2.png" alt="Logo Hover">
            </div>

            <a href="contact.php" class="nav-button-style">contact</a>
            <span class="separator"> </span> <a href="/?path=projects%2F">home</a>

            <div class="random-button-container">
                <button id="dark-mode-toggle" class="nav-button-style">dark mode</button>
            </div>
        </div>
    </header>

    <div class="contact-container">
        <h1 style="font-weight:700;">get in touch</h1>
        <?php
        // The PHP messages for form submission are no longer relevant here
        // because Fillout handles its own submission feedback directly within the embedded form.
        // You can remove this entire PHP block if you don't use $formMessage or $formError for anything else.
        if (!empty($formMessage)): ?>
            <div class="form-message <?php echo $formError ? 'error' : 'success'; ?>">
                <?php echo $formMessage; ?>
            </div>
        <?php endif; ?>

        <div style="width:100%;height:500px;" data-fillout-id="m7GMw7PLUyus" data-fillout-embed-type="standard" data-fillout-inherit-parameters data-fillout-dynamic-resize></div>
        <script src="https://server.fillout.com/embed/v1/"></script>
        </div>

    <script>
        // Your existing dark mode toggle and logo update functions
        const logoContainer = document.getElementById('logo-container');
        const logoBaseImg = document.getElementById('logo-base');
        const logoOverlayImg = document.getElementById('logo-overlay');
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const HTML_ELEMENT = document.documentElement;
        const BODY_CLASS = 'dark-mode';
        const STORAGE_KEY = 'darkModeEnabled';

        const logoSources = {
            light: { static: 'logo.png', hover: 'logo2.png' },
            dark:  { static: 'darklogo.png', hover: 'darklogo2.png' }
        };

        // Preload images
        for (const theme in logoSources) {
            new Image().src = logoSources[theme].static;
            new Image().src = logoSources[theme].hover;
        }

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

        window.onload = () => {
            applySavedDarkModePreference();

            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', toggleDarkMode);
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
        };
    </script>
</body>
</html>