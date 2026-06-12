<?php
/*
 * 公告列表页模板：/LingYiZiYuan/bulletin/update/
 */
if (!defined('ABSPATH')) exit;

$bulletins = new WP_Query(array(
    'post_type'      => 'bulletin',
    'posts_per_page' => 30,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>公告中心 | MC灵依资源站</title>
<link rel="shortcut icon" href="/favicon.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
:root{--bg:#0c0c14;--surface:rgba(18,18,28,.85);--surface2:rgba(24,24,36,.9);--border:rgba(212,175,55,.12);--border2:rgba(255,255,255,.06);--text:rgba(255,255,255,.92);--text2:rgba(255,255,255,.5);--gold:#d4af37;--gold-dim:rgba(212,175,55,.15);--accent:#f0c040;--card-hover:rgba(212,175,55,.04);--glass:rgba(255,255,255,.04)}
[data-theme="light"]{--bg:#f5f5f7;--surface:rgba(255,255,255,.92);--surface2:rgba(245,245,247,.95);--border:rgba(212,175,55,.2);--border2:rgba(0,0,0,.06);--text:rgba(0,0,0,.88);--text2:rgba(0,0,0,.45);--card-hover:rgba(212,175,55,.06);--glass:rgba(0,0,0,.03)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;min-height:100vh;line-height:1.6}
.page-wrap{max-width:800px;margin:0 auto;padding:60px 20px 40px}
.page-header{text-align:center;margin-bottom:40px}
.page-header h1{font-size:28px;font-weight:700;color:var(--gold);margin-bottom:8px}
.page-header p{color:var(--text2);font-size:14px}
.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--gold);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:24px;padding:8px 16px;border:1px solid var(--border);border-radius:8px;transition:all .2s}
.back-link:hover{background:var(--gold-dim)}
.bulletin-list{display:flex;flex-direction:column;gap:12px}
.bulletin-card{display:block;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px 24px;text-decoration:none;color:var(--text);transition:all .25s}
.bulletin-card:hover{border-color:rgba(212,175,55,.3);background:var(--card-hover);transform:translateY(-2px);box-shadow:0 4px 20px rgba(0,0,0,.2)}
.bulletin-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.bulletin-card-title{font-size:17px;font-weight:600;color:var(--text);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bulletin-card-date{font-size:12px;color:var(--text2);white-space:nowrap;margin-left:16px}
.bulletin-card-excerpt{font-size:13px;color:var(--text2);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bulletin-card-badge{display:inline-block;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;margin-right:8px}
.badge-new{background:rgba(212,175,55,.12);color:var(--gold);border:1px solid rgba(212,175,55,.2)}
.badge-update{background:rgba(76,175,80,.1);color:#66bb6a;border:1px solid rgba(76,175,80,.2)}
.badge-security{background:rgba(244,67,54,.1);color:#ef5350;border:1px solid rgba(244,67,54,.2)}
.empty-state{text-align:center;padding:60px 20px;color:var(--text2)}
.empty-state i{font-size:48px;margin-bottom:16px;display:block}
.theme-toggle{position:fixed;top:20px;right:20px;background:var(--surface);border:1px solid var(--border);border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;z-index:100;transition:all .2s}
.theme-toggle:hover{border-color:var(--gold);transform:scale(1.05)}
@media(max-width:768px){.page-wrap{padding:40px 14px 30px}.page-header h1{font-size:22px}.bulletin-card{padding:16px}.bulletin-card-title{font-size:15px}}
</style>
</head>
<body>
<canvas id="meteor-canvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0"></canvas>
<div class="page-wrap" style="position:relative;z-index:1">
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn">🌙</button>
    <a href="https://mcres.cn" class="back-link"><i class="fa fa-home"></i> 返回主站</a>
    <div class="page-header">
        <h1><i class="fa fa-bullhorn"></i> 公告中心</h1>
        <p>站点更新日志与安全通告</p>
    </div>
    <div class="bulletin-list">
    <?php if ($bulletins->have_posts()): while ($bulletins->have_posts()): $bulletins->the_post();
        $date = get_the_date('Y-m-d');
        $excerpt = get_the_excerpt();
        $title = get_the_title();
        $badge_class = 'badge-new'; $badge_text = '新公告';
        if (strpos($title, '安全') !== false || strpos($title, '漏洞') !== false) { $badge_class = 'badge-security'; $badge_text = '安全通告'; }
        elseif (strpos($title, '更新') !== false || strpos($title, 'Update') !== false) { $badge_class = 'badge-update'; $badge_text = '版本更新'; }
    ?>
        <a href="/LingYiZiYuan/bulletin/update/<?= get_the_ID() ?>" class="bulletin-card">
            <div class="bulletin-card-top">
                <span class="bulletin-card-title"><span class="bulletin-card-badge <?= $badge_class ?>"><?= $badge_text ?></span><?= esc_html($title) ?></span>
                <span class="bulletin-card-date"><i class="fa fa-calendar" style="margin-right:4px;"></i><?= $date ?></span>
            </div>
            <div class="bulletin-card-excerpt"><?= esc_html(wp_strip_all_tags($excerpt)) ?></div>
        </a>
    <?php endwhile; else: ?>
        <div class="empty-state"><i>📭</i><p>暂无公告</p></div>
    <?php endif; wp_reset_postdata(); ?>
    </div>
</div>
<script>
(function(){var c=document.getElementById("meteor-canvas"),ctx=c.getContext("2d"),W,H;function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight}resize();window.addEventListener("resize",resize);var meteors=[];setInterval(function(){meteors.push({x:Math.random()*W*1.5-W*.25,y:-10,len:Math.random()*80+40,speed:Math.random()*6+4,opacity:Math.random()*.5+.2})},400);function draw(){ctx.clearRect(0,0,W,H);for(var i=meteors.length-1;i>=0;i--){var m=meteors[i];var g=ctx.createLinearGradient(m.x,m.y,m.x+m.len*.3,m.y+m.len);g.addColorStop(0,"rgba(212,175,55,0)");g.addColorStop(1,"rgba(212,175,55,"+m.opacity+")");ctx.beginPath();ctx.moveTo(m.x,m.y);ctx.lineTo(m.x+m.len*.3,m.y+m.len);ctx.strokeStyle=g;ctx.lineWidth=1.5;ctx.stroke();ctx.beginPath();ctx.arc(m.x+m.len*.3,m.y+m.len,1.5,0,Math.PI*2);ctx.fillStyle="rgba(212,175,55,"+m.opacity+")";ctx.fill();m.x+=m.speed*.4;m.y+=m.speed;if(m.y>H+100)meteors.splice(i,1)}requestAnimationFrame(draw)}draw()})();
(function(){var s=localStorage.getItem("theme");if(s==="light"){document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️"}else if(s==="dark"){document.documentElement.removeAttribute("data-theme");document.getElementById("themeBtn").textContent="🌙"}else if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:light)").matches){document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️"}else{document.getElementById("themeBtn").textContent="🌙"}})();
function toggleTheme(){var isLight=document.documentElement.getAttribute("data-theme")==="light";if(isLight){document.documentElement.removeAttribute("data-theme");document.getElementById("themeBtn").textContent="🌙";localStorage.setItem("theme","dark")}else{document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️";localStorage.setItem("theme","light")}}
</script>
</body>
</html>
