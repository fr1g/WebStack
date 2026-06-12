<?php
/*
 * 公告详情页模板：/LingYiZiYuan/bulletin/update/{id}
 */
if (!defined('ABSPATH')) exit;

$id = intval($GLOBALS['bulletin_single_id'] ?? 0);
if (!$id) { wp_redirect('/LingYiZiYuan/bulletin/update/'); exit; }

$post = get_post($id);
if (!$post || $post->post_type !== 'bulletin' || $post->post_status !== 'publish') {
    wp_redirect('/LingYiZiYuan/bulletin/update/');
    exit;
}
setup_postdata($post);

$date = get_the_date('Y-m-d H:i', $post);
$title = get_the_title($post);
$content = get_the_content(null, false, $post);
$has_thumbnail = has_post_thumbnail($post);
$thumbnail_url = $has_thumbnail ? get_the_post_thumbnail_url($post->ID, 'large') : '';

$badge_class = 'badge-new'; $badge_text = '新公告';
if (strpos($title, '安全') !== false || strpos($title, '漏洞') !== false) { $badge_class = 'badge-security'; $badge_text = '安全通告'; }
elseif (strpos($title, '更新') !== false || strpos($title, 'Update') !== false) { $badge_class = 'badge-update'; $badge_text = '版本更新'; }

// 获取上一篇/下一篇
global $wpdb;
$prev = $wpdb->get_row("SELECT ID,post_title FROM {$wpdb->posts} WHERE post_type='bulletin' AND post_status='publish' AND ID < {$id} ORDER BY ID DESC LIMIT 1");
$next = $wpdb->get_row("SELECT ID,post_title FROM {$wpdb->posts} WHERE post_type='bulletin' AND post_status='publish' AND ID > {$id} ORDER BY ID ASC LIMIT 1");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc_html($title) ?> | MC灵依资源站</title>
<link rel="shortcut icon" href="/favicon.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
:root{--bg:#0c0c14;--surface:rgba(18,18,28,.85);--surface2:rgba(24,24,36,.9);--border:rgba(212,175,55,.12);--border2:rgba(255,255,255,.06);--text:rgba(255,255,255,.92);--text2:rgba(255,255,255,.5);--gold:#d4af37;--gold-dim:rgba(212,175,55,.15);--accent:#f0c040;--glass:rgba(255,255,255,.04)}
[data-theme="light"]{--bg:#f5f5f7;--surface:rgba(255,255,255,.92);--surface2:rgba(245,245,247,.95);--border:rgba(212,175,55,.2);--border2:rgba(0,0,0,.06);--text:rgba(0,0,0,.88);--text2:rgba(0,0,0,.45);--glass:rgba(0,0,0,.03)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;min-height:100vh;line-height:1.8}
.page-wrap{max-width:800px;margin:0 auto;padding:60px 20px 40px}
.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--gold);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:24px;padding:8px 16px;border:1px solid var(--border);border-radius:8px;transition:all .2s}
.back-link:hover{background:var(--gold-dim)}
.article-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:36px 32px}
.article-header{margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border2)}
.article-badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 12px;border-radius:20px;margin-bottom:12px}
.badge-new{background:rgba(212,175,55,.12);color:var(--gold);border:1px solid rgba(212,175,55,.2)}
.badge-update{background:rgba(76,175,80,.1);color:#66bb6a;border:1px solid rgba(76,175,80,.2)}
.badge-security{background:rgba(244,67,54,.1);color:#ef5350;border:1px solid rgba(244,67,54,.2)}
.article-title{font-size:26px;font-weight:700;color:var(--text);margin-bottom:8px;line-height:1.3}
.article-meta{font-size:13px;color:var(--text2);display:flex;align-items:center;gap:12px}
.article-content{font-size:15px;line-height:1.85;color:var(--text)}
.article-content h1,.article-content h2,.article-content h3,.article-content h4{color:var(--gold);margin:24px 0 12px;font-weight:600}
.article-content h2{font-size:20px}
.article-content h3{font-size:17px}
.article-content p{margin-bottom:14px}
.article-content ul,.article-content ol{margin:12px 0;padding-left:24px}
.article-content li{margin-bottom:6px}
.article-content img{max-width:100%;border-radius:8px;margin:16px 0;border:1px solid var(--border)}
.article-content code{background:var(--glass);padding:2px 6px;border-radius:4px;font-size:13px;color:var(--gold)}
.article-content pre{background:rgba(0,0,0,.3);padding:16px;border-radius:8px;overflow-x:auto;margin:16px 0;border:1px solid var(--border2)}
.article-content pre code{background:none;padding:0}
.article-content a{color:var(--gold);text-decoration:underline;text-underline-offset:3px}
.article-content blockquote{border-left:3px solid var(--gold);padding:12px 16px;margin:16px 0;background:var(--gold-dim);border-radius:0 8px 8px 0}
.article-content table{width:100%;border-collapse:collapse;margin:16px 0}
.article-content th,.article-content td{padding:10px 14px;border:1px solid var(--border);text-align:left;font-size:14px}
.article-content th{background:var(--gold-dim);color:var(--gold);font-weight:600}
.article-nav{display:flex;justify-content:space-between;margin-top:24px;padding-top:20px;border-top:1px solid var(--border2)}
.article-nav a{color:var(--gold);text-decoration:none;font-size:13px;font-weight:600;padding:8px 16px;border:1px solid var(--border);border-radius:8px;transition:all .2s;max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.article-nav a:hover{background:var(--gold-dim)}
.theme-toggle{position:fixed;top:20px;right:20px;background:var(--surface);border:1px solid var(--border);border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;z-index:100;transition:all .2s}
.theme-toggle:hover{border-color:var(--gold);transform:scale(1.05)}
@media(max-width:768px){.page-wrap{padding:40px 14px 30px}.article-card{padding:24px 18px}.article-title{font-size:22px}}
</style>
</head>
<body>
<canvas id="meteor-canvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0"></canvas>
<div class="page-wrap" style="position:relative;z-index:1">
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn">🌙</button>
    <a href="/LingYiZiYuan/bulletin/update/" class="back-link"><i class="fa fa-arrow-left"></i> 返回公告列表</a>
    <article class="article-card">
        <div class="article-header">
            <span class="article-badge <?= $badge_class ?>"><?= $badge_text ?></span>
            <h1 class="article-title"><?= esc_html($title) ?></h1>
            <div class="article-meta">
                <span><i class="fa fa-calendar"></i> <?= $date ?></span>
                <span><i class="fa fa-user"></i> MC灵依资源站</span>
            </div>
        </div>
        <?php if ($thumbnail_url): ?>
        <div style="margin-bottom:24px"><img src="<?= esc_url($thumbnail_url) ?>" style="width:100%;border-radius:12px;border:1px solid var(--border)" alt=""></div>
        <?php endif; ?>
        <div class="article-content"><?= wpautop($content) ?></div>
    </article>
    <div class="article-nav">
        <a href="<?= $prev ? '/LingYiZiYuan/bulletin/update/'.$prev->ID : '/LingYiZiYuan/bulletin/update/' ?>"><?= $prev ? '<i class="fa fa-arrow-left"></i> '.esc_html($prev->post_title) : '<i class="fa fa-arrow-left"></i> 返回列表' ?></a>
        <a href="<?= $next ? '/LingYiZiYuan/bulletin/update/'.$next->ID : '/LingYiZiYuan/bulletin/' ?>" style="text-align:right"><?= $next ? esc_html($next->post_title).' <i class="fa fa-arrow-right"></i>' : '公告列表 <i class="fa fa-arrow-right"></i>' ?></a>
    </div>
</div>
<script>
(function(){var c=document.getElementById("meteor-canvas"),ctx=c.getContext("2d"),W,H;function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight}resize();window.addEventListener("resize",resize);var meteors=[];setInterval(function(){meteors.push({x:Math.random()*W*1.5-W*.25,y:-10,len:Math.random()*80+40,speed:Math.random()*6+4,opacity:Math.random()*.5+.2})},400);function draw(){ctx.clearRect(0,0,W,H);for(var i=meteors.length-1;i>=0;i--){var m=meteors[i];var g=ctx.createLinearGradient(m.x,m.y,m.x+m.len*.3,m.y+m.len);g.addColorStop(0,"rgba(212,175,55,0)");g.addColorStop(1,"rgba(212,175,55,"+m.opacity+")");ctx.beginPath();ctx.moveTo(m.x,m.y);ctx.lineTo(m.x+m.len*.3,m.y+m.len);ctx.strokeStyle=g;ctx.lineWidth=1.5;ctx.stroke();ctx.beginPath();ctx.arc(m.x+m.len*.3,m.y+m.len,1.5,0,Math.PI*2);ctx.fillStyle="rgba(212,175,55,"+m.opacity+")";ctx.fill();m.x+=m.speed*.4;m.y+=m.speed;if(m.y>H+100)meteors.splice(i,1)}requestAnimationFrame(draw)}draw()})();
(function(){var s=localStorage.getItem("theme");if(s==="light"){document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️"}else if(s==="dark"){document.documentElement.removeAttribute("data-theme");document.getElementById("themeBtn").textContent="🌙"}else if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:light)").matches){document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️"}else{document.getElementById("themeBtn").textContent="🌙"}})();
function toggleTheme(){var isLight=document.documentElement.getAttribute("data-theme")==="light";if(isLight){document.documentElement.removeAttribute("data-theme");document.getElementById("themeBtn").textContent="🌙";localStorage.setItem("theme","dark")}else{document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️";localStorage.setItem("theme","light")}}
</script>
</body>
</html>
<?php wp_reset_postdata(); ?>
