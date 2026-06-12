<?php
/**
 * Plugin Name: MC下载页生成器
 * Description: WP后台生成/编辑下载页HTML到/downloads/目录
 * Version: 3.0.0
 */
if (!defined('ABSPATH')) exit;

$MCRES_DL_DIR = '/www/wwwroot/mcres.cn/downloads';
$MCRES_QQ_URL = 'https://qm.qq.com/cgi-bin/qm/qr?_wv=1027&k=B2G7aGdS6AcwPnV0bw-Uvho23YTAMPo5&authKey=pR840xaXIbq2jlV6aMESl2E%2BnZDuC9vCGGOW8JMf5WjKLdIXq91ZmDM%2BmUEhP7j3&noverify=0&group_code=162358740';

add_action('admin_menu', function() {
    add_menu_page('下载页管理', '下载页管理', 'manage_options', 'mcres-dl-gen', 'mcres_dl_gen_page', 'dashicons-download', 3);
});

function mcres_get_subdirs($base) {
    $dirs = array('' => '📁 /downloads/ (根目录)');
    foreach (glob($base . '/*', GLOB_ONLYDIR) as $d) {
        $rel = str_replace($base . '/', '', $d);
        $count = count(glob($d . '/*.html'));
        $dirs[$rel] = '📂 /' . $rel . '/ (' . $count . '个)';
    }
    return $dirs;
}

function mcres_dl_gen_page() {
    global $MCRES_DL_DIR, $MCRES_QQ_URL;
    $msg = '';

    // 当前选中目录
    $cur_dir = isset($_GET['dir']) ? sanitize_text_field($_GET['dir']) : (isset($_POST['dir']) ? sanitize_text_field($_POST['dir']) : '');
    $cur_dir = preg_replace('#[^a-zA-Z0-9_/]#', '', $cur_dir);
    $work_dir = $cur_dir ? $MCRES_DL_DIR . '/' . $cur_dir : $MCRES_DL_DIR;
    $dir_param = $cur_dir ? '&dir=' . urlencode($cur_dir) : '';

    // 所有子目录
    $subdirs = mcres_get_subdirs($MCRES_DL_DIR);

    // 删除
    if (isset($_POST['mcres_delete']) && wp_verify_nonce($_POST['_wpnonce'], 'mcres_del')) {
        $del_dir = sanitize_text_field($_POST['dir'] ?? '');
        $del_dir = preg_replace('#[^a-zA-Z0-9_/]#', '', $del_dir);
        $del_work = $del_dir ? $MCRES_DL_DIR . '/' . $del_dir : $MCRES_DL_DIR;
        $file = basename($_POST['file']);
        if (file_exists($del_work . '/' . $file)) { unlink($del_work . '/' . $file); $msg = '<div class="notice notice-success"><p>已删除 ' . esc_html($file) . '</p></div>'; }
    }

    // 生成/更新
    if (isset($_POST['mcres_generate']) && wp_verify_nonce($_POST['_wpnonce'], 'mcres_gen')) {
        $gen_dir = sanitize_text_field($_POST['dir'] ?? '');
        $gen_dir = preg_replace('#[^a-zA-Z0-9_/]#', '', $gen_dir);
        $gen_work = $gen_dir ? $MCRES_DL_DIR . '/' . $gen_dir : $MCRES_DL_DIR;
        $name = sanitize_text_field($_POST['name']);
        $filename = sanitize_file_name($_POST['filename']);
        if (empty($filename)) $filename = sanitize_title($name);
        if (empty($filename)) { $msg = '<div class="notice notice-error"><p>请填写文件名</p></div>'; }
        else {
            $filename = preg_replace('/\.html$/', '', $filename) . '.html';
            $icon = esc_url_raw($_POST['icon'] ?? '');
            $links = array();
            if (!empty($_POST['link_title']) && is_array($_POST['link_title'])) {
                for ($i = 0; $i < count($_POST['link_title']); $i++) {
                    $t = sanitize_text_field($_POST['link_title'][$i]);
                    $u = esc_url_raw($_POST['link_url'][$i] ?? '');
                    $s = sanitize_text_field($_POST['link_sub'][$i] ?? '');
                    $b = sanitize_text_field($_POST['link_badge'][$i] ?? 'MC灵依资源站');
                    $ic = esc_url_raw($_POST['link_icon'][$i] ?? $icon);
                    if ($t && $u) $links[] = array('title'=>$t,'url'=>$u,'sub'=>$s,'badge'=>$b,'icon'=>$ic);
                }
            }
            $html = mcres_generate_html($name, $icon, $links, $MCRES_QQ_URL);
            file_put_contents($gen_work . '/' . $filename, $html);
            $old = !empty($_POST['edit_original']) ? basename($_POST['edit_original']) : '';
            $url_path = 'downloads/' . ($gen_dir ? $gen_dir . '/' : '') . $filename;
            $msg = '<div class="notice notice-success"><p>已生成: <a href="https://mcres.cn/' . $url_path . '" target="_blank">' . $filename . '</a></p><p><code>https://mcres.cn/' . $url_path . '</code></p></div>';
            if ($old && $old !== $filename && file_exists($gen_work . '/' . $old)) @unlink($gen_work . '/' . $old);
            $cur_dir = $gen_dir;
            $work_dir = $gen_work;
        }
    }

    // 文件列表
    $files = glob($work_dir . '/*.html');
    usort($files, function($a,$b){ return filemtime($b)-filemtime($a); });

    // 编辑解析
    $edit = array('name'=>'','icon'=>'','links'=>array(),'filename'=>'');
    if (isset($_GET['edit']) && $_GET['edit']) {
        $ef = $work_dir . '/' . basename($_GET['edit']);
        if (file_exists($ef)) {
            $html_content = file_get_contents($ef);
            $edit['filename'] = basename($ef, '.html');
            if (preg_match('/<title>MC灵依资源站\s*\|\s*(.+?)<\/title>/', $html_content, $m)) $edit['name'] = $m[1];
            // 分割法：按dl-card边界切分，逐卡片解析
            $cards = preg_split('/<div\s+class="dl-card"\s+data-url="/', $html_content);
            array_shift($cards); // 移除第一个（卡片前的内容）
            foreach ($cards as $card) {
                $url = $title = $sub = $badge = $ic = '';
                // 提取data-url的值（它在分割后的开头）
                if (preg_match('/^([^"]*)"/', $card, $um)) $url = $um[1];
                // 在当前卡片片段内提取各字段
                if (preg_match('/dl-card-title">\s*(.*?)\s*<\/div/', $card, $tm)) $title = trim($tm[1]);
                if (preg_match('/dl-card-sub">\s*(.*?)\s*<\/div/', $card, $sm)) $sub = trim($sm[1]);
                if (preg_match('/dl-badge[^"]*">\s*(.*?)\s*<\/div/', $card, $bm)) $badge = trim(strip_tags($bm[1]));
                if (preg_match('/<img\s+src="([^"]*)"/', $card, $im)) $ic = $im[1];
                if ($title && $url) $edit['links'][] = array('url'=>$url,'title'=>$title,'sub'=>$sub,'badge'=>$badge,'icon'=>$ic);
            }
            // 兼容旧版a标签格式
            if (empty($edit['links'])) {
                preg_match_all('/<a class="dl-card" href="([^"]*)"[^>]*>(.*?)<\/a>/s', $html_content, $ms, PREG_SET_ORDER);
                foreach ($ms as $ml) {
                    $url = $ml[1]; $title = $sub = $badge = $ic = '';
                    if (preg_match('/dl-card-title">\s*(.*?)\s*</s', $ml[2], $tm)) $title = trim($tm[1]);
                    if (preg_match('/dl-card-sub">\s*(.*?)\s*</s', $ml[2], $sm)) $sub = trim($sm[1]);
                    if (preg_match('/dl-badge[^"]*">\s*(.*?)\s*</s', $ml[2], $bm)) $badge = trim($bm[1]);
                    if (preg_match('/<img\s+src="([^"]*)"/', $ml[2], $im)) $ic = $im[1];
                    if ($title && $url) $edit['links'][] = array('url'=>$url,'title'=>$title,'sub'=>$sub,'badge'=>$badge,'icon'=>$ic);
                }
            }
            if (preg_match('/<img\s+src="([^"]*?)"/', $html_content, $fm)) $edit['icon'] = $fm[1];
        }
    }
    ?>
    <div class="wrap">
    <h1>下载页管理</h1>
    <?= $msg ?>
    <div style="margin-bottom:16px;padding:12px 16px;background:#fff;border:1px solid #d4af37;border-radius:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <strong style="color:#d4af37;">📂 当前目录:</strong>
        <select id="dirSelect" onchange="window.location.href='<?= admin_url('admin.php?page=mcres-dl-gen&dir=') ?>'+'&'+this.value" style="font-size:14px;padding:6px 12px;border:1px solid #d4af37;border-radius:6px;">
        <?php foreach ($subdirs as $k => $v): ?>
            <option value="dir=<?= urlencode($k) ?>" <?= $cur_dir === $k ? 'selected' : '' ?>><?= esc_html($v) ?></option>
        <?php endforeach; ?>
        </select>
        <span style="color:#999;font-size:13px;">切换目录后页面会刷新</span>
    </div>
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
    <div style="flex:1.2;min-width:520px;">
    <div class="card" style="padding:20px;border:1px solid #d4af37;">
    <h2 style="margin-top:0;"><?= $edit['filename'] ? '编辑: '.$edit['filename'].'.html' : '新建下载页' ?></h2>
    <form method="post">
    <?= wp_nonce_field('mcres_gen') ?>
    <input type="hidden" name="dir" value="<?= esc_attr($cur_dir) ?>">
    <input type="hidden" name="edit_original" value="<?= esc_attr($edit['filename']) ?>">
    <table class="form-table">
        <tr><th>资源名称 *</th><td><input type="text" name="name" class="regular-text" required placeholder="如: Paper" value="<?= esc_attr($edit['name']) ?>"></td></tr>
        <tr><th>文件名 *</th><td><input type="text" name="filename" class="regular-text" placeholder="如: paper" value="<?= esc_attr($edit['filename']) ?>">.html</td></tr>
        <tr><th>图标URL</th><td><input type="url" name="icon" class="regular-text" placeholder="https://mcres.cn/wp-content/uploads/..." value="<?= esc_attr($edit['icon']) ?>"></td></tr>
    </table>
    <h3 style="display:flex;align-items:center;gap:8px;">下载链接 <small style="color:#999;font-weight:400;">(拖动左侧 ⋮⋮ 可排序)</small></h3>
    <style>
    #links-container{display:flex;flex-direction:column;}
    .link-row{display:flex;gap:8px;align-items:center;padding:10px 12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;transition:all .2s;position:relative;}
    .link-row:hover{border-color:#c3c3c3;background:#fff;}
    .link-row.dragging{opacity:.4;border-color:#d4af37;background:#fffbe6;}
    .link-row+.link-row{margin-top:6px;}
    .link-row::before{content:'';position:absolute;left:0;right:0;top:-4px;height:1px;background:transparent;}
    .drag-handle{cursor:grab;user-select:none;color:#bbb;font-size:16px;padding:4px 2px;letter-spacing:1px;line-height:1;flex-shrink:0;transition:color .2s;}
    .drag-handle:hover{color:#d4af37;}
    .drag-handle:active{cursor:grabbing;}
    .link-num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#d4af37,#f0c040);color:#fff;font-size:11px;font-weight:700;flex-shrink:0;}
    .link-fields{display:flex;gap:8px;align-items:center;flex:1;flex-wrap:wrap;}
    .link-del{color:#d32f2f;border:none;background:none;cursor:pointer;font-size:18px;padding:4px 6px;border-radius:4px;transition:all .2s;flex-shrink:0;}
    .link-del:hover{background:rgba(211,47,47,.1);}
    .link-row .link-sep{display:none;}
    </style>
    <p><button type="button" class="button button-secondary" onclick="addLink()" style="margin:0 0 8px 0;">+ 添加下载链接</button></p>
    <div id="links-container">
    <?php $idx=0; if ($edit['links']): foreach ($edit['links'] as $el): $idx++; ?>
        <div class="link-row" draggable="true">
            <span class="drag-handle" title="拖动排序">⋮⋮</span>
            <span class="link-num"><?=$idx?></span>
            <div class="link-fields">
                <input type="text" name="link_title[]" placeholder="标题" style="flex:2;min-width:120px;" required value="<?= esc_attr($el['title']) ?>">
                <input type="url" name="link_url[]" placeholder="下载URL" style="flex:3;min-width:160px;" required value="<?= esc_url($el['url']) ?>">
                <input type="text" name="link_sub[]" placeholder="副标题" style="flex:2;min-width:100px;" value="<?= esc_attr($el['sub']) ?>">
                <input type="url" name="link_icon[]" placeholder="图标" style="flex:1;min-width:80px;" value="<?= esc_url($el['icon']) ?>">
                <select name="link_badge[]" style="flex:0.6;">
                    <?php foreach(array('MC灵依资源站','GitHub','官网','第三方') as $bd): ?>
                    <option value="<?=$bd?>" <?= $el['badge']===$bd?'selected':'' ?>><?=$bd?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="link-del" onclick="this.closest('.link-row').remove();renumLinks();" title="删除">✕</button>
        </div>
    <?php endforeach; endif; ?>
    </div>
    <p class="submit"><button type="submit" name="mcres_generate" class="button button-primary button-hero"><?= $edit['filename'] ? '💾 保存修改' : '🚀 生成下载页' ?></button>
    <?php if($edit['filename']): ?> <a href="<?= admin_url('admin.php?page=mcres-dl-gen') ?>" class="button button-secondary">取消编辑</a><?php endif; ?>
    </p></form></div></div>

    <div style="flex:0.8;min-width:320px;">
    <div class="card" style="padding:20px;border:1px solid #d4af37;">
    <h2 style="margin-top:0;">已有下载页 (<?= count($files) ?>)</h2>
    <div style="max-height:650px;overflow-y:auto;">
    <table class="widefat striped"><thead><tr><th>文件</th><th>时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($files as $f): $bn = basename($f); $url_path = 'downloads/' . ($cur_dir ? $cur_dir.'/' : '') . $bn; ?>
    <tr><td><a href="https://mcres.cn/<?= $url_path ?>" target="_blank"><?= $bn ?></a></td>
    <td style="font-size:11px;"><?= date('m/d H:i', filemtime($f)) ?></td>
    <td><a href="<?= admin_url('admin.php?page=mcres-dl-gen&edit='.$bn.$dir_param) ?>" class="button button-small">编辑</a>
    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除?')">
    <?= wp_nonce_field('mcres_del') ?><input type="hidden" name="file" value="<?= $bn ?>"><input type="hidden" name="dir" value="<?= esc_attr($cur_dir) ?>">
    <button type="submit" name="mcres_delete" class="button button-small" style="color:red;">删</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div></div>
    <script>
    var linkCounter=<?=$idx?>;
    function renumLinks(){var rows=document.getElementById('links-container').querySelectorAll('.link-row');rows.forEach(function(r,i){var n=r.querySelector('.link-num');if(n)n.textContent=i+1;});linkCounter=rows.length;}
    function addLink(){
        linkCounter++;
        var c=document.getElementById('links-container'),r=document.createElement('div');
        r.className='link-row';r.draggable=true;
        r.innerHTML='<span class="drag-handle" title="拖动排序">⋮⋮</span><span class="link-num">'+linkCounter+'</span>'
            +'<div class="link-fields">'
            +'<input type="text" name="link_title[]" placeholder="标题" style="flex:2;min-width:120px;" required>'
            +'<input type="url" name="link_url[]" placeholder="下载URL" style="flex:3;min-width:160px;" required>'
            +'<input type="text" name="link_sub[]" placeholder="副标题" style="flex:2;min-width:100px;">'
            +'<input type="url" name="link_icon[]" placeholder="图标" style="flex:1;min-width:80px;">'
            +'<select name="link_badge[]" style="flex:0.6;"><option>MC灵依资源站</option><option>GitHub</option><option>官网</option><option>第三方</option></select>'
            +'</div><button type="button" class="link-del" onclick="this.closest(\'.link-row\').remove();renumLinks();" title="删除">✕</button>';
        c.prepend(r);
        r.querySelector('input').focus();
        renumLinks();
        initDrag(r);
    }
    var dragSrc=null;
    function initDrag(el){
        el.addEventListener('dragstart',function(e){dragSrc=this;this.classList.add('dragging');e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain','');});
        el.addEventListener('dragend',function(){this.classList.remove('dragging');dragSrc=null;document.querySelectorAll('.link-row').forEach(function(r){r.style.borderTop='';r.style.borderBottom='';});renumLinks();});
        el.addEventListener('dragover',function(e){e.preventDefault();e.dataTransfer.dropEffect='move';var t=this.closest('.link-row');if(t&&t!==dragSrc){var rect=t.getBoundingClientRect();var mid=rect.top+rect.height/2;if(e.clientY<mid)t.style.borderTop='2px solid #d4af37';else t.style.borderBottom='2px solid #d4af37';}});
        el.addEventListener('dragleave',function(){this.style.borderTop='';this.style.borderBottom='';});
        el.addEventListener('drop',function(e){
            e.preventDefault();this.style.borderTop='';this.style.borderBottom='';
            if(!dragSrc||dragSrc===this)return;
            var container=document.getElementById('links-container');
            var rect=this.getBoundingClientRect();var mid=rect.top+rect.height/2;
            if(e.clientY<mid)container.insertBefore(dragSrc,this);else container.insertBefore(dragSrc,this.nextSibling);
        });
    }
    document.querySelectorAll('#links-container .link-row').forEach(initDrag);
    </script></div><?php
}

function mcres_generate_html($name, $icon, $links, $qq_url) {
    $cards = '';
    foreach ($links as $l) {
        $ic = $l['icon'] ?: $icon;
        $ic_html = $ic ? '<img src="'.htmlspecialchars($ic).'" alt="" style="width:48px;height:48px;border-radius:12px;object-fit:cover;border:1px solid rgba(212,175,55,.3);">' : '';
        $url_h = htmlspecialchars($l['url'], ENT_QUOTES);
        $title_h = htmlspecialchars($l['title'], ENT_QUOTES);
        $sub_h = htmlspecialchars($l['sub'], ENT_QUOTES);
        $badge_h = htmlspecialchars($l['badge'], ENT_QUOTES);
        $cards .= <<<CARD
        <div class="dl-card" data-url="{$url_h}">
            <div class="dl-card-inner">
                <div class="dl-card-top">{$ic_html}
                    <div class="dl-card-info">
                        <div class="dl-card-title">{$title_h}</div>
                        <div class="dl-card-sub">{$sub_h}</div>
                    </div>
                </div>
                <span class="dl-badge badge-{$l['badge']}">{$badge_h}</span>
            </div>
        </div>
CARD;
    }
    $name_h = htmlspecialchars($name, ENT_QUOTES);
    $qq_h = htmlspecialchars($qq_url, ENT_QUOTES);
    $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>MC灵依资源站 | ' . $name_h . '</title>
<link rel="shortcut icon" href="https://mcres.cn/wp-content/uploads/2023/11/LINGYI.png">
<link rel="icon" href="https://mcres.cn/wp-content/uploads/2023/11/LINGYI.png" type="image/png">
<style>
:root{--bg:#0a0a0f;--surface:rgba(255,255,255,.04);--surface2:rgba(255,255,255,.08);--text:#e8e6e3;--text2:#8a8780;--border:rgba(212,175,55,.25);--border2:rgba(212,175,55,.12);--gold:#d4af37;--gold-dim:rgba(212,175,55,.6);--accent:#f0c040;--glass:rgba(255,255,255,.05);--glass-border:rgba(212,175,55,.2);--card-hover:rgba(212,175,55,.06)}
[data-theme="light"]{--bg:#f5f5f0;--surface:rgba(0,0,0,.03);--surface2:rgba(0,0,0,.06);--text:#1a1a1a;--text2:#666;--border:rgba(180,150,40,.3);--border2:rgba(180,150,40,.15);--gold:#b49628;--gold-dim:rgba(180,150,40,.7);--accent:#8a7420;--glass:rgba(255,255,255,.7);--glass-border:rgba(180,150,40,.2);--card-hover:rgba(180,150,40,.08)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Helvetica Neue","PingFang SC",sans-serif;min-height:100vh;transition:background .4s,color .4s;overflow-x:hidden}
#meteor-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none}
.topbar{position:fixed;top:0;left:0;right:0;height:56px;background:var(--glass);border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:100;backdrop-filter:saturate(180%) blur(24px);-webkit-backdrop-filter:saturate(180%) blur(24px)}
.topbar-left{display:flex;align-items:center;gap:8px}
.topbar-title{font-size:16px;font-weight:600;color:var(--gold);letter-spacing:.5px;display:flex;align-items:center;gap:8px}
.topbar-title span{opacity:.6}
.topbar-right{display:flex;align-items:center;gap:10px}
.fab-back{position:fixed;left:20px;top:76px;z-index:99;display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:var(--glass);border:1px solid var(--gold);border-radius:10px;color:var(--gold);text-decoration:none;font-size:14px;font-weight:600;backdrop-filter:blur(12px);transition:all .3s;white-space:nowrap;letter-spacing:.3px}
.fab-back:hover{background:rgba(212,175,55,.15);box-shadow:0 0 20px rgba(212,175,55,.25);transform:translateY(-1px)}
.btn-back-mobile{display:none;}
.theme-toggle{width:48px;height:48px;border-radius:50%;border:2px solid var(--gold);background:var(--glass);backdrop-filter:blur(12px);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:22px;transition:all .3s;color:var(--gold)}
.theme-toggle:hover{box-shadow:0 0 24px rgba(212,175,55,.3);transform:scale(1.1)}
.content{position:relative;z-index:10;max-width:780px;margin:0 auto;padding:88px 20px 60px}
.page-title{font-size:32px;font-weight:700;letter-spacing:-.5px;margin-bottom:6px;background:linear-gradient(135deg,var(--gold),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.page-desc{color:var(--text2);font-size:14px;margin-bottom:28px}
.btn-row{display:flex;gap:12px;margin-bottom:32px;flex-wrap:wrap}
.dl-grid{display:flex;flex-direction:column;border-radius:16px;overflow:hidden;border:1px solid var(--border2);background:var(--surface)}
.dl-card{cursor:pointer;border-bottom:1px solid var(--border2);transition:all .25s}
.dl-card:last-child{border-bottom:none}
.dl-card:hover{background:var(--card-hover)}
.dl-card-inner{padding:16px 20px;display:flex;align-items:center;justify-content:space-between}
.dl-card-top{display:flex;align-items:center;gap:14px;flex:1;min-width:0}
.dl-card-info{min-width:0}
.dl-card-title{font-size:15px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-card-sub{font-size:12px;color:var(--text2);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-badge{font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;white-space:nowrap;flex-shrink:0;margin-left:12px;letter-spacing:.3px}
.badge-MC灵依资源站{background:rgba(212,175,55,.12);color:var(--gold);border:1px solid rgba(212,175,55,.2)}
.badge-GitHub{background:var(--surface2);color:var(--text2);border:1px solid var(--border2)}
.badge-官网{background:rgba(76,175,80,.1);color:#66bb6a;border:1px solid rgba(76,175,80,.2)}
.badge-第三方{background:rgba(255,152,0,.1);color:#ffa726;border:1px solid rgba(255,152,0,.2)}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:40px;text-align:center;max-width:380px;width:90%;backdrop-filter:blur(24px);box-shadow:0 24px 80px rgba(0,0,0,.4),0 0 40px rgba(212,175,55,.1)}
.modal-icon{font-size:48px;margin-bottom:16px}
.modal-title{font-size:18px;font-weight:600;color:var(--gold);margin-bottom:8px}
.modal-sub{font-size:13px;color:var(--text2);margin-bottom:20px}
.modal-countdown{font-size:48px;font-weight:700;color:var(--gold);font-variant-numeric:tabular-nums;line-height:1}
.modal-countdown-unit{font-size:14px;color:var(--text2);font-weight:400}
.modal-progress{width:100%;height:3px;background:var(--surface2);border-radius:2px;margin-top:16px;overflow:hidden}
.modal-progress-bar{height:100%;background:linear-gradient(90deg,var(--gold),var(--accent));border-radius:2px;transition:width 1s linear}
.success-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:1000;align-items:center;justify-content:center}
.success-modal-overlay.active{display:flex}
.success-modal{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:40px 36px;text-align:center;max-width:400px;width:90%;backdrop-filter:blur(24px);box-shadow:0 24px 80px rgba(0,0,0,.4),0 0 40px rgba(212,175,55,.1)}
.success-icon{font-size:48px;margin-bottom:12px}
.success-title{font-size:18px;font-weight:600;color:var(--gold);margin-bottom:8px}
.success-sub{font-size:13px;color:var(--text2);margin-bottom:24px;line-height:1.6}
.success-actions{display:flex;align-items:center;justify-content:center;gap:12px}
.btn-qq-success{display:inline-flex;align-items:center;gap:6px;padding:12px 24px;background:linear-gradient(135deg,rgba(212,175,55,.15),rgba(212,175,55,.05));border:1px solid var(--border);border-radius:12px;color:var(--accent);text-decoration:none;font-size:15px;font-weight:600;backdrop-filter:blur(12px);transition:all .3s;white-space:nowrap}
.btn-qq-success:hover{background:linear-gradient(135deg,rgba(212,175,55,.25),rgba(212,175,55,.1));box-shadow:0 0 20px rgba(212,175,55,.2);transform:translateY(-1px)}
.btn-close-success{width:48px;height:48px;border-radius:50%;border:2px solid var(--gold);background:var(--glass);backdrop-filter:blur(12px);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:22px;transition:all .3s;color:var(--gold);flex-shrink:0}
.btn-close-success:hover{background:rgba(212,175,55,.15);box-shadow:0 0 20px rgba(212,175,55,.25);transform:scale(1.1)}
.footer{text-align:center;padding:40px 20px;font-size:12px;color:var(--text2);position:relative;z-index:10}
.footer a{color:var(--gold-dim);text-decoration:none}
.footer a:hover{text-decoration:underline}
@media(max-width:768px){.content{padding:76px 14px 40px}.page-title{font-size:24px}.dl-card-inner{padding:14px 16px}.fab-back{display:none!important}.btn-back-mobile{display:inline-flex!important;padding:8px 16px;background:var(--glass);border:1px solid var(--gold);border-radius:10px;color:var(--gold);text-decoration:none;font-size:13px;font-weight:600;white-space:nowrap;letter-spacing:.3px}}
</style>
</head>
<body>
<canvas id="meteor-canvas"></canvas>
<div class="topbar">
    <div class="topbar-left">
        <a href="https://mcres.cn" class="btn-back-mobile">← 返回</a>
        <div class="topbar-title"><span>⬇</span> MC灵依资源站</div>
    </div>
    <div class="topbar-right"><button class="theme-toggle" onclick="toggleTheme()" id="themeBtn">☀️</button></div>
</div>
<a href="https://mcres.cn" class="fab-back">← 返回主站</a>
<div class="content">
    <div class="page-title">' . $name_h . '</div>
    <div class="page-desc">选择版本下载 · 所有链接均由 MC灵依资源站 镜像加速</div>
    <div class="dl-grid" id="dlGrid">' . $cards . '</div>
</div>
<div class="footer">
    <p>© 2023-Forever By Infinite Bytes Studio · <a href="https://mcres.cn" target="_blank">MC灵依资源站</a></p>
    <p style="margin-top:4px;"><a href="https://beian.miit.gov.cn/" target="_blank">冀ICP备2024091724号-1</a> · <a href="https://beian.mps.gov.cn/#/query/webSearch?code=13040302001660" target="_blank">冀公网安备13040302001660号</a></p>
</div>
<div class="modal-overlay" id="dlModal">
    <div class="modal">
        <div class="modal-icon">⚡</div>
        <div class="modal-title">准备下载</div>
        <div class="modal-sub">即将开始下载，请稍候...</div>
        <div class="modal-countdown" id="cdNum">3<span class="modal-countdown-unit">s</span></div>
        <div class="modal-progress"><div class="modal-progress-bar" id="cdBar" style="width:100%"></div></div>
    </div>
</div>
<div class="success-modal-overlay" id="dlSuccess">
    <div class="success-modal">
        <div class="success-icon">✅</div>
        <div class="success-title">下载已开始</div>
        <div class="success-sub">欢迎使用灵依资源站！<br>感谢你的支持与信赖</div>
        <div class="success-actions">
            <a href="' . $qq_h . '" target="_blank" class="btn-qq-success">💬 加入官方QQ群</a>
            <button class="btn-close-success" onclick="document.getElementById(\'dlSuccess\').classList.remove(\'active\')" title="关闭">✓</button>
        </div>
    </div>
</div>
<script>
(function(){var c=document.getElementById("meteor-canvas"),ctx=c.getContext("2d"),W,H;function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight}resize();window.addEventListener("resize",resize);var meteors=[];setInterval(function(){meteors.push({x:Math.random()*W*1.5-W*.25,y:-10,len:Math.random()*80+40,speed:Math.random()*6+4,opacity:Math.random()*.5+.2})},400);function draw(){ctx.clearRect(0,0,W,H);for(var i=meteors.length-1;i>=0;i--){var m=meteors[i];var g=ctx.createLinearGradient(m.x,m.y,m.x+m.len*.3,m.y+m.len);g.addColorStop(0,"rgba(212,175,55,0)");g.addColorStop(1,"rgba(212,175,55,"+m.opacity+")");ctx.beginPath();ctx.moveTo(m.x,m.y);ctx.lineTo(m.x+m.len*.3,m.y+m.len);ctx.strokeStyle=g;ctx.lineWidth=1.5;ctx.stroke();ctx.beginPath();ctx.arc(m.x+m.len*.3,m.y+m.len,1.5,0,Math.PI*2);ctx.fillStyle="rgba(212,175,55,"+m.opacity+")";ctx.fill();m.x+=m.speed*.4;m.y+=m.speed;if(m.y>H+100)meteors.splice(i,1)}requestAnimationFrame(draw)}draw()})();
(function(){var s=localStorage.getItem("theme");if(s==="light"){document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️"}else if(s==="dark"){document.documentElement.removeAttribute("data-theme");document.getElementById("themeBtn").textContent="🌙"}else if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:light)").matches){document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️"}else{document.getElementById("themeBtn").textContent="🌙"}})();
function toggleTheme(){var isLight=document.documentElement.getAttribute("data-theme")==="light";if(isLight){document.documentElement.removeAttribute("data-theme");document.getElementById("themeBtn").textContent="🌙";localStorage.setItem("theme","dark")}else{document.documentElement.setAttribute("data-theme","light");document.getElementById("themeBtn").textContent="☀️";localStorage.setItem("theme","light")}}
(function(){var modal=document.getElementById("dlModal"),success=document.getElementById("dlSuccess"),cdNum=document.getElementById("cdNum"),cdBar=document.getElementById("cdBar");document.getElementById("dlGrid").addEventListener("click",function(e){var card=e.target.closest(".dl-card");if(!card)return;e.preventDefault();var url=card.getAttribute("data-url");if(!url)return;if(url.indexOf("vip.123pan.cn")!==-1||url.indexOf("v.123pan.cn")!==-1){modal.classList.add("active");var count=3;cdNum.innerHTML=count+"<span class=\"modal-countdown-unit\">s</span>";cdBar.style.width="100%";var timer=setInterval(function(){count--;if(count<=0){clearInterval(timer);modal.classList.remove("active");window.open(url,"_self");success.classList.add("active");return}cdNum.innerHTML=count+"<span class=\"modal-countdown-unit\">s</span>";cdBar.style.width=(count/3*100)+"%"},1000)}else{window.open(url,"_blank")}})})();
document.addEventListener("contextmenu",function(e){if(e.target.tagName!=="INPUT"&&e.target.tagName!=="TEXTAREA")e.preventDefault()});
document.addEventListener("selectstart",function(e){if(e.target.tagName!=="INPUT"&&e.target.tagName!=="TEXTAREA"&&e.target.tagName!=="SELECT")return false});
</script>
</body>
</html>';
    return $html;
}
