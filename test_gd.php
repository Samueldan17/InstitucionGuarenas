<?php
echo "<h2>Prueba de GD</h2>";
if (extension_loaded('gd')) {
    echo "<p style='color:green;'>✅ La extensión GD está cargada.</p>";
    if (function_exists('imagecreate')) {
        echo "<p style='color:green;'>✅ Las funciones de imagen (imagecreate) existen y funcionan.</p>";
    } else {
        echo "<p style='color:red;'>❌ Las funciones de imagen NO existen. El DLL está roto o falta una dependencia.</p>";
    }
} else {
    echo "<p style='color:red;'>❌ La extensión GD NO está cargada en Apache.</p>";
}
?>