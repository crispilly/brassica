<?php
declare(strict_types=1);
require_once __DIR__ . '/session_bootstrap_page.php';
require_login_page();
require_once __DIR__ . '/../i18n.php';
$id=max(0,(int)($_GET['id']??0));
?>
<!doctype html><html lang="<?=htmlspecialchars(current_language(),ENT_QUOTES)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kochmodus</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="cook-body"><main class="cook-shell" data-recipe-id="<?=$id?>"><div class="cook-top"><button id="cook-close" type="button">×</button><div id="cook-title">Kochmodus</div><button id="cook-wake" type="button">Display an</button></div><section id="cook-page" class="cook-page"></section><div class="cook-controls"><button id="cook-prev" type="button">◀</button><span id="cook-counter"></span><button id="cook-next" type="button">▶</button></div></main><script src="/assets/js/cook.js"></script></body></html>
