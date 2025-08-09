<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Base directory for all projects, relative to the web root
// This is the *physical* location of your content on the server, relative to DocumentRoot
$projectsBaseRelativePath = 'projects/';

// Handle directory download
if (isset($_GET['download_dir'])) {
    // Set a high execution time limit for this script to prevent timeouts with large directories
    set_time_limit(300); // 5 minutes

    // Sanitize the path for safety
    $pathForDownload = isset($_GET['path']) ? urldecode($_GET['path']) : $projectsBaseRelativePath;
    $fullPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . $pathForDownload);

    // Security check to ensure the path is within the projects directory
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $projectsAbsolutePath = realpath($documentRoot . '/' . $projectsBaseRelativePath);
    if ($fullPath === false || strpos($fullPath, $projectsAbsolutePath) !== 0) {
        die("Invalid directory path.");
    }
    
    // Check only for files in the current directory, not recursively.
    $filesInCurrentDir = array_diff(scandir($fullPath), array('.', '..'));
    $hasFiles = false;
    foreach ($filesInCurrentDir as $file) {
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
        if (is_file($filePath) && basename($file) !== '.htaccess' && basename($file) !== 'desc.txt') {
            $hasFiles = true;
            break;
        }
    }

    if (!$hasFiles) {
        die("No files to download in this directory.");
    }

    $zip = new ZipArchive();
    $dirName = basename($fullPath);
    $zipFileName = $dirName . '.zip';
    $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

    if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Loop only through files directly in the current directory
        foreach ($filesInCurrentDir as $file) {
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
            // Only add files, and skip our special ones
            if (is_file($filePath) && basename($file) !== '.htaccess' && basename($file) !== 'desc.txt') {
                $zip->addFile($filePath, $file);
            }
        }
        $zip->close();

        if (file_exists($tempZipFile)) {
            // Headers for streaming the file to the browser
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tempZipFile));
            
            // Stream the file in chunks to prevent memory issues and timeouts
            ob_clean();
            flush();
            readfile($tempZipFile);
            
            // Clean up the temporary file after it has been sent
            unlink($tempZipFile); 
            exit;
        } else {
            die("Could not create zip file.");
        }
    } else {
        die("Could not open zip file for writing.");
    }
}


// Handle random redirect
function getAllFilesRecursive($dir) { // Renamed for clarity, original was getAllFiles
    $files = [];
    // Check if the directory exists and is readable
    if (!is_dir($dir) || !is_readable($dir)) {
        error_log("Permission denied or directory not found for: " . $dir);
        return [];
    }

    $items = scandir($dir);
    if ($items === false) {
        error_log("scandir failed for: " . $dir);
        return [];
    }

    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = "$dir/$item";
        if (is_dir($path)) {
            $files = array_merge($files, getAllFilesRecursive($path));
        } else {
            $files[] = $path;
        }
    }
    return $files;
}

if (isset($_GET['random'])) {
    // Need to get the full absolute server path to projects/ for recursive scanning
    $actualProjectsAbsPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . $projectsBaseRelativePath);
    
    // Get all files recursively, but this time to support the enhanced randomness
    $allAvailableFiles = getAllFilesRecursive($actualProjectsAbsPath);

    // Define media extensions for PHP filtering
    $phpMediaExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
        'mp4', 'webm', 'ogg', 'mov', 'avi', 'mp3', 'wav',
        'txt', 'log', 'md', 'php', 'html', 'css', 'js', 'json', 'xml', 'csv',
        'py', 'bat', 'cmd', 'sh', 'c', 'cpp', 'h', 'hpp', 'java', 'cs', 'go', 'rb', 'pl', 'swift', 'kt', 'rs', 'ts', 'jsx', 'tsx', 'vue', 'scss', 'less', 'jsonc', 'yaml', 'yml', 'toml', 'ini', 'cfg'
    ];

    $validFiles = array_filter($allAvailableFiles, function ($file) use ($phpMediaExtensions) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        // Exclude desc.txt and .htaccess from random selection
        if (basename($file) === 'desc.txt' || basename(strtolower($file)) === '.htaccess') {
            return false;
        }
        return in_array($extension, $phpMediaExtensions);
    });

    if (!empty($validFiles)) {
        // --- Enhanced Randomness: Pick a random directory first, then a random file from it ---
        $directoriesWithValidFiles = [];
        foreach ($validFiles as $file) {
            $dir = dirname($file);
            if (!isset($directoriesWithValidFiles[$dir])) {
                $directoriesWithValidFiles[$dir] = [];
            }
            $directoriesWithValidFiles[$dir][] = $file;
        }

        // Shuffle directory keys to randomize directory selection order
        $randomDirKeys = array_keys($directoriesWithValidFiles);
        shuffle($randomDirKeys);

        $randomFile = false;
        foreach ($randomDirKeys as $dirKey) {
            $filesInSelectedDir = $directoriesWithValidFiles[$dirKey];
            if (!empty($filesInSelectedDir)) {
                $randomFile = $filesInSelectedDir[array_rand($filesInSelectedDir)];
                break; // Found a random file from a random directory
            }
        }

        if ($randomFile) {
            // Get the path of the random file relative to DocumentRoot (including 'projects/')
            $randomFileFullPathFromDocRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . '/', '', $randomFile);

            $randomFileDirFromDocRoot = dirname($randomFileFullPathFromDocRoot); // e.g., 'projects/test project'
            $randomFileBaseName = basename($randomFileFullPathFromDocRoot); // e.g., 'image.jpg'

            // Construct the path for the 'path' query parameter value, using rawurlencode for segments
            $pathForQueryParam = $projectsBaseRelativePath;
            // Explode and rawurlencode segments to handle spaces correctly (%20)
            $segments = explode('/', trim(str_replace($projectsBaseRelativePath, '', $randomFileDirFromDocRoot), '/'));
            foreach ($segments as $seg) {
                if (!empty($seg)) {
                    $pathForQueryParam .= rawurlencode($seg) . '/'; // Use rawurlencode for segment
                }
            }
            
            // Construct the redirect URL: /?path=full/path/from/projects/&show=filename.ext
            $redirectUrl = '/?path=' . urlencode($pathForQueryParam) . '&show=' . urlencode($randomFileBaseName);

            header("Location: " . $redirectUrl);
            exit;
        } else {
            // Fallback if no random file could be found after all (shouldn't happen with validFiles check)
            header("Location: /");
            exit;
        }
    } else {
        // Redirect to homepage if no valid files are found to prevent an empty page
        header("Location: /");
        exit;
    }
}
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
    <link rel="icon" href="favicon.ico" type="image/x-icon">
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
            flex-direction: column; /* Stack breadcrumb, description, and main container */
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
        /* Make sure body also inherits these or explicitly sets them */
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
            color: inherit; /* Inherit from body/html */
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
        html.dark-mode .breadcrumb { /* Apply to html.dark-mode */
            background-color: var(--dark-bg); /* Dark Mode */
        }

        /* Logo Container for crossfade */
        .breadcrumb #logo-container {
            width: 120px; /* Increased width */
            height: 120px; /* Increased height */
            margin-right: 15px;
            vertical-align: middle;
            cursor: pointer;
            position: relative; /* Needed for absolute positioning of images inside */
            display: inline-block; /* Ensure it occupies space */
            overflow: hidden; /* Hide any overflow if images are slightly larger */
        }

        /* Base Logo Image (logo.png or darklogo.png) */
        .breadcrumb #logo-base { 
            position: absolute;
            top: 0;
            left: 0;
            width: 100%; /* Fill parent container */
            height: 100%; /* Fill parent container */
            object-fit: contain; /* Prevents stretching */
            opacity: 1; /* Initially visible */
            transition: opacity 1s ease-in-out; /* Smooth transition for opacity */
            display: block; /* Make sure it takes full space of container */
        }

        /* Overlay Logo Image (logo2.png or darklogo2.png) */
        .breadcrumb #logo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            opacity: 0; /* Hidden by default */
            transition: opacity 1s ease-in-out; /* Smooth transition for opacity */
        }
        /* On hover, fade out the base and fade in the overlay */
        .breadcrumb #logo-container:hover #logo-base {
            opacity: 0;
        }
        .breadcrumb #logo-container:hover #logo-overlay {
            opacity: 1;
        }


        /* NEW: Base styling for all non-button links in breadcrumb, removing underlines and visited states */
        .breadcrumb a:not(.nav-button-style) { /* Target only links that are NOT buttons */
            text-decoration: none !important; /* Force no underline */
            color: var(--light-text); /* Default text color for non-button links */
            font-weight: bold;
            padding: 2px 0; /* Minimal padding */
            transition: color 0.1s ease;
        }
        html.dark-mode .breadcrumb a:not(.nav-button-style) {
            color: var(--dark-text);
        }
        .breadcrumb a:not(.nav-button-style):hover {
            color: var(--light-accent); /* Accent color on hover */
        }
        html.dark-mode .breadcrumb a:not(.nav-button-style):hover {
            color: var(--dark-accent);
        }
        .breadcrumb a:not(.nav-button-style):visited { /* Ensure visited links are also not underlined and retain color */
            text-decoration: none !important;
            color: var(--light-text);
        }
        html.dark-mode .breadcrumb a:not(.nav-button-style):visited {
            color: var(--dark-text);
        }


        /* The separator itself */
        .breadcrumb .separator {
            color: var(--light-text); /* Default Light Mode */
            font-weight: normal;
            margin: 0 5px; /* Adjust spacing around the separator */
        }
        html.dark-mode .breadcrumb .separator {
            color: var(--dark-text); /* Dark Mode */
        }


        /* Random file button positioning - Always top right */
        .breadcrumb div.random-button-container {
            margin-left: auto; /* Pushes it to the far right */
            display: flex;
            align-items: center;
            gap: 10px; /* Space between random file and dark mode toggle */
        }

        /* NEW: Shared button styling for navigation elements (accent color by default) */
        .nav-button-style {
            background: none;
            border: 2px solid var(--light-accent);
            color: var(--light-accent); /* Text color matches border */
            padding: 5px 10px; /* Consistent padding for all buttons */
            font-size: 0.9em;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none !important; /* Force no underline for buttons */
            transition: background-color 0.1s ease, color 0.1s ease, border-color 0.1s ease;
            text-transform: lowercase;
            display: inline-block; /* Ensure it behaves like a button */
            line-height: 1.2; /* Better vertical alignment */
            white-space: nowrap; /* Prevent text wrapping inside buttons */
            box-sizing: border-box; /* Include padding and border in element's total width and height */
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
            color: var(--light-bg); /* Text becomes background color on hover */
            border-color: var(--light-accent); /* Border stays same on hover */
        }
        html.dark-mode .nav-button-style:hover:not(:disabled) {
            background-color: var(--dark-accent);
            color: var(--dark-bg); /* Text becomes background color on hover */
            border-color: var(--dark-accent); /* Border stays same on hover */
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
        #dark-mode-toggle {
            /* All styling inherited from .nav-button-style */
        }


        /* Directory Description Container */
        #directory-description-container {
            width: 100%;
            max-width: 1200px; /* Match main container width */
            margin: 0 auto; /* Center it */
            padding: 20px;
            background-color: var(--light-bg); /* Default Light Mode */
            color: var(--light-text);
            text-align: left; /* Left align content */
            border-bottom: 1px solid var(--light-gray-text); /* Separator */
            display: none; /* Hidden by default, shown by JS if desc.txt exists */
            margin-bottom: 20px; /* Space below description for desktop */
        }
        html.dark-mode #directory-description-container {
            background-color: var(--dark-bg);
            color: var(--dark-text);
            border-color: var(--dark-gray-text);
        }

        #directory-description-container h3 {
            font-size: 1.4em;
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: bold;
            color: var(--light-accent); /* Title uses accent color */
            text-transform: lowercase; /* Lowercase title */
        }
        html.dark-mode #directory-description-container h3 { 
            color: var(--dark-accent);
        }

        #directory-description-container p {
            font-size: 1em;
            margin-top: 0;
            margin-bottom: 0;
            line-height: 1.6;
            color: inherit;
        }

        #directory-description-container p a {
             text-decoration: underline;
             color: inherit;
             font-weight: bold;
        }
        #directory-description-container p a:hover {
            color: var(--light-accent);
        }
        html.dark-mode #directory-description-container p a:hover {
            color: var(--dark-accent);
        }
        #directory-description-container p a:visited {
            color: var(--light-text);
        }
        html.dark-mode #directory-description-container p a:visited {
            color: var(--dark-text);
        }


        /* Main Content Container (List and Preview) */
        .container {
            display: flex;
            flex-direction: row; /* Horizontal arrangement on desktop */
            align-items: stretch; /* Stretch items to fill container height */
            width: 100%;
            max-width: 1200px; /* Max width for content */
            margin: 0 auto; /* Horizontal centering only */
            flex-grow: 1; /* Allows container to grow and fill remaining vertical space */
            overflow: hidden; /* Contains inner scrolling of list/preview on desktop */
            min-height: 250px; /* Ensure it's not too small if content is tiny */
        }

        /* File List Area */
        .list {
            flex-basis: 280px; /* Fixed width on desktop */
            flex-shrink: 0;
            padding: 20px;
            background-color: var(--light-bg); /* Default Light Mode */
            overflow-y: auto; /* Scroll for long lists */
            display: block; /* Always visible */
        }
        html.dark-mode .list { /* Apply to html.dark-mode */
            background-color: var(--dark-bg); /* Dark Mode */
        }

        .list h2 {
            color: inherit; /* Inherit from list */
        }

        .list a {
            display: block;
            margin-bottom: 8px;
            text-decoration: none;
            color: var(--light-text); /* Default Light Mode */
            padding: 5px 0;
            font-weight: bold;
            transition: color 0.1s ease;
        }
        html.dark-mode .list a { /* Apply to html.dark-mode */
            color: var(--dark-text); /* Dark Mode */
        }

        .list a:hover {
            color: var(--light-accent); /* Default Light Mode */
            background-color: var(--white); /* Keep background white on hover */
        }
        html.dark-mode .list a:hover { /* Apply to html.dark-mode */
            color: var(--dark-accent); /* Dark Mode */
            background-color: var(--black); /* Keep background black on hover */
        }

        /* Highlight for selected item */
        .list a.selected {
            color: var(--light-accent); /* Default Light Mode */
            text-decoration: none; /* Underline for selection */
        }
        html.dark-mode .list a.selected { /* Apply to html.dark-mode */
            color: var(--dark-accent); /* Dark Mode */
        }

        .list a.non-interactive-file {
            color: var(--light-gray-text); /* Muted for non-interactive files */
            font-weight: normal;
        }
        html.dark-mode .list a.non-interactive-file:hover { /* Apply to html.dark-mode */
            color: var(--dark-gray-text); /* Keep muted on hover */
        }

        .list a.non-interactive-file:hover {
            background-color: transparent;
            color: var(--light-gray-text); /* Keep muted on hover */
        }
        html.dark-mode .list a.non-interactive-file:hover { /* Apply to html.dark-mode */
            color: var(--dark-gray-text); /* Keep muted on hover */
        }
        /* NEW: Hide image links on homepage when slideshow is active */
        .list a.hide-on-homepage-slideshow {
            display: none !important;
        }


        /* Preview Area */
        .preview {
            flex-grow: 1;
            padding: 20px;
            padding-top: 60px; /* Added padding-top to create space for absolute elements */
            display: flex;
            flex-direction: column;
            align-items: center; /* Centers items horizontally within the column */
            justify-content: flex-start; /* Aligns content to the top */
            background-color: var(--light-bg); /* Default Light Mode */
            text-align: center;
            position: relative; /* Crucial for positioning filename and nav buttons */
            overflow-y: auto; /* Allow this panel to scroll if content exceeds its height */
        }
        html.dark-mode .preview { /* Apply to html.dark-mode */
            background-color: var(--dark-bg); /* Dark Mode */
        }

        /* File Name Display (Top Left) */
        .preview .file-name-display {
            font-weight: bold;
            color: var(--light-text); /* Default Light Mode */
            font-size: 1.2em;
            display: none; /* Hidden by default; controlled by JS */
            position: absolute; /* Position relative to .preview */
            top: 20px; /* Positions inside the 60px padding-top */
            left: 20px;
            text-transform: lowercase;
            z-index: 10; /* Ensure it's above other content if needed */
        }
        html.dark-mode .preview .file-name-display { /* Apply to html.dark-mode */
            color: var(--dark-text); /* Dark Mode */
        }
        /* NEW: Force hide preview name on homepage slideshow */
        .preview .file-name-display.hide-on-homepage-slideshow {
            display: none !important;
        }


        /* NEW: Wrapper for main media content */
        .preview-media-wrapper {
            flex-grow: 1; /* Takes all available space between top padding and download link */
            display: flex; /* Makes its children (media) flex items */
            align-items: center; /* Centers children vertically within the wrapper */
            justify-content: center; /* Centers children horizontally within the wrapper */
            width: 100%; /* Ensure it takes full width */
            overflow: hidden; /* Important for media scaling */
            min-height: 100px; /* A default small height to ensure wrapper exists */
        }

        /* Main Preview Content (Images, Videos, etc.) now within wrapper */
        .preview-media-wrapper img,
        .preview-media-wrapper video,
        .preview-media-wrapper audio,
        .preview-media-wrapper iframe#pdf-preview,
        .preview-media-wrapper pre,
        .preview-media-wrapper p.alt-text {
            max-width: 100%;
            max-height: 100%; /* Scale to 100% of the wrapper's height */
            object-fit: contain; /* Contain aspect ratio */
            display: block; /* Important for flex centering */
            margin: 0; /* Remove any previous auto margins on these elements */
        }

        .preview-media-wrapper img[src=""] {
            display: none;
        }

        /* Adjust alt-text specific styles if needed */
        .preview-media-wrapper p.alt-text {
            color: var(--light-gray-text);
            font-style: italic;
            font-size: 1.2em;
            text-align: center;
        }
        html.dark-mode .preview-media-wrapper p.alt-text {
            color: var(--dark-gray-text);
        }
        
        /* NEW: Specific min-height for PDF previewer and text previews */
        .preview-media-wrapper iframe#pdf-preview {
            min-height: 750px; /* Increased Desktop min-height for PDF */
            width: 100%; /* Ensure it takes full width of its wrapper */
        }
        .preview-media-wrapper pre {
            min-height: 400px; /* Increased Desktop min-height for text/code */
            width: 100%; /* Ensure it takes full width of its wrapper */
        }


        /* Style for the download link, now a separate flex item */
        #download-link {
            flex-shrink: 0; /* Prevent it from shrinking */
            margin-top: 10px; /* Provides space below the content it's attached to */
            margin-bottom: 0; /* Ensure no extra space below itself */
            text-decoration: none;
            color: var(--light-accent); /* Blue in light mode */
            font-weight: bold;
            display: none; /* Hidden by default, controlled by JS */
        }
        html.dark-mode #download-link {
            color: var(--dark-accent); /* Yellow in dark mode */
        }
        /* NEW: Force hide download link on homepage slideshow */
        #download-link.hide-on-homepage-slideshow {
            display: none !important;
        }
        
        /* NEW: Style for the download directory button */
        #download-dir-link {
            margin-top: 10px; /* Same margin as download-link */
            margin-bottom: 0;
            display: none; /* Hidden by default, shown by JS */
        }
        /* NEW: Style for the loading indicator */
        .download-loading-indicator {
            display: none;
            text-align: center;
            font-style: italic;
            color: var(--light-accent);
            margin-top: 10px;
            margin-bottom: 0;
            white-space: nowrap;
        }
        html.dark-mode .download-loading-indicator {
            color: var(--dark-accent);
        }

        /* NEW: Spinner animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .download-loading-indicator:before {
            content: '';
            display: inline-block;
            width: 1em;
            height: 1em;
            border: 2px solid currentColor;
            border-bottom-color: transparent;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin-right: 0.5em;
            vertical-align: middle;
        }


        /* Preview Navigation Buttons (Top Right) */
        .preview-nav {
            display: none; /* Hidden by default, shown by JS */
            position: absolute; /* Position relative to .preview */
            top: 20px; /* Positions inside the 60px padding-top */
            right: 20px;
            gap: 10px; /* Space between buttons */
            z-index: 10; /* Ensure it's above other content if needed */
        }

        .preview-nav button {
            /* Inherits from .nav-button-style */
        }
        /* Using the class from the previous iteration, which forces display: none */
        .nav-button-style.disabled-hide { 
            display: none !important;
        }
        .preview-nav button:disabled {
            /* Overrides from .nav-button-style for specific disabled state */
        }


        /* Base styles for all text/code previews */
        /* Removed flex-grow: 1 from pre; it's on .preview-media-wrapper now */
        .preview pre, #lightbox-text {
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow: auto; /* Text content can scroll internally if too large for 100% max-height */
            max-width: 100%;
            padding: 15px;
            box-sizing: border-box;
            display: none;
            text-align: left;
            margin: 0; 
        }

        /* Specific styles for "plain text" files in preview pane */
        .preview pre.plain-text {
            background-color: var(--light-bg); /* Default Light Mode */
            border: none;
            color: var(--light-text); /* Default Light Mode */
            /* max-height handled by .preview-media-wrapper */
            font-family: sans-serif;
            font-size: 1em;
        }
        html.dark-mode .preview pre.plain-text { /* Apply to html.dark-mode */
            background-color: var(--dark-bg); /* Dark Mode */
            color: var(--dark-text); /* Dark Mode */
        }

        /* Specific styles for "code" files in preview pane */
        .preview pre.code-text {
            background-color: var(--black); /* Always black for code */
            border: none;
            color: var(--white); /* Always white for code */
            /* max-height handled by .preview-media-wrapper */
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; 
            font-size: 0.9em;
        }

        /* Lightbox Overlay */
        #lightbox-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--black); /* Always black for lightbox overlay */
            z-index: 1000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        #lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: var(--white); /* Always white for close button */
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
            padding: 5px 10px;
            background-color: var(--black); /* Always black for close button background */
            line-height: 1;
            text-decoration: none;
            transition: color 0.1s ease, background-color 0.1s ease;
        }

        #lightbox-close:hover {
            color: var(--light-accent); /* Default Light Mode */
            background-color: var(--black);
        }
        html.dark-mode #lightbox-close:hover { /* Apply to html.dark-mode */
            color: var(--dark-accent); /* Dark Mode */
        }


        #lightbox-image, #lightbox-video, #lightbox-audio, #lightbox-pdf, #lightbox-text { 
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            display: none;
            margin: auto;
        }
        
        /* Utility class to prevent scrolling when lightbox is open */
        body.no-scroll {
            overflow: hidden;
        }

        #lightbox-image {
            transition: transform 0.2s ease;
            cursor: grab;
            user-select: none;
        }

        #lightbox-image:active {
            cursor: grabbing;
        }


        /* --- Mobile Optimizations (applies below 768px) --- */
        @media (max-width: 768px) {
            html, body {
                height: auto;
                min-height: 100vh;
                overflow-y: auto; /* Enable page scrolling on mobile */
                -webkit-overflow-scrolling: touch;
            }

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
            /* .breadcrumb a will now inherit .nav-button-style on mobile as well */
            .breadcrumb .nav-button-style {
                padding: 3px 6px; /* Smaller padding for mobile buttons */
                font-size: 0.8em; /* Smaller font size for mobile buttons */
            }
            .breadcrumb .separator { /* Separator on mobile for breadcrumb items */
                margin: 0 3px;
            }

            .breadcrumb div { /* random file button container - adjusted from general div */
                margin-left: auto; /* Keep it to the right */
                width: auto;
                text-align: right;
                margin-top: 0;
            }

            /* Mobile Directory Description */
            #directory-description-container {
                padding: 10px;
                margin-bottom: 10px; /* Adjust margin for mobile */
            }
            #directory-description-container h3 {
                font-size: 1.2em;
            }
            #directory-description-container p {
                font-size: 0.9em;
            }

            .container {
                flex-direction: column; /* Stack list and preview vertically */
                max-width: 100%;
                margin: 0 auto; /* Removed vertical auto margin on mobile too */
                min-height: auto;
                height: auto;
            }

            #list-toggle-button { /* Still keeping this ID for safety, but hidden */
                display: none;
            }
            
            .list {
                flex: none;
                width: 100%;
                padding: 10px;
                max-height: 50vh; /* Limit list height on mobile to allow preview space */
                overflow-y: auto; /* List itself scrolls */
                -webkit-overflow-scrolling: touch;
                display: block; /* Always visible on mobile too */
            }

            .list h2 {
                font-size: 1.1em;
                padding-bottom: 5px;
                margin-bottom: 10px;
            }

            .list a {
                padding: 10px;
                font-size: 0.9em;
            }
            
            .preview {
                padding: 10px;
                padding-top: 40px; /* Adjusted padding-top for mobile */
                min-height: 250px;
                align-items: center;
                justify-content: center;
            }
            /* Mobile adjustments for positioned elements */
            .file-name-display {
                top: 10px;
                left: 10px;
                font-size: 1em;
            }
            .preview-nav {
                top: 10px;
                right: 10px;
                gap: 5px;
            }
            .preview-nav button {
                /* Inherits from .nav-button-style */
            }

            /* Adjust media preview sizes for mobile */
            /* max-height: 100% within .preview-media-wrapper handles scaling */
            /* NEW: Mobile specific min-height for PDF previewer and text previews */
            .preview-media-wrapper iframe#pdf-preview {
                min-height: 500px; /* Increased Mobile min-height for PDF */
            }
            .preview-media-wrapper pre {
                min-height: 300px; /* Increased Mobile min-height for text/code */
            }

            /* Lightbox adjustments for mobile */
            #lightbox-close {
                top: 10px;
                right: 15px;
                font-size: 30px;
            }
            #lightbox-image, #lightbox-video, #lightbox-audio, #lightbox-pdf, #lightbox-text {
                max-width: 95%;
                max-height: 95%;
            }
            #lightbox-text {
                padding: 15px;
                font-size: 0.9em;
            }
        }
    </style>
    <script>
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

    <meta name="description" content="Freelance media consultant in Williamsport, PA offering graphic design, web design, photography, tech support, and digital content creation.">
    <meta name="keywords" content="graphic design, web design, photography, tech support, video editing, audio editing, logo design, freelance designer, digital content, IT support, Williamsport PA, small business help, website development, copywriting, data entry, media consultant">
    <meta name="author" content="Jacob Zimmerman">



</head>
<body>
    <header> <div class="breadcrumb">
            <div id="logo-container">
                <img id="logo-base" src="logo.png" alt="Logo">
                <img id="logo-overlay" src="logo2.png" alt="Logo Hover">
            </div>

            <a href="contact.php" class="nav-button-style">contact</a>
            &nbsp;&nbsp; <?php
            // This is the current path that Apache has rewritten for us (e.g., 'projects/6-25/' or 'projects/test project/')
            $currentPath = isset($_GET['path']) ? $_GET['path'] : $projectsBaseRelativePath; // Default to 'projects/' if no path

            // It's crucial to decode currentPath here because $_GET might turn %2B back to +, but we need raw spaces for filesystem.
            // It might also be that the browser passed %20 as %20. PHP handles %20 correctly but can struggle with mixed encoding.
            // Let's decode it completely and then re-encode for *outputting* in URLs
            $currentPathDecoded = urldecode($currentPath); // Decode anything like %20 or + to space

            // Security check: ensure the path stays within the intended projects directory
            $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
            $projectsAbsolutePath = realpath($documentRoot . '/' . $projectsBaseRelativePath);

            $fullCurrentDir = realpath($documentRoot . '/' . $currentPathDecoded); // Use decoded path for realpath
            
            // Debugging: echo values to console
            // echo '<script>console.log("PHP Debug: documentRoot = ", ' . json_encode($documentRoot) . ');</script>';
            // echo '<script>console.log("PHP Debug: projectsAbsolutePath = ", ' . json_encode($projectsAbsolutePath) . ');</script>';
            // echo '<script>console.log("PHP Debug: fullCurrentDir = ", ' . json_encode($fullCurrentDir) . ');</script>';


            if ($fullCurrentDir === false || strpos($fullCurrentDir, $projectsAbsolutePath) !== 0) {
                // If path is invalid or outside projects, reset to projects root
                $fullCurrentDir = $projectsAbsolutePath;
                $currentPathDecoded = $projectsBaseRelativePath; // Reset to projects root path if invalid
            }
            
            // This is the clean path used for *displaying* in breadcrumbs and for *generating* links (e.g., '6-25/' or 'test project/')
            $cleanDisplayPath = str_replace($projectsBaseRelativePath, '', $currentPathDecoded);
            // If cleanDisplayPath is just '/', make it empty for proper home link handling
            if ($cleanDisplayPath === '/') $cleanDisplayPath = ''; 
            // Ensure it doesn't have trailing slash for split, but might add for URL construction later
            $displayPathTrimmed = trim($cleanDisplayPath, '/');


            // File to show from query string (used by JS for initial preview)
            $fileToShow = isset($_GET['show']) ? $_GET['show'] : '';

            // --- Handle desc.txt content ---
            $descTxtTitle = '';
            $descTxtDescription = '';
            $descTxtPath = $fullCurrentDir . '/desc.txt';
            if (file_exists($descTxtPath) && is_readable($descTxtPath)) {
                $descContent = file_get_contents($descTxtPath);
                $lines = explode("\n", $descContent);
                if (!empty($lines)) {
                    $descTxtTitle = trim($lines[0]);
                    array_shift($lines); // Remove the first line
                    $descTxtDescription = trim(implode("\n", $lines));

                    // NEW: Use a regex to find links and convert them to HTML tags
                    $descTxtDescription = preg_replace_callback(
                        '/(?<=[\s\S])([\w\s]+)\[(https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(?:\/[a-zA-Z0-9\-\._~:\/?#\[\]@!$&\'()*+,;=]*)?)\]/',
                        function ($matches) {
                            $text = trim($matches[1]);
                            $url = $matches[2];
                            return ' ' . htmlspecialchars($text) . '[<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url) . '</a>]';
                        },
                        $descTxtDescription
                    );
                }
            }
            // --- END Handle desc.txt content ---

            // 'home' link is now standard breadcrumb text
            echo "<a href='/?path=" . urlencode($projectsBaseRelativePath) . "'>" . strtolower(htmlspecialchars("home")) . "</a>"; 

            $pathSegments = explode('/', $displayPathTrimmed); // Use trimmed displayPath for splitting
            $cumulativePathForLinks = $projectsBaseRelativePath; // Start with 'projects/' for path parameter

            foreach ($pathSegments as $segment) {
                if (!empty($segment)) {
                    // For breadcrumb links, path segments for the *query string value* should use rawurlencode to avoid '+'
                    $cumulativePathForLinks .= rawurlencode($segment) . '/'; 
                    // Other breadcrumb links remain standard text with bullets
                    echo " <span class='separator'>&bullet;</span> <a href='/?path=" . urlencode($cumulativePathForLinks) . "'>" . strtolower(htmlspecialchars($segment)) . "</a>"; 
                }
            }

            // 'random file' and 'dark mode/light mode' now correctly use nav-button-style for accent color
            echo '<div class="random-button-container">
                        <a class="nav-button-style" href="?random=1">random file</a>
                        <button id="dark-mode-toggle" class="nav-button-style">dark mode</button> </div>';
            ?>
        </div>

        <div id="directory-description-container">
            <h3 id="directory-description-title"></h3>
            <p id="directory-description-text"></p>
        </div>
    </header> <div class="container">
        <div class="list">
            <h2>content:</h2>
            <?php
            // Common media extensions for slideshow and general display
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
            $videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi']; 
            $audioExtensions = ['mp3', 'wav'];
            $pdfExtensions = ['pdf']; 
            $plainTextExtensions = ['txt', 'log', 'md', 'json', 'xml', 'csv', 'ini', 'cfg'];
            $codeExtensions = [
                'php', 'html', 'css', 'js', 'py', 'bat', 'cmd', 'sh', 'c', 'cpp', 'h', 'hpp',
                'java', 'cs', 'go', 'rb', 'pl', 'swift', 'kt', 'rs', 'ts', 'jsx', 'tsx', 'vue',
                'scss', 'less', 'jsonc', 'yaml', 'yml', 'toml'
            ];
            $allPreviewableExtensions = array_merge($imageExtensions, $videoExtensions, $audioExtensions, $pdfExtensions, $plainTextExtensions, $codeExtensions);

            $isRootProjectsDirectory = (trim($currentPathDecoded, '/') === trim($projectsBaseRelativePath, '/'));
            $phpHomeImagesForSlideshow = []; // NEW: Array to hold image paths for slideshow
            
            // NEW: Variable to check if there are any files in the current directory
            $currentDirectoryHasFiles = false;

            if (!is_dir($fullCurrentDir)) {
                echo "<p>DIRECTORY NOT FOUND.</p>";
            } else {
                $files = scandir($fullCurrentDir);
                if ($files === false) {
                    echo "<p>PERMISSION DENIED OR DIRECTORY UNREADABLE.</p>";
                } else {
                    natcasesort($files);

                    // Store the first *file's* relative path and type
                    $firstFileToPreview = null; 

                    // Separate files and directories for processing, keeping original order for display
                    $actualFiles = [];
                    $directories = [];
                    foreach ($files as $file) {
                        if ($file === '.' || $file == '..') continue; 

                        $filePath = $fullCurrentDir . '/' . $file;
                        if (is_dir($filePath)) {
                            $directories[] = $file;
                        } else {
                            // This is the key change: if we find a file, set the flag.
                            if (basename($file) !== 'desc.txt' && basename($file) !== '.htaccess') {
                                $currentDirectoryHasFiles = true;
                            }
                            $actualFiles[] = $file;
                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                            // Populate the home images array if it's the root directory AND an image
                            if ($isRootProjectsDirectory && in_array(strtolower($extension), $imageExtensions)) {
                                $phpHomeImagesForSlideshow[] = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . '/', '', $filePath);
                            }
                        }
                    }

                    // Prioritize finding the first file to preview from actual files first
                    foreach ($actualFiles as $file) {
                        if ($firstFileToPreview !== null) break; 
                        
                        $filePath = $fullCurrentDir . '/' . $file;
                        // Get relative path from DocumentRoot for current file (including 'projects/')
                        $fileRelativePathFromDocRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . '/', '', $filePath);
                        $extension = pathinfo($file, PATHINFO_EXTENSION);

                        if (in_array(strtolower($extension), $imageExtensions)) {
                            $firstFileToPreview = ['path' => $fileRelativePathFromDocRoot, 'type' => 'image'];
                        } else if (in_array(strtolower($extension), $videoExtensions)) {
                            $firstFileToPreview = ['path' => $fileRelativePathFromDocRoot, 'type' => 'video'];
                        } else if (in_array(strtolower($extension), $audioExtensions)) {
                            $firstFileToPreview = ['path' => $fileRelativePathFromDocRoot, 'type' => 'audio'];
                        } else if (in_array(strtolower($extension), $pdfExtensions)) { 
                            $firstFileToPreview = ['path' => $fileRelativePathFromDocRoot, 'type' => 'pdf'];
                        } else if (in_array(strtolower($extension), $plainTextExtensions)) {
                            $firstFileToPreview = ['path' => $fileRelativePathFromDocRoot, 'type' => 'plain_text'];
                        } else if (in_array(strtolower($extension), $codeExtensions)) {
                            $firstFileToPreview = ['path' => $fileRelativePathFromDocRoot, 'type' => 'code_text'];
                        }
                        else {
                            // Non-interactive files will not be auto-previewed
                        }
                    }

                    // If no *file* was found (meaning the directory only contains folders or non-interactive files),
                    // but there are directories, pick the first directory to display its name.
                    if ($firstFileToPreview === null && !empty($directories)) {
                        $firstDirectory = $directories[0]; 
                        $firstDirectoryPath = $fullCurrentDir . '/' . $firstDirectory;
                        $firstFileToPreview = ['path' => str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . '/', '', $firstDirectoryPath), 'type' => 'directory'];
                    }

                    // --- Sort Folders Before Files ---
                    natcasesort($directories); 
                    natcasesort($actualFiles); 
                    $allDisplayItems = array_merge($directories, $actualFiles); 
                    // --- End Sort Folders Before Files ---

                    foreach ($allDisplayItems as $file) {
                        // NEW: Skip desc.txt entirely from the file list display
                        if (strtolower($file) === 'desc.txt') {
                            continue;
                        }

                        $filePath = $fullCurrentDir . '/' . $file;
                        // Get relative path from DocumentRoot for current item (including 'projects/')
                        $itemRelativePathFromDocRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . '/', '', $filePath);
                        $extension = pathinfo($file, PATHINFO_EXTENSION);
                        $fileLowercase = strtolower($file);

                        // NEW: Add 'hide-on-homepage-slideshow' class to image links if it's the root directory
                        $isImageAndRoot = ($isRootProjectsDirectory && in_array(strtolower($extension), $imageExtensions));
                        $hideClass = $isImageAndRoot ? ' hide-on-homepage-slideshow' : '';

                        if (is_dir($filePath)) {
                            // Directory link: Use ?path= projects/current_path/segment/
                            $dirLinkPath = $currentPathDecoded; 
                            if (!empty($dirLinkPath) && substr($dirLinkPath, -1) !== '/') {
                                $dirLinkPath .= '/';
                            }
                            $dirLinkPath .= rawurlencode($file) . '/'; // Append and rawurlencode folder name for query string
                            echo "<a href='/?path=" . urlencode($dirLinkPath) . "'>" . strtolower(htmlspecialchars($file)) . "</a>"; // Use urlencode for final query param value
                        } else if (in_array(strtolower($extension), $imageExtensions)) {
                            // File link: pass DocumentRoot-relative path to showMedia JS function
                            echo "<a href='#' class='image-file-link{$hideClass}' onclick=\"showMedia('" . htmlspecialchars($itemRelativePathFromDocRoot) . "', 'image'); return false;\">" . $fileLowercase . "</a>";
                        } else if (in_array(strtolower($extension), $videoExtensions)) {
                            echo "<a href='#' onclick=\"showMedia('" . htmlspecialchars($itemRelativePathFromDocRoot) . "', 'video'); return false;\">" . $fileLowercase . "</a>";
                        } else if (in_array(strtolower($extension), $audioExtensions)) {
                            echo "<a href='#' onclick=\"showMedia('" . htmlspecialchars($itemRelativePathFromDocRoot) . "', 'audio'); return false;\">" . $fileLowercase . "</a>";
                        } else if (in_array(strtolower($extension), $pdfExtensions)) { 
                            echo "<a href='#' onclick=\"showMedia('" . htmlspecialchars($itemRelativePathFromDocRoot) . "', 'pdf'); return false;\">" . $fileLowercase . "</a>";
                        } else if (in_array(strtolower($extension), $plainTextExtensions)) {
                            echo "<a href='#' onclick=\"showMedia('" . htmlspecialchars($itemRelativePathFromDocRoot) . "', 'plain_text'); return false;\">" . $fileLowercase . "</a>";
                        } else if (in_array(strtolower($extension), $codeExtensions)) {
                            echo "<a href='#' onclick=\"showMedia('" . htmlspecialchars($itemRelativePathFromDocRoot) . "', 'code_text'); return false;\">" . $fileLowercase . "</a>";
                        }
                        else {
                            echo "<a class='non-interactive-file' href='#'>" . $fileLowercase . "</a>";
                        }
                    }

                    // Pass necessary variables to JavaScript
                    echo "<script type='text/javascript'>";
                    echo "var phpCurrentPath = " . json_encode($currentPathDecoded) . ";"; 
                    echo "var phpFileToShow = " . json_encode($fileToShow) . ";";
                    echo "var firstFileToLoad = " . json_encode($firstFileToPreview) . ";";
                    // Pass desc.txt content to JavaScript
                    echo "var phpDescTxtTitle = " . json_encode($descTxtTitle) . ";";
                    echo "var phpDescTxtDescription = " . json_encode($descTxtDescription) . ";";

                    // Pass a variable to JS to indicate if this is the root 'projects/' directory
                    echo "var isRootProjectsDirectory = " . json_encode($isRootProjectsDirectory) . ";";
                    // NEW: Pass the list of root images for slideshow
                    echo "var phpHomeImagesForSlideshow = " . json_encode($phpHomeImagesForSlideshow) . ";";
                    // NEW: Pass the boolean flag for if the current directory has files
                    echo "var currentDirectoryHasFiles = " . json_encode($currentDirectoryHasFiles) . ";";


                    // Debugging lines for JS console - KEEP THESE FOR TESTING
                    echo 'console.log("PHP Passed isRootProjectsDirectory:", ' . json_encode($isRootProjectsDirectory) . ');';
                    echo 'console.log("PHP Passed phpHomeImagesForSlideshow:", ' . json_encode($phpHomeImagesForSlideshow) . ');';
                    echo 'console.log("PHP Passed currentDirectoryHasFiles:", ' . json_encode($currentDirectoryHasFiles) . ');';


                    echo "</script>";
                }
            }
            ?>
        </div>
        <div class="preview">
            <p id="selected-file-name" class="file-name-display <?php echo ($isRootProjectsDirectory && !$fileToShow) ? 'hide-on-homepage-slideshow' : ''; ?>"></p>
            <div class="preview-nav">
                <button id="prev-file-button" class="nav-button-style">previous</button>
                <button id="next-file-button" class="nav-button-style">next</button>
            </div>
            
            <div class="preview-media-wrapper"> <img id="preview-image" src="" alt="" style="display:none;" onclick="openLightbox(this.src, 'image')">
                <video id="preview-video" controls style="display:none;" ></video> 
                <audio id="preview-audio" controls style="display:none;" ></audio> 
                <iframe id="pdf-preview" style="display:none;"></iframe>
                <pre id="preview-text-content" style="display:none;"></pre>
                <p id="preview-text" class="alt-text">SELECT A FILE OR FOLDER.</p> </div>

            <a id="download-link" target="_blank" rel="noopener noreferrer" href="#" class="nav-button-style <?php echo ($isRootProjectsDirectory && !$fileToShow) ? 'hide-on-homepage-slideshow' : ''; ?>" style="display:none;" download>download file</a>
            <a id="download-dir-link" class="nav-button-style" style="display:none;">download folder</a>
            <p id="download-loading" class="download-loading-indicator">downloading...</p>
        </div>
    </div>

    <div id="lightbox-overlay" onclick="closeLightbox()">
        <span id="lightbox-close" onclick="event.stopPropagation(); closeLightbox();">&times;</span>
        <img id="lightbox-image" src="" alt="Maximized Image" onclick="event.stopPropagation();">
        <video id="lightbox-video" controls onclick="event.stopPropagation();"></video>
        <audio id="lightbox-audio" controls onclick="event.stopPropagation();"></audio>
        <iframe id="lightbox-pdf" style="display:none;"></iframe> <pre id="lightbox-text" onclick="event.stopPropagation();"></pre>
    </div>

    <script>
        let zoomLevel = 1;
        let isDragging = false;
        let startX = 0, startY = 0;
        let currentX = 0, currentY = 0;
        let animationFrameId = null;
        let slideshowInterval = null; // New: to store the slideshow interval

        const lightboxImage = document.getElementById('lightbox-image');
        const lightboxVideo = document.getElementById('lightbox-video');
        const lightboxAudio = document.getElementById('lightbox-audio');
        const lightboxPDF = document.getElementById('lightbox-pdf'); 
        const lightboxText = document.getElementById('lightbox-text');
        const previewImage = document.getElementById('preview-image');
        const previewVideo = document.getElementById('preview-video');
        const previewAudio = document.getElementById('preview-audio');
        const previewPDF = document.getElementById('pdf-preview'); 
        const downloadLink = document.getElementById('download-link'); 
        const downloadDirLink = document.getElementById('download-dir-link');
        const downloadLoadingIndicator = document.getElementById('download-loading');
        const previewTextContent = document.getElementById('preview-text-content');
        const selectedFileNameDisplay = document.getElementById('selected-file-name');
        const previewTextDefault = document.getElementById('preview-text');
        const fileListDiv = document.querySelector('.list');

        const previewNavContainer = document.querySelector('.preview-nav');
        const prevFileButton = document.getElementById('prev-file-button');
        const nextFileButton = document.getElementById('next-file-button');

        const logoContainer = document.getElementById('logo-container');
        const logoBaseImg = document.getElementById('logo-base'); 
        const logoOverlayImg = document.getElementById('logo-overlay'); 

        // Directory Description elements
        const directoryDescriptionContainer = document.getElementById('directory-description-container');
        const directoryDescriptionTitle = document.getElementById('directory-description-title');
        const directoryDescriptionText = document.getElementById('directory-description-text');

        const logoSources = {
            light: { static: 'logo.png', hover: 'logo2.png' },
            dark:  { static: 'darklogo.png', hover: 'darklogo2.png' }
        };

        for (const theme in logoSources) {
            new Image().src = logoSources[theme].static;
            new Image().src = logoSources[theme].hover;
        }

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


        function updateTransform() {
            lightboxImage.style.transform = 'translate(' + currentX + 'px, ' + currentY + 'px) scale(' + zoomLevel + ')';
            animationFrameId = null;
        }

        function requestUpdate() {
            if (!animationFrameId) {
                animationFrameId = requestAnimationFrame(updateTransform);
            }
        }

        lightboxImage.addEventListener('mousedown', (e) => {
            if (zoomLevel <= 1) return;
            isDragging = true;
            startX = e.clientX - currentX;
            startY = e.clientY - currentY;
            lightboxImage.style.cursor = 'grabbing';
            e.preventDefault();
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            currentX = e.clientX - startX;
            currentY = e.clientY - startY;
            requestUpdate();
        });

        window.addEventListener('mouseup', () => {
            isDragging = false;
            lightboxImage.style.cursor = 'grab';
        });

        lightboxImage.addEventListener('wheel', (e) => {
            const rect = lightboxImage.getBoundingClientRect();
            const offsetX = e.clientX - rect.left;
            const offsetY = e.clientY - rect.top;
            const prevZoom = zoomLevel;

            zoomLevel += (e.deltaY < 0) ? 0.1 : -0.1;
            zoomLevel = Math.max(0.1, zoomLevel);

            const dx = offsetX - rect.width / 2;
            const dy = offsetY - rect.height / 2;
            currentY -= dy * (zoomLevel - prevZoom) / zoomLevel; 

            requestUpdate();
            e.preventDefault();
        }, { passive: false });

        function resetTransform() {
            zoomLevel = 1;
            currentX = 0;
            currentY = 0;
            updateTransform();
        }

        function stopAllMedia() {
            if (previewVideo && !previewVideo.paused) {
                previewVideo.pause();
                previewVideo.currentTime = 0;
            }
            if (previewAudio && !previewAudio.paused) {
                previewAudio.pause();
                previewAudio.currentTime = 0;
            }
            if (lightboxVideo && !lightboxVideo.paused) {
                lightboxVideo.pause();
                lightboxVideo.currentTime = 0;
            }
            if (lightboxAudio && !lightboxAudio.paused) {
                lightboxAudio.pause();
                lightboxAudio.currentTime = 0;
            }
        }

        function openLightbox(mediaSrc, type) { 
            const overlay = document.getElementById('lightbox-overlay');
            const body = document.body;

            stopAllMedia(); 
            // Also stop slideshow if opening lightbox
            clearInterval(slideshowInterval);
            slideshowInterval = null;

            lightboxImage.style.display = 'none';
            lightboxImage.src = ''; 
            lightboxVideo.style.display = 'none';
            lightboxVideo.src = ''; 
            lightboxVideo.pause();
            lightboxAudio.style.display = 'none';
            lightboxAudio.src = ''; 
            lightboxAudio.pause();
            lightboxPDF.style.display = 'none'; 
            lightboxPDF.src = ''; 
            lightboxText.style.display = 'none';
            lightboxText.textContent = ''; 

            if (mediaSrc && mediaSrc !== window.location.href) {
                if (type === 'image') {
                    lightboxImage.src = mediaSrc;
                    lightboxImage.style.display = 'block';
                    resetTransform();
                } else if (type === 'video' || type === 'audio') { 
                    console.warn(`Attempted to open ${type} in lightbox. This functionality is disabled.`);
                    return; 
                } else if (type === 'pdf') { 
                    lightboxPDF.src = mediaSrc;
                    lightboxPDF.style.display = 'block';
                } else if (type === 'plain_text' || type === 'code_text') {
                    fetch(mediaSrc)
                        .then(response => response.text())
                        .then(data => {
                            lightboxText.textContent = data;
                            lightboxText.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error fetching text for lightbox:', error);
                            lightboxText.textContent = 'Failed to load content: ' + error.message;
                            lightboxText.style.display = 'block';
                        });
                }

                overlay.style.display = 'flex';
                body.classList.add('no-scroll');
            }
        }

        function closeLightbox() {
            const overlay = document.getElementById('lightbox-overlay');
            const body = document.body;

            stopAllMedia();

            overlay.style.display = 'none';
            lightboxImage.src = '';
            lightboxImage.style.display = 'none';
            lightboxVideo.src = '';
            lightboxVideo.pause();
            lightboxVideo.style.display = 'none';
            lightboxAudio.src = '';
            lightboxAudio.pause();
            lightboxAudio.style.display = 'none';
            lightboxPDF.src = ''; 
            lightboxPDF.style.display = 'none'; 
            lightboxText.textContent = '';
            lightboxText.style.display = 'none';

            body.classList.remove('no-scroll');
            resetTransform();

            // Re-start slideshow if closing lightbox on the root page and no file is explicitly selected
            if (isRootProjectsDirectory && !document.querySelector('.list a.selected')) {
                startHomeSlideshow();
            }
        }

        lightboxImage.onclick = e => e.stopPropagation();
        lightboxPDF.onclick = e => e.stopPropagation(); 
        lightboxText.onclick = e => e.stopPropagation();
        document.getElementById('lightbox-overlay').onclick = closeLightbox;
        
        function handleDirectoryDownload(event) {
            event.preventDefault(); // Prevent the default link behavior
            
            // Show the loading indicator and hide the button
            downloadDirLink.style.display = 'none';
            downloadLoadingIndicator.style.display = 'block';

            // Use a unique name for the cookie to avoid conflicts
            const downloadCookieName = 'download_in_progress';

            // Set a cookie that expires in a short time
            document.cookie = downloadCookieName + '=1; path=/';

            // Start the actual download by navigating to the URL in a new window/tab
            window.location.href = downloadDirLink.href;

            // Poll for the cookie to be cleared by the server
            const checkDownloadComplete = setInterval(() => {
                // If the cookie is gone, the download has started on the server side
                if (document.cookie.indexOf(downloadCookieName) === -1) {
                    clearInterval(checkDownloadComplete);
                    
                    // Revert the UI
                    downloadDirLink.style.display = 'block';
                    downloadLoadingIndicator.style.display = 'none';
                }
            }, 1000); // Check every second
        }

        function updateDownloadButtons() {
            // Logic to show/hide the download folder button
            if (currentDirectoryHasFiles && !isRootProjectsDirectory) {
                downloadDirLink.style.display = 'block';
                // Construct the download URL
                const downloadUrl = `/?path=${encodeURIComponent(phpCurrentPath)}&download_dir=1`;
                downloadDirLink.href = downloadUrl;
            } else {
                downloadDirLink.style.display = 'none';
            }
            // Ensure the loading indicator is hidden initially
            downloadLoadingIndicator.style.display = 'none';
        }

        // Add the event listener to the download directory link
        downloadDirLink.addEventListener('click', handleDirectoryDownload);

        function showMedia(fullPathFromDocRoot, type) { 
            stopAllMedia(); 
            // Crucial: Stop slideshow if a user explicitly clicks a file in the list or a random file loads
            clearInterval(slideshowInterval);
            slideshowInterval = null;

            previewImage.style.display = 'none';
            previewImage.src = '';

            previewVideo.style.display = 'none';
            previewVideo.src = ''; 
            previewVideo.pause(); 

            previewAudio.style.display = 'none';
            previewAudio.src = ''; 
            previewAudio.pause(); 

            previewPDF.style.display = 'none'; 
            previewPDF.src = ''; 
            downloadLink.style.display = 'none'; 
            downloadLink.href = '#'; 
            downloadLink.removeAttribute('download'); 

            previewTextContent.style.display = 'none';
            previewTextContent.textContent = ''; 
            previewTextContent.classList.remove('plain-text', 'code-text'); 

            previewTextDefault.style.display = 'none'; 
            selectedFileNameDisplay.textContent = '';

            // --- Highlight current file in list & update navigation buttons ---
            document.querySelectorAll('.list a').forEach(link => {
                link.classList.remove('selected');
            });
            
            const allPreviewableFileLinks = Array.from(document.querySelectorAll('.list a')).filter(link => {
                return link.getAttribute('onclick') && link.getAttribute('onclick').startsWith('showMedia(');
            });

            let currentFileLink = null;
            if (type !== 'directory') {
                const expectedOnclick = `showMedia('${fullPathFromDocRoot}', '${type}'); return false;`;
                currentFileLink = allPreviewableFileLinks.find(link => link.getAttribute('onclick') === expectedOnclick);
            }

            if (currentFileLink) {
                currentFileLink.classList.add('selected');
            }
            
            // Re-evaluate button visibility immediately after selecting a file
            updateNavigationButtonVisibility();


            if (fullPathFromDocRoot) {
                const filename = fullPathFromDocRoot.split('/').pop();
                const urlToFetch = '/' + fullPathFromDocRoot; 

                previewTextDefault.style.display = 'none';

                // Only show download link and file name display if NOT on homepage slideshow mode
                if (!(isRootProjectsDirectory && slideshowInterval !== null && !currentFileLink)) {
                    downloadLink.href = urlToFetch;
                    downloadLink.textContent = `download ${filename.toLowerCase()}`;
                    downloadLink.style.display = 'block'; 
                    downloadLink.setAttribute('download', filename); 
                    selectedFileNameDisplay.textContent = filename.toLowerCase(); // Set the name
                    selectedFileNameDisplay.style.display = 'block'; // Make it visible
                }


                if (type === 'image') {
                    previewImage.src = urlToFetch;
                    previewImage.style.display = 'block';
                    previewImage.onclick = () => openLightbox(urlToFetch, 'image'); 
                } else if (type === 'video') {
                    previewVideo.src = urlToFetch;
                    previewVideo.style.display = 'block';
                    previewVideo.load();
                    previewVideo.play(); 
                } else if (type === 'audio') {
                    previewAudio.src = urlToFetch;
                    previewAudio.style.display = 'block';
                    previewAudio.load();
                    previewAudio.play(); 
                } else if (type === 'pdf') { 
                    previewPDF.src = urlToFetch; 
                    previewPDF.style.display = 'block'; 
                    previewPDF.onclick = () => openLightbox(urlToFetch, 'pdf'); 
                } else if (type === 'plain_text' || type === 'code_text') {
                    fetch(urlToFetch)
                        .then(response => response.text())
                        .then(data => {
                            previewTextContent.textContent = data;
                            if (type === 'plain_text') {
                                previewTextContent.classList.add('plain-text');
                            } else if (type === 'code_text') {
                                previewTextContent.classList.add('code-text');
                            }
                            previewTextContent.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error fetching file content:', error);
                            previewTextContent.textContent = 'Failed to load content: ' + error.message;
                            previewTextContent.style.display = 'block';
                        });
                } else if (type === 'directory') {
                    // Handled by selectedFileNameDisplay and previewTextDefault below
                }

            } else {
                // If no file is selected (e.g., initial load of empty directory or no specific 'show' param)
                previewTextDefault.style.display = 'block'; 
                selectedFileNameDisplay.style.display = 'none'; // Hide filename if nothing specific is selected
                downloadLink.style.display = 'none'; 
                downloadLink.removeAttribute('download'); 
            }
            updateDownloadButtons(); // Call the new function to update folder download button state
        }

        // NEW: Function to handle the home directory image slideshow
        function startHomeSlideshow() {
            // Stop any existing slideshow just in case
            clearInterval(slideshowInterval);
            slideshowInterval = null;

            // Apply hide-on-homepage-slideshow classes
            document.querySelectorAll('.list a.image-file-link').forEach(link => {
                link.classList.add('hide-on-homepage-slideshow');
            });
            selectedFileNameDisplay.classList.add('hide-on-homepage-slideshow');
            downloadLink.classList.add('hide-on-homepage-slideshow');
            downloadDirLink.style.display = 'none'; // Ensure the new button is hidden too
            
            // Make sure regular preview text is hidden too
            previewTextDefault.style.display = 'none';

            // Immediately hide prev/next buttons
            previewNavContainer.style.display = 'none';
            prevFileButton.disabled = true;
            nextFileButton.disabled = true;

            // Only run if it's the root 'projects/' directory AND no specific file is being shown via URL param
            if (!isRootProjectsDirectory || phpFileToShow) {
                // console.log("Not starting slideshow: Not root or specific file requested via URL."); // Debug
                return;
            }
            
            // phpHomeImagesForSlideshow should already contain only paths relative to DocumentRoot
            const imagesForSlideshow = phpHomeImagesForSlideshow;

            if (imagesForSlideshow.length === 0) {
                // console.log("No images found in root for slideshow."); // Debug
                previewTextDefault.style.display = 'block'; // Show default text if no images
                // Remove hide class from default text if there are no images
                selectedFileNameDisplay.classList.remove('hide-on-homepage-slideshow');
                downloadLink.classList.remove('hide-on-homepage-slideshow');
                return;
            }

            const cycleRandomImage = () => {
                // Pick a random image path from the pre-filtered array
                const randomIndex = Math.floor(Math.random() * imagesForSlideshow.length);
                const randomImagePath = imagesForSlideshow[randomIndex];
                const filename = randomImagePath.split('/').pop();
                
                // Update preview pane directly, mimicking showMedia for images
                stopAllMedia(); // Stop any other media playing
                
                previewImage.src = '/' + randomImagePath;
                previewImage.style.display = 'block';
                previewImage.onclick = () => openLightbox('/' + randomImagePath, 'image'); 
                
                // No filename or download link on homepage slideshow
                selectedFileNameDisplay.textContent = '';
                selectedFileNameDisplay.style.display = 'none'; // Ensure it's hidden by JS
                downloadLink.href = '#';
                downloadLink.textContent = '';
                downloadLink.style.display = 'none'; // Ensure it's hidden by JS
                downloadLink.removeAttribute('download');
                downloadDirLink.style.display = 'none'; // Hide folder download link

                // Remove 'selected' class from all links, as no file is "selected" in slideshow mode
                document.querySelectorAll('.list a').forEach(a => a.classList.remove('selected'));
                
                // console.log("Slideshow displaying random image:", filename); // Debug
            };

            // Initial display of a random image
            cycleRandomImage();
            // Start the interval for random image cycling
            slideshowInterval = setInterval(cycleRandomImage, 3500); // Change image every 3.5 seconds
            // console.log("Slideshow started with interval ID:", slideshowInterval); // Debug
        }


        window.onload = () => {
            applySavedDarkModePreference();

            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', toggleDarkMode);
            }

            prevFileButton.addEventListener('click', () => navigateFiles('prev'));
            nextFileButton.addEventListener('click', () => navigateFiles('next'));

            // Display desc.txt content on load
            if (phpDescTxtTitle || phpDescTxtDescription) {
                directoryDescriptionContainer.style.display = 'block';
                directoryDescriptionTitle.textContent = phpDescTxtTitle;
                directoryDescriptionText.innerHTML = phpDescTxtDescription;
            } else {
                directoryDescriptionContainer.style.display = 'none';
            }
            
            // --- Logic for initial file/slideshow load ---
            // If a specific file is requested via URL (?show=), load it and disable slideshow
            if (phpFileToShow && phpCurrentPath) {
                let fullPathToShowMediaInList = phpCurrentPath;
                if (!fullPathToShowMediaInList.endsWith('/')) {
                    fullPathToShowMediaInList += '/';
                }
                fullPathToShowMediaInList += phpFileToShow;

                const extension = phpFileToShow.split('.').pop().toLowerCase();
                let fileTypeForLoad = ''; // Renamed to avoid confusion with `fileTypeForScroll`
                const allSupportedExtensions = { 
                    image: ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'],
                    video: ['mp4', 'webm', 'ogg', 'mov', 'avi'], 
                    audio: ['mp3', 'wav'],
                    pdf:   ['pdf'], 
                    plain_text: ['txt', 'log', 'md', 'json', 'xml', 'csv', 'ini', 'cfg'],
                    code_text: [
                        'php', 'html', 'css', 'js', 'py', 'bat', 'cmd', 'sh', 'c', 'cpp', 'h', 'hpp',
                        'java', 'cs', 'go', 'rb', 'pl', 'swift', 'kt', 'rs', 'ts', 'jsx', 'tsx', 'vue',
                        'scss', 'less', 'jsonc', 'yaml', 'yml', 'toml'
                    ]
                };
                for (const typeKey in allSupportedExtensions) {
                    if (allSupportedExtensions[typeKey].includes(extension)) {
                        fileTypeForLoad = typeKey;
                        break;
                    }
                }
                
                showMedia(fullPathToShowMediaInList, fileTypeForLoad || 'plain_text'); 
                
                let targetLink = null;
                // Find the selected link and scroll to it if it's a previewable file link
                if (fileTypeForLoad) { // Only attempt to find and scroll if it's a known previewable file
                    const expectedOnclick = `showMedia('${fullPathToShowMediaInList}', '${fileTypeForLoad}'); return false;`;
                    targetLink = document.querySelector(`.list a[onclick="${expectedOnclick}"]`);
                }
                
                if (targetLink) {
                    targetLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                // console.log("onload: Specific file requested, slideshow prevented."); // Debug
            } else {
                // If no specific file requested via URL (regular directory Browse or root)
                if (isRootProjectsDirectory) {
                    // console.log("onload: Attempting to start home slideshow for root directory."); // Debug
                    startHomeSlideshow(); // Start slideshow if at root
                } else if (typeof firstFileToLoad !== 'undefined' && firstFileToLoad && firstFileToLoad.path) {
                    // Otherwise, load the first previewable item in the directory (if it exists)
                    showMedia(firstFileToLoad.path, firstFileToLoad.type);
                    if (firstFileToLoad.type !== 'directory') {
                        const expectedOnclickForFirst = `showMedia('${firstFileToLoad.path}', '${firstFileToLoad.type}'); return false;`;
                        const targetLinkForFirst = document.querySelector(`.list a[onclick="${expectedOnclickForFirst}"]`);
                        if (targetLinkForFirst) {
                            targetLinkForFirst.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    } else {
                        // If the first item to load is a directory itself
                        const decodedPath = decodeURIComponent(firstFileToLoad.path);
                        const currentPathForHref = phpCurrentPath; // This already has projects/
                        let targetDirName = decodedPath.split('/').pop(); // Get just the directory name
                        // Re-construct the exact href to match the PHP generated one
                        let expectedHrefForDir = `/?path=${encodeURIComponent(currentPathForHref.endsWith('/') ? currentPathForHref : currentPathForHref + '/')}${encodeURIComponent(targetDirName)}/`;
                        
                        const targetLinkForDir = document.querySelector(`.list a[href="${expectedHrefForDir}"]`);
                        if (targetLinkForDir) {
                            targetLinkForDir.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                    // console.log("onload: First previewable item loaded in non-root directory."); // Debug
                } else {
                    showMedia('', ''); // Nothing to preview
                    // console.log("onload: No files to preview and not slideshow context."); // Debug
                }
            }
            // Final check on button visibility based on the initial load state
            updateNavigationButtonVisibility();
            updateDownloadButtons();
        };

        // NEW: Centralized function to update navigation button visibility
        function updateNavigationButtonVisibility() {
            const allPreviewableFileLinks = Array.from(document.querySelectorAll('.list a')).filter(link => {
                return link.getAttribute('onclick') && link.getAttribute('onclick').startsWith('showMedia(');
            });
            const currentSelectedLink = document.querySelector('.list a.selected');

            // console.log("updateNavigationButtonVisibility called."); // Debug
            // console.log("isRootProjectsDirectory:", isRootProjectsDirectory); // Debug
            // console.log("currentSelectedLink (for visibility check):", currentSelectedLink ? currentSelectedLink.textContent : "none"); // Debug
            // console.log("slideshowInterval (for visibility check):", slideshowInterval); // Debug
            // console.log("allPreviewableFileLinks.length (for visibility check):", allPreviewableFileLinks.length); // Debug

            // Condition for hiding: It's the root PROJECTS directory AND the slideshow is active (slideshowInterval is not null)
            // AND no specific file is currently selected (meaning the user hasn't clicked a file to take over from slideshow)
            if (isRootProjectsDirectory && slideshowInterval !== null && !currentSelectedLink) { 
                previewNavContainer.style.display = 'none';
                prevFileButton.disabled = true;
                nextFileButton.disabled = true;
                // console.log("Nav buttons HIDDEN and DISABLED for root slideshow state."); // Debug
            } else if (allPreviewableFileLinks.length > 1) { // Normal navigation condition (more than 1 previewable file)
                previewNavContainer.style.display = 'flex';
                prevFileButton.disabled = false;
                nextFileButton.disabled = false;
                // console.log("Nav buttons SHOWN and ENABLED for navigable state."); // Debug
            } else { // 0 or 1 previewable files, always hide/disable nav buttons
                previewNavContainer.style.display = 'none';
                prevFileButton.disabled = true;
                nextFileButton.disabled = true;
                // console.log("Nav buttons HIDDEN and DISABLED due to insufficient previewable files (0 or 1)."); // Debug
            }
        }


        function navigateFiles(direction) {
            // Check current button state before allowing navigation
            if (prevFileButton.disabled && nextFileButton.disabled) {
                // console.log("Navigation blocked: buttons are disabled or not visible."); // Debug
                return; 
            }

            // Stop slideshow if user manually navigates
            clearInterval(slideshowInterval);
            slideshowInterval = null;

            const allPreviewableFileLinks = Array.from(document.querySelectorAll('.list a')).filter(link => {
                return link.getAttribute('onclick') && link.getAttribute('onclick').startsWith('showMedia(');
            });

            if (allPreviewableFileLinks.length === 0) {
                // console.log("No previewable files to navigate."); // Debug
                return;
            }

            let currentSelectedLink = document.querySelector('.list a.selected');
            let currentIndex = -1;

            if (currentSelectedLink) {
                currentIndex = allPreviewableFileLinks.indexOf(currentSelectedLink);
            } else {
                // Fallback: If no link has the 'selected' class, try to find the file currently displayed by name
                const currentFileName = selectedFileNameDisplay.textContent;
                if (selectedFileNameDisplay.style.display !== 'none' && currentFileName) {
                    currentSelectedLink = allPreviewableFileLinks.find(link => 
                        link.textContent.toLowerCase() === currentFileName
                    );
                    if (currentSelectedLink) {
                        currentIndex = allPreviewableFileLinks.indexOf(currentSelectedLink);
                    }
                }
            }

            // If still no selected link or if the found link is no longer in the previewable list (e.g., hidden file)
            // or if it was the last selected file from a previous directory (rare edge case),
            // start from a valid point.
            if (currentIndex === -1) {
                // If moving 'next' from an unselected state, start from the first element (index 0)
                // If moving 'prev' from an unselected state, start from the last element (index length - 1)
                currentIndex = (direction === 'next') ? -1 : allPreviewableFileLinks.length;
                // console.log(`Starting navigation from ${direction} direction, initial index adjusted to: ${currentIndex}`); // Debug
            }
            
            let nextIndex;
            if (direction === 'next') {
                nextIndex = (currentIndex + 1) % allPreviewableFileLinks.length;
            } else { 
                nextIndex = (currentIndex - 1 + allPreviewableFileLinks.length) % allPreviewableFileLinks.length;
            }

            const nextLink = allPreviewableFileLinks[nextIndex];
            if (nextLink) {
                const onclickStr = nextLink.getAttribute('onclick');
                const match = onclickStr.match(/showMedia\('([^']*)',\s*'([^']*)'\)/);
                if (match && match[1] && match[2]) {
                    const path = match[1];
                    const type = match[2];
                    showMedia(path, type); // This will update the selected class
                    nextLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    // console.log(`Navigating to: ${nextLink.textContent}`); // Debug
                }
            }
            // After navigation, re-evaluate button visibility (important for edge cases like only 1 file left)
            updateNavigationButtonVisibility();
        }

        document.addEventListener('keydown', (e) => {
            if (document.getElementById('lightbox-overlay').style.display === 'flex') {
                return;
            }
            // Check if navigation buttons are disabled (implying slideshow or no navigation allowed)
            if (prevFileButton.disabled && nextFileButton.disabled) {
                 // console.log("Keyboard navigation blocked: buttons disabled."); // Debug
                 return;
            }

            if (e.key === 'ArrowRight') {
                e.preventDefault(); 
                navigateFiles('next');
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigateFiles('prev');
            }
        });
    </script>

</body>
</html>