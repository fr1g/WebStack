<?php
/*
 * Plugin Name: MC灵依资源站 - 公告系统
 * Description: 注册bulletin CPT，路由为 /LingYiZiYuan/bulletin/update/
 * Version: 1.1
 */
if (!defined('ABSPATH')) exit;

// ===== 1. 注册公告CPT =====
add_action('init', function(){
    register_post_type('bulletin', array(
        'labels' => array(
            'name'          => '公告管理',
            'singular_name' => '公告',
            'add_new_item'  => '发布新公告',
            'edit_item'     => '编辑公告',
            'all_items'     => '所有公告',
        ),
        'public'       => true,
        'has_archive'  => false,
        'menu_icon'    => 'dashicons-megaphone',
        'supports'     => array('title','editor','thumbnail','custom-fields'),
        'rewrite'      => array('slug' => 'LingYiZiYuan/bulletin/update', 'with_front' => false),
        'show_in_rest' => true,
    ));
});

// ===== 2. 修复CPT permalink =====
add_filter('post_type_link', function($post_link, $post) {
    if ($post->post_type === 'bulletin') {
        return '/LingYiZiYuan/bulletin/update/' . $post->ID . '/';
    }
    return $post_link;
}, 10, 2);

// ===== 3. 零rewrite路由：直接匹配REQUEST_URI =====
add_action('template_redirect', function(){
    $uri = trim($_SERVER['REQUEST_URI'], '/');
    // 匹配: LingYiZiYuan/bulletin/update
    if ($uri === 'LingYiZiYuan/bulletin/update') {
        include __DIR__ . '/templates/bulletin-list.php';
        exit;
    }
    // 匹配: LingYiZiYuan/bulletin/update/{id}
    if (preg_match('#^LingYiZiYuan/bulletin/update/(\d+)$#', $uri, $m)) {
        $GLOBALS['bulletin_single_id'] = intval($m[1]);
        include __DIR__ . '/templates/bulletin-single.php';
        exit;
    }
    // 匹配: LingYiZiYuan/bulletin (CPT存档)
    if ($uri === 'LingYiZiYuan/bulletin') {
        include __DIR__ . '/templates/bulletin-list.php';
        exit;
    }
});

// ===== 3. 后台菜单：公告管理 =====
add_action('admin_menu', function(){
    add_menu_page(
        '公告管理',
        '公告管理',
        'manage_options',
        'bulletin-manage',
        'mcres_bulletin_admin_page',
        'dashicons-megaphone',
        30
    );
});

function mcres_bulletin_admin_page(){
    global $wpdb;
    $table = $wpdb->posts;
    $bulletins = $wpdb->get_results("SELECT ID,post_title,post_date,post_status FROM $table WHERE post_type='bulletin' ORDER BY post_date DESC");
    ?>
    <div class="wrap">
        <h1>公告管理 <a href="<?= admin_url('post-new.php?post_type=bulletin') ?>" class="page-title-action">+ 新建公告</a></h1>
        <p style="color:#666;">前台访问地址：<a href="https://mcres.cn/LingYiZiYuan/bulletin/update/" target="_blank">https://mcres.cn/LingYiZiYuan/bulletin/update/</a></p>
        <table class="widefat striped" style="max-width:800px;">
            <thead><tr><th>ID</th><th>标题</th><th>日期</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if ($bulletins): foreach ($bulletins as $b): ?>
                <tr>
                    <td><?= $b->ID ?></td>
                    <td><a href="<?= admin_url('post.php?post='.$b->ID.'&action=edit') ?>"><?= esc_html($b->post_title) ?></a></td>
                    <td><?= date('Y-m-d H:i', strtotime($b->post_date)) ?></td>
                    <td><?= $b->post_status === 'publish' ? '已发布' : $b->post_status ?></td>
                    <td>
                        <a href="<?= admin_url('post.php?post='.$b->ID.'&action=edit') ?>" class="button button-small">编辑</a>
                        <a href="<?= home_url('/LingYiZiYuan/bulletin/update/'.$b->ID) ?>" target="_blank" class="button button-small">查看</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align:center;padding:20px;">暂无公告，点击上方按钮新建。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
