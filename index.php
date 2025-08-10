<?php
// Enable error reporting for debugging. Disable on a live server.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Base directory for all projects, relative to the web root.
$projectsBaseRelativePath = 'projects/';

/**
 * Resolves a given URL-safe path to a secure, real file system path.
 *
 * @param string $path The URL path from a GET parameter (e.g., 'projects/my-folder').
 * @param string $projectsBaseRelativePath The base directory name (e.g., 'projects/').
 * @return string The absolute, real path on the file system, or a secure default.
 */
function resolveSecurePath($path, $projectsBaseRelativePath) {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $projectsAbsolutePath = realpath($documentRoot . DIRECTORY_SEPARATOR . $projectsBaseRelativePath);

    // Default to the project root if no path is given or if it's invalid.
    if (empty($path) || !is_string($path)) {
        return $projectsAbsolutePath;
    }

    // Decode the path from the URL parameter for file system operations.
    $decodedPath = urldecode($path);

    // Ensure consistent path separators.
    $sanitizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decodedPath);
    
    // Concatenate document root with the decoded path and resolve it.
    $fullPath = realpath($documentRoot . DIRECTORY_SEPARATOR . $sanitizedPath);
    
    // Security check: If the path doesn't resolve or is outside the projects folder,
    // prevent directory traversal and return the secure projects root path.
    if ($fullPath === false || strpos($fullPath, $projectsAbsolutePath) !== 0) {
        return $projectsAbsolutePath;
    }

    // If the resolved path is a file, return its containing directory.
    if (is_file($fullPath)) {
        return dirname($fullPath);
    }

    return $fullPath;
}

// --- Handle single file download ---
if (isset($_GET['download_file'])) {
    $pathForDownload = isset($_GET['path']) ? $_GET['path'] : '';
    $fileName = isset($_GET['file']) ? $_GET['file'] : '';
    
    if (empty($pathForDownload) || empty($fileName)) {
        http_response_code(400);
        die("Invalid download request.");
    }

    $fullPath = resolveSecurePath($pathForDownload, $projectsBaseRelativePath);
    $filePath = $fullPath . DIRECTORY_SEPARATOR . $fileName;

    // Additional security checks to ensure the file exists and is within the requested directory.
    if (!is_file($filePath) || basename($filePath) !== $fileName || strpos(realpath($filePath), realpath($fullPath)) !== 0) {
        http_response_code(404);
        die("File not found or invalid path.");
    }

    // Set headers for a direct download.
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    ob_clean();
    flush();
    readfile($filePath);
    exit;
}

// --- Handle directory download (zipped) ---
if (isset($_GET['download_dir'])) {
    set_time_limit(300); // 5 minutes for large directories.

    $pathForDownload = isset($_GET['path']) ? $_GET['path'] : $projectsBaseRelativePath;
    $fullPath = resolveSecurePath($pathForDownload, $projectsBaseRelativePath);

    $filesInCurrentDir = @array_diff(scandir($fullPath), array('.', '..'));
    if ($filesInCurrentDir === false) {
        http_response_code(403);
        die("Permission denied or directory unreadable.");
    }

    $zip = new ZipArchive();
    $dirName = basename($fullPath);
    $zipFileName = $dirName . '.zip';
    $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

    if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($filesInCurrentDir as $file) {
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath) && basename($file) !== '.htaccess' && basename($file) !== 'desc.txt') {
                $zip->addFile($filePath, $file);
            }
        }
        $zip->close();

        if (file_exists($tempZipFile)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tempZipFile));
            
            ob_clean();
            flush();
            readfile($tempZipFile);
            
            unlink($tempZipFile); 
            exit;
        } else {
            http_response_code(500);
            die("Could not create zip file.");
        }
    } else {
        http_response_code(500);
        die("Could not open zip file for writing.");
    }
}

// --- Handle random redirect ---
function getAllFilesRecursive($dir) {
    $files = [];
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }
    $items = scandir($dir);
    if ($items === false) {
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
    $actualProjectsAbsPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . $projectsBaseRelativePath);
    $allAvailableFiles = getAllFilesRecursive($actualProjectsAbsPath);

    $phpMediaExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
        'mp4', 'webm', 'ogg', 'mov', 'avi', 'mp3', 'wav',
        'txt', 'log', 'md', 'php', 'html', 'css', 'js', 'json', 'xml', 'csv',
        'py', 'bat', 'cmd', 'sh', 'c', 'cpp', 'h', 'hpp', 'java', 'cs', 'go', 'rb', 'pl', 'swift', 'kt', 'rs', 'ts', 'jsx', 'tsx', 'vue', 'scss', 'less', 'jsonc', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'pdf'
    ];

    $validFiles = array_filter($allAvailableFiles, function ($file) use ($phpMediaExtensions) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (basename($file) === 'desc.txt' || basename(strtolower($file)) === '.htaccess') {
            return false;
        }
        return in_array($extension, $phpMediaExtensions);
    });

    if (!empty($validFiles)) {
        $directoriesWithValidFiles = [];
        foreach ($validFiles as $file) {
            $dir = dirname($file);
            if (!isset($directoriesWithValidFiles[$dir])) {
                $directoriesWithValidFiles[$dir] = [];
            }
            $directoriesWithValidFiles[$dir][] = $file;
        }
        $randomDirKeys = array_keys($directoriesWithValidFiles);
        shuffle($randomDirKeys);

        $randomFile = false;
        foreach ($randomDirKeys as $dirKey) {
            $filesInSelectedDir = $directoriesWithValidFiles[$dirKey];
            if (!empty($filesInSelectedDir)) {
                $randomFile = $filesInSelectedDir[array_rand($filesInSelectedDir)];
                break;
            }
        }

        if ($randomFile) {
            $randomFileFullPathFromDocRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR, '', $randomFile);
            $randomFileDirFromDocRoot = dirname($randomFileFullPathFromDocRoot);
            $randomFileBaseName = basename($randomFileFullPathFromDocRoot);
            $pathForQueryParam = str_replace(DIRECTORY_SEPARATOR, '/', $randomFileDirFromDocRoot);
            
            $redirectUrl = '/?path=' . rawurlencode($pathForQueryParam) . '&show=' . rawurlencode($randomFileBaseName);
            
            header("Location: " . $redirectUrl);
            exit;
        } else {
            header("Location: /");
            exit;
        }
    } else {
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
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; font-family: 'Arial', sans-serif; height: 100vh; display: flex; flex-direction: column; }
        :root {
            --light-bg: #FFFFFF; --light-text: #000000; --light-gray-text: #555555; --light-accent: rgb(0, 0, 255);
            --dark-bg: #000000; --dark-text: #FFFFFF; --dark-gray-text: #AAAAAA; --dark-accent: rgb(255, 255, 0);
        }
        html { background-color: var(--light-bg); color: var(--light-text); }
        html.dark-mode { background-color: var(--dark-bg); color: var(--dark-text); }
        body { background-color: inherit; color: inherit; }
        h1, h2 { font-weight: normal; margin-top: 0; margin-bottom: 15px; padding-bottom: 5px; font-size: 1.5em; letter-spacing: 1px; color: inherit; }
        .breadcrumb { width: 100%; padding: 20px; display: flex; flex-wrap: wrap; align-items: center; background-color: var(--light-bg); gap: 10px; }
        html.dark-mode .breadcrumb { background-color: var(--dark-bg); }
        .breadcrumb #logo-container { width: 120px; height: 120px; margin-right: 15px; vertical-align: middle; cursor: pointer; position: relative; display: inline-block; overflow: hidden; }
        .breadcrumb #logo-base { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; opacity: 1; transition: opacity 1s ease-in-out; display: block; }
        .breadcrumb #logo-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; opacity: 0; transition: opacity 1s ease-in-out; }
        .breadcrumb #logo-container:hover #logo-base { opacity: 0; }
        .breadcrumb #logo-container:hover #logo-overlay { opacity: 1; }
        .breadcrumb a:not(.nav-button-style) { text-decoration: none !important; color: var(--light-text); font-weight: bold; padding: 2px 0; transition: color 0.1s ease; }
        html.dark-mode .breadcrumb a:not(.nav-button-style) { color: var(--dark-text); }
        .breadcrumb a:not(.nav-button-style):hover { color: var(--light-accent); }
        html.dark-mode .breadcrumb a:not(.nav-button-style):hover { color: var(--dark-accent); }
        .breadcrumb a:not(.nav-button-style):visited { text-decoration: none !important; color: var(--light-text); }
        html.dark-mode .breadcrumb a:not(.nav-button-style):visited { color: var(--dark-text); }
        .breadcrumb .separator { color: var(--light-text); font-weight: normal; margin: 0 5px; }
        html.dark-mode .breadcrumb .separator { color: var(--dark-text); }
        .breadcrumb div.random-button-container { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .nav-button-style { background: none; border: 2px solid var(--light-accent); color: var(--light-accent); padding: 5px 10px; font-size: 0.9em; font-weight: bold; cursor: pointer; text-decoration: none !important; transition: background-color 0.1s ease, color 0.1s ease, border-color 0.1s ease; text-transform: lowercase; display: inline-block; line-height: 1.2; white-space: nowrap; box-sizing: border-box; }
        html.dark-mode .nav-button-style { border-color: var(--dark-accent); color: var(--dark-accent); }
        .nav-button-style:visited { color: var(--light-accent); border-color: var(--light-accent); }
        html.dark-mode .nav-button-style:visited { color: var(--dark-accent); border-color: var(--dark-accent); }
        .nav-button-style:hover:not(:disabled) { background-color: var(--light-accent); color: var(--light-bg); border-color: var(--light-accent); }
        html.dark-mode .nav-button-style:hover:not(:disabled) { background-color: var(--dark-accent); color: var(--dark-bg); border-color: var(--dark-accent); }
        .nav-button-style:disabled { border-color: var(--light-gray-text); color: var(--light-gray-text); cursor: not-allowed; opacity: 0.6; }
        html.dark-mode .nav-button-style:disabled { border-color: var(--dark-gray-text); color: var(--dark-gray-text); }
        #dark-mode-toggle { }
        #directory-description-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 20px; background-color: var(--light-bg); color: var(--light-text); text-align: left; border-bottom: 1px solid var(--light-gray-text); display: none; margin-bottom: 20px; }
        html.dark-mode #directory-description-container { background-color: var(--dark-bg); color: var(--dark-text); border-color: var(--dark-gray-text); }
        #directory-description-container h3 { font-size: 1.4em; margin-top: 0; margin-bottom: 10px; font-weight: bold; color: var(--light-accent); text-transform: lowercase; }
        html.dark-mode #directory-description-container h3 { color: var(--dark-accent); }
        #directory-description-container p { font-size: 1em; margin-top: 0; margin-bottom: 0; line-height: 1.6; color: inherit; }
        #directory-description-container p a { text-decoration: underline; color: inherit; font-weight: bold; }
        html.dark-mode #directory-description-container p a:hover { color: var(--dark-accent); }
        html.dark-mode #directory-description-container p a:visited { color: var(--dark-text); }
        .container { display: flex; flex-direction: row; align-items: stretch; width: 100%; max-width: 1200px; margin: 0 auto; flex-grow: 1; overflow: hidden; min-height: 250px; }
        .list { flex-basis: 280px; flex-shrink: 0; padding: 20px; background-color: var(--light-bg); overflow-y: auto; display: block; }
        html.dark-mode .list { background-color: var(--dark-bg); }
        .list h2 { color: inherit; }
        .list a { display: block; margin-bottom: 8px; text-decoration: none; color: var(--light-text); padding: 5px 0; font-weight: bold; transition: color 0.1s ease; }
        html.dark-mode .list a { color: var(--dark-text); }
        .list a:hover { color: var(--light-accent); background-color: var(--white); }
        html.dark-mode .list a:hover { color: var(--dark-accent); background-color: var(--black); }
        .list a.selected { color: var(--light-accent); text-decoration: none; }
        html.dark-mode .list a.selected { color: var(--dark-accent); }
        .list a.non-interactive-file { color: var(--light-gray-text); font-weight: normal; }
        html.dark-mode .list a.non-interactive-file:hover { color: var(--dark-gray-text); }
        .list a.non-interactive-file:hover { background-color: transparent; color: var(--light-gray-text); }
        html.dark-mode .list a.non-interactive-file:hover { color: var(--dark-gray-text); }
        .list a.hide-on-homepage-slideshow { display: none !important; }
        .preview { flex-grow: 1; padding: 20px; padding-top: 60px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; background-color: var(--light-bg); text-align: center; position: relative; overflow-y: auto; }
        html.dark-mode .preview { background-color: var(--dark-bg); }
        .preview .file-name-display { font-weight: bold; color: var(--light-text); font-size: 1.2em; display: none; position: absolute; top: 20px; left: 20px; text-transform: lowercase; z-index: 10; }
        html.dark-mode .preview .file-name-display { color: var(--dark-text); }
        .preview .file-name-display.hide-on-homepage-slideshow { display: none !important; }
        .preview-media-wrapper { flex-grow: 1; display: flex; align-items: center; justify-content: center; width: 100%; overflow: hidden; min-height: 100px; }
        .preview-media-wrapper img, .preview-media-wrapper video, .preview-media-wrapper audio, .preview-media-wrapper iframe#pdf-preview, .preview-media-wrapper pre, .preview-media-wrapper p.alt-text { max-width: 100%; max-height: 100%; object-fit: contain; display: block; margin: 0; }
        .preview-media-wrapper img[src=""] { display: none; }
        .preview-media-wrapper p.alt-text { color: var(--light-gray-text); font-style: italic; font-size: 1.2em; text-align: center; }
        html.dark-mode .preview-media-wrapper p.alt-text { color: var(--dark-gray-text); }
        .preview-media-wrapper iframe#pdf-preview { min-height: 750px; width: 100%; }
        .preview-media-wrapper pre { min-height: 400px; width: 100%; }
        #download-link { flex-shrink: 0; margin-top: 10px; margin-bottom: 0; text-decoration: none; color: var(--light-accent); font-weight: bold; display: none; }
        html.dark-mode #download-link { color: var(--dark-accent); }
        #download-link.hide-on-homepage-slideshow { display: none !important; }
        #download-dir-link { margin-top: 10px; margin-bottom: 0; display: none; }
        .download-loading-indicator { display: none; text-align: center; font-style: italic; color: var(--light-accent); margin-top: 10px; margin-bottom: 0; white-space: nowrap; }
        html.dark-mode .download-loading-indicator { color: var(--dark-accent); }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .download-loading-indicator:before { content: ''; display: inline-block; width: 1em; height: 1em; border: 2px solid currentColor; border-bottom-color: transparent; border-radius: 50%; animation: spin 0.7s linear infinite; margin-right: 0.5em; vertical-align: middle; }
        .preview-nav { display: none; position: absolute; top: 20px; right: 20px; gap: 10px; z-index: 10; }
        .preview-nav button { }
        .nav-button-style.disabled-hide { display: none !important; }
        .preview-nav button:disabled { }
        .preview pre, #lightbox-text { white-space: pre-wrap; word-wrap: break-word; overflow: auto; max-width: 100%; padding: 15px; box-sizing: border-box; display: none; text-align: left; margin: 0; }
        .preview pre.plain-text { background-color: var(--light-bg); border: none; color: var(--light-text); font-family: sans-serif; font-size: 1em; }
        html.dark-mode .preview pre.plain-text { background-color: var(--dark-bg); color: var(--dark-text); }
        .preview pre.code-text { background-color: var(--black); border: none; color: var(--white); font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; font-size: 0.9em; }
        #lightbox-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: var(--black); z-index: 1000; justify-content: center; align-items: center; flex-direction: column; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        #lightbox-close { position: absolute; top: 20px; right: 20px; color: var(--white); font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; padding: 5px 10px; background-color: var(--black); line-height: 1; text-decoration: none; transition: color 0.1s ease, background-color 0.1s ease; }
        #lightbox-close:hover { color: var(--light-accent); background-color: var(--black); }
        html.dark-mode #lightbox-close:hover { color: var(--dark-accent); }
        #lightbox-image, #lightbox-video, #lightbox-audio, #lightbox-pdf, #lightbox-text { max-width: 90%; max-height: 90%; object-fit: contain; display: none; margin: auto; }
        body.no-scroll { overflow: hidden; }
        #lightbox-image { transition: transform 0.2s ease; cursor: grab; user-select: none; }
        #lightbox-image:active { cursor: grabbing; }
        @media (max-width: 768px) {
            html, body { height: auto; min-height: 100vh; overflow-y: auto; -webkit-overflow-scrolling: touch; }
            .breadcrumb { padding: 10px; font-size: 0.8em; flex-direction: row; justify-content: space-between; align-items: center; gap: 5px; }
            .breadcrumb #logo-container { width: 50px; height: 50px; margin-right: 5px; }
            .breadcrumb #logo-base, .breadcrumb #logo-overlay { height: 100%; }
            .breadcrumb .nav-button-style { padding: 3px 6px; font-size: 0.8em; }
            .breadcrumb .separator { margin: 0 3px; }
            .breadcrumb div { margin-left: auto; width: auto; text-align: right; margin-top: 0; }
            #directory-description-container { padding: 10px; margin-bottom: 10px; }
            #directory-description-container h3 { font-size: 1.2em; }
            #directory-description-container p { font-size: 0.9em; }
            .container { flex-direction: column; max-width: 100%; margin: 0 auto; min-height: auto; height: auto; }
            #list-toggle-button { display: none; }
            .list { flex: none; width: 100%; padding: 10px; max-height: 50vh; overflow-y: auto; -webkit-overflow-scrolling: touch; display: block; }
            .list h2 { font-size: 1.1em; padding-bottom: 5px; margin-bottom: 10px; }
            .list a { padding: 10px; font-size: 0.9em; }
            .preview { padding: 10px; padding-top: 40px; min-height: 250px; align-items: center; justify-content: center; }
            .file-name-display { top: 10px; left: 10px; font-size: 1em; }
            .preview-nav { top: 10px; right: 10px; gap: 5px; }
            .preview-nav button { }
            .preview-media-wrapper iframe#pdf-preview { min-height: 500px; }
            .preview-media-wrapper pre { min-height: 300px; }
            #lightbox-close { top: 10px; right: 15px; font-size: 30px; }
            #lightbox-image, #lightbox-video, #lightbox-audio, #lightbox-pdf, #lightbox-text { max-width: 95%; max-height: 95%; }
            #lightbox-text { padding: 15px; font-size: 0.9em; }
        }
    </style>
    <meta name="description" content="Freelance media consultant in Williamsport, PA offering graphic design, web design, photography, tech support, and digital content creation.">
    <meta name="keywords" content="graphic design, web design, photography, tech support, video editing, audio editing, logo design, freelance designer, digital content, IT support, Williamsport PA, website development, copywriting, data entry, media consultant">
    <meta name="author" content="Jacob Zimmerman">
</head>
<body>
    <header>
        <div class="breadcrumb">
            <a href="/?path=projects/" id="logo-container">
                <img id="logo-base" src="/logo.png" alt="Logo">
                <img id="logo-overlay" src="/logo2.png" alt="Logo Hover">
            </a>

            <a href="/contact.php" class="nav-button-style">contact</a>
            &nbsp;&nbsp;
            <?php
            $currentPath = isset($_GET['path']) ? $_GET['path'] : 'projects/';
            $currentPathDecoded = urldecode($currentPath);

            $fullCurrentDir = resolveSecurePath($currentPath, $projectsBaseRelativePath);
            $isRootProjectsDirectory = (trim(strtolower($currentPathDecoded), '/') === 'projects');
            
            $cleanDisplayPath = str_replace('projects/', '', $currentPathDecoded);
            if ($cleanDisplayPath === '/') $cleanDisplayPath = '';
            $displayPathTrimmed = trim($cleanDisplayPath, '/');

            $fileToShow = isset($_GET['show']) ? urldecode($_GET['show']) : '';

            $descTxtTitle = '';
            $descTxtDescription = '';
            $descTxtPath = $fullCurrentDir . '/desc.txt';
            if (file_exists($descTxtPath) && is_readable($descTxtPath)) {
                $descContent = file_get_contents($descTxtPath);
                $lines = explode("\n", $descContent);
                if (!empty($lines)) {
                    $descTxtTitle = trim($lines[0]);
                    array_shift($lines);
                    $descTxtDescription = trim(implode("\n", $lines));
                    $descTxtDescription = preg_replace_callback(
                        '/(?<=\s|^)([\w\s]+)\[(https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(?:\/[a-zA-Z0-9\-\._~:\/?#\[\]@!$&\'()*+,;=]*)?)\]/',
                        function ($matches) {
                            $text = trim($matches[1]);
                            $url = $matches[2];
                            return htmlspecialchars($text) . '[<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url) . '</a>]';
                        },
                        $descTxtDescription
                    );
                }
            }

            echo "<a href='/?path=" . rawurlencode('projects/') . "'>" . strtolower(htmlspecialchars("home")) . "</a>";

            $pathSegments = explode('/', $displayPathTrimmed);
            $cumulativePathForLinks = 'projects/';

            foreach ($pathSegments as $segment) {
                if (!empty($segment)) {
                    $cumulativePathForLinks .= rawurlencode($segment) . '/';
                    echo " <span class='separator'>&bullet;</span> <a href='/?path=" . rawurlencode($cumulativePathForLinks) . "'>" . strtolower(htmlspecialchars($segment)) . "</a>";
                }
            }
            
            echo '<div class="random-button-container">
                    <a class="nav-button-style" href="?random=1">random file</a>
                    <button id="dark-mode-toggle" class="nav-button-style">dark mode</button>
                  </div>';
            ?>
        </div>

        <div id="directory-description-container">
            <h3 id="directory-description-title"></h3>
            <p id="directory-description-text"></p>
        </div>
    </header>
    <div class="container">
        <div class="list">
            <h2>content:</h2>
            <?php
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

            $phpHomeImagesForSlideshow = [];
            $currentDirectoryHasFiles = false;

            if (!is_dir($fullCurrentDir)) {
                echo "<p>DIRECTORY NOT FOUND.</p>";
            } else {
                $files = scandir($fullCurrentDir);
                if ($files === false) {
                    echo "<p>PERMISSION DENIED OR DIRECTORY UNREADABLE.</p>";
                } else {
                    natcasesort($files);

                    $firstFileToPreview = null;
                    $actualFiles = [];
                    $directories = [];
                    foreach ($files as $file) {
                        if ($file === '.' || $file == '..') continue;
                        $filePath = $fullCurrentDir . '/' . $file;
                        if (is_dir($filePath)) {
                            $directories[] = $file;
                        } else {
                            if (basename($file) !== 'desc.txt' && basename($file) !== '.htaccess') {
                                $currentDirectoryHasFiles = true;
                            }
                            $actualFiles[] = $file;
                        }
                    }

                    foreach ($actualFiles as $file) {
                        if ($firstFileToPreview !== null) break;
                        $filePath = $fullCurrentDir . '/' . $file;
                        $fileRelativePathFromDocRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR, '', realpath($filePath));
                        $extension = pathinfo($file, PATHINFO_EXTENSION);
                        $fileType = '';
                        $lowerExt = strtolower($extension);

                        if (in_array($lowerExt, $imageExtensions)) { $fileType = 'image'; }
                        else if (in_array($lowerExt, $videoExtensions)) { $fileType = 'video'; }
                        else if (in_array($lowerExt, $audioExtensions)) { $fileType = 'audio'; }
                        else if (in_array($lowerExt, $pdfExtensions)) { $fileType = 'pdf'; }
                        else if (in_array($lowerExt, $plainTextExtensions)) { $fileType = 'plain_text'; }
                        else if (in_array($lowerExt, $codeExtensions)) { $fileType = 'code_text'; }
                        
                        if ($fileType) {
                            $firstFileToPreview = ['path' => str_replace(DIRECTORY_SEPARATOR, '/', $fileRelativePathFromDocRoot), 'type' => $fileType];
                        }

                        if ($isRootProjectsDirectory && in_array(strtolower($extension), $imageExtensions)) {
                            $phpHomeImagesForSlideshow[] = str_replace(DIRECTORY_SEPARATOR, '/', $fileRelativePathFromDocRoot);
                        }
                    }

                    if ($firstFileToPreview === null && !empty($directories)) {
                        $firstDirectory = $directories[0];
                        $firstDirectoryPath = $fullCurrentDir . '/' . $firstDirectory;
                        $firstFileToPreview = ['path' => str_replace(DIRECTORY_SEPARATOR, '/', str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR, '', realpath($firstDirectoryPath))), 'type' => 'directory'];
                    }

                    natcasesort($directories);
                    natcasesort($actualFiles);
                    $allDisplayItems = array_merge($directories, $actualFiles);

                    foreach ($allDisplayItems as $file) {
                        if (strtolower($file) === 'desc.txt' || strtolower($file) === '.htaccess') continue;
                        
                        $filePath = $fullCurrentDir . '/' . $file;
                        $itemRelativePathFromDocRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR, '', realpath($filePath));
                        $extension = pathinfo($file, PATHINFO_EXTENSION);
                        $fileLowercase = strtolower(htmlspecialchars($file));

                        if (is_dir($filePath)) {
                            $dirLinkPath = $currentPathDecoded;
                            if (!empty($dirLinkPath) && substr($dirLinkPath, -1) !== '/') {
                                $dirLinkPath .= '/';
                            }
                            $dirLinkPath .= rawurlencode($file) . '/';
                            echo "<a href='/?path=" . rawurlencode($dirLinkPath) . "'>" . $fileLowercase . "</a>";
                        } else {
                            $lowerExt = strtolower($extension);
                            $fileType = '';
                            if (in_array($lowerExt, $imageExtensions)) { $fileType = 'image'; }
                            else if (in_array($lowerExt, $videoExtensions)) { $fileType = 'video'; }
                            else if (in_array($lowerExt, $audioExtensions)) { $fileType = 'audio'; }
                            else if (in_array($lowerExt, $pdfExtensions)) { $fileType = 'pdf'; }
                            else if (in_array($lowerExt, $plainTextExtensions)) { $fileType = 'plain_text'; }
                            else if (in_array($lowerExt, $codeExtensions)) { $fileType = 'code_text'; }

                            if ($fileType) {
                                $hideClass = ($isRootProjectsDirectory && in_array($lowerExt, $imageExtensions)) ? ' hide-on-homepage-slideshow' : '';
                                echo "<a href='#' class='previewable-file-link{$hideClass}' data-path='" . htmlspecialchars(str_replace(DIRECTORY_SEPARATOR, '/', $itemRelativePathFromDocRoot)) . "' data-type='" . htmlspecialchars($fileType) . "'>" . $fileLowercase . "</a>";
                            } else {
                                echo "<a class='non-interactive-file' href='#'>" . $fileLowercase . "</a>";
                            }
                        }
                    }

                    // --- DEBUGGING CODE START ---
                    echo "<script>";
                    echo "console.log('PHP Debug: isRootProjectsDirectory = ', " . json_encode($isRootProjectsDirectory) . ");";
                    echo "console.log('PHP Debug: Images found for slideshow = ', " . json_encode($phpHomeImagesForSlideshow) . ");";
                    echo "</script>";
                    // --- DEBUGGING CODE END ---
                    
                    echo "<script type='text/javascript'>";
                    echo "var phpCurrentPath = " . json_encode($currentPathDecoded) . ";";
                    echo "var phpFileToShow = " . json_encode($fileToShow) . ";";
                    echo "var firstFileToLoad = " . json_encode($firstFileToPreview) . ";";
                    echo "var phpDescTxtTitle = " . json_encode($descTxtTitle) . ";";
                    echo "var phpDescTxtDescription = " . json_encode($descTxtDescription) . ";";
                    echo "var isRootProjectsDirectory = " . json_encode($isRootProjectsDirectory) . ";";
                    echo "var phpHomeImagesForSlideshow = " . json_encode($phpHomeImagesForSlideshow) . ";";
                    echo "var currentDirectoryHasFiles = " . json_encode($currentDirectoryHasFiles) . ";";
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
            
            <div class="preview-media-wrapper">
                <img id="preview-image" src="" alt="" style="display:none;" onclick="openLightbox(this.src, 'image')">
                <video id="preview-video" controls style="display:none;"></video>
                <audio id="preview-audio" controls style="display:none;"></audio>
                <iframe id="pdf-preview" style="display:none;"></iframe>
                <pre id="preview-text-content" style="display:none;"></pre>
                <p id="preview-text" class="alt-text">SELECT A FILE OR FOLDER.</p>
            </div>

            <a id="download-link" target="_blank" rel="noopener noreferrer" href="#" class="nav-button-style <?php echo ($isRootProjectsDirectory && !$fileToShow) ? 'hide-on-homepage-slideshow' : ''; ?>" style="display:none;">download file</a>
            <a id="download-dir-link" class="nav-button-style" style="display:none;">download folder</a>
            <p id="download-loading" class="download-loading-indicator">downloading...</p>
        </div>
    </div>

    <div id="lightbox-overlay" onclick="closeLightbox()">
        <span id="lightbox-close" onclick="event.stopPropagation(); closeLightbox();">&times;</span>
        <img id="lightbox-image" src="" alt="Maximized Image" onclick="event.stopPropagation();">
        <video id="lightbox-video" controls onclick="event.stopPropagation();"></video>
        <audio id="lightbox-audio" controls onclick="event.stopPropagation();"></audio>
        <iframe id="lightbox-pdf" style="display:none;"></iframe>
        <pre id="lightbox-text" onclick="event.stopPropagation();"></pre>
    </div>

    <script>
        let zoomLevel = 1;
        let isDragging = false;
        let startX = 0, startY = 0;
        let currentX = 0, currentY = 0;
        let animationFrameId = null;
        let slideshowInterval = null;

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

        const directoryDescriptionContainer = document.getElementById('directory-description-container');
        const directoryDescriptionTitle = document.getElementById('directory-description-title');
        const directoryDescriptionText = document.getElementById('directory-description-text');

        const logoSources = {
            light: { static: '/logo.png', hover: '/logo2.png' },
            dark:  { static: '/darklogo.png', hover: '/darklogo2.png' }
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

            if (isRootProjectsDirectory && !document.querySelector('.list a.selected')) {
                startHomeSlideshow();
            }
        }

        lightboxImage.onclick = e => e.stopPropagation();
        lightboxPDF.onclick = e => e.stopPropagation(); 
        lightboxText.onclick = e => e.stopPropagation();
        document.getElementById('lightbox-overlay').onclick = closeLightbox;
        
        function handleDirectoryDownload(event) {
            event.preventDefault();
            downloadDirLink.style.display = 'none';
            downloadLoadingIndicator.style.display = 'block';
            const downloadCookieName = 'download_in_progress';
            document.cookie = downloadCookieName + '=1; path=/';
            window.location.href = downloadDirLink.href;
            const checkDownloadComplete = setInterval(() => {
                if (document.cookie.indexOf(downloadCookieName) === -1) {
                    clearInterval(checkDownloadComplete);
                    downloadDirLink.style.display = 'block';
                    downloadLoadingIndicator.style.display = 'none';
                }
            }, 1000);
        }

        function updateDownloadButtons() {
            if (currentDirectoryHasFiles && !isRootProjectsDirectory) {
                downloadDirLink.style.display = 'block';
                const downloadUrl = `/?path=${encodeURIComponent(phpCurrentPath)}&download_dir=1`;
                downloadDirLink.href = downloadUrl;
            } else {
                downloadDirLink.style.display = 'none';
            }
            downloadLoadingIndicator.style.display = 'none';
        }

        downloadDirLink.addEventListener('click', handleDirectoryDownload);

        function showMedia(linkElement) {
            const fullPathFromDocRoot = linkElement.getAttribute('data-path');
            const type = linkElement.getAttribute('data-type');
            
            stopAllMedia();
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

            previewTextContent.style.display = 'none';
            previewTextContent.textContent = '';
            previewTextContent.classList.remove('plain-text', 'code-text');
            previewTextDefault.style.display = 'none';
            selectedFileNameDisplay.textContent = '';

            document.querySelectorAll('.previewable-file-link').forEach(link => {
                link.classList.remove('selected');
            });
            linkElement.classList.add('selected');

            updateNavigationButtonVisibility();

            if (fullPathFromDocRoot) {
                // Correctly extract the filename and path for the download URL.
                const filename = fullPathFromDocRoot.split('/').pop();
                const pathParts = fullPathFromDocRoot.split('/').slice(0, -1);
                const pathForDownload = pathParts.join('/');

                // Create the download URL using the new server-side handler.
                const downloadUrl = `/?path=${encodeURIComponent(pathForDownload)}&file=${encodeURIComponent(filename)}&download_file=1`;
                downloadLink.href = downloadUrl;
                downloadLink.textContent = `download ${filename.toLowerCase()}`;
                downloadLink.style.display = 'block';

                selectedFileNameDisplay.textContent = filename.toLowerCase();
                selectedFileNameDisplay.style.display = 'block';
                
                // Use a proper web-relative path for media display, not the server's absolute path.
                const urlForDisplay = `/${fullPathFromDocRoot}`;

                if (type === 'image') {
                    previewImage.src = urlForDisplay;
                    previewImage.style.display = 'block';
                    previewImage.onclick = () => openLightbox(urlForDisplay, 'image');
                } else if (type === 'video') {
                    previewVideo.src = urlForDisplay;
                    previewVideo.style.display = 'block';
                    previewVideo.load();
                    previewVideo.play();
                } else if (type === 'audio') {
                    previewAudio.src = urlForDisplay;
                    previewAudio.style.display = 'block';
                    previewAudio.load();
                    previewAudio.play();
                } else if (type === 'pdf') {
                    previewPDF.src = urlForDisplay;
                    previewPDF.style.display = 'block';
                    previewPDF.onclick = () => openLightbox(urlForDisplay, 'pdf');
                } else if (type === 'plain_text' || type === 'code_text') {
                    fetch(urlForDisplay)
                        .then(response => response.text())
                        .then(data => {
                            previewTextContent.textContent = data;
                            previewTextContent.classList.add(type);
                            previewTextContent.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error fetching file content:', error);
                            previewTextContent.textContent = 'Failed to load content: ' + error.message;
                            previewTextContent.style.display = 'block';
                        });
                }
            } else {
                previewTextDefault.style.display = 'block';
                selectedFileNameDisplay.style.display = 'none';
                downloadLink.style.display = 'none';
            }
            updateDownloadButtons();
        }
        
        fileListDiv.addEventListener('click', (e) => {
            const link = e.target.closest('.previewable-file-link');
            if (link) {
                e.preventDefault();
                showMedia(link);
            }
        });
        
        function startHomeSlideshow() {
            clearInterval(slideshowInterval);
            slideshowInterval = null;

            // --- DEBUGGING CODE START ---
            console.log('JS Debug: Starting slideshow...');
            console.log('JS Debug: Images for slideshow = ', phpHomeImagesForSlideshow);
            // --- DEBUGGING CODE END ---

            document.querySelectorAll('.previewable-file-link[data-type="image"]').forEach(link => {
                link.classList.add('hide-on-homepage-slideshow');
            });
            selectedFileNameDisplay.classList.add('hide-on-homepage-slideshow');
            downloadLink.classList.add('hide-on-homepage-slideshow');
            downloadDirLink.style.display = 'none';
            
            previewTextDefault.style.display = 'none';
            previewNavContainer.style.display = 'none';
            prevFileButton.disabled = true;
            nextFileButton.disabled = true;

            if (!isRootProjectsDirectory || phpFileToShow || phpHomeImagesForSlideshow.length === 0) {
                if (phpHomeImagesForSlideshow.length === 0) {
                    previewTextDefault.style.display = 'block';
                    selectedFileNameDisplay.classList.remove('hide-on-homepage-slideshow');
                    downloadLink.classList.remove('hide-on-homepage-slideshow');
                }
                return;
            }

            const imagesForSlideshow = phpHomeImagesForSlideshow;
            let lastIndex = -1;

            const cycleRandomImage = () => {
                // --- DEBUGGING CODE START ---
                console.log('JS Debug: Cycling to next image...');
                // --- DEBUGGING CODE END ---
                if (imagesForSlideshow.length === 0) {
                    console.warn("No images found for the slideshow.");
                    return;
                }

                let randomIndex;
                do {
                    randomIndex = Math.floor(Math.random() * imagesForSlideshow.length);
                } while (randomIndex === lastIndex && imagesForSlideshow.length > 1);
                
                lastIndex = randomIndex;
                const randomImagePath = imagesForSlideshow[randomIndex];
                
                stopAllMedia();
                
                previewImage.src = '/' + randomImagePath;
                previewImage.style.display = 'block';
                previewImage.onclick = () => openLightbox('/' + randomImagePath, 'image');
                
                selectedFileNameDisplay.textContent = '';
                selectedFileNameDisplay.style.display = 'none';
                downloadLink.href = '#';
                downloadLink.textContent = '';
                downloadLink.style.display = 'none';
                downloadDirLink.style.display = 'none';

                document.querySelectorAll('.previewable-file-link').forEach(a => a.classList.remove('selected'));
            };

            cycleRandomImage();
            slideshowInterval = setInterval(cycleRandomImage, 3500);
        }

        window.onload = () => {
            applySavedDarkModePreference();
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', toggleDarkMode);
            }

            prevFileButton.addEventListener('click', () => navigateFiles('prev'));
            nextFileButton.addEventListener('click', () => navigateFiles('next'));

            if (phpDescTxtTitle || phpDescTxtDescription) {
                directoryDescriptionContainer.style.display = 'block';
                directoryDescriptionTitle.textContent = phpDescTxtTitle;
                directoryDescriptionText.innerHTML = phpDescTxtDescription;
            } else {
                directoryDescriptionContainer.style.display = 'none';
            }
            
            if (phpFileToShow && phpCurrentPath) {
                let fullPathToShowMediaInList = phpCurrentPath;
                if (!fullPathToShowMediaInList.endsWith('/')) {
                    fullPathToShowMediaInList += '/';
                }
                fullPathToShowMediaInList += phpFileToShow;
                
                const targetLink = document.querySelector(`.previewable-file-link[data-path="${fullPathToShowMediaInList}"]`);
                if (targetLink) {
                    showMedia(targetLink);
                    targetLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    showMedia('', '');
                }
            } else if (isRootProjectsDirectory) {
                startHomeSlideshow();
            } else if (typeof firstFileToLoad !== 'undefined' && firstFileToLoad && firstFileToLoad.path) {
                const targetLink = document.querySelector(`.previewable-file-link[data-path="${firstFileToLoad.path}"]`);
                if (targetLink) {
                    showMedia(targetLink);
                    targetLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    showMedia('', '');
                }
            } else {
                showMedia('', '');
            }
            
            updateDownloadButtons();
        };

        function updateNavigationButtonVisibility() {
            const allPreviewableFileLinks = Array.from(document.querySelectorAll('.previewable-file-link'));
            const currentSelectedLink = document.querySelector('.previewable-file-link.selected');

            if (isRootProjectsDirectory && slideshowInterval !== null && !currentSelectedLink) { 
                previewNavContainer.style.display = 'none';
                prevFileButton.disabled = true;
                nextFileButton.disabled = true;
            } else if (allPreviewableFileLinks.length > 1) {
                previewNavContainer.style.display = 'flex';
                prevFileButton.disabled = false;
                nextFileButton.disabled = false;
            } else {
                previewNavContainer.style.display = 'none';
                prevFileButton.disabled = true;
                nextFileButton.disabled = true;
            }
        }

        function navigateFiles(direction) {
            if (prevFileButton.disabled && nextFileButton.disabled) {
                return; 
            }

            clearInterval(slideshowInterval);
            slideshowInterval = null;

            const allPreviewableFileLinks = Array.from(document.querySelectorAll('.previewable-file-link'));
            if (allPreviewableFileLinks.length === 0) {
                return;
            }

            let currentSelectedLink = document.querySelector('.previewable-file-link.selected');
            let currentIndex = -1;

            if (currentSelectedLink) {
                currentIndex = allPreviewableFileLinks.indexOf(currentSelectedLink);
            } else {
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

            if (currentIndex === -1) {
                currentIndex = (direction === 'next') ? -1 : allPreviewableFileLinks.length;
            }
            
            let nextIndex;
            if (direction === 'next') {
                nextIndex = (currentIndex + 1) % allPreviewableFileLinks.length;
            } else { 
                nextIndex = (currentIndex - 1 + allPreviewableFileLinks.length) % allPreviewableFileLinks.length;
            }

            const nextLink = allPreviewableFileLinks[nextIndex];
            if (nextLink) {
                showMedia(nextLink);
                nextLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            updateNavigationButtonVisibility();
        }

        document.addEventListener('keydown', (e) => {
            if (document.getElementById('lightbox-overlay').style.display === 'flex') {
                return;
            }
            if (prevFileButton.disabled && nextFileButton.disabled) {
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