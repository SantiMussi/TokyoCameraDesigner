const fs = require('fs');
const code = fs.readFileSync('admin.php', 'utf8');

// Extraer el JS del bottom
const match = code.match(/<script>([\s\S]*?)<\/script>/g);
if (match) {
    const js = match[match.length - 1]
        .replace(/<\/?script>/g, '')
        .replace(/<\?=\s*json_encode\(\$pedidos_js\)\s*\?>/g, '{}')
        .replace(/<\?= isset\(\$_SESSION\["csrf_token"\]\) \? \$_SESSION\["csrf_token"\] : "" \?>/g, 'dummy');
    
    try {
        new Function(js);
        console.log("Syntax OK");
    } catch (e) {
        console.log("Syntax ERROR:");
        console.error(e);
    }
}
