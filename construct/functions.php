<?php
$_ENV['exaccips'] = [];
include('api/api.php');
if ( ! defined( 'ABSPATH' ) ) { exit; }
date_default_timezone_set('Asia/Shanghai');
require get_template_directory() . '/inc/inc.php';
require_once get_template_directory() . '/inc/frame/cs-framework.php';
   
//登录页面的LOGO链接为首页链接
add_filter('login_headerurl',function() {return get_bloginfo('url');});
//登陆界面logo的title为博客副标题
add_filter('login_headertext',function() {return get_bloginfo( 'description' );});

//WordPress 5.0+移除 block-library CSS
add_action( 'wp_enqueue_scripts', 'fanly_remove_block_library_css', 100 );
function fanly_remove_block_library_css() {
	wp_dequeue_style( 'wp-block-library' );
}

add_action('admin_menu', 'submissionProccPage');
function submissionProccPage(){
    add_menu_page( 
        '管理投稿-投稿后台管理页面', 
        '管理投稿', 
        'manage_options', 
        's-man', 
        'submissionProccPageRender', 
        'dashicons-admin-customizer', 
        86.114514 
    ); 
}
function submissionProccPageRender() {
    include(get_template_directory() . '/api/manage.php');
}
